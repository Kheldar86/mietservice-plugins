<?php
/**
 * Mietbedingungen und Datenschutz bei manuell angelegten Buchungen per Link bestätigen lassen.
 *
 * Bei einer im Backend angelegten Buchung hat der Kunde die Checkboxen des Buchungsformulars nicht selbst
 * gesetzt. Er bekommt deshalb eine Mail mit einem Link, über den er sie bestätigt. Die Bestätigung steht
 * danach mit Zeitpunkt in den Kundenangaben der Buchung.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Terms_Confirm {

	const CONFIRMED_KEY = 'terms_confirmed';

	public static function init() {
		add_action( 'msb_booking_created', array( __CLASS__, 'on_created' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'handle_post' ) );
		add_filter( 'the_content', array( __CLASS__, 'content' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'handle_admin' ), 9 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_extend_mails' ) );
		add_action( 'msb_booking_terms_confirmed', array( __CLASS__, 'on_confirmed' ) );
	}

	/**
	 * Checkboxen des Buchungsformulars (z. B. AGB, Datenschutz).
	 */
	public static function fields() {
		return array_values(
			array_filter(
				MSB_Form_Fields::get(),
				function ( $f ) {
					return 'checkbox' === $f['type'];
				}
			)
		);
	}

	public static function is_confirmed( array $b ) {
		return ! empty( $b['form_data'][ self::CONFIRMED_KEY ]['value'] );
	}

	public static function needs_confirmation( array $b ) {
		return 'admin' === $b['source'] && ! self::is_confirmed( $b ) && self::fields() && is_email( $b['customer_email'] );
	}

	public static function url( array $b ) {
		$base = msb_checkout_url();
		return add_query_arg( 'msb_terms', $b['token'], $base ? $base : home_url( '/' ) );
	}

	/**
	 * Relative Links in Feldbeschriftungen (z. B. /agb) für Mails absolut machen.
	 */
	private static function absolute_links( $html ) {
		return preg_replace( '#href="/(?!/)#', 'href="' . esc_url( home_url( '/' ) ), (string) $html );
	}

	/**
	 * Neue manuelle Buchung: Häkchen gelten nicht als Zustimmung des Kunden, Bestätigung anfragen.
	 */
	public static function on_created( $id, $args ) {
		if ( empty( $args['source'] ) || 'admin' !== $args['source'] ) {
			return;
		}
		$b = MSB_Bookings::get( $id );
		if ( ! $b || ! self::fields() ) {
			return;
		}
		$form_data = (array) $b['form_data'];
		foreach ( self::fields() as $f ) {
			$form_data[ $f['key'] ] = array(
				'label' => wp_strip_all_tags( $f['label'] ),
				'type'  => 'checkbox',
				'value' => 'Offen – Bestätigung per Link angefragt',
			);
		}
		self::save_form_data( $id, $form_data );
		// Zuerst nur der Bestätigungslink. Die Mail „Bestätigt“ folgt, wenn der Kunde bestätigt hat.
		if ( ! empty( $args['send_emails'] ) ) {
			self::send( $id );
		}
	}

	/**
	 * Kunde hat per Link bestätigt: Buchung auf „Bestätigt“ setzen und die Bestätigungsmail schicken,
	 * außerdem das Team informieren.
	 */
	public static function on_confirmed( $id ) {
		$b = MSB_Bookings::get( $id );
		if ( ! $b || 'admin' !== $b['source'] ) {
			return;
		}
		if ( 'pending' === $b['status'] ) {
			MSB_Bookings::update_status( $id, 'confirmed', true );
		} elseif ( 'confirmed' === $b['status'] ) {
			MSB_Emails::send( $id, 'confirmed' );
		}
		$to = msb_setting( 'admin_email' );
		if ( is_email( $to ) ) {
			$b = MSB_Bookings::get( $id );
			MSB_Emails::send_raw(
				$to,
				'Buchung ' . $b['booking_number'] . ' vom Kunden bestätigt',
				'<p>' . esc_html( $b['customer_name'] ) . ' hat die Buchung <strong>' . esc_html( $b['booking_number'] ) . '</strong> mit Mietbedingungen und Datenschutz bestätigt. Status: ' . esc_html( msb_status_label( $b['status'] ) ) . '.</p>'
				. MSB_Emails::items_table( $b )
				. '<p><a href="' . esc_url( admin_url( 'admin.php?page=msb-booking&id=' . (int) $id ) ) . '">Buchung im Backend öffnen</a></p>'
			);
		}
	}

	private static function save_form_data( $id, array $form_data ) {
		global $wpdb;
		$wpdb->update(
			MSB_Install::table( 'bookings' ),
			array(
				'form_data'  => wp_json_encode( $form_data ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $id )
		);
	}

	/**
	 * Mail mit dem Bestätigungslink.
	 */
	public static function send( $id ) {
		$b = MSB_Bookings::get( $id );
		if ( ! $b || ! self::needs_confirmation( $b ) ) {
			return false;
		}
		$body = '<p>Hallo ' . esc_html( $b['customer_name'] ) . ',</p>'
			. '<p>wie besprochen haben wir Ihre Buchung ' . esc_html( $b['booking_number'] ) . ' bei ' . esc_html( get_bloginfo( 'name' ) ) . ' für Sie vorgemerkt.</p>'
			. MSB_Emails::items_table( $b )
			. '<p>' . esc_html( self::payment_note( $b ) ) . '</p>'
			. self::mail_block( $b )
			. '<p>Nach Ihrer Bestätigung erhalten Sie die Buchungsbestätigung mit allen Angaben zur Zahlung.</p>'
			. '<p>Viele Grüße<br>Ihr Team von ' . esc_html( get_bloginfo( 'name' ) ) . '</p>';
		return MSB_Emails::send_raw( $b['customer_email'], 'Bitte bestätigen Sie Ihre Buchung ' . $b['booking_number'], $body );
	}

	/**
	 * Abschnitt mit Checkbox-Texten und Bestätigungsknopf, auch für die Eingangs- und Bestätigungsmail.
	 */
	public static function mail_block( array $b ) {
		$list = '';
		foreach ( self::fields() as $f ) {
			$list .= '<li>' . self::absolute_links( wp_kses_post( $f['label'] ) ) . '</li>';
		}
		return '<p>Bitte bestätigen Sie die Buchung über den folgenden Link:</p><ul>' . $list . '</ul>'
			. '<p><a href="' . esc_url( self::url( $b ) ) . '" style="display:inline-block;padding:10px 18px;background:' . esc_attr( msb_setting( 'primary_color', '#2f6b3a' ) ) . ';color:#fff;text-decoration:none;border-radius:4px">Jetzt bestätigen</a></p>';
	}

	/**
	 * Zahlungshinweis vor der Bestätigung: bei Barzahlung Beträge nennen, bei Online-Zahlung auf die Bestätigungsmail verweisen.
	 */
	public static function payment_note( array $b ) {
		if ( MSB_Payments::is_cash( $b ) ) {
			return MSB_Emails::payment_info( $b, false );
		}
		return 'Die Miete bezahlen Sie online, zum Beispiel per Karte. Den Bezahlknopf erhalten Sie mit der Buchungsbestätigung.';
	}

	private static function booking_from_request() {
		// phpcs:ignore WordPress.Security.NonceVerification
		$token = isset( $_REQUEST['msb_terms'] ) ? sanitize_key( wp_unslash( $_REQUEST['msb_terms'] ) ) : '';
		return $token ? MSB_Bookings::get_by_token( $token ) : null;
	}

	/**
	 * Formular des Kunden absenden.
	 */
	public static function handle_post() {
		if ( empty( $_POST['msb_terms_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$b = self::booking_from_request();
		if ( ! $b ) {
			return;
		}
		$url = self::url( $b );
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'msb_terms_' . $b['id'] ) ) {
			wp_safe_redirect( add_query_arg( 'msb_terms_err', 'nonce', $url ) );
			exit;
		}
		if ( ! self::is_confirmed( $b ) && 'cancelled' !== $b['status'] ) {
			$input = isset( $_POST['msb_field'] ) && is_array( $_POST['msb_field'] ) ? wp_unslash( $_POST['msb_field'] ) : array(); // phpcs:ignore
			$all   = true;
			foreach ( self::fields() as $f ) {
				if ( empty( $input[ $f['key'] ] ) && $f['required'] ) {
					$all = false;
				}
			}
			if ( ! $all ) {
				wp_safe_redirect( add_query_arg( 'msb_terms_err', 'missing', $url ) );
				exit;
			}
			$form_data = (array) $b['form_data'];
			foreach ( self::fields() as $f ) {
				$form_data[ $f['key'] ] = array(
					'label' => wp_strip_all_tags( $f['label'] ),
					'type'  => 'checkbox',
					'value' => empty( $input[ $f['key'] ] ) ? 'Nein' : 'Ja',
				);
			}
			$form_data[ self::CONFIRMED_KEY ] = array(
				'label' => 'Vom Kunden per Link bestätigt',
				'type'  => 'text',
				'value' => wp_date( 'd.m.Y H:i' ) . ' Uhr',
			);
			self::save_form_data( $b['id'], $form_data );
			do_action( 'msb_booking_terms_confirmed', $b['id'] );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Bestätigungsseite statt des normalen Seiteninhalts.
	 */
	public static function content( $content ) {
		if ( ! isset( $_GET['msb_terms'] ) || ! in_the_loop() || ! is_main_query() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return $content;
		}
		$b = self::booking_from_request();
		if ( ! $b ) {
			return '<div class="msb"><div class="msb-msg msb-msg--error">Dieser Link ist ungültig. Bitte melden Sie sich bei uns.</div></div>';
		}
		$html = '<div class="msb msb-terms-confirm"><h2>Buchung ' . esc_html( $b['booking_number'] ) . ' bestätigen</h2>';
		if ( self::is_confirmed( $b ) ) {
			return $html . '<div class="msb-msg msb-msg--success">Vielen Dank, Sie haben am ' . esc_html( $b['form_data'][ self::CONFIRMED_KEY ]['value'] ) . ' bestätigt. Die Buchungsbestätigung haben wir Ihnen per E-Mail geschickt. Wir freuen uns auf Sie!</div></div>';
		}
		if ( 'cancelled' === $b['status'] ) {
			return $html . '<div class="msb-msg msb-msg--error">Diese Buchung wurde storniert.</div></div>';
		}
		$err = isset( $_GET['msb_terms_err'] ) ? sanitize_key( wp_unslash( $_GET['msb_terms_err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $err ) {
			$html .= '<div class="msb-msg msb-msg--error">' . ( 'missing' === $err ? 'Bitte bestätigen Sie alle Pflichtfelder.' : 'Das Formular ist abgelaufen. Bitte versuchen Sie es noch einmal.' ) . '</div>';
		}
		$html .= MSB_Emails::items_table( $b );
		$html .= '<p>' . esc_html( self::payment_note( $b ) ) . '</p>';
		$html .= '<form method="post" class="msb-form" action="' . esc_url( self::url( $b ) ) . '">';
		$html .= wp_nonce_field( 'msb_terms_' . $b['id'], '_wpnonce', true, false );
		$html .= '<input type="hidden" name="msb_terms_action" value="confirm">';
		$html .= '<div class="msb-form__grid">' . MSB_Form_Fields::render( array(), self::fields() ) . '</div>';
		$html .= '<button type="submit" class="msb-btn msb-btn--primary">Bestätigen</button></form></div>';
		return $html;
	}

	/**
	 * Status und „Link senden“ in der Buchung (Backend).
	 */
	public static function admin_status( array $b ) {
		if ( 'admin' !== $b['source'] || ! self::fields() ) {
			return;
		}
		if ( self::is_confirmed( $b ) ) {
			echo '<p class="description">Mietbedingungen und Datenschutz vom Kunden bestätigt am ' . esc_html( $b['form_data'][ self::CONFIRMED_KEY ]['value'] ) . '.</p>';
			return;
		}
		echo '<form method="post"><p class="description">Mietbedingungen und Datenschutz: noch nicht vom Kunden bestätigt.</p>';
		if ( is_email( $b['customer_email'] ) && 'cancelled' !== $b['status'] ) {
			wp_nonce_field( 'msb_booking_' . (int) $b['id'] );
			echo '<input type="hidden" name="id" value="' . (int) $b['id'] . '"><input type="hidden" name="msb_booking_action" value="terms_link">';
			echo '<p><button type="submit" class="button">Bestätigungslink per E-Mail senden</button></p>';
		}
		echo '</form>';
	}

	public static function handle_admin() {
		// phpcs:disable WordPress.Security.NonceVerification -- Nonce wird unten geprüft.
		if ( empty( $_POST['msb_booking_action'] ) || 'terms_link' !== $_POST['msb_booking_action'] || ! current_user_can( msb_capability() ) ) {
			return;
		}
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		// phpcs:enable
		check_admin_referer( 'msb_booking_' . $id );
		$ok = self::send( $id );
		set_transient( 'msb_notice_' . get_current_user_id(), $ok ? array( 'Bestätigungslink gesendet.', 'success' ) : array( 'Bestätigungslink konnte nicht gesendet werden.', 'error' ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=msb-booking&id=' . $id ) );
		exit;
	}

	/**
	 * Einmalig: Hinweis auf Mietbedingungen und Datenschutz in die Mails „Eingegangen“ und „Bestätigt“ einfügen.
	 */
	public static function maybe_extend_mails() {
		if ( get_option( 'msb_terms_mail_version' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = get_option( 'msb_settings', array() );
		if ( is_array( $s ) ) {
			$agb     = get_page_by_path( 'agb' );
			$agb_url = $agb ? get_permalink( $agb ) : home_url( '/agb/' );
			$dsg     = get_page_by_path( 'datenschutzerklaerung' );
			$dsg_url = get_privacy_policy_url() ? get_privacy_policy_url() : ( $dsg ? get_permalink( $dsg ) : home_url( '/datenschutz/' ) );
			$note    = 'Es gelten unsere <a href="' . esc_url( $agb_url ) . '">Mietbedingungen (AGB)</a>. Wie wir Ihre Daten verarbeiten, lesen Sie in unserer <a href="' . esc_url( $dsg_url ) . '">Datenschutzerklärung</a>.';
			foreach ( array( 'email_received_body', 'email_confirmed_body' ) as $key ) {
				if ( empty( $s[ $key ] ) || false !== stripos( $s[ $key ], 'Mietbedingungen' ) ) {
					continue;
				}
				$pos       = strpos( $s[ $key ], 'Viele Grüße' );
				$s[ $key ] = false === $pos ? rtrim( $s[ $key ] ) . "\r\n\r\n" . $note : substr( $s[ $key ], 0, $pos ) . $note . "\r\n\r\n" . substr( $s[ $key ], $pos );
			}
			update_option( 'msb_settings', $s );
		}
		update_option( 'msb_terms_mail_version', '1.5.2', false );
	}
}
