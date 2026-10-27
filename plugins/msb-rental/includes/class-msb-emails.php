<?php
/**
 * E-Mail-Versand.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Emails {

	public static function init() {
		add_action( 'wp_mail_succeeded', array( __CLASS__, 'log_sent' ) );
		add_action( 'wp_mail_failed', array( __CLASS__, 'log_failed' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_add_payment_info' ) );
	}

	/**
	 * Einmalig (1.5.5): Den festen Kautionssatz der Bestätigungsmail durch {payment_info} ersetzen,
	 * damit die Mail bei Bar- und Kartenzahlung den passenden Hinweis zeigt.
	 */
	public static function maybe_add_payment_info() {
		if ( get_option( 'msb_payment_info_version' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s   = get_option( 'msb_settings', array() );
		$old = 'Haben Sie online mit Karte bezahlt, reservieren wir die Kaution auf Ihrer Karte und geben sie nach der Rückgabe frei. Bei Barzahlung zahlen Sie die Kaution bei der Abholung und erhalten sie bei der Rückgabe zurück.';
		if ( is_array( $s ) && ! empty( $s['email_confirmed_body'] ) && false !== strpos( $s['email_confirmed_body'], $old ) && false === strpos( $s['email_confirmed_body'], '{payment_info}' ) ) {
			$s['email_confirmed_body'] = preg_replace( '/\s*' . preg_quote( $old, '/' ) . '/u', "\r\n\r\n{payment_info}", $s['email_confirmed_body'], 1 );
			update_option( 'msb_settings', $s );
		}
		update_option( 'msb_payment_info_version', '1.5.5', false );
	}

	/**
	 * Kurzes Versandprotokoll (die letzten 30 Mails der Webseite), damit sich prüfen lässt, ob eine Mail
	 * an den Mailserver übergeben wurde. Steht in der Option msb_mail_log, wird nicht automatisch geladen.
	 */
	private static function log( $to, $subject, $ok, $error = '' ) {
		$log = get_option( 'msb_mail_log', array() );
		$log = is_array( $log ) ? $log : array();
		array_unshift(
			$log,
			array(
				'time'    => current_time( 'mysql' ),
				'to'      => implode( ', ', (array) $to ),
				'subject' => (string) $subject,
				'ok'      => $ok ? 1 : 0,
				'error'   => (string) $error,
			)
		);
		update_option( 'msb_mail_log', array_slice( $log, 0, 30 ), false );
	}

	public static function log_sent( $data ) {
		self::log( isset( $data['to'] ) ? $data['to'] : '', isset( $data['subject'] ) ? $data['subject'] : '', true );
	}

	public static function log_failed( $error ) {
		$data = is_wp_error( $error ) ? (array) $error->get_error_data() : array();
		self::log( isset( $data['to'] ) ? $data['to'] : '', isset( $data['subject'] ) ? $data['subject'] : '', false, is_wp_error( $error ) ? $error->get_error_message() : '' );
	}

	public static function types() {
		return array(
			'admin'     => 'Benachrichtigung an Sie (neue Buchung)',
			'received'  => 'Kunde: Buchungsanfrage eingegangen',
			'confirmed' => 'Kunde: Buchung bestätigt',
			'cancelled' => 'Kunde: Buchung storniert',
			'payment_link'      => 'Kunde: Zahlungslink (Anzahlung, Rest oder Kaution)',
			'security_released' => 'Kunde: Kaution freigegeben',
		);
	}

	public static function placeholders() {
		return array(
			'{booking_number}' => 'Buchungsnummer',
			'{customer_name}'  => 'Name des Kunden',
			'{customer_email}' => 'E-Mail des Kunden',
			'{customer_phone}' => 'Telefon des Kunden',
			'{items}'          => 'Tabelle der gemieteten Artikel mit Zeitraum und Preis',
			'{total}'          => 'Mietpreis gesamt',
			'{deposit}'        => 'Kaution gesamt',
			'{discount}'       => 'Gewährte Rabatte gesamt',
			'{prepay}'         => 'Fällige Anzahlung',
			'{paid}'           => 'Bereits bezahlt',
			'{open_amount}'    => 'Noch offener Mietpreis',
			'{payment_method}' => 'Gewählte Zahlungsart',
			'{payment_info}'   => 'Zahlungshinweis passend zur Zahlungsart: bar bei Abholung oder Online-Zahlung mit Bezahlknopf, jeweils mit Kaution',
			'{payment_link}'   => 'Link zur Online-Zahlung (nur in der Zahlungslink-Mail)',
			'{payment_amount}' => 'Betrag des Zahlungslinks',
			'{payment_purpose}'=> 'Zweck des Zahlungslinks (Anzahlung, Restzahlung, Kaution)',
			'{account_link}'   => 'Link zum Kundenkonto',
			'{cancel_fee}'     => 'Stornogebühr (nur in der Stornomail)',
			'{refund_amount}'  => 'Erstatteter Betrag (nur in der Stornomail)',
			'{status}'         => 'Buchungsstatus',
			'{source}'         => 'Herkunft: „Webseite“ oder „manuell angelegt von …“',
			'{created_by}'     => 'Wer die Buchung im Backend angelegt hat (leer bei Buchungen über die Webseite)',
			'{customer_data}'  => 'Alle Angaben aus dem Buchungsformular',
			'{field:schluessel}' => 'Einzelnes Formularfeld, z. B. {field:street}',
			'{booking_date}'   => 'Buchungsdatum',
			'{site_name}'      => 'Name der Webseite',
			'{site_url}'       => 'Adresse der Webseite',
			'{admin_link}'     => 'Link zur Buchung im Backend (nur für Admin-Mail sinnvoll)',
		);
	}

	/**
	 * Einfache HTML-Mail im Design des Plugins.
	 */
	public static function send_raw( $to, $subject, $html_body, array $attachments = array() ) {
		$s          = msb_settings();
		$from_name  = $s['from_name'] ? $s['from_name'] : get_bloginfo( 'name' );
		$from_email = is_email( $s['from_email'] ) ? $s['from_email'] : get_option( 'admin_email' );
		return wp_mail(
			$to,
			$subject,
			self::wrap( $html_body ),
			array(
				'Content-Type: text/html; charset=UTF-8',
				'From: ' . self::header_safe( $from_name ) . ' <' . $from_email . '>',
			),
			$attachments
		);
	}

	public static function send( $booking_id, $type, array $extra = array() ) {
		$s = msb_settings();
		if ( empty( $s[ 'email_' . $type . '_enabled' ] ) ) {
			return false;
		}
		$booking = MSB_Bookings::get( $booking_id );
		if ( ! $booking ) {
			return false;
		}

		if ( 'admin' === $type ) {
			$to = $s['admin_email'];
		} else {
			$to = $booking['customer_email'];
		}
		if ( ! is_email( $to ) ) {
			return false;
		}

		if ( 'payment_link' === $type ) {
			$for = isset( $extra['for'] ) ? $extra['for'] : MSB_Payments::default_for( $booking );
			if ( ! $for || ! MSB_Payments::online_providers() ) {
				return false;
			}
			$labels                       = MSB_Payments::type_labels();
			$booking['_payment_link']     = MSB_Payments::pay_url( $booking, $for );
			$booking['_payment_amount']   = MSB_Payments::amount_for( $booking, $for );
			$booking['_payment_purpose']  = 'security' === $for ? 'Kaution – wird nur reserviert und nach der Rückgabe freigegeben' : $labels[ $for ];
		}

		$subject = wp_strip_all_tags( self::replace( $s[ 'email_' . $type . '_subject' ], $booking, false ) );
		$body    = self::replace( wpautop( $s[ 'email_' . $type . '_body' ] ), $booking, true );
		// Online zu zahlende Buchung ohne {payment_info} in der Vorlage: Bezahlknopf trotzdem mitschicken.
		if ( in_array( $type, array( 'received', 'confirmed' ), true ) && false === strpos( $s[ 'email_' . $type . '_body' ], '{payment_info}' ) && self::needs_online_payment( $booking ) ) {
			$body .= self::payment_info( $booking, true );
		}
		// Manuelle Buchung: Bestätigung von Mietbedingungen und Datenschutz in dieselbe Mail statt einer zweiten Mail.
		if ( in_array( $type, array( 'received', 'confirmed' ), true ) && class_exists( 'MSB_Terms_Confirm' ) && MSB_Terms_Confirm::needs_confirmation( $booking ) ) {
			$block = MSB_Terms_Confirm::mail_block( $booking );
			$pos   = strpos( $body, '<p>Viele Grüße' );
			$body  = false === $pos ? $body . $block : substr( $body, 0, $pos ) . $block . substr( $body, $pos );
		}
		// Stornomail: Gebühr und Erstattung immer nennen, auch wenn die Vorlage die Platzhalter nicht enthält.
		if ( 'cancelled' === $type && isset( $booking['cancel_fee'] ) && null !== $booking['cancel_fee'] && false === strpos( $s['email_cancelled_body'], '{cancel_fee}' ) && false === strpos( $s['email_cancelled_body'], '{refund_amount}' ) ) {
			$refund = class_exists( 'MSB_Cancellation' ) ? MSB_Cancellation::refunded_total( $booking['id'] ) : 0;
			$body  .= '<p>Stornogebühr: ' . esc_html( msb_price( $booking['cancel_fee'] ) ) . '<br>Erstattung: ' . esc_html( msb_price( $refund ) ) . ( $refund > 0 ? ' – wir überweisen sie auf Ihr Zahlungsmittel, die Gutschrift dauert meist 5 bis 10 Bankarbeitstage.' : '' ) . '</p>';
		}
		// Team-Mail zu einer manuellen Buchung: immer als manuell erkennbar, auch wenn die Vorlage {source} nicht nutzt.
		if ( 'admin' === $type && 'admin' === $booking['source'] ) {
			$tpl = $s['email_admin_subject'] . $s['email_admin_body'];
			if ( false === strpos( $tpl, '{source}' ) && false === strpos( $tpl, '{created_by}' ) ) {
				$subject .= ' (' . MSB_Bookings::source_label( $booking ) . ')';
				$body     = '<p><strong>' . esc_html( ucfirst( MSB_Bookings::source_label( $booking ) ) . ' am ' . mysql2date( 'd.m.Y H:i', $booking['created_at'] ) . ' Uhr.' ) . '</strong></p>' . $body;
			}
		}
		// Bestätigung: AGB und Widerrufsbelehrung als PDF anhängen (1.8.3, dauerhafter Datenträger nach § 312f BGB).
		$attachments = array();
		if ( 'confirmed' === $type && class_exists( 'MSB_Legal_PDF' ) ) {
			$attachments = MSB_Legal_PDF::attachments();
			if ( $attachments ) {
				$note = '<p>Im Anhang finden Sie unsere AGB und die Widerrufsbelehrung in der Fassung, die für Ihre Buchung gilt.</p>';
				$pos  = strpos( $body, '<p>Viele Grüße' );
				$body = false === $pos ? $body . $note : substr( $body, 0, $pos ) . $note . substr( $body, $pos );
			}
		}
		$body    = self::wrap( $body );

		$from_name  = $s['from_name'] ? $s['from_name'] : get_bloginfo( 'name' );
		$from_email = is_email( $s['from_email'] ) ? $s['from_email'] : get_option( 'admin_email' );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . self::header_safe( $from_name ) . ' <' . $from_email . '>',
		);
		if ( 'admin' === $type && is_email( $booking['customer_email'] ) ) {
			$headers[] = 'Reply-To: ' . self::header_safe( $booking['customer_name'] ) . ' <' . $booking['customer_email'] . '>';
		}

		$args = apply_filters( 'msb_email_args', compact( 'to', 'subject', 'body', 'headers', 'attachments' ), $type, $booking );
		return wp_mail( $args['to'], $args['subject'], $args['body'], $args['headers'], isset( $args['attachments'] ) ? (array) $args['attachments'] : array() );
	}

	private static function header_safe( $s ) {
		return trim( str_replace( array( "\r", "\n", '<', '>', '"' ), '', (string) $s ) );
	}

	public static function items_table( array $booking ) {
		// Zwei Spalten, damit die Tabelle auch auf dem Handy nicht über den Rand läuft.
		$cell  = 'padding:8px 6px;border-bottom:1px solid #e3e3e3;text-align:left;vertical-align:top';
		$price = $cell . ';text-align:right;white-space:nowrap';
		$html  = '<table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%">';
		$html .= '<tr><th style="' . $cell . '">Artikel</th><th style="' . $price . '">Preis</th></tr>';
		foreach ( $booking['items'] as $l ) {
			$adj = '';
			foreach ( (array) $l['adjustments'] as $a ) {
				$adj .= '<br><small style="color:' . ( $a['amount'] < 0 ? '#2f6b3a' : '#8a5a00' ) . '">' . esc_html( $a['label'] ) . ': ' . ( $a['amount'] < 0 ? '−' : '+' ) . esc_html( msb_price( abs( $a['amount'] ) ) ) . '</small>';
			}
			$period = $l['start_date'] === $l['end_date'] ? msb_date( $l['start_date'] ) : msb_date( $l['start_date'] ) . ' – ' . msb_date( $l['end_date'] );
			$html  .= '<tr><td style="' . $cell . '">' . esc_html( $l['item_title'] )
				. '<br><small style="color:#555">' . esc_html( $period . ' · ' . MSB_Pricing::days_label( $l['days'] ) . ' · Anzahl ' . (int) $l['quantity'] ) . '</small>' . $adj . '</td>';
			$html  .= '<td style="' . $price . '">' . esc_html( msb_price( $l['line_total'] ) ) . '</td></tr>';
		}
		$html .= '<tr><td style="' . $cell . '"><strong>Mietpreis gesamt</strong></td><td style="' . $price . '"><strong>' . esc_html( msb_price( $booking['total'] ) ) . '</strong></td></tr>';
		if ( (float) $booking['deposit'] > 0 ) {
			$sum   = MSB_Payments::summary( $booking );
			$label = $sum['security_label'] ? ' <small>(' . esc_html( $sum['security_label'] ) . ')</small>' : '';
			$html .= '<tr><td style="' . $cell . '">Kaution' . $label . '</td><td style="' . $price . '">' . esc_html( msb_price( $booking['deposit'] ) ) . '</td></tr>';
		}
		$sum = isset( $sum ) ? $sum : MSB_Payments::summary( $booking );
		if ( $sum['paid'] > 0 ) {
			$html .= '<tr><td style="' . $cell . '">Bereits bezahlt</td><td style="' . $price . '">' . esc_html( msb_price( $sum['paid'] ) ) . '</td></tr>';
			$html .= '<tr><td style="' . $cell . '"><strong>Noch offen</strong></td><td style="' . $price . '"><strong>' . esc_html( msb_price( $sum['open'] ) ) . '</strong></td></tr>';
		}
		return $html . '</table>';
	}

	/**
	 * Platzhalter ersetzen.
	 */
	public static function replace( $text, array $booking, $html = true ) {
		$map = array(
			'{booking_number}' => $booking['booking_number'],
			'{customer_name}'  => $booking['customer_name'],
			'{customer_email}' => $booking['customer_email'],
			'{customer_phone}' => $booking['customer_phone'],
			'{total}'          => msb_price( $booking['total'] ),
			'{deposit}'        => msb_price( $booking['deposit'] ),
			'{status}'         => msb_status_label( $booking['status'] ),
			'{source}'         => MSB_Bookings::source_label( $booking ),
			'{created_by}'     => MSB_Bookings::creator_name( $booking ),
			'{booking_date}'   => mysql2date( 'd.m.Y H:i', $booking['created_at'] ),
			'{site_name}'      => get_bloginfo( 'name' ),
			'{site_url}'       => home_url( '/' ),
			'{admin_link}'     => admin_url( 'admin.php?page=msb-booking&id=' . (int) $booking['id'] ),
			'{discount}'       => msb_price( max( 0, (float) $booking['discount'] ) ),
			'{prepay}'         => msb_price( $booking['prepay'] ),
			'{payment_method}' => self::method_label( $booking['payment_method'] ),
			'{account_link}'   => msb_account_url(),
			'{payment_amount}' => isset( $booking['_payment_amount'] ) ? msb_price( $booking['_payment_amount'] ) : '',
			'{payment_purpose}'=> isset( $booking['_payment_purpose'] ) ? $booking['_payment_purpose'] : '',
			'{cancel_fee}'     => isset( $booking['cancel_fee'] ) && null !== $booking['cancel_fee'] ? msb_price( $booking['cancel_fee'] ) : msb_price( 0 ),
			'{refund_amount}'  => msb_price( class_exists( 'MSB_Cancellation' ) ? MSB_Cancellation::refunded_total( $booking['id'] ) : 0 ),
		);
		$sum                    = MSB_Payments::summary( $booking );
		$map['{paid}']          = msb_price( $sum['paid'] );
		$map['{open_amount}']   = msb_price( $sum['open'] );
		$map['{payment_link}']  = isset( $booking['_payment_link'] ) ? $booking['_payment_link'] : MSB_Payments::pay_url( $booking );
		$map['{payment_info}']  = self::payment_info( $booking, false );

		if ( $html ) {
			$map = array_map( 'esc_html', $map );
			// Blockelemente nicht in <p> einschließen.
			$text = preg_replace( '#<p>\s*(\{items\}|\{customer_data\}|\{payment_info\})\s*</p>#', '$1', $text );
			$map['{items}']         = self::items_table( $booking );
			$map['{customer_data}'] = MSB_Form_Fields::data_table( $booking['form_data'] );
			$map['{admin_link}']    = '<a href="' . esc_url( admin_url( 'admin.php?page=msb-booking&id=' . (int) $booking['id'] ) ) . '">' . esc_html( $booking['booking_number'] ) . '</a>';
			$pay_url                = isset( $booking['_payment_link'] ) ? $booking['_payment_link'] : MSB_Payments::pay_url( $booking );
			$map['{payment_link}']  = self::pay_button( $pay_url );
			$map['{payment_info}']  = self::payment_info( $booking, true );
			$map['{account_link}']  = msb_account_url() ? '<a href="' . esc_url( msb_account_url() ) . '">' . esc_html( msb_account_url() ) . '</a>' : '';
			$text                   = preg_replace( '#<p>\s*(\{payment_link\})\s*</p>#', '<p>$1</p>', $text );
		} else {
			$lines = array();
			foreach ( $booking['items'] as $l ) {
				$lines[] = $l['quantity'] . '× ' . $l['item_title'] . ' (' . msb_date( $l['start_date'] ) . ' – ' . msb_date( $l['end_date'] ) . ')';
			}
			$map['{items}']         = implode( ', ', $lines );
			$map['{customer_data}'] = '';
		}

		$text = preg_replace_callback(
			'/\{field:([a-z0-9_\-]+)\}/',
			function ( $m ) use ( $booking, $html ) {
				$v = isset( $booking['form_data'][ $m[1] ]['value'] ) ? $booking['form_data'][ $m[1] ]['value'] : '';
				return $html ? nl2br( esc_html( $v ) ) : $v;
			},
			$text
		);

		return strtr( $text, $map );
	}

	private static function pay_button( $url ) {
		$color = sanitize_hex_color( msb_setting( 'primary_color' ) );
		return '<a href="' . esc_url( $url ) . '" style="display:inline-block;padding:10px 18px;background:' . esc_attr( $color ? $color : '#2f6b3a' ) . ';color:#fff;text-decoration:none;border-radius:6px">Jetzt online bezahlen</a>';
	}

	/**
	 * Ist für die Buchung noch eine Online-Zahlung der Miete offen?
	 */
	public static function needs_online_payment( array $booking ) {
		if ( 'offline' === $booking['payment_method'] || ! MSB_Payments::online_providers() ) {
			return false;
		}
		$sum = MSB_Payments::summary( $booking );
		return $sum['open'] > 0;
	}

	/**
	 * Zahlungshinweis für {payment_info}: bar bei Abholung oder online per Karte, jeweils mit Kaution.
	 */
	public static function payment_info( array $booking, $html = true ) {
		$sum     = MSB_Payments::summary( $booking );
		$deposit = (float) $booking['deposit'];
		$parts   = array();
		$button  = '';

		if ( 'offline' === $booking['payment_method'] ) {
			if ( $sum['open'] > 0 ) {
				$parts[] = $deposit > 0
					? sprintf( 'Bitte zahlen Sie die Miete von %1$s und die Kaution von %2$s bei der Abholung in bar.', msb_price( $sum['open'] ), msb_price( $deposit ) )
					: sprintf( 'Bitte zahlen Sie die Miete von %s bei der Abholung in bar.', msb_price( $sum['open'] ) );
			} else {
				$parts[] = 'Die Miete ist bereits bezahlt.';
				if ( $deposit > 0 ) {
					$parts[] = sprintf( 'Die Kaution von %s zahlen Sie bei der Abholung in bar.', msb_price( $deposit ) );
				}
			}
			if ( $deposit > 0 ) {
				$parts[] = 'Die Kaution erhalten Sie bei der Rückgabe zurück.';
			}
		} else {
			if ( self::needs_online_payment( $booking ) ) {
				$parts[] = sprintf( 'Bitte bezahlen Sie die Miete von %s online, zum Beispiel per Karte.', msb_price( $sum['open'] ) );
				$button  = MSB_Payments::pay_url( $booking, 'rest' );
			} elseif ( $sum['paid'] > 0 ) {
				$parts[] = 'Die Miete haben Sie bereits online bezahlt.';
			}
			if ( $deposit > 0 ) {
				$parts[] = MSB_Payments::security_hold_active()
					? sprintf( 'Die Kaution von %s reservieren wir kurz vor der Abholung auf Ihrer Karte und geben sie nach der Rückgabe frei.', msb_price( $deposit ) )
					: sprintf( 'Die Kaution von %s zahlen Sie bei der Abholung und erhalten sie bei der Rückgabe zurück.', msb_price( $deposit ) );
			}
		}

		$text = implode( ' ', $parts );
		if ( ! $html ) {
			return trim( $text . ( $button ? ' ' . $button : '' ) );
		}
		return ( '' !== $text ? '<p>' . esc_html( $text ) . '</p>' : '' ) . ( $button ? '<p>' . self::pay_button( $button ) . '</p>' : '' );
	}

	public static function method_label( $method ) {
		$methods = MSB_Payments::checkout_methods();
		if ( isset( $methods[ $method ] ) ) {
			return $methods[ $method ];
		}
		if ( 'offline' === $method ) {
			$label = trim( (string) msb_setting( 'offline_label' ) );
			return '' === $label || is_email( $label ) ? 'Bar bei Abholung' : $label;
		}
		return 'stripe' === $method ? 'Stripe' : ( 'paypal' === $method ? 'PayPal' : '–' );
	}

	private static function wrap( $body ) {
		$color = sanitize_hex_color( msb_setting( 'primary_color' ) );
		$color = $color ? $color : '#2f6b3a';
		$logo  = class_exists( 'MSB_Documents' ) ? MSB_Documents::logo_url() : '';
		$logo  = $logo ? '<div style="text-align:center;margin:0 0 12px"><a href="' . esc_url( home_url( '/' ) ) . '"><img src="' . esc_url( $logo ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" width="180" style="width:180px;max-width:60%;height:auto;border:0"></a></div>' : '';
		return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body style="margin:0;padding:0;background:#f4f4f1">'
			. '<div style="max-width:680px;margin:0 auto;padding:12px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.5;color:#222">'
			. '<div style="background:#fff;border-top:4px solid ' . esc_attr( $color ) . ';padding:16px;border-radius:4px;overflow-wrap:break-word;word-wrap:break-word">' . $logo . $body . '</div>'
			. '<p style="font-size:12px;color:#777;text-align:center;margin-top:16px">' . esc_html( get_bloginfo( 'name' ) ) . ' · ' . esc_html( home_url( '/' ) ) . '</p>'
			. '</div></body></html>';
	}
}
