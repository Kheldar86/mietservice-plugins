<?php
/**
 * Stornierung mit Stornostaffel und automatischer Erstattung (KTO-07, BKG-17, PAY-15..17).
 *
 * Staffel (Fristen ab Mietbeginn 0:00 Uhr): ab 7 Tagen vorher kostenlos, ab 48 Stunden vorher 25 %,
 * danach 50 %. Die Gebühr gilt nur für die Tagesmiete nach Rabatten, nicht für die Grundgebühr.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Cancellation {

	public static function init() {
		add_action( 'msb_booking_status_changed', array( __CLASS__, 'on_status_change' ), 5, 3 );
		add_action( 'admin_init', array( __CLASS__, 'handle_admin' ), 9 );
	}

	/**
	 * Staffel: Stunden vor Mietbeginn => Gebühr in Prozent (absteigend sortiert).
	 */
	public static function tiers() {
		return apply_filters(
			'msb_cancel_tiers',
			array(
				168 => 0,
				48  => 25,
				0   => 50,
			)
		);
	}

	/**
	 * Mietbeginn der Buchung (frühester Abholtag) als Zeitstempel, 0:00 Uhr Ortszeit.
	 */
	public static function start_timestamp( array $booking ) {
		$start = '';
		foreach ( $booking['items'] as $l ) {
			$start = $start ? min( $start, $l['start_date'] ) : $l['start_date'];
		}
		if ( ! msb_valid_date( $start ) ) {
			return 0;
		}
		$d = new DateTimeImmutable( $start . ' 00:00:00', wp_timezone() );
		return $d->getTimestamp();
	}

	public static function fee_percent( array $booking, $now = null ) {
		$now   = null === $now ? time() : (int) $now;
		$hours = ( self::start_timestamp( $booking ) - $now ) / HOUR_IN_SECONDS;
		$tiers = self::tiers();
		krsort( $tiers );
		foreach ( $tiers as $min => $percent ) {
			if ( $hours >= $min ) {
				return (float) $percent;
			}
		}
		return (float) end( $tiers );
	}

	/**
	 * Grundlage der Gebühr: Tagesmiete nach Rabatten × Menge, ohne Grundgebühr (PAY-17).
	 */
	public static function fee_base( array $booking ) {
		$base = 0.0;
		foreach ( $booking['items'] as $l ) {
			$base += max( 0, (float) $l['line_total'] - (float) $l['base_fee'] * (int) $l['quantity'] );
		}
		return round( $base, 2 );
	}

	/**
	 * Berechnung für Bestätigungsseite, Backend-Vorschlag und Ausführung.
	 */
	public static function quote( array $booking, $now = null ) {
		$percent = self::fee_percent( $booking, $now );
		$base    = self::fee_base( $booking );
		$fee     = round( $base * $percent / 100, 2 );
		$sum     = MSB_Payments::summary( $booking );
		return array(
			'percent' => $percent,
			'base'    => $base,
			'fee'     => $fee,
			'paid'    => $sum['paid'],
			'refund'  => round( max( 0, $sum['paid'] - $fee ), 2 ),
		);
	}

	/**
	 * Darf der Kunde selbst stornieren? Nur angefragte oder bestätigte Buchungen bis Mietbeginn 0:00 Uhr.
	 */
	public static function customer_can_cancel( array $booking, $now = null ) {
		$now = null === $now ? time() : (int) $now;
		return in_array( $booking['status'], array( 'pending', 'confirmed' ), true ) && $now < self::start_timestamp( $booking );
	}

	/**
	 * Storniert eine Buchung und erstattet den bezahlten Betrag abzüglich Gebühr.
	 *
	 * @param string $by   customer|admin
	 * @param array  $opts fee (float, nur Backend), refund (bool), notify (bool, Stornomail an den Kunden)
	 * @return array|WP_Error Ergebnis mit fee, refund, refunded, manual, errors
	 */
	public static function execute( $booking_id, $by = 'customer', array $opts = array() ) {
		global $wpdb;
		$opts    = wp_parse_args(
			$opts,
			array(
				'fee'    => null,
				'refund' => true,
				'notify' => true,
			)
		);
		$booking = MSB_Bookings::get( $booking_id );
		if ( ! $booking ) {
			return new WP_Error( 'msb_cancel', 'Buchung nicht gefunden.' );
		}
		if ( ! msb_status_allowed( $booking['status'], 'cancelled' ) ) {
			return new WP_Error( 'msb_cancel', 'Diese Buchung kann nicht mehr storniert werden.' );
		}
		$quote = self::quote( $booking );
		$fee   = null === $opts['fee'] ? $quote['fee'] : round( max( 0, (float) $opts['fee'] ), 2 );

		// Stornierung für sich beanspruchen, damit ein Doppelklick nicht zweimal erstattet.
		$table   = MSB_Install::table( 'bookings' );
		$claimed = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET cancelled_at = %s, cancelled_by = %s, cancel_fee = %f WHERE id = %d AND cancelled_at IS NULL", current_time( 'mysql' ), $by, $fee, $booking_id ) ); // phpcs:ignore
		if ( 1 !== (int) $claimed ) {
			return new WP_Error( 'msb_cancel', 'Diese Buchung wird bereits storniert.' );
		}
		$r = MSB_Bookings::update_status( $booking_id, 'cancelled', false );
		if ( true !== $r ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET cancelled_at = NULL, cancelled_by = '', cancel_fee = NULL WHERE id = %d", $booking_id ) ); // phpcs:ignore
			return is_wp_error( $r ) ? $r : new WP_Error( 'msb_cancel', 'Die Buchung konnte nicht storniert werden.' );
		}

		$result = array(
			'percent'  => $quote['percent'],
			'fee'      => $fee,
			'paid'     => $quote['paid'],
			'refund'   => round( max( 0, $quote['paid'] - $fee ), 2 ),
			'refunded' => 0.0,
			'manual'   => 0.0,
			'errors'   => array(),
		);
		if ( $opts['refund'] && $result['refund'] > 0 ) {
			$result = self::refund_payments( $booking_id, $result );
		}

		if ( $opts['notify'] ) {
			MSB_Emails::send( $booking_id, 'cancelled' );
		}
		if ( 'customer' === $by || $result['errors'] || $result['manual'] > 0 ) {
			self::notify_team( $booking_id, $by, $result );
		}
		do_action( 'msb_booking_cancelled', $booking_id, $by, $result );
		return $result;
	}

	/**
	 * Erstattet den Betrag über die bezahlten Online-Zahlungen, neueste zuerst.
	 */
	private static function refund_payments( $booking_id, array $result ) {
		$left     = $result['refund'];
		$payments = array_reverse( MSB_Payments::for_booking( $booking_id ) );
		foreach ( $payments as $p ) {
			if ( $left <= 0 ) {
				break;
			}
			if ( 'security' === $p['type'] || 'paid' !== $p['status'] ) {
				continue;
			}
			$open = round( (float) $p['amount'] - ( isset( $p['data']['refunded'] ) ? (float) $p['data']['refunded'] : 0 ), 2 );
			$part = round( min( $left, $open ), 2 );
			if ( $part <= 0 ) {
				continue;
			}
			if ( ! in_array( $p['provider'], array( 'stripe', 'paypal' ), true ) ) {
				// Bar oder manuell erfasst: muss von Hand erstattet werden.
				$result['manual'] = round( $result['manual'] + $part, 2 );
				$left             = round( $left - $part, 2 );
				continue;
			}
			$r = MSB_Payments::refund( $p['id'], $part );
			if ( is_wp_error( $r ) ) {
				$result['errors'][] = $r->get_error_message();
				continue;
			}
			$result['refunded'] = round( $result['refunded'] + $part, 2 );
			$left               = round( $left - $part, 2 );
		}
		if ( $left > 0 && ! $result['errors'] ) {
			$result['errors'][] = sprintf( '%s konnten keiner Zahlung zugeordnet werden.', msb_price( $left ) );
		}
		return $result;
	}

	/**
	 * Info-Mail an die Team-Adresse aus den Einstellungen (wie die Mail zu neuen Buchungen).
	 */
	private static function notify_team( $booking_id, $by, array $result ) {
		$booking = MSB_Bookings::get( $booking_id );
		if ( ! $booking ) {
			return;
		}
		$to = array_filter( array( msb_setting( 'admin_email' ) ), 'is_email' );
		if ( ! $to ) {
			return;
		}
		$who  = 'customer' === $by ? 'vom Kunden in „Mein Konto“' : 'im Backend';
		$body = '<p>Die Buchung <strong>' . esc_html( $booking['booking_number'] ) . '</strong> (' . esc_html( $booking['customer_name'] ) . ') wurde ' . esc_html( $who ) . ' storniert.</p>'
			. MSB_Emails::items_table( $booking )
			. '<p>Stornogebühr: ' . esc_html( msb_price( $result['fee'] ) ) . ' (' . esc_html( rtrim( rtrim( number_format( $result['percent'], 2, ',', '' ), '0' ), ',' ) ) . ' % der Tagesmiete)<br>'
			. 'Bezahlt: ' . esc_html( msb_price( $result['paid'] ) ) . '<br>'
			. 'Erstattung: ' . esc_html( msb_price( $result['refund'] ) ) . ', davon automatisch erstattet: ' . esc_html( msb_price( $result['refunded'] ) ) . '</p>';
		if ( $result['manual'] > 0 ) {
			$body .= '<p><strong>Bitte von Hand erstatten:</strong> ' . esc_html( msb_price( $result['manual'] ) ) . ' (bar oder manuell erfasste Zahlung).</p>';
		}
		if ( $result['errors'] ) {
			$body .= '<p style="color:#b32d2e"><strong>Erstattung fehlgeschlagen:</strong> ' . esc_html( implode( ' ', $result['errors'] ) ) . '<br>Sie können die Erstattung in der Buchung unter „Zahlungen“ erneut auslösen.</p>';
		}
		$body .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=msb-booking&id=' . (int) $booking_id ) ) . '">Buchung im Backend öffnen</a></p>';
		$subject = ( $result['errors'] ? 'Erstattung fehlgeschlagen: ' : 'Stornierung: ' ) . $booking['booking_number'];
		foreach ( $to as $email ) {
			MSB_Emails::send_raw( $email, $subject, $body );
		}
	}

	/**
	 * Stornierungen über die Statusauswahl, Schnell- und Sammelaktionen oder den Cron vermerken.
	 * Wird eine Stornierung zurückgenommen, werden die Angaben gelöscht.
	 */
	public static function on_status_change( $booking_id, $status, $old ) {
		global $wpdb;
		$table = MSB_Install::table( 'bookings' );
		if ( 'cancelled' === $status ) {
			$by = is_user_logged_in() ? ( current_user_can( msb_capability() ) ? 'admin' : 'customer' ) : 'system';
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET cancelled_at = %s, cancelled_by = %s WHERE id = %d AND cancelled_at IS NULL", current_time( 'mysql' ), $by, $booking_id ) ); // phpcs:ignore
		} elseif ( 'cancelled' === $old ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET cancelled_at = NULL, cancelled_by = '', cancel_fee = NULL WHERE id = %d", $booking_id ) ); // phpcs:ignore
		}
	}

	/**
	 * Summe der Erstattungen einer Buchung (ohne Kaution).
	 */
	public static function refunded_total( $booking_id ) {
		$total = 0.0;
		foreach ( MSB_Payments::for_booking( $booking_id ) as $p ) {
			if ( 'security' !== $p['type'] && ! empty( $p['data']['refunded'] ) ) {
				$total += (float) $p['data']['refunded'];
			}
		}
		return round( $total, 2 );
	}

	/* ------------------------------------------------------------------
	 * Backend: Kasten „Stornieren“ in der Buchung (PAY-15)
	 * ------------------------------------------------------------------ */

	/**
	 * Schlägt die Gebühr nach Staffel vor; Betrag und Erstattung sind änderbar.
	 */
	public static function admin_box( array $b ) {
		$id = (int) $b['id'];
		if ( 'cancelled' === $b['status'] ) {
			if ( ! empty( $b['cancelled_at'] ) ) {
				echo '<div class="msb-card-box"><h2>Stornierung</h2><p>Am ' . esc_html( mysql2date( 'd.m.Y H:i', $b['cancelled_at'] ) ) . ' durch ' . esc_html( self::by_label( $b['cancelled_by'] ) ) . '<br>Stornogebühr: ' . esc_html( isset( $b['cancel_fee'] ) ? msb_price( $b['cancel_fee'] ) : '–' ) . '<br>Erstattet: ' . esc_html( msb_price( self::refunded_total( $id ) ) ) . '</p></div>';
			}
			return;
		}
		if ( ! msb_status_allowed( $b['status'], 'cancelled' ) ) {
			return;
		}
		$q   = self::quote( $b );
		$pct = rtrim( rtrim( number_format( $q['percent'], 2, ',', '' ), '0' ), ',' );
		echo '<form method="post" class="msb-card-box" onsubmit="return confirm(\'' . esc_js( 'Buchung wirklich stornieren?' ) . '\');">';
		wp_nonce_field( 'msb_booking_' . $id );
		echo '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="msb_booking_action" value="cancel">';
		echo '<h2>Stornieren</h2>';
		echo '<p><label>Stornogebühr <input type="number" step="0.01" min="0" name="cancel_fee" value="' . esc_attr( number_format( $q['fee'], 2, '.', '' ) ) . '" class="small-text"> ' . esc_html( msb_setting( 'currency', '€' ) ) . '</label><br>';
		echo '<small>Vorschlag nach Staffel: ' . esc_html( $pct ) . ' % von ' . esc_html( msb_price( $q['base'] ) ) . ' Tagesmiete. Bezahlt: ' . esc_html( msb_price( $q['paid'] ) ) . '.</small></p>';
		echo '<p><label><input type="checkbox" name="cancel_refund" value="1" checked> Bezahlten Betrag abzüglich Gebühr automatisch erstatten</label></p>';
		echo '<p><label><input type="checkbox" name="notify" value="1" checked> Kunden per E-Mail informieren</label></p>';
		echo '<p><button type="submit" class="button">Buchung stornieren</button></p></form>';
	}

	public static function handle_admin() {
		// phpcs:disable WordPress.Security.NonceVerification -- Nonce wird unten geprüft.
		if ( empty( $_POST['msb_booking_action'] ) || 'cancel' !== $_POST['msb_booking_action'] || ! current_user_can( msb_capability() ) ) {
			return;
		}
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		check_admin_referer( 'msb_booking_' . $id );
		$fee = isset( $_POST['cancel_fee'] ) && '' !== $_POST['cancel_fee'] ? (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['cancel_fee'] ) ) ) : null;
		$r   = self::execute(
			$id,
			'admin',
			array(
				'fee'    => $fee,
				'refund' => ! empty( $_POST['cancel_refund'] ),
				'notify' => ! empty( $_POST['notify'] ),
			)
		);
		// phpcs:enable
		if ( is_wp_error( $r ) ) {
			$notice = array( $r->get_error_message(), 'error' );
		} else {
			$msg = sprintf( 'Buchung storniert. Stornogebühr %1$s, erstattet %2$s.', msb_price( $r['fee'] ), msb_price( $r['refunded'] ) );
			if ( $r['manual'] > 0 ) {
				$msg .= sprintf( ' Bitte %s von Hand erstatten.', msb_price( $r['manual'] ) );
			}
			if ( $r['errors'] ) {
				$msg .= ' Erstattung fehlgeschlagen: ' . implode( ' ', $r['errors'] );
			}
			$notice = array( $msg, $r['errors'] || $r['manual'] > 0 ? 'warning' : 'success' );
		}
		// Gleicher Hinweis-Mechanismus wie MSB_Admin_Bookings::redirect().
		set_transient( 'msb_notice_' . get_current_user_id(), $notice, 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=msb-booking&id=' . $id ) );
		exit;
	}

	public static function by_label( $by ) {
		$map = array(
			'customer' => 'Kunde (Mein Konto)',
			'admin'    => 'Backend',
			'system'   => 'automatisch (Zahlungsfrist)',
		);
		return isset( $map[ $by ] ) ? $map[ $by ] : '–';
	}
}
