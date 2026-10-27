<?php
/**
 * Stripe (Checkout Sessions + PaymentIntents) über die REST-API – ohne SDK.
 *
 * Kartendaten werden nie auf Ihrem Server verarbeitet: Kunden zahlen auf der von Stripe gehosteten Seite.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Gateway_Stripe {

	const API = 'https://api.stripe.com/v1/';

	public static function id() {
		return 'stripe';
	}

	public static function enabled() {
		return msb_setting( 'stripe_enabled' ) && self::secret();
	}

	public static function secret() {
		return 'live' === msb_setting( 'stripe_mode' ) ? msb_setting( 'stripe_secret_live' ) : msb_setting( 'stripe_secret_test' );
	}

	public static function label() {
		return msb_setting( 'stripe_label', 'Stripe' );
	}

	/**
	 * Gültigkeit einer Kartenreservierung in Tagen.
	 */
	public static function hold_validity_days() {
		return (int) apply_filters( 'msb_stripe_hold_days', 7 );
	}

	/**
	 * API-Aufruf.
	 *
	 * @return array|WP_Error
	 */
	public static function request( $method, $path, array $params = array() ) {
		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Authorization'  => 'Bearer ' . self::secret(),
				'Stripe-Version' => '2024-06-20',
			),
		);
		$url = self::API . ltrim( $path, '/' );
		if ( 'GET' === $method ) {
			if ( $params ) {
				$url .= '?' . self::encode( $params );
			}
		} else {
			$args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
			$args['body']                    = self::encode( $params );
			if ( isset( $params['metadata']['idempotency'] ) ) {
				$args['headers']['Idempotency-Key'] = $params['metadata']['idempotency'];
			}
		}

		$res = wp_remote_request( $url, $args );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'msb_stripe', 'Stripe nicht erreichbar: ' . $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( $code >= 400 || ! is_array( $body ) ) {
			$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Unbekannter Fehler (' . $code . ')';
			return new WP_Error( 'msb_stripe', 'Stripe: ' . $msg, array( 'stripe_code' => isset( $body['error']['code'] ) ? $body['error']['code'] : '', 'body' => $body ) );
		}
		return $body;
	}

	/**
	 * Verschachtelte Parameter im Stripe-Format (a[b][c]=x).
	 */
	public static function encode( array $params, $prefix = '' ) {
		$out = array();
		foreach ( $params as $k => $v ) {
			$key = '' === $prefix ? $k : $prefix . '[' . $k . ']';
			if ( is_array( $v ) ) {
				$out[] = self::encode( $v, $key );
			} elseif ( is_bool( $v ) ) {
				$out[] = rawurlencode( $key ) . '=' . ( $v ? 'true' : 'false' );
			} elseif ( null !== $v ) {
				$out[] = rawurlencode( $key ) . '=' . rawurlencode( (string) $v );
			}
		}
		return implode( '&', array_filter( $out, 'strlen' ) );
	}

	/**
	 * Stripe-Kunde zum Buchungskunden (wird wiederverwendet).
	 */
	public static function customer_id( array $booking ) {
		global $wpdb;
		$customer = $booking['customer_id'] ? MSB_Customers::get( $booking['customer_id'] ) : null;
		$mode_key = 'live' === msb_setting( 'stripe_mode' ) ? 'live:' : 'test:';
		if ( $customer && 0 === strpos( (string) $customer['stripe_customer'], $mode_key ) ) {
			return substr( $customer['stripe_customer'], strlen( $mode_key ) );
		}
		$res = self::request(
			'POST',
			'customers',
			array(
				'email'    => $booking['customer_email'],
				'name'     => $booking['customer_name'],
				'phone'    => $booking['customer_phone'],
				'metadata' => array( 'msb_customer_id' => (int) $booking['customer_id'] ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( $customer ) {
			$wpdb->update( MSB_Install::table( 'customers' ), array( 'stripe_customer' => $mode_key . $res['id'] ), array( 'id' => (int) $customer['id'] ) );
		}
		return $res['id'];
	}

	/**
	 * Checkout-Session anlegen.
	 *
	 * @param array $opts amount (float), description, mode (payment|setup), save_card (bool), manual_capture (bool), success_url, cancel_url
	 * @return array|WP_Error Session
	 */
	public static function create_session( array $booking, array $opts ) {
		$customer = self::customer_id( $booking );
		if ( is_wp_error( $customer ) ) {
			return $customer;
		}
		$currency = strtolower( msb_setting( 'currency_code', 'EUR' ) );
		$params   = array(
			'mode'                => $opts['mode'],
			'customer'            => $customer,
			'success_url'         => $opts['success_url'],
			'cancel_url'          => $opts['cancel_url'],
			'client_reference_id' => $booking['booking_number'],
			'locale'              => 'de',
			'metadata'            => array(
				'booking_id'     => (int) $booking['id'],
				'booking_number' => $booking['booking_number'],
			),
		);

		if ( 'setup' === $opts['mode'] ) {
			$params['currency']           = $currency;
			$params['setup_intent_data'] = array(
				'description' => $opts['description'],
				'metadata'    => array( 'booking_id' => (int) $booking['id'] ),
			);
		} else {
			$params['line_items']          = array(
				array(
					'quantity'   => 1,
					'price_data' => array(
						'currency'     => $currency,
						'unit_amount'  => msb_cents( $opts['amount'] ),
						'product_data' => array( 'name' => $opts['description'] ),
					),
				),
			);
			$params['payment_intent_data'] = array(
				'description' => $opts['description'],
				'metadata'    => array( 'booking_id' => (int) $booking['id'] ),
			);
			if ( ! empty( $opts['save_card'] ) ) {
				$params['payment_intent_data']['setup_future_usage'] = 'off_session';
			}
			if ( ! empty( $opts['manual_capture'] ) ) {
				$params['payment_intent_data']['capture_method'] = 'manual';
			}
		}
		return self::request( 'POST', 'checkout/sessions', $params );
	}

	public static function get_session( $id ) {
		return self::request(
			'GET',
			'checkout/sessions/' . rawurlencode( $id ),
			array( 'expand' => array( 'payment_intent', 'setup_intent' ) )
		);
	}

	/**
	 * Kaution ohne Anwesenheit des Kunden auf der gespeicherten Karte reservieren.
	 */
	public static function hold_off_session( array $booking, $amount, $customer, $payment_method ) {
		return self::request(
			'POST',
			'payment_intents',
			array(
				'amount'         => msb_cents( $amount ),
				'currency'       => strtolower( msb_setting( 'currency_code', 'EUR' ) ),
				'customer'       => $customer,
				'payment_method' => $payment_method,
				'off_session'    => true,
				'confirm'        => true,
				'capture_method' => 'manual',
				'description'    => 'Kaution ' . $booking['booking_number'],
				'metadata'       => array(
					'booking_id'  => (int) $booking['id'],
					'type'        => 'security',
					'idempotency' => 'msb-hold-' . $booking['id'] . '-' . gmdate( 'Ymd' ),
				),
			)
		);
	}

	public static function release( $payment_intent ) {
		return self::request( 'POST', 'payment_intents/' . rawurlencode( $payment_intent ) . '/cancel', array( 'cancellation_reason' => 'requested_by_customer' ) );
	}

	public static function capture( $payment_intent, $amount ) {
		return self::request( 'POST', 'payment_intents/' . rawurlencode( $payment_intent ) . '/capture', array( 'amount_to_capture' => msb_cents( $amount ) ) );
	}

	public static function refund( $payment_intent, $amount ) {
		return self::request(
			'POST',
			'refunds',
			array(
				'payment_intent' => $payment_intent,
				'amount'         => msb_cents( $amount ),
			)
		);
	}

	/**
	 * Webhook-Signatur prüfen.
	 */
	public static function verify_signature( $payload, $header, $secret, $tolerance = 300 ) {
		if ( ! $secret || ! $header ) {
			return false;
		}
		$ts   = 0;
		$sigs = array();
		foreach ( explode( ',', $header ) as $part ) {
			$kv = explode( '=', trim( $part ), 2 );
			if ( 2 !== count( $kv ) ) {
				continue;
			}
			if ( 't' === $kv[0] ) {
				$ts = (int) $kv[1];
			} elseif ( 'v1' === $kv[0] ) {
				$sigs[] = $kv[1];
			}
		}
		if ( ! $ts || abs( time() - $ts ) > $tolerance ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $ts . '.' . $payload, $secret );
		foreach ( $sigs as $s ) {
			if ( hash_equals( $expected, $s ) ) {
				return true;
			}
		}
		return false;
	}
}
