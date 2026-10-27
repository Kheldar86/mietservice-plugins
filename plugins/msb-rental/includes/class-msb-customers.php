<?php
/**
 * Kundenstammdaten.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Customers {

	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MSB_Install::table( 'customers' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
		return $row ? self::prepare( $row ) : null;
	}

	public static function get_by_user( $user_id ) {
		global $wpdb;
		if ( ! $user_id ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MSB_Install::table( 'customers' ) . ' WHERE user_id = %d ORDER BY id LIMIT 1', $user_id ), ARRAY_A ); // phpcs:ignore
		return $row ? self::prepare( $row ) : null;
	}

	/**
	 * Gastkunde (ohne Konto) mit dieser E-Mail.
	 */
	public static function get_guest_by_email( $email ) {
		global $wpdb;
		if ( ! is_email( $email ) ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MSB_Install::table( 'customers' ) . ' WHERE email = %s AND user_id = 0 ORDER BY id LIMIT 1', strtolower( $email ) ), ARRAY_A ); // phpcs:ignore
		return $row ? self::prepare( $row ) : null;
	}

	public static function prepare( array $row ) {
		$row['data'] = json_decode( (string) $row['data'], true );
		if ( ! is_array( $row['data'] ) ) {
			$row['data'] = array();
		}
		$row['name'] = trim( $row['first_name'] . ' ' . $row['last_name'] );
		return $row;
	}

	/**
	 * Legt einen Kunden an oder aktualisiert ihn.
	 *
	 * @param array $values Formularwerte (Schlüssel => Wert).
	 * @param int   $id     Bestehende Kunden-ID oder 0.
	 * @param int   $user_id
	 * @return int Kunden-ID
	 */
	public static function save( array $values, $id = 0, $user_id = 0, $notes = null ) {
		global $wpdb;
		$table = MSB_Install::table( 'customers' );
		$now   = current_time( 'mysql' );

		$existing = $id ? self::get( $id ) : null;
		$data     = $existing ? $existing['data'] : array();

		// Nur Felder mit "Kundenstammdaten" übernehmen.
		foreach ( MSB_Form_Fields::get() as $f ) {
			if ( ! empty( $f['profile'] ) && array_key_exists( $f['key'], $values ) ) {
				$data[ $f['key'] ] = $values[ $f['key'] ];
			}
		}
		foreach ( array( 'first_name', 'last_name', 'phone', 'city', 'email' ) as $k ) {
			if ( isset( $values[ $k ] ) && ! isset( $data[ $k ] ) ) {
				$data[ $k ] = $values[ $k ];
			}
		}

		list( $first, $last, $email, $phone, $city ) = self::core_fields( $data, $values );

		$row = array(
			'email'      => strtolower( $email ),
			'first_name' => $first,
			'last_name'  => $last,
			'phone'      => $phone,
			'city'       => $city,
			'data'       => wp_json_encode( $data ),
			'updated_at' => $now,
		);
		if ( $user_id ) {
			$row['user_id'] = (int) $user_id;
		}
		if ( null !== $notes ) {
			$row['notes'] = sanitize_textarea_field( $notes );
		}

		if ( $existing ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $id ) );
			return (int) $id;
		}
		$row['created_at'] = $now;
		$row['user_id']    = (int) $user_id;
		$wpdb->insert( $table, $row );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Name, E-Mail, Telefon, Ort aus den Daten ableiten.
	 */
	private static function core_fields( array $data, array $values ) {
		$first = isset( $data['first_name'] ) ? $data['first_name'] : '';
		$last  = isset( $data['last_name'] ) ? $data['last_name'] : '';
		if ( '' === $first && '' === $last && ! empty( $data['name'] ) ) {
			$parts = explode( ' ', $data['name'], 2 );
			$first = $parts[0];
			$last  = isset( $parts[1] ) ? $parts[1] : '';
		}
		$email = '';
		$phone = '';
		foreach ( MSB_Form_Fields::get() as $f ) {
			$v = isset( $data[ $f['key'] ] ) ? $data[ $f['key'] ] : ( isset( $values[ $f['key'] ] ) ? $values[ $f['key'] ] : '' );
			if ( ! $email && 'email' === $f['type'] && is_email( $v ) ) {
				$email = $v;
			}
			if ( ! $phone && 'tel' === $f['type'] && $v ) {
				$phone = $v;
			}
		}
		if ( ! $email && ! empty( $values['email'] ) && is_email( $values['email'] ) ) {
			$email = $values['email'];
		}
		$city = isset( $data['city'] ) ? $data['city'] : '';
		return array(
			sanitize_text_field( $first ),
			sanitize_text_field( $last ),
			sanitize_email( $email ),
			sanitize_text_field( $phone ),
			sanitize_text_field( $city ),
		);
	}

	/**
	 * Kunde für eine neue Buchung finden/anlegen.
	 */
	public static function for_booking( array $values, $user_id = 0 ) {
		$customer = $user_id ? self::get_by_user( $user_id ) : null;
		if ( ! $customer ) {
			$email = '';
			foreach ( MSB_Form_Fields::get() as $f ) {
				if ( 'email' === $f['type'] && ! empty( $values[ $f['key'] ] ) ) {
					$email = $values[ $f['key'] ];
					break;
				}
			}
			$customer = $user_id ? null : self::get_guest_by_email( $email );
		}
		return self::save( $values, $customer ? $customer['id'] : 0, $user_id );
	}

	/**
	 * Gastbuchungen mit derselben E-Mail einem (bestätigten) Konto zuordnen.
	 */
	public static function merge_guests_into( $customer_id ) {
		global $wpdb;
		$c = self::get( $customer_id );
		if ( ! $c || ! $c['email'] ) {
			return 0;
		}
		$guest_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . MSB_Install::table( 'customers' ) . ' WHERE email = %s AND user_id = 0 AND id <> %d', $c['email'], $customer_id ) ); // phpcs:ignore
		foreach ( $guest_ids as $gid ) {
			$wpdb->update( MSB_Install::table( 'bookings' ), array( 'customer_id' => (int) $customer_id ), array( 'customer_id' => (int) $gid ) );
			$wpdb->delete( MSB_Install::table( 'customers' ), array( 'id' => (int) $gid ) );
		}
		return count( $guest_ids );
	}

	public static function delete( $id ) {
		global $wpdb;
		$wpdb->update( MSB_Install::table( 'bookings' ), array( 'customer_id' => 0 ), array( 'customer_id' => (int) $id ) );
		$wpdb->delete( MSB_Install::table( 'customers' ), array( 'id' => (int) $id ) );
	}

	public static function bookings( $id ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . MSB_Install::table( 'bookings' ) . ' WHERE customer_id = %d ORDER BY created_at DESC', $id ), ARRAY_A ); // phpcs:ignore
	}

	/**
	 * @return array( rows, total )
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$args   = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'orderby'  => 'name',
				'order'    => 'ASC',
				'per_page' => 20,
				'page'     => 1,
				'account'  => '',
			)
		);
		$c      = MSB_Install::table( 'customers' );
		$b      = MSB_Install::table( 'bookings' );
		$where  = array( '1=1' );
		$params = array();
		if ( $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s OR c.phone LIKE %s OR c.city LIKE %s)';
			$params  = array_merge( $params, array_fill( 0, 5, $like ) );
		}
		if ( 'yes' === $args['account'] ) {
			$where[] = 'c.user_id > 0';
		} elseif ( 'no' === $args['account'] ) {
			$where[] = 'c.user_id = 0';
		}
		$orderable = array(
			'name'       => 'c.last_name',
			'email'      => 'c.email',
			'city'       => 'c.city',
			'bookings'   => 'bookings',
			'last'       => 'last_booking',
			'created_at' => 'c.created_at',
		);
		$orderby   = isset( $orderable[ $args['orderby'] ] ) ? $orderable[ $args['orderby'] ] : 'c.last_name';
		$order     = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';
		$w         = implode( ' AND ', $where );

		$count = "SELECT COUNT(*) FROM {$c} c WHERE {$w}";
		$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count, $params ) ) : $wpdb->get_var( $count ) ); // phpcs:ignore

		$sql = "SELECT c.*, (SELECT COUNT(*) FROM {$b} x WHERE x.customer_id = c.id) AS bookings, (SELECT MAX(created_at) FROM {$b} y WHERE y.customer_id = c.id) AS last_booking FROM {$c} c WHERE {$w} ORDER BY {$orderby} {$order}, c.id DESC";
		if ( $args['per_page'] > 0 ) {
			$sql .= sprintf( ' LIMIT %d OFFSET %d', (int) $args['per_page'], ( max( 1, (int) $args['page'] ) - 1 ) * (int) $args['per_page'] );
		}
		$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore
		return array( array_map( array( __CLASS__, 'prepare' ), (array) $rows ), $total );
	}

	/**
	 * Alle Kunden für Auswahllisten.
	 */
	public static function all_for_select() {
		list( $rows ) = self::query( array( 'per_page' => 0 ) );
		return $rows;
	}
}
