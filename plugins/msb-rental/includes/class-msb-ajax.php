<?php
/**
 * AJAX-Endpunkte für das Frontend (über admin-ajax.php, funktioniert auch bei deaktivierter REST-API).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Ajax {

	public static function init() {
		$actions = array( 'availability', 'quote', 'cart_add', 'cart_remove', 'checkout' );
		foreach ( $actions as $a ) {
			add_action( 'wp_ajax_msb_' . $a, array( __CLASS__, $a ) );
			add_action( 'wp_ajax_nopriv_msb_' . $a, array( __CLASS__, $a ) );
		}
	}

	private static function post( $key, $default = '' ) {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : $default; // phpcs:ignore WordPress.Security.NonceVerification
	}

	private static function item_param() {
		$item = absint( self::post( 'item' ) );
		if ( ! MSB_Items::exists( $item ) ) {
			wp_send_json_error( array( 'message' => 'Artikel nicht gefunden.' ), 404 );
		}
		return $item;
	}

	/**
	 * Verfügbarkeit eines Monats.
	 */
	public static function availability() {
		$item  = self::item_param();
		$month = self::post( 'month' );
		if ( ! preg_match( '/^(\d{4})-(\d{2})$/', $month, $m ) || (int) $m[2] < 1 || (int) $m[2] > 12 ) {
			wp_send_json_error( array( 'message' => 'Ungültiger Monat.' ), 400 );
		}
		$from = $month . '-01';
		$to   = DateTime::createFromFormat( '!Y-m-d', $from )->format( 'Y-m-t' );
		$data = MSB_Items::data( $item );

		wp_send_json_success(
			array(
				'days'  => MSB_Availability::calendar( $item, $from, $to, MSB_Cart::get() ),
				'stock' => $data['stock'],
			)
		);
	}

	/**
	 * Preisberechnung für einen gewählten Zeitraum.
	 */
	public static function quote() {
		$item  = self::item_param();
		$start = self::post( 'start' );
		$end   = self::post( 'end' );
		$qty   = max( 1, absint( self::post( 'qty', 1 ) ) );

		$check = MSB_Availability::check( $item, $start, $end, $qty, array( 'extra_lines' => MSB_Cart::get() ) );
		if ( is_wp_error( $check ) ) {
			wp_send_json_error( array( 'message' => $check->get_error_message() ) );
		}

		$p    = MSB_Pricing::calculate( $item, $start, $end, $qty );
		$html = MSB_Pricing::breakdown_html( $p );
		$html .= '<div class="msb-quote__row msb-quote__total"><span>Mietpreis</span><span>' . esc_html( msb_price( $p['line_total'] ) ) . '</span></div>';
		if ( $p['deposit'] > 0 ) {
			$html .= '<div class="msb-quote__row msb-quote__deposit"><span>zzgl. Kaution</span><span>' . esc_html( msb_price( $p['deposit'] ) ) . '</span></div>';
		}
		if ( msb_setting( 'price_note' ) ) {
			$html .= '<div class="msb-quote__note">' . esc_html( msb_setting( 'price_note' ) ) . '</div>';
		}

		wp_send_json_success(
			array(
				'html'  => $html,
				'price' => $p,
			)
		);
	}

	public static function cart_add() {
		$item  = self::item_param();
		$start = self::post( 'start' );
		$end   = self::post( 'end' );
		$qty   = max( 1, absint( self::post( 'qty', 1 ) ) );

		$result = MSB_Cart::add( $item, $start, $end, $qty );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'  => sprintf( '„%s“ wurde für %s – %s zur Buchung hinzugefügt.', get_the_title( $item ), msb_date( $start ), msb_date( $end ) ),
				'count'    => MSB_Cart::count(),
				'checkout' => msb_checkout_url(),
				'redirect' => (bool) msb_setting( 'redirect_to_checkout' ),
			)
		);
	}

	public static function cart_remove() {
		MSB_Cart::remove( absint( self::post( 'index' ) ) );
		wp_send_json_success( array( 'count' => MSB_Cart::count() ) );
	}

	/**
	 * Buchung abschließen.
	 */
	public static function checkout() {
		// Honeypot.
		if ( '' !== self::post( 'msb_website' ) ) {
			wp_send_json_error( array( 'message' => 'Ihre Anfrage konnte nicht verarbeitet werden.' ), 400 );
		}

		// Schutz gegen massenhafte Anfragen: max. 5 Buchungen und 20 Versuche pro 10 Minuten und IP (BKG-02).
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$rkey  = 'msb_rl_' . md5( $ip );
		$akey  = 'msb_rla_' . md5( $ip );
		$hits  = (int) get_transient( $rkey );
		$tries = (int) get_transient( $akey );
		if ( $hits >= 5 || $tries >= 20 ) {
			wp_send_json_error( array( 'message' => 'Zu viele Anfragen. Bitte versuchen Sie es in einigen Minuten erneut.' ), 429 );
		}
		set_transient( $akey, $tries + 1, 10 * MINUTE_IN_SECONDS );

		// Sicherheitstoken: nur das Buchungsformular dieser Website wird angenommen (BKG-03).
		if ( ! wp_verify_nonce( self::post( 'msb_checkout_nonce' ), 'msb_checkout' ) ) {
			wp_send_json_error( array( 'message' => 'Die Seite ist abgelaufen. Bitte laden Sie die Seite neu und senden Sie das Formular erneut.' ), 403 );
		}

		$lines = MSB_Cart::get();
		if ( empty( $lines ) ) {
			wp_send_json_error( array( 'message' => 'Ihre Auswahl ist leer. Bitte wählen Sie zuerst einen Artikel und Zeitraum.' ) );
		}

		$input = isset( $_POST['msb_field'] ) && is_array( $_POST['msb_field'] ) ? $_POST['msb_field'] : array(); // phpcs:ignore
		list( , $errors, $data ) = MSB_Form_Fields::validate( $input );

		if ( $errors ) {
			wp_send_json_error(
				array(
					'message' => 'Bitte prüfen Sie die markierten Felder.',
					'errors'  => $errors,
				)
			);
		}

		list( $name, $email, $phone ) = MSB_Form_Fields::customer_from_data( $data );
		list( $values )               = MSB_Form_Fields::validate( $input );

		// Zahlungsart.
		$methods = MSB_Payments::checkout_methods();
		$method  = self::post( 'msb_payment_method', (string) key( $methods ) );
		if ( ! isset( $methods[ $method ] ) ) {
			wp_send_json_error( array( 'message' => 'Bitte wählen Sie eine Zahlungsart.' ) );
		}

		// Kundenkonto (optional).
		$user_id = get_current_user_id();
		if ( ! $user_id && MSB_Accounts::enabled() && ( '1' === self::post( 'msb_create_account' ) || msb_setting( 'account_required' ) ) ) {
			$pass = isset( $_POST['msb_account_password'] ) ? (string) wp_unslash( $_POST['msb_account_password'] ) : ''; // phpcs:ignore
			$uid  = MSB_Accounts::create_user( $email, $pass, isset( $values['first_name'] ) ? $values['first_name'] : '', isset( $values['last_name'] ) ? $values['last_name'] : '' );
			if ( is_wp_error( $uid ) ) {
				wp_send_json_error(
					array(
						'message' => $uid->get_error_message(),
						'errors'  => array( 'account' => $uid->get_error_message() ),
					)
				);
			}
			$user_id = $uid;
			MSB_Accounts::login( $uid );
		}

		$customer_id = MSB_Customers::for_booking( $values, $user_id );
		$online      = 'offline' !== $method;

		$id = MSB_Bookings::create(
			array(
				'lines'          => $lines,
				'customer_id'    => $customer_id,
				'customer_name'  => $name,
				'customer_email' => $email,
				'customer_phone' => $phone,
				'form_data'      => $data,
				'source'         => 'web',
				'payment_method' => $method,
				'await_payment'  => $online,
			)
		);

		if ( is_wp_error( $id ) ) {
			wp_send_json_error( array( 'message' => $id->get_error_message() ) );
		}

		set_transient( $rkey, $hits + 1, 10 * MINUTE_IN_SECONDS );
		MSB_Cart::clear();

		$booking = MSB_Bookings::get( $id );

		// Online-Zahlung: Buchung bis zur Zahlung reservieren, dann zum Anbieter weiterleiten.
		if ( $online ) {
			$redirect          = MSB_Payments::start_checkout( $booking, $method );
			if ( is_wp_error( $redirect ) ) {
				MSB_Bookings::update_status( $id, 'cancelled', false );
				MSB_Cart::restore_from_booking( $booking );
				wp_send_json_error( array( 'message' => 'Die Zahlung konnte nicht gestartet werden. ' . $redirect->get_error_message() . ' Bitte wählen Sie eine andere Zahlungsart.' ) );
			}
			if ( $redirect ) {
				wp_send_json_success( array( 'redirect' => $redirect ) );
			}
			// Nichts sofort zu zahlen (z. B. keine Anzahlung, Kaution folgt später).
			MSB_Bookings::finalize( $id );
			MSB_Payments::maybe_hold_security( $id );
		}

		wp_send_json_success(
			array(
				'redirect' => MSB_Payments::done_url( MSB_Bookings::get( $id ) ),
			)
		);
	}
}
