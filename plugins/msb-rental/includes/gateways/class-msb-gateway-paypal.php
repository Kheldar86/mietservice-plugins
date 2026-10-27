<?php
/**
 * PayPal (Orders API v2) – Bezahlen und Kaution reservieren (Autorisierung).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Gateway_PayPal {

	public static function id() {
		return 'paypal';
	}

	public static function sandbox() {
		return 'live' !== msb_setting( 'paypal_mode' );
	}

	public static function credentials() {
		return self::sandbox()
			? array( msb_setting( 'paypal_client_id_sandbox' ), msb_setting( 'paypal_secret_sandbox' ) )
			: array( msb_setting( 'paypal_client_id' ), msb_setting( 'paypal_secret' ) );
	}

	public static function enabled() {
		list( $id, $secret ) = self::credentials();
		return msb_setting( 'paypal_enabled' ) && $id && $secret;
	}

	public static function label() {
		return msb_setting( 'paypal_label', 'PayPal' );
	}

	public static function base() {
		return self::sandbox() ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
	}

	/**
	 * Eine Autorisierung bleibt 29 Tage gültig.
	 */
	public static function hold_validity_days() {
		return (int) apply_filters( 'msb_paypal_hold_days', 29 );
	}

	private static function token() {
		$key    = 'msb_pp_token_' . ( self::sandbox() ? 's' : 'l' );
		$cached = get_transient( $key );
		if ( $cached ) {
			return $cached;
		}
		list( $id, $secret ) = self::credentials();
		$res = wp_remote_post(
			self::base() . '/v1/oauth2/token',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $id . ':' . $secret ), // phpcs:ignore
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => 'grant_type=client_credentials',
			)
		);
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'msb_paypal', 'PayPal nicht erreichbar: ' . $res->get_error_message() );
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $body['access_token'] ) ) {
			return new WP_Error( 'msb_paypal', 'PayPal: Anmeldung fehlgeschlagen. Bitte Client-ID und Secret prüfen.' );
		}
		set_transient( $key, $body['access_token'], max( 60, (int) $body['expires_in'] - 120 ) );
		return $body['access_token'];
	}

	/**
	 * @return array|WP_Error
	 */
	public static function request( $method, $path, $body = null, $request_id = '' ) {
		$token = self::token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
				'Prefer'        => 'return=representation',
			),
		);
		if ( $request_id ) {
			$args['headers']['PayPal-Request-Id'] = $request_id;
		}
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$res = wp_remote_request( self::base() . $path, $args );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'msb_paypal', 'PayPal nicht erreichbar: ' . $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( $code >= 400 ) {
			$msg = '';
			if ( isset( $data['details'][0]['description'] ) ) {
				$msg = $data['details'][0]['description'];
			} elseif ( isset( $data['message'] ) ) {
				$msg = $data['message'];
			}
			return new WP_Error( 'msb_paypal', 'PayPal: ' . ( $msg ? $msg : 'Fehler ' . $code ), array( 'body' => $data ) );
		}
		return is_array( $data ) ? $data : array();
	}

	private static function money( $amount ) {
		return array(
			'currency_code' => strtoupper( msb_setting( 'currency_code', 'EUR' ) ),
			'value'         => number_format( (float) $amount, 2, '.', '' ),
		);
	}

	/**
	 * Bestellung anlegen.
	 *
	 * @param string $intent CAPTURE | AUTHORIZE
	 * @return array|WP_Error array( id, approve_url )
	 */
	public static function create_order( array $booking, $intent, $amount, $description, $return_url, $cancel_url ) {
		$res = self::request(
			'POST',
			'/v2/checkout/orders',
			array(
				'intent'         => $intent,
				'purchase_units' => array(
					array(
						'reference_id' => $booking['booking_number'],
						'custom_id'    => (string) $booking['id'],
						'invoice_id'   => $booking['booking_number'] . '-' . substr( md5( $description . microtime() ), 0, 6 ),
						'description'  => mb_substr( $description, 0, 127 ),
						'amount'       => self::money( $amount ),
					),
				),
				'payment_source' => array(
					'paypal' => array(
						'experience_context' => array(
							'brand_name'          => mb_substr( get_bloginfo( 'name' ), 0, 127 ),
							'locale'              => 'de-DE',
							'shipping_preference' => 'NO_SHIPPING',
							'user_action'         => 'PAY_NOW',
							'return_url'          => $return_url,
							'cancel_url'          => $cancel_url,
						),
					),
				),
			),
			'msb-' . $booking['id'] . '-' . md5( $description . $amount . microtime() )
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$url = '';
		foreach ( (array) ( isset( $res['links'] ) ? $res['links'] : array() ) as $l ) {
			if ( in_array( $l['rel'], array( 'payer-action', 'approve' ), true ) ) {
				$url = $l['href'];
			}
		}
		if ( ! $url ) {
			return new WP_Error( 'msb_paypal', 'PayPal hat keinen Bezahllink geliefert.' );
		}
		return array(
			'id'          => $res['id'],
			'approve_url' => $url,
		);
	}

	public static function get_order( $id ) {
		return self::request( 'GET', '/v2/checkout/orders/' . rawurlencode( $id ) );
	}

	/**
	 * @return array|WP_Error array( id, expires )
	 */
	public static function authorize_order( $id ) {
		$res = self::request( 'POST', '/v2/checkout/orders/' . rawurlencode( $id ) . '/authorize', new stdClass(), 'msb-auth-' . $id );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$auth = isset( $res['purchase_units'][0]['payments']['authorizations'][0] ) ? $res['purchase_units'][0]['payments']['authorizations'][0] : null;
		if ( ! $auth || ! in_array( $auth['status'], array( 'CREATED', 'PENDING' ), true ) ) {
			return new WP_Error( 'msb_paypal', 'PayPal: Die Reservierung wurde nicht erteilt.' );
		}
		return array(
			'id'      => $auth['id'],
			'expires' => isset( $auth['expiration_time'] ) ? $auth['expiration_time'] : '',
		);
	}

	/**
	 * @return array|WP_Error array( id )
	 */
	public static function capture_order( $id ) {
		$res = self::request( 'POST', '/v2/checkout/orders/' . rawurlencode( $id ) . '/capture', new stdClass(), 'msb-cap-' . $id );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$cap = isset( $res['purchase_units'][0]['payments']['captures'][0] ) ? $res['purchase_units'][0]['payments']['captures'][0] : null;
		if ( ! $cap || ! in_array( $cap['status'], array( 'COMPLETED', 'PENDING' ), true ) ) {
			return new WP_Error( 'msb_paypal', 'PayPal: Die Zahlung wurde nicht abgeschlossen.' );
		}
		return array( 'id' => $cap['id'] );
	}

	/**
	 * Teil oder Gesamtbetrag einer Autorisierung einziehen.
	 */
	public static function capture_authorization( $auth_id, $amount, $final ) {
		$res = self::request(
			'POST',
			'/v2/payments/authorizations/' . rawurlencode( $auth_id ) . '/capture',
			array(
				'amount'        => self::money( $amount ),
				'final_capture' => (bool) $final,
			),
			'msb-capauth-' . $auth_id . '-' . msb_cents( $amount ) . ( $final ? '-f' : '' )
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return array( 'id' => isset( $res['id'] ) ? $res['id'] : '' );
	}

	public static function void( $auth_id ) {
		return self::request( 'POST', '/v2/payments/authorizations/' . rawurlencode( $auth_id ) . '/void' );
	}

	public static function refund( $capture_id, $amount ) {
		return self::request( 'POST', '/v2/payments/captures/' . rawurlencode( $capture_id ) . '/refund', array( 'amount' => self::money( $amount ) ) );
	}
}
