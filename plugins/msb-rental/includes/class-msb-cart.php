<?php
/**
 * Warenkorb (ohne PHP-Session, über Cookie + Transient).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Cart {

	const COOKIE = 'msb_cart';
	const TTL    = 3 * DAY_IN_SECONDS;

	private static $token = null;

	private static function token( $create = false ) {
		if ( null !== self::$token ) {
			return self::$token;
		}
		$cookie = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		if ( preg_match( '/^[a-f0-9]{32}$/', $cookie ) ) {
			self::$token = $cookie;
			return self::$token;
		}
		if ( ! $create ) {
			return '';
		}
		self::$token = bin2hex( random_bytes( 16 ) );
		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE,
				self::$token,
				array(
					'expires'  => time() + self::TTL,
					'path'     => COOKIEPATH ? COOKIEPATH : '/',
					'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}
		return self::$token;
	}

	/**
	 * Positionen: array( array( item_id, start, end, qty ) ).
	 */
	public static function get() {
		$token = self::token();
		if ( ! $token ) {
			return array();
		}
		$lines = get_transient( 'msb_cart_' . $token );
		if ( ! is_array( $lines ) ) {
			return array();
		}
		// Gelöschte/unveröffentlichte Artikel entfernen.
		return array_values(
			array_filter(
				$lines,
				function ( $l ) {
					return MSB_Items::exists( $l['item_id'] );
				}
			)
		);
	}

	private static function save( array $lines ) {
		$token = self::token( true );
		set_transient( 'msb_cart_' . $token, array_values( $lines ), self::TTL );
	}

	public static function count() {
		$n = 0;
		foreach ( self::get() as $l ) {
			$n += (int) $l['qty'];
		}
		return $n;
	}

	/**
	 * @return true|WP_Error
	 */
	public static function add( $item_id, $start, $end, $qty ) {
		$lines = self::get();
		$check = MSB_Availability::check( $item_id, $start, $end, $qty, array( 'extra_lines' => $lines ) );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// Gleiche Position zusammenfassen.
		foreach ( $lines as &$l ) {
			if ( (int) $l['item_id'] === (int) $item_id && $l['start'] === $start && $l['end'] === $end ) {
				$l['qty'] += (int) $qty;
				self::save( $lines );
				return true;
			}
		}
		unset( $l );

		$lines[] = array(
			'item_id' => (int) $item_id,
			'start'   => $start,
			'end'     => $end,
			'qty'     => (int) $qty,
		);
		self::save( $lines );
		return true;
	}

	public static function remove( $index ) {
		$lines = self::get();
		if ( isset( $lines[ $index ] ) ) {
			unset( $lines[ $index ] );
			self::save( $lines );
		}
	}

	/**
	 * Nach abgebrochener Zahlung die Auswahl wiederherstellen.
	 */
	public static function restore_from_booking( array $booking ) {
		$lines = array();
		foreach ( $booking['items'] as $l ) {
			$lines[] = array(
				'item_id' => (int) $l['item_id'],
				'start'   => $l['start_date'],
				'end'     => $l['end_date'],
				'qty'     => (int) $l['quantity'],
			);
		}
		self::save( $lines );
	}

	/**
	 * Herkunft der Auswahl (1.8.0), z. B. „kleinanzeigen“. Liegt neben dem Warenkorb, kein eigenes Cookie.
	 */
	public static function set_ref( $ref ) {
		$token = self::token( true );
		set_transient( 'msb_cart_ref_' . $token, sanitize_key( $ref ), self::TTL );
	}

	public static function ref() {
		$token = self::token();
		return $token ? (string) get_transient( 'msb_cart_ref_' . $token ) : '';
	}

	public static function clear() {
		$token = self::token();
		if ( $token ) {
			delete_transient( 'msb_cart_' . $token );
			delete_transient( 'msb_cart_ref_' . $token );
		}
	}

	/**
	 * Positionen mit Preisen und Gesamtsummen.
	 */
	public static function totals( $lines = null ) {
		$lines = null === $lines ? self::get() : $lines;
		$out   = array(
			'lines'   => array(),
			'total'   => 0.0,
			'deposit' => 0.0,
		);
		foreach ( $lines as $i => $l ) {
			$price            = MSB_Pricing::calculate( $l['item_id'], $l['start'], $l['end'], $l['qty'] );
			$out['lines'][ $i ] = array_merge( $l, $price, array( 'title' => get_the_title( $l['item_id'] ) ) );
			$out['total']    += $price['line_total'];
			$out['deposit']  += $price['deposit'];
		}
		return $out;
	}
}
