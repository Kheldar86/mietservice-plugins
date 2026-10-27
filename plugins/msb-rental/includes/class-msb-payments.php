<?php
/**
 * Zahlungen: Anzahlung, Restzahlung, Kaution (Reservierung) über Stripe und PayPal.
 *
 * Zahlungsarten (type):  prepay = Anzahlung, rest = Restzahlung, security = Kaution
 * Status:                pending, paid, scheduled (Karte für Kaution hinterlegt), requested (Zahlungslink gesendet),
 *                        authorized (Kaution reserviert), released (freigegeben), captured (einbehalten),
 *                        refunded, failed, cancelled
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Payments {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'handle_requests' ), 5 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_webhook' ) );
		add_action( 'msb_cron_hourly', array( __CLASS__, 'cron' ) );
		add_action( 'msb_booking_status_changed', array( __CLASS__, 'on_status_change' ), 10, 3 );
	}

	/* ------------------------------------------------------------------
	 * Konfiguration
	 * ------------------------------------------------------------------ */

	public static function gateways() {
		return array(
			'stripe' => 'MSB_Gateway_Stripe',
			'paypal' => 'MSB_Gateway_PayPal',
		);
	}

	/**
	 * Aktive Online-Anbieter: id => Bezeichnung.
	 */
	public static function online_providers() {
		$out = array();
		foreach ( self::gateways() as $id => $class ) {
			if ( $class::enabled() ) {
				$out[ $id ] = $class::label();
			}
		}
		return $out;
	}

	/**
	 * Zahlungsarten im Checkout.
	 */
	public static function checkout_methods() {
		$out = self::online_providers();
		if ( msb_setting( 'offline_enabled' ) || empty( $out ) ) {
			$out['offline'] = msb_setting( 'offline_label', 'Bezahlung bei Abholung' );
		}
		return $out;
	}

	/**
	 * Barzahlung bei Abholung (Miete und Kaution), z. B. bei manuell angelegten Buchungen.
	 */
	public static function is_cash( array $booking ) {
		return isset( $booking['payment_method'] ) && 'offline' === $booking['payment_method'];
	}

	public static function security_hold_active() {
		return 'hold' === msb_setting( 'security_mode' ) && ! empty( self::online_providers() );
	}

	public static function prepay_amount( $total ) {
		$v = (float) msb_setting( 'prepay_value', 0 );
		switch ( msb_setting( 'prepay_type' ) ) {
			case 'percent':
				return round( min( $total, $total * $v / 100 ), 2 );
			case 'fixed':
				return round( min( $total, $v ), 2 );
			case 'full':
				return round( $total, 2 );
		}
		return 0.0;
	}

	public static function type_labels() {
		return array(
			'prepay'   => 'Anzahlung',
			'rest'     => 'Restzahlung',
			'security' => 'Kaution',
		);
	}

	public static function status_labels() {
		return array(
			'pending'    => 'offen',
			'paid'       => 'bezahlt',
			'scheduled'  => 'Karte hinterlegt – Reservierung folgt',
			'requested'  => 'Zahlungslink gesendet',
			'authorized' => 'reserviert',
			'released'   => 'freigegeben',
			'captured'   => 'einbehalten',
			'refunded'   => 'erstattet',
			'failed'     => 'fehlgeschlagen',
			'cancelled'  => 'abgebrochen',
		);
	}

	public static function provider_label( $p ) {
		$map = array(
			'stripe'  => 'Stripe',
			'paypal'  => 'PayPal',
			'manual'  => 'manuell erfasst',
			'offline' => 'vor Ort',
			''        => '–',
		);
		return isset( $map[ $p ] ) ? $map[ $p ] : $p;
	}

	/* ------------------------------------------------------------------
	 * Datenbank
	 * ------------------------------------------------------------------ */

	public static function add( $booking_id, $provider, $type, $amount, $status, $ref = '', array $data = array() ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		$wpdb->insert(
			MSB_Install::table( 'payments' ),
			array(
				'booking_id'   => (int) $booking_id,
				'provider'     => $provider,
				'type'         => $type,
				'amount'       => round( (float) $amount, 2 ),
				'status'       => $status,
				'provider_ref' => (string) $ref,
				'data'         => wp_json_encode( $data ),
				'created_at'   => $now,
				'updated_at'   => $now,
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function update( $id, array $fields, $data = null ) {
		global $wpdb;
		if ( null !== $data ) {
			$p              = self::get( $id );
			$fields['data'] = wp_json_encode( array_merge( $p ? $p['data'] : array(), $data ) );
		}
		$fields['updated_at'] = current_time( 'mysql' );
		$wpdb->update( MSB_Install::table( 'payments' ), $fields, array( 'id' => (int) $id ) );
	}

	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MSB_Install::table( 'payments' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
		return $row ? self::prepare( $row ) : null;
	}

	public static function for_booking( $booking_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . MSB_Install::table( 'payments' ) . ' WHERE booking_id = %d ORDER BY id', $booking_id ), ARRAY_A ); // phpcs:ignore
		return array_map( array( __CLASS__, 'prepare' ), (array) $rows );
	}

	public static function by_ref( $ref ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . MSB_Install::table( 'payments' ) . ' WHERE provider_ref = %s ORDER BY id', $ref ), ARRAY_A ); // phpcs:ignore
		return array_map( array( __CLASS__, 'prepare' ), (array) $rows );
	}

	public static function prepare( array $row ) {
		$row['data'] = json_decode( (string) $row['data'], true );
		if ( ! is_array( $row['data'] ) ) {
			$row['data'] = array();
		}
		return $row;
	}

	/**
	 * Übersicht der Zahlungen einer Buchung.
	 */
	public static function summary( array $booking ) {
		$paid     = 0.0;
		$security = null;
		foreach ( self::for_booking( $booking['id'] ) as $p ) {
			if ( 'security' === $p['type'] ) {
				if ( ! in_array( $p['status'], array( 'failed', 'cancelled' ), true ) || ! $security ) {
					$security = $p;
				}
				continue;
			}
			if ( 'paid' === $p['status'] ) {
				$paid += (float) $p['amount'] - ( isset( $p['data']['refunded'] ) ? (float) $p['data']['refunded'] : 0 );
			}
			if ( 'refunded' === $p['status'] ) {
				$paid += (float) $p['amount'] - ( isset( $p['data']['refunded'] ) ? (float) $p['data']['refunded'] : (float) $p['amount'] );
			}
		}
		$total = (float) $booking['total'];
		return array(
			'paid'           => round( $paid, 2 ),
			'open'           => round( max( 0, $total - $paid ), 2 ),
			'prepay_open'    => round( max( 0, (float) $booking['prepay'] - $paid ), 2 ),
			'security'       => $security,
			'security_label' => $security ? self::status_labels()[ $security['status'] ] : ( (float) $booking['deposit'] > 0 ? ( self::security_hold_active() && ! self::is_cash( $booking ) ? 'noch nicht reserviert' : ( self::is_cash( $booking ) ? 'bar bei Abholung' : 'bei Abholung' ) ) : '' ),
		);
	}

	/* ------------------------------------------------------------------
	 * URLs
	 * ------------------------------------------------------------------ */

	private static function base_url() {
		$url = msb_checkout_url();
		return $url ? $url : home_url( '/' );
	}

	public static function pay_url( array $booking, $for = '' ) {
		$args = array( 'msb_pay' => $booking['token'] );
		if ( $for ) {
			$args['for'] = $for;
		}
		return add_query_arg( $args, self::base_url() );
	}

	public static function done_url( array $booking, $error = '' ) {
		$args = array( 'msb_done' => $booking['token'] );
		if ( $error ) {
			$args['msb_err'] = rawurlencode( $error );
		}
		return add_query_arg( $args, self::base_url() );
	}

	private static function return_url( $provider, array $booking, $pid ) {
		$url = add_query_arg(
			array(
				'msb_return' => $provider,
				'msb_pay'    => $booking['token'],
				'pid'        => (int) $pid,
			),
			self::base_url()
		);
		// Stripe ersetzt den Platzhalter durch die Session-ID.
		return 'stripe' === $provider ? $url . '&session_id={CHECKOUT_SESSION_ID}' : $url;
	}

	private static function cancel_url( array $booking, $pid ) {
		return add_query_arg(
			array(
				'msb_cancel' => 1,
				'msb_pay'    => $booking['token'],
				'pid'        => (int) $pid,
			),
			self::base_url()
		);
	}

	/* ------------------------------------------------------------------
	 * Zahlung starten
	 * ------------------------------------------------------------------ */

	/**
	 * Online-Zahlung direkt nach der Buchung.
	 *
	 * @return string|null|WP_Error Weiterleitungs-URL, null wenn jetzt nichts zu zahlen ist.
	 */
	public static function start_checkout( array $booking, $provider ) {
		$prepay   = (float) $booking['prepay'];
		$security = self::security_hold_active() ? (float) $booking['deposit'] : 0.0;

		if ( 'stripe' === $provider && MSB_Gateway_Stripe::enabled() ) {
			if ( $prepay <= 0 && $security <= 0 ) {
				return null;
			}
			$type = $prepay > 0 ? 'prepay' : 'security';
			$pid  = self::add( $booking['id'], 'stripe', $type, $prepay > 0 ? $prepay : $security, 'pending', '', array( 'mode' => $prepay > 0 ? 'payment' : 'setup' ) );
			$s    = MSB_Gateway_Stripe::create_session(
				$booking,
				array(
					'mode'        => $prepay > 0 ? 'payment' : 'setup',
					'amount'      => $prepay,
					'description' => ( $prepay >= (float) $booking['total'] ? 'Miete ' : 'Anzahlung ' ) . $booking['booking_number'],
					'save_card'   => $security > 0,
					'success_url' => self::return_url( 'stripe', $booking, $pid ),
					'cancel_url'  => self::cancel_url( $booking, $pid ),
				)
			);
			if ( is_wp_error( $s ) ) {
				self::update( $pid, array( 'status' => 'failed' ), array( 'error' => $s->get_error_message() ) );
				return $s;
			}
			self::update( $pid, array( 'provider_ref' => $s['id'] ) );
			return $s['url'];
		}

		if ( 'paypal' === $provider && MSB_Gateway_PayPal::enabled() ) {
			$end    = self::latest_end( $booking );
			$window = msb_add_days( msb_today(), MSB_Gateway_PayPal::hold_validity_days() - 1 );
			// Kaution nur gleich mit reservieren, wenn die Reservierung bis zur Rückgabe gültig bleibt.
			$with_security = $security > 0 && $end <= $window;
			$amount        = $prepay + ( $with_security ? $security : 0 );
			if ( $amount <= 0 ) {
				return null;
			}
			$intent = $with_security ? 'AUTHORIZE' : 'CAPTURE';
			$first  = 0;
			if ( $prepay > 0 ) {
				$first = self::add( $booking['id'], 'paypal', 'prepay', $prepay, 'pending', '', array( 'intent' => $intent ) );
			}
			if ( $with_security ) {
				$sid   = self::add( $booking['id'], 'paypal', 'security', $security, 'pending', '', array( 'intent' => $intent ) );
				$first = $first ? $first : $sid;
			}
			$desc  = 'Buchung ' . $booking['booking_number'] . ( $with_security ? ( $prepay > 0 ? ' – Anzahlung + Kaution (Kaution wird nur reserviert)' : ' – Kaution (wird nur reserviert)' ) : ' – Anzahlung' );
			$order = MSB_Gateway_PayPal::create_order( $booking, $intent, $amount, $desc, self::return_url( 'paypal', $booking, $first ), self::cancel_url( $booking, $first ) );
			if ( is_wp_error( $order ) ) {
				foreach ( self::for_booking( $booking['id'] ) as $p ) {
					if ( 'pending' === $p['status'] && '' === $p['provider_ref'] ) {
						self::update( $p['id'], array( 'status' => 'failed' ), array( 'error' => $order->get_error_message() ) );
					}
				}
				return $order;
			}
			foreach ( self::for_booking( $booking['id'] ) as $p ) {
				if ( 'pending' === $p['status'] && '' === $p['provider_ref'] && 'paypal' === $p['provider'] ) {
					self::update( $p['id'], array( 'provider_ref' => $order['id'] ) );
				}
			}
			return $order['approve_url'];
		}

		return null;
	}

	/**
	 * Einzelne Zahlung über einen Zahlungslink (Anzahlung, Rest, Kaution).
	 *
	 * @return string|WP_Error Weiterleitungs-URL
	 */
	public static function start_single( array $booking, $provider, $for ) {
		$amount = self::amount_for( $booking, $for );
		if ( $amount <= 0 ) {
			return new WP_Error( 'msb_pay', 'Für diese Buchung ist nichts mehr offen.' );
		}
		$labels = self::type_labels();
		$desc   = $labels[ $for ] . ' ' . $booking['booking_number'];

		if ( 'stripe' === $provider && MSB_Gateway_Stripe::enabled() ) {
			$pid = self::add( $booking['id'], 'stripe', $for, $amount, 'pending', '', array( 'mode' => 'payment' ) );
			$s   = MSB_Gateway_Stripe::create_session(
				$booking,
				array(
					'mode'           => 'payment',
					'amount'         => $amount,
					'description'    => 'security' === $for ? 'Kaution ' . $booking['booking_number'] . ' (wird nur reserviert)' : $desc,
					'manual_capture' => 'security' === $for,
					// Bei Zahlung der Miete die Karte für die spätere Kautions-Reservierung speichern.
					'save_card'      => 'security' !== $for && self::security_hold_active() && (float) $booking['deposit'] > 0 && ! self::has_active_security( $booking['id'] ),
					'success_url'    => self::return_url( 'stripe', $booking, $pid ),
					'cancel_url'     => self::cancel_url( $booking, $pid ),
				)
			);
			if ( is_wp_error( $s ) ) {
				self::update( $pid, array( 'status' => 'failed' ), array( 'error' => $s->get_error_message() ) );
				return $s;
			}
			self::update( $pid, array( 'provider_ref' => $s['id'] ) );
			return $s['url'];
		}

		if ( 'paypal' === $provider && MSB_Gateway_PayPal::enabled() ) {
			$intent = 'security' === $for ? 'AUTHORIZE' : 'CAPTURE';
			$pid    = self::add( $booking['id'], 'paypal', $for, $amount, 'pending', '', array( 'intent' => $intent ) );
			$order  = MSB_Gateway_PayPal::create_order( $booking, $intent, $amount, 'security' === $for ? 'Kaution ' . $booking['booking_number'] . ' (wird nur reserviert)' : $desc, self::return_url( 'paypal', $booking, $pid ), self::cancel_url( $booking, $pid ) );
			if ( is_wp_error( $order ) ) {
				self::update( $pid, array( 'status' => 'failed' ), array( 'error' => $order->get_error_message() ) );
				return $order;
			}
			self::update( $pid, array( 'provider_ref' => $order['id'] ) );
			return $order['approve_url'];
		}

		return new WP_Error( 'msb_pay', 'Diese Zahlungsart ist nicht verfügbar.' );
	}

	public static function amount_for( array $booking, $for ) {
		$sum = self::summary( $booking );
		switch ( $for ) {
			case 'prepay':
				return $sum['prepay_open'];
			case 'rest':
				return $sum['open'];
			case 'security':
				$s = $sum['security'];
				if ( $s && in_array( $s['status'], array( 'authorized', 'scheduled', 'captured' ), true ) ) {
					return 0.0;
				}
				return (float) $booking['deposit'];
		}
		return 0.0;
	}

	/**
	 * Welche Zahlung ist für einen Link sinnvoll?
	 */
	public static function default_for( array $booking ) {
		if ( self::amount_for( $booking, 'prepay' ) > 0 ) {
			return 'prepay';
		}
		if ( self::security_hold_active() && self::amount_for( $booking, 'security' ) > 0 ) {
			return 'security';
		}
		return self::amount_for( $booking, 'rest' ) > 0 ? 'rest' : '';
	}

	private static function latest_end( array $booking ) {
		$end = '';
		foreach ( $booking['items'] as $l ) {
			$end = max( $end, $l['end_date'] );
		}
		return $end;
	}

	private static function earliest_start( array $booking ) {
		$start = '9999-12-31';
		foreach ( $booking['items'] as $l ) {
			$start = min( $start, $l['start_date'] );
		}
		return $start;
	}

	/* ------------------------------------------------------------------
	 * Rückkehr vom Zahlungsanbieter, Zahlungsseite, Webhook
	 * ------------------------------------------------------------------ */

	public static function handle_requests() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( empty( $_GET['msb_pay'] ) ) {
			return;
		}
		$token   = sanitize_key( wp_unslash( $_GET['msb_pay'] ) );
		$booking = MSB_Bookings::get_by_token( $token );
		if ( ! $booking ) {
			return;
		}

		// Abbruch beim Zahlungsanbieter.
		if ( isset( $_GET['msb_cancel'] ) ) {
			$pid = isset( $_GET['pid'] ) ? absint( $_GET['pid'] ) : 0;
			$p   = $pid ? self::get( $pid ) : null;
			if ( $p && (int) $p['booking_id'] === (int) $booking['id'] && 'pending' === $p['status'] ) {
				foreach ( self::for_booking( $booking['id'] ) as $row ) {
					if ( 'pending' === $row['status'] && $row['provider_ref'] === $p['provider_ref'] ) {
						self::update( $row['id'], array( 'status' => 'cancelled' ) );
					}
				}
			}
			if ( 'awaiting_payment' === $booking['status'] ) {
				// Erste Zahlung abgebrochen: Buchung stornieren, Auswahl zurück in den Warenkorb.
				MSB_Bookings::update_status( $booking['id'], 'cancelled', false );
				MSB_Cart::restore_from_booking( $booking );
				wp_safe_redirect( add_query_arg( 'msb_cancelled', 1, self::base_url() ) );
				exit;
			}
			wp_safe_redirect( self::pay_url( $booking ) );
			exit;
		}

		// Rückkehr nach erfolgreicher Zahlung.
		if ( ! empty( $_GET['msb_return'] ) ) {
			$provider = sanitize_key( wp_unslash( $_GET['msb_return'] ) );
			$pid      = isset( $_GET['pid'] ) ? absint( $_GET['pid'] ) : 0;
			$p        = $pid ? self::get( $pid ) : null;
			$error    = '';
			if ( ! $p || (int) $p['booking_id'] !== (int) $booking['id'] ) {
				$error = 'Zahlung nicht gefunden.';
			} elseif ( 'stripe' === $provider ) {
				$session = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : $p['provider_ref'];
				if ( $session !== $p['provider_ref'] ) {
					$error = 'Ungültige Zahlungssitzung.';
				} else {
					$r     = self::complete_stripe_session( $session );
					$error = is_wp_error( $r ) ? $r->get_error_message() : '';
				}
			} elseif ( 'paypal' === $provider ) {
				$order = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : $p['provider_ref'];
				if ( $order !== $p['provider_ref'] ) {
					$error = 'Ungültige PayPal-Bestellung.';
				} else {
					$r     = self::complete_paypal_order( $order );
					$error = is_wp_error( $r ) ? $r->get_error_message() : '';
				}
			}
			wp_safe_redirect( self::done_url( MSB_Bookings::get( $booking['id'] ), $error ) );
			exit;
		}

		// Zahlungsseite: Kunde wählt Anbieter.
		if ( 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) && isset( $_POST['msb_pay_provider'] ) ) {
			check_admin_referer( 'msb_pay_' . $booking['token'] );
			$provider = sanitize_key( wp_unslash( $_POST['msb_pay_provider'] ) );
			$for      = isset( $_POST['for'] ) ? sanitize_key( wp_unslash( $_POST['for'] ) ) : 'prepay';
			if ( ! in_array( $for, array( 'prepay', 'rest', 'security' ), true ) ) {
				$for = 'prepay';
			}
			$url = self::start_single( $booking, $provider, $for );
			if ( is_wp_error( $url ) ) {
				wp_safe_redirect( add_query_arg( 'msb_err', rawurlencode( $url->get_error_message() ), self::pay_url( $booking, $for ) ) );
				exit;
			}
			wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect -- externe Zahlungsseite.
			exit;
		}
		// phpcs:enable
	}

	/**
	 * Stripe-Checkout abschließen (Rückkehr oder Webhook). Idempotent.
	 */
	public static function complete_stripe_session( $session_id ) {
		$rows = array_filter(
			self::by_ref( $session_id ),
			function ( $r ) {
				return 'pending' === $r['status'];
			}
		);
		if ( empty( $rows ) ) {
			return true; // Bereits verarbeitet.
		}
		$s = MSB_Gateway_Stripe::get_session( $session_id );
		if ( is_wp_error( $s ) ) {
			return $s;
		}
		if ( 'complete' !== $s['status'] ) {
			return new WP_Error( 'msb_stripe', 'Die Zahlung wurde noch nicht abgeschlossen.' );
		}
		$booking_id = 0;
		foreach ( $rows as $row ) {
			$booking_id = (int) $row['booking_id'];
			$customer   = is_array( $s['customer'] ) ? $s['customer']['id'] : (string) $s['customer'];

			if ( 'setup' === $s['mode'] ) {
				$si = $s['setup_intent'];
				$pm = is_array( $si['payment_method'] ) ? $si['payment_method']['id'] : $si['payment_method'];
				self::update(
					$row['id'],
					array( 'status' => 'scheduled', 'provider_ref' => $si['id'] ),
					array( 'customer' => $customer, 'payment_method' => $pm )
				);
				continue;
			}

			$pi = $s['payment_intent'];
			$pm = is_array( $pi['payment_method'] ) ? $pi['payment_method']['id'] : $pi['payment_method'];
			if ( 'security' === $row['type'] ) {
				if ( 'requires_capture' !== $pi['status'] ) {
					self::update( $row['id'], array( 'status' => 'failed' ), array( 'error' => 'Status ' . $pi['status'] ) );
					continue;
				}
				self::update(
					$row['id'],
					array( 'status' => 'authorized', 'provider_ref' => $pi['id'] ),
					array( 'expires' => gmdate( 'c', time() + MSB_Gateway_Stripe::hold_validity_days() * DAY_IN_SECONDS ) )
				);
				self::cancel_open_security_requests( $booking_id, $row['id'] );
				continue;
			}

			if ( ! in_array( $pi['status'], array( 'succeeded', 'processing' ), true ) ) {
				self::update( $row['id'], array( 'status' => 'failed' ), array( 'error' => 'Status ' . $pi['status'] ) );
				continue;
			}
			self::update( $row['id'], array( 'status' => 'paid', 'provider_ref' => $pi['id'] ), array( 'customer' => $customer, 'payment_method' => $pm ) );

			// Karte wurde für die Kaution gespeichert.
			if ( in_array( $row['type'], array( 'prepay', 'rest' ), true ) && ! empty( $pi['setup_future_usage'] ) && $pm ) {
				$booking = MSB_Bookings::get( $booking_id );
				if ( $booking && (float) $booking['deposit'] > 0 && ! self::has_active_security( $booking_id ) ) {
					self::add( $booking_id, 'stripe', 'security', $booking['deposit'], 'scheduled', '', array( 'customer' => $customer, 'payment_method' => $pm ) );
				}
			}
		}
		self::after_payment( $booking_id );
		return true;
	}

	/**
	 * PayPal-Bestellung abschließen. Idempotent.
	 */
	public static function complete_paypal_order( $order_id ) {
		$rows = array_values(
			array_filter(
				self::by_ref( $order_id ),
				function ( $r ) {
					return 'pending' === $r['status'];
				}
			)
		);
		if ( empty( $rows ) ) {
			return true;
		}
		$booking_id = (int) $rows[0]['booking_id'];
		$intent     = isset( $rows[0]['data']['intent'] ) ? $rows[0]['data']['intent'] : 'CAPTURE';

		if ( 'CAPTURE' === $intent ) {
			$cap = MSB_Gateway_PayPal::capture_order( $order_id );
			if ( is_wp_error( $cap ) ) {
				foreach ( $rows as $r ) {
					self::update( $r['id'], array( 'status' => 'failed' ), array( 'error' => $cap->get_error_message() ) );
				}
				return $cap;
			}
			foreach ( $rows as $r ) {
				self::update( $r['id'], array( 'status' => 'paid', 'provider_ref' => $cap['id'] ), array( 'order' => $order_id ) );
			}
			self::after_payment( $booking_id );
			return true;
		}

		$auth = MSB_Gateway_PayPal::authorize_order( $order_id );
		if ( is_wp_error( $auth ) ) {
			foreach ( $rows as $r ) {
				self::update( $r['id'], array( 'status' => 'failed' ), array( 'error' => $auth->get_error_message() ) );
			}
			return $auth;
		}
		$security = null;
		$prepay   = null;
		foreach ( $rows as $r ) {
			if ( 'security' === $r['type'] ) {
				$security = $r;
			} else {
				$prepay = $r;
			}
		}
		if ( $prepay ) {
			$cap = MSB_Gateway_PayPal::capture_authorization( $auth['id'], $prepay['amount'], ! $security );
			if ( is_wp_error( $cap ) ) {
				self::update( $prepay['id'], array( 'status' => 'failed' ), array( 'error' => $cap->get_error_message() ) );
				if ( $security ) {
					MSB_Gateway_PayPal::void( $auth['id'] );
					self::update( $security['id'], array( 'status' => 'cancelled' ) );
				}
				return $cap;
			}
			self::update( $prepay['id'], array( 'status' => 'paid', 'provider_ref' => $cap['id'] ), array( 'order' => $order_id, 'authorization' => $auth['id'] ) );
		}
		if ( $security ) {
			self::update( $security['id'], array( 'status' => 'authorized', 'provider_ref' => $auth['id'] ), array( 'order' => $order_id, 'expires' => $auth['expires'] ) );
			self::cancel_open_security_requests( $booking_id, $security['id'] );
		}
		self::after_payment( $booking_id );
		return true;
	}

	private static function has_active_security( $booking_id ) {
		foreach ( self::for_booking( $booking_id ) as $p ) {
			if ( 'security' === $p['type'] && in_array( $p['status'], array( 'scheduled', 'authorized', 'captured', 'pending' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	private static function cancel_open_security_requests( $booking_id, $except ) {
		foreach ( self::for_booking( $booking_id ) as $p ) {
			if ( 'security' === $p['type'] && 'requested' === $p['status'] && (int) $p['id'] !== (int) $except ) {
				self::update( $p['id'], array( 'status' => 'cancelled' ) );
			}
		}
	}

	/**
	 * Nach jeder erfolgreichen Zahlung.
	 */
	private static function after_payment( $booking_id ) {
		$booking = MSB_Bookings::get( $booking_id );
		if ( ! $booking ) {
			return;
		}
		if ( 'awaiting_payment' === $booking['status'] ) {
			MSB_Bookings::finalize( $booking_id );
		}
		do_action( 'msb_payment_completed', $booking_id );
		self::maybe_hold_security( $booking_id );
	}

	public static function register_webhook() {
		register_rest_route(
			'msb/v1',
			'/stripe-webhook',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'stripe_webhook' ),
			)
		);
	}

	public static function webhook_url() {
		return rest_url( 'msb/v1/stripe-webhook' );
	}

	public static function stripe_webhook( WP_REST_Request $req ) {
		$payload = $req->get_body();
		$sig     = $req->get_header( 'stripe_signature' );
		if ( ! MSB_Gateway_Stripe::verify_signature( $payload, $sig, msb_setting( 'stripe_webhook_secret' ) ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid signature' ), 400 );
		}
		$event = json_decode( $payload, true );
		if ( isset( $event['type'] ) && 'checkout.session.completed' === $event['type'] ) {
			$r = self::complete_stripe_session( $event['data']['object']['id'] );
			if ( is_wp_error( $r ) ) {
				return new WP_REST_Response( array( 'error' => $r->get_error_message() ), 500 );
			}
		}
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	/* ------------------------------------------------------------------
	 * Kaution
	 * ------------------------------------------------------------------ */

	/**
	 * Reserviert die Kaution, wenn die Abholung nah genug ist.
	 *
	 * @param bool $force Sofort (Button im Backend), unabhängig vom Zeitpunkt.
	 * @return true|WP_Error|null
	 */
	public static function maybe_hold_security( $booking_id, $force = false ) {
		if ( ! self::security_hold_active() ) {
			return null;
		}
		$booking = MSB_Bookings::get( $booking_id );
		if ( ! $booking || (float) $booking['deposit'] <= 0 || ! in_array( $booking['status'], array( 'pending', 'confirmed' ), true ) ) {
			return null;
		}
		// Barzahlung: Kaution wird bei Abholung bar gezahlt, nur manuell auslösbar.
		if ( ! $force && self::is_cash( $booking ) ) {
			return null;
		}
		$due = msb_add_days( msb_today(), max( 0, (int) msb_setting( 'security_hold_days' ) ) );
		if ( ! $force && self::earliest_start( $booking ) > $due ) {
			return null;
		}

		$rows = self::for_booking( $booking_id );
		foreach ( $rows as $p ) {
			if ( 'security' === $p['type'] && in_array( $p['status'], array( 'authorized', 'captured', 'pending' ), true ) ) {
				return null; // Bereits reserviert oder in Arbeit.
			}
		}

		// 1. Gespeicherte Karte (Stripe) belasten.
		foreach ( $rows as $p ) {
			if ( 'security' === $p['type'] && 'scheduled' === $p['status'] && 'stripe' === $p['provider'] && MSB_Gateway_Stripe::enabled() ) {
				$pi = MSB_Gateway_Stripe::hold_off_session( $booking, $booking['deposit'], $p['data']['customer'], $p['data']['payment_method'] );
				if ( ! is_wp_error( $pi ) && 'requires_capture' === $pi['status'] ) {
					self::update(
						$p['id'],
						array( 'status' => 'authorized', 'provider_ref' => $pi['id'], 'amount' => $booking['deposit'] ),
						array( 'expires' => gmdate( 'c', time() + MSB_Gateway_Stripe::hold_validity_days() * DAY_IN_SECONDS ) )
					);
					return true;
				}
				$err = is_wp_error( $pi ) ? $pi->get_error_message() : 'Status ' . $pi['status'];
				self::update( $p['id'], array( 'status' => 'failed' ), array( 'error' => $err ) );
				break; // Weiter mit Zahlungslink.
			}
		}

		// 2. Zahlungslink senden (einmal).
		foreach ( self::for_booking( $booking_id ) as $p ) {
			if ( 'security' === $p['type'] && 'requested' === $p['status'] && ! $force ) {
				return null;
			}
		}
		self::add( $booking_id, '', 'security', $booking['deposit'], 'requested', '', array( 'sent' => current_time( 'mysql' ) ) );
		MSB_Emails::send( $booking_id, 'payment_link', array( 'for' => 'security' ) );
		return true;
	}

	/**
	 * Kaution freigeben.
	 */
	public static function release_security( $payment_id, $notify = true ) {
		$p = self::get( $payment_id );
		if ( ! $p || 'security' !== $p['type'] ) {
			return new WP_Error( 'msb_pay', 'Zahlung nicht gefunden.' );
		}
		if ( 'scheduled' === $p['status'] || 'requested' === $p['status'] || 'pending' === $p['status'] ) {
			self::update( $p['id'], array( 'status' => 'cancelled' ) );
			return true;
		}
		if ( 'authorized' !== $p['status'] ) {
			return new WP_Error( 'msb_pay', 'Die Kaution ist nicht reserviert.' );
		}
		// Barkaution (manuell erfasst): kein Zahlungsanbieter, nur vermerken, dass sie zurückgegeben wurde.
		$manual = 'manual' === $p['provider'];
		if ( ! $manual ) {
			$r = 'stripe' === $p['provider'] ? MSB_Gateway_Stripe::release( $p['provider_ref'] ) : MSB_Gateway_PayPal::void( $p['provider_ref'] );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
		self::update( $p['id'], array( 'status' => 'released' ), array( 'released_at' => current_time( 'mysql' ) ) );
		if ( $notify && ! $manual ) {
			MSB_Emails::send( $p['booking_id'], 'security_released' );
		}
		do_action( 'msb_security_released', $p['booking_id'], $p['id'] );
		return true;
	}

	/**
	 * Kaution ganz oder teilweise einbehalten (Rest wird freigegeben).
	 */
	public static function capture_security( $payment_id, $amount, $reason = '' ) {
		$p = self::get( $payment_id );
		if ( ! $p || 'security' !== $p['type'] || 'authorized' !== $p['status'] ) {
			return new WP_Error( 'msb_pay', 'Die Kaution ist nicht reserviert.' );
		}
		$amount = round( min( (float) $p['amount'], max( 0.01, (float) $amount ) ), 2 );
		if ( 'manual' === $p['provider'] ) {
			$r = array(); // Barkaution: Einbehalt nur vermerken, der Rest wird bar zurückgegeben.
		} else {
			$r = 'stripe' === $p['provider'] ? MSB_Gateway_Stripe::capture( $p['provider_ref'], $amount ) : MSB_Gateway_PayPal::capture_authorization( $p['provider_ref'], $amount, true );
		}
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		self::update(
			$p['id'],
			array( 'status' => 'captured' ),
			array(
				'captured'    => $amount,
				'reason'      => sanitize_text_field( $reason ),
				'captured_at' => current_time( 'mysql' ),
				'capture_ref' => isset( $r['id'] ) ? $r['id'] : '',
			)
		);
		do_action( 'msb_security_captured', $p['booking_id'], $p['id'] );
		return true;
	}

	/**
	 * Barzahlung bei der Abholung vermerken (Häkchen im Statuskasten beim Wechsel auf „Abgeholt“).
	 * Miete: offener Betrag als bezahlt. Kaution: als bar erhalten, solange noch keine Kaution erfasst ist.
	 */
	public static function record_cash_pickup( $booking_id, $rent, $deposit ) {
		$b = MSB_Bookings::get( $booking_id );
		if ( ! $b ) {
			return;
		}
		$sum  = self::summary( $b );
		$data = array(
			'note' => 'bar bei Abholung',
			'by'   => get_current_user_id(),
		);
		if ( $rent && $sum['open'] > 0 ) {
			self::add( $booking_id, 'manual', 'rest', $sum['open'], 'paid', '', $data );
		}
		$active = $sum['security'] && in_array( $sum['security']['status'], array( 'authorized', 'captured' ), true );
		if ( $deposit && (float) $b['deposit'] > 0 && ! $active ) {
			self::add( $booking_id, 'manual', 'security', (float) $b['deposit'], 'authorized', '', $data );
		}
	}

	/**
	 * Zahlung (ganz oder teilweise) erstatten.
	 */
	public static function refund( $payment_id, $amount ) {
		$p = self::get( $payment_id );
		if ( ! $p || 'paid' !== $p['status'] || 'security' === $p['type'] ) {
			return new WP_Error( 'msb_pay', 'Diese Zahlung kann nicht erstattet werden.' );
		}
		$already = isset( $p['data']['refunded'] ) ? (float) $p['data']['refunded'] : 0;
		$amount  = round( min( (float) $p['amount'] - $already, max( 0.01, (float) $amount ) ), 2 );
		if ( 'stripe' === $p['provider'] ) {
			$r = MSB_Gateway_Stripe::refund( $p['provider_ref'], $amount );
		} elseif ( 'paypal' === $p['provider'] ) {
			$r = MSB_Gateway_PayPal::refund( $p['provider_ref'], $amount );
		} else {
			$r = true; // Manuell erfasste Zahlung: nur vermerken.
		}
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$total   = $already + $amount;
		$refunds = isset( $p['data']['refunds'] ) && is_array( $p['data']['refunds'] ) ? $p['data']['refunds'] : array();
		// Jede Erstattung mit Datum merken, für den EÜR-Export (ab 1.7.1).
		$refunds[] = array( current_time( 'mysql' ), $amount );
		self::update( $p['id'], array( 'status' => $total >= (float) $p['amount'] ? 'refunded' : 'paid' ), array( 'refunded' => $total, 'refunds' => $refunds ) );
		return true;
	}

	/* ------------------------------------------------------------------
	 * Automatik
	 * ------------------------------------------------------------------ */

	public static function on_status_change( $booking_id, $status, $old ) {
		if ( 'returned' === $status && msb_setting( 'security_auto_release' ) ) {
			foreach ( self::for_booking( $booking_id ) as $p ) {
				if ( 'security' === $p['type'] && 'authorized' === $p['status'] && 'manual' !== $p['provider'] ) {
					self::release_security( $p['id'] );
				}
			}
		}
		if ( in_array( $status, array( 'returned', 'cancelled' ), true ) ) {
			foreach ( self::for_booking( $booking_id ) as $p ) {
				if ( 'security' === $p['type'] && in_array( $p['status'], array( 'scheduled', 'requested', 'pending' ), true ) ) {
					self::update( $p['id'], array( 'status' => 'cancelled' ) );
				}
				if ( 'cancelled' === $status && 'security' === $p['type'] && 'authorized' === $p['status'] ) {
					self::release_security( $p['id'], false );
				}
			}
		}
		if ( 'confirmed' === $status ) {
			self::maybe_hold_security( $booking_id );
		}
	}

	public static function cron() {
		global $wpdb;
		$b = MSB_Install::table( 'bookings' );

		// 1. Unbezahlte Online-Buchungen nach Ablauf stornieren (vorher PayPal-Status prüfen).
		$timeout = max( 15, (int) msb_setting( 'payment_timeout', 60 ) );
		$limit   = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - $timeout * MINUTE_IN_SECONDS );
		$ids     = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$b} WHERE status = 'awaiting_payment' AND created_at < %s", $limit ) ); // phpcs:ignore
		foreach ( $ids as $id ) {
			self::check_pending_paypal( $id );
			$booking = MSB_Bookings::get( $id );
			if ( $booking && 'awaiting_payment' === $booking['status'] ) {
				foreach ( self::for_booking( $id ) as $p ) {
					if ( 'pending' === $p['status'] ) {
						self::update( $p['id'], array( 'status' => 'cancelled' ) );
					}
				}
				MSB_Bookings::update_status( $id, 'cancelled', false );
			}
		}

		// 2. Kautionen kurz vor Abholung reservieren.
		if ( self::security_hold_active() ) {
			$due  = msb_add_days( msb_today(), max( 0, (int) msb_setting( 'security_hold_days' ) ) );
			$i    = MSB_Install::table( 'booking_items' );
			$list = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT b.id FROM {$b} b INNER JOIN {$i} i ON i.booking_id = b.id WHERE b.status IN ('pending','confirmed') AND b.deposit > 0 AND i.start_date <= %s AND i.end_date >= %s", $due, msb_today() ) ); // phpcs:ignore
			foreach ( $list as $id ) {
				self::maybe_hold_security( (int) $id );
			}
		}
	}

	/**
	 * Falls ein Kunde bei PayPal bezahlt, aber das Fenster vor der Rückleitung geschlossen hat.
	 */
	public static function check_pending_paypal( $booking_id ) {
		if ( ! MSB_Gateway_PayPal::enabled() ) {
			return;
		}
		$done = array();
		foreach ( self::for_booking( $booking_id ) as $p ) {
			if ( 'paypal' !== $p['provider'] || 'pending' !== $p['status'] || ! $p['provider_ref'] || isset( $done[ $p['provider_ref'] ] ) ) {
				continue;
			}
			$done[ $p['provider_ref'] ] = true;
			$order                      = MSB_Gateway_PayPal::get_order( $p['provider_ref'] );
			if ( ! is_wp_error( $order ) && isset( $order['status'] ) && 'APPROVED' === $order['status'] ) {
				self::complete_paypal_order( $p['provider_ref'] );
			}
		}
	}
}
