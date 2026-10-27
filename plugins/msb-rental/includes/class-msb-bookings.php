<?php
/**
 * Buchungen anlegen, lesen, ändern.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Bookings {

	/**
	 * Legt eine Buchung an.
	 *
	 * @param array $args {
	 *   lines          array( array( item_id, start, end, qty ) )
	 *   status         string
	 *   customer_name, customer_email, customer_phone string
	 *   form_data      array
	 *   admin_notes    string
	 *   source         web|admin
	 *   skip_check     bool  Verfügbarkeit nicht prüfen (nur Backend)
	 *   ignore_rules   bool  Buchungsregeln ignorieren (nur Backend)
	 *   send_emails    bool
	 * }
	 * @return int|WP_Error Buchungs-ID
	 */
	public static function create( array $args ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'lines'          => array(),
				'status'         => msb_setting( 'default_status', 'pending' ),
				'customer_name'  => '',
				'customer_email' => '',
				'customer_phone' => '',
				'form_data'      => array(),
				'admin_notes'    => '',
				'source'         => 'web',
				'customer_id'    => 0,
				'payment_method' => '',
				'await_payment'  => false,
				'skip_check'     => false,
				'ignore_rules'   => false,
				'send_emails'    => true,
			)
		);

		$lines = array();
		foreach ( (array) $args['lines'] as $l ) {
			if ( empty( $l['item_id'] ) ) {
				continue;
			}
			$lines[] = array(
				'item_id' => (int) $l['item_id'],
				'start'   => (string) $l['start'],
				'end'     => (string) $l['end'],
				'qty'     => max( 1, (int) $l['qty'] ),
			);
		}
		if ( empty( $lines ) ) {
			return new WP_Error( 'msb_empty', 'Es wurden keine Artikel ausgewählt.' );
		}

		$statuses = msb_statuses();
		$status   = isset( $statuses[ $args['status'] ] ) ? $args['status'] : 'pending';
		if ( $args['await_payment'] ) {
			$status = 'awaiting_payment';
		}

		// Sperre gegen gleichzeitige Doppelbuchungen. Ohne Sperre wird nicht gebucht (BKG-05).
		if ( '1' !== (string) $wpdb->get_var( "SELECT GET_LOCK('msb_booking_lock', 10)" ) ) {
			return new WP_Error( 'msb_busy', 'Gerade wird eine andere Buchung gespeichert. Bitte versuchen Sie es in einigen Sekunden erneut.' );
		}

		try {
			foreach ( $lines as $i => $l ) {
				if ( ! MSB_Items::exists( $l['item_id'] ) && ! $args['skip_check'] ) {
					return new WP_Error( 'msb_item', 'Ein Artikel ist nicht mehr verfügbar.' );
				}
				if ( ! msb_valid_date( $l['start'] ) || ! msb_valid_date( $l['end'] ) || $l['end'] < $l['start'] ) {
					return new WP_Error( 'msb_dates', 'Ungültiger Zeitraum.' );
				}
				if ( $args['skip_check'] ) {
					continue;
				}
				$check = MSB_Availability::check(
					$l['item_id'],
					$l['start'],
					$l['end'],
					$l['qty'],
					array(
						'extra_lines'  => array_slice( $lines, 0, $i ), // vorherige Positionen derselben Buchung mitzählen
						'ignore_rules' => $args['ignore_rules'],
					)
				);
				if ( is_wp_error( $check ) ) {
					return $check;
				}
			}

			$totals = MSB_Cart::totals( $lines );
			$now    = current_time( 'mysql' );

			$discount = 0.0;
			foreach ( $totals['lines'] as $l ) {
				$discount += $l['discount'];
			}
			$wpdb->insert(
				MSB_Install::table( 'bookings' ),
				array(
					'booking_number' => '',
					'token'          => msb_random_token(),
					'status'         => $status,
					'customer_id'    => (int) $args['customer_id'],
					'discount'       => round( $discount, 2 ),
					'prepay'         => $args['await_payment'] ? MSB_Payments::prepay_amount( $totals['total'] ) : 0, // Anzahlung nur bei Online-Zahlung (PAY-04).
					'payment_method' => sanitize_key( $args['payment_method'] ),
					'customer_name'  => sanitize_text_field( $args['customer_name'] ),
					'customer_email' => sanitize_email( $args['customer_email'] ),
					'customer_phone' => sanitize_text_field( $args['customer_phone'] ),
					'form_data'      => wp_json_encode( $args['form_data'] ),
					'total'          => round( $totals['total'], 2 ),
					'deposit'        => round( $totals['deposit'], 2 ),
					'admin_notes'    => sanitize_textarea_field( $args['admin_notes'] ),
					'source'         => 'admin' === $args['source'] ? 'admin' : 'web',
					'created_by'     => 'admin' === $args['source'] ? get_current_user_id() : 0,
					'created_at'     => $now,
					'updated_at'     => $now,
				),
				array( '%s', '%s', '%s', '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%d', '%s', '%s' )
			);
			$booking_id = (int) $wpdb->insert_id;

			if ( ! $booking_id ) {
				return new WP_Error( 'msb_db', 'Die Buchung konnte nicht gespeichert werden.' );
			}

			// Buchungsnummer: Präfix + Jahr + laufende Nummer, jedes Jahr ab 0001 (BKG-07).
			$seq = msb_next_sequence( 'booking' );
			if ( ! $seq ) {
				$wpdb->delete( MSB_Install::table( 'bookings' ), array( 'id' => $booking_id ), array( '%d' ) );
				return new WP_Error( 'msb_db', 'Die Buchung konnte nicht gespeichert werden.' );
			}
			$number = msb_setting( 'booking_prefix', 'MB-' ) . current_time( 'Y' ) . '-' . str_pad( (string) $seq, 4, '0', STR_PAD_LEFT );
			$wpdb->update( MSB_Install::table( 'bookings' ), array( 'booking_number' => $number ), array( 'id' => $booking_id ) );

			foreach ( $totals['lines'] as $l ) {
				$wpdb->insert(
					MSB_Install::table( 'booking_items' ),
					array(
						'booking_id' => $booking_id,
						'item_id'    => $l['item_id'],
						'item_title' => $l['title'],
						'quantity'   => $l['qty'],
						'start_date' => $l['start'],
						'end_date'   => $l['end'],
						'days'       => $l['days'],
						'day_rate'   => $l['day_rate'],
						'base_fee'   => $l['base_fee'],
						'base_total' => $l['base_total'],
						'line_total' => $l['line_total'],
						'deposit'    => $l['deposit'],
						'adjustments'=> wp_json_encode( $l['adjustments'] ),
					),
					array( '%d', '%d', '%s', '%d', '%s', '%s', '%d', '%f', '%f', '%f', '%f', '%f', '%s' )
				);
			}
		} finally {
			$wpdb->get_var( "SELECT RELEASE_LOCK('msb_booking_lock')" );
		}

		do_action( 'msb_booking_created', $booking_id, $args );

		if ( 'awaiting_payment' !== $status ) {
			// Manuelle Buchungen melden sich immer beim Team; „Kunden informieren“ gilt nur für die Kundenmail.
			if ( $args['send_emails'] || 'admin' === $args['source'] ) {
				MSB_Emails::send( $booking_id, 'admin' );
			}
			// Manuelle Buchung mit AGB-Bestätigung: Der Kunde bekommt zuerst nur den Bestätigungslink,
			// die Mail „Bestätigt“ folgt nach seiner Bestätigung (MSB_Terms_Confirm::on_confirmed).
			$terms_first = 'admin' === $args['source'] && class_exists( 'MSB_Terms_Confirm' ) && MSB_Terms_Confirm::fields();
			if ( $args['send_emails'] && ! $terms_first ) {
				MSB_Emails::send( $booking_id, 'confirmed' === $status ? 'confirmed' : 'received' );
			}
		}

		return $booking_id;
	}

	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MSB_Install::table( 'bookings' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
		if ( ! $row ) {
			return null;
		}
		$row['form_data'] = json_decode( (string) $row['form_data'], true );
		if ( ! is_array( $row['form_data'] ) ) {
			$row['form_data'] = array();
		}
		$row['items'] = self::get_items( $id );
		return $row;
	}

	/**
	 * Herkunft der Buchung als Text, z. B. „manuell angelegt von Eric“ oder „Webseite“.
	 */
	public static function source_label( array $b ) {
		if ( 'admin' !== $b['source'] ) {
			return ! empty( $b['channel'] ) && 'kleinanzeigen' === $b['channel'] ? 'Webseite über Kleinanzeigen' : 'Webseite';
		}
		$name = self::creator_name( $b );
		return 'manuell angelegt' . ( $name ? ' von ' . $name : '' );
	}

	/**
	 * Name des Benutzers, der die Buchung im Backend angelegt hat (leer bei Buchungen über die Webseite).
	 */
	public static function creator_name( array $b ) {
		$user = ! empty( $b['created_by'] ) ? get_userdata( (int) $b['created_by'] ) : null;
		return $user ? $user->display_name : '';
	}

	public static function get_items( $booking_id ) {
		global $wpdb;
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . MSB_Install::table( 'booking_items' ) . ' WHERE booking_id = %d ORDER BY start_date, id', $booking_id ), ARRAY_A ); // phpcs:ignore
		foreach ( $rows as &$r ) {
			$r['adjustments'] = json_decode( (string) $r['adjustments'], true );
			if ( ! is_array( $r['adjustments'] ) ) {
				$r['adjustments'] = array();
			}
		}
		return $rows;
	}

	public static function get_by_token( $token ) {
		global $wpdb;
		if ( ! preg_match( '/^[a-f0-9]{32}$/', (string) $token ) ) {
			return null;
		}
		$id = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . MSB_Install::table( 'bookings' ) . ' WHERE token = %s', $token ) ); // phpcs:ignore
		return $id ? self::get( $id ) : null;
	}

	/**
	 * Nach erfolgreicher erster Online-Zahlung: Buchung freigeben und E-Mails senden.
	 */
	public static function finalize( $id ) {
		global $wpdb;
		$booking = self::get( $id );
		if ( ! $booking || 'awaiting_payment' !== $booking['status'] ) {
			return;
		}
		$status = 'confirmed' === msb_setting( 'default_status' ) ? 'confirmed' : 'pending';
		$wpdb->update(
			MSB_Install::table( 'bookings' ),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $id )
		);
		do_action( 'msb_booking_status_changed', $id, $status, 'awaiting_payment' );
		MSB_Emails::send( $id, 'admin' );
		MSB_Emails::send( $id, 'confirmed' === $status ? 'confirmed' : 'received' );
	}

	public static function by_customer( $customer_id ) {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . MSB_Install::table( 'bookings' ) . " WHERE customer_id = %d AND status <> 'awaiting_payment' ORDER BY created_at DESC", $customer_id ) ); // phpcs:ignore
		return array_filter( array_map( array( __CLASS__, 'get' ), $ids ) );
	}

	/**
	 * Status ändern (versendet ggf. E-Mail).
	 */
	public static function update_status( $id, $status, $send_email = true ) {
		global $wpdb;
		$booking = self::get( $id );
		if ( ! $booking || ! array_key_exists( $status, msb_statuses() ) || $booking['status'] === $status ) {
			return false;
		}

		// Nur erlaubte Statuswechsel (BKG-16).
		if ( ! msb_status_allowed( $booking['status'], $status ) ) {
			return new WP_Error( 'msb_status', sprintf( 'Der Wechsel von „%1$s“ zu „%2$s“ ist nicht erlaubt.', msb_status_label( $booking['status'] ), msb_status_label( $status ) ) );
		}

		// Reaktivierung einer stornierten oder zurückgegebenen Buchung nur, wenn noch frei (BKG-14).
		if ( in_array( $status, msb_blocking_statuses(), true ) && ! in_array( $booking['status'], msb_blocking_statuses(), true ) ) {
			foreach ( $booking['items'] as $l ) {
				$check = MSB_Availability::check( $l['item_id'], $l['start_date'], $l['end_date'], $l['quantity'], array( 'exclude_booking' => $id, 'ignore_rules' => true ) );
				if ( is_wp_error( $check ) ) {
					return $check;
				}
			}
		}

		$wpdb->update(
			MSB_Install::table( 'bookings' ),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		do_action( 'msb_booking_status_changed', $id, $status, $booking['status'] );

		if ( $send_email && in_array( $status, array( 'confirmed', 'cancelled' ), true ) ) {
			MSB_Emails::send( $id, $status );
		}
		return true;
	}

	public static function update_notes( $id, $notes ) {
		global $wpdb;
		$wpdb->update(
			MSB_Install::table( 'bookings' ),
			array(
				'admin_notes' => sanitize_textarea_field( $notes ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);
	}

	/**
	 * Löscht eine Buchung endgültig. Buchungen mit Zahlung oder Beleg sind bis Fristende gesperrt (ADM-06, DSG-02).
	 *
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;
		$until = MSB_Retention::retained_until( $id );
		if ( $until ) {
			return new WP_Error( 'msb_retained', 'Diese Buchung hat eine Zahlung oder einen Beleg und muss bis ' . msb_date( $until ) . ' aufbewahrt werden. Löschen ist bis dahin gesperrt, Stornieren geht weiterhin.' );
		}
		$wpdb->delete( MSB_Install::table( 'booking_items' ), array( 'booking_id' => $id ), array( '%d' ) );
		$wpdb->delete( MSB_Install::table( 'payments' ), array( 'booking_id' => $id ), array( '%d' ) );
		$wpdb->delete( MSB_Install::table( 'bookings' ), array( 'id' => $id ), array( '%d' ) );
		do_action( 'msb_booking_deleted', $id );
		return true;
	}

	/**
	 * Buchungen abfragen.
	 *
	 * @param array $args status, search, from, to (Mietzeitraum), item_id, orderby, order, per_page, page.
	 * @return array( rows, total )
	 */
	/**
	 * Testbuchung (1.7.2): Der Haken ändert nur die Buchhaltung. Solange die Buchung gültige Belege hat, bleibt er gesperrt.
	 */
	public static function test_locked( $id ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . MSB_Install::table( 'documents' ) . " WHERE booking_id = %d AND status = 'issued' AND type <> 'correction'", $id ) ); // phpcs:ignore
	}

	public static function test_badge() {
		return '<span class="msb-test-badge" style="display:inline-block;padding:0 6px;border-radius:3px;background:#dcdcde;color:#50575e;font-size:11px;font-weight:600;line-height:18px;vertical-align:middle">Test</span>';
	}

	public static function set_test( $id, $flag ) {
		global $wpdb;
		if ( self::test_locked( $id ) ) {
			return new WP_Error( 'msb_test', 'Die Buchung hat gültige Belege. „Testbuchung“ lässt sich erst ändern, wenn diese Belege korrigiert sind.' );
		}
		$wpdb->update( MSB_Install::table( 'bookings' ), array( 'is_test' => $flag ? 1 : 0, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $id ) );
		return true;
	}

	public static function query( array $args = array() ) {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'status'   => '',
				'search'   => '',
				'from'     => '',
				'to'       => '',
				'item_id'  => 0,
				'source'   => '',
				'test'     => '',
				'orderby'  => 'created_at',
				'order'    => 'DESC',
				'per_page' => 20,
				'page'     => 1,
			)
		);

		$b      = MSB_Install::table( 'bookings' );
		$i      = MSB_Install::table( 'booking_items' );
		$where  = array( '1=1' );
		$params = array();

		if ( $args['status'] ) {
			$where[]  = 'b.status = %s';
			$params[] = $args['status'];
		}
		if ( $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(b.booking_number LIKE %s OR b.customer_name LIKE %s OR b.customer_email LIKE %s OR b.customer_phone LIKE %s)';
			$params   = array_merge( $params, array( $like, $like, $like, $like ) );
		}
		if ( $args['from'] && $args['to'] ) {
			$where[]  = "EXISTS (SELECT 1 FROM {$i} x WHERE x.booking_id = b.id AND x.start_date <= %s AND x.end_date >= %s)";
			$params[] = $args['to'];
			$params[] = $args['from'];
		}
		if ( $args['item_id'] ) {
			$where[]  = "EXISTS (SELECT 1 FROM {$i} y WHERE y.booking_id = b.id AND y.item_id = %d)";
			$params[] = (int) $args['item_id'];
		}
		if ( in_array( $args['source'], array( 'web', 'admin' ), true ) ) {
			$where[]  = 'b.source = %s';
			$params[] = $args['source'];
		}
		if ( 'hide' === $args['test'] ) {
			$where[] = 'b.is_test = 0';
		} elseif ( 'only' === $args['test'] ) {
			$where[] = 'b.is_test = 1';
		}

		$orderable = array(
			'created_at'     => 'b.created_at',
			'booking_number' => 'b.id',
			'customer_name'  => 'b.customer_name',
			'total'          => 'b.total',
			'status'         => 'b.status',
			'start'          => 'start_min',
		);
		$orderby   = isset( $orderable[ $args['orderby'] ] ) ? $orderable[ $args['orderby'] ] : 'b.created_at';
		$order     = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$b} b WHERE {$where_sql}";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) ); // phpcs:ignore

		$sql = "SELECT b.*, (SELECT MIN(start_date) FROM {$i} s WHERE s.booking_id = b.id) AS start_min, (SELECT MAX(end_date) FROM {$i} e WHERE e.booking_id = b.id) AS end_max FROM {$b} b WHERE {$where_sql} ORDER BY {$orderby} {$order}, b.id DESC";
		if ( $args['per_page'] > 0 ) {
			$sql .= sprintf( ' LIMIT %d OFFSET %d', (int) $args['per_page'], ( max( 1, (int) $args['page'] ) - 1 ) * (int) $args['per_page'] );
		}
		$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore

		return array( (array) $rows, $total );
	}

	public static function count_by_status() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT status, COUNT(*) AS n FROM ' . MSB_Install::table( 'bookings' ) . ' GROUP BY status', ARRAY_A ); // phpcs:ignore
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ $r['status'] ] = (int) $r['n'];
		}
		return $out;
	}

	/**
	 * Positionen, die einen Zeitraum berühren (für Kalender).
	 */
	public static function items_in_range( $from, $to, $item_id = 0, $include_cancelled = false ) {
		global $wpdb;
		$b      = MSB_Install::table( 'bookings' );
		$i      = MSB_Install::table( 'booking_items' );
		$sql    = "SELECT i.*, b.status, b.customer_name, b.booking_number FROM {$i} i INNER JOIN {$b} b ON b.id = i.booking_id WHERE i.start_date <= %s AND i.end_date >= %s";
		$params = array( $to, $from );
		if ( ! $include_cancelled ) {
			$sql .= " AND b.status <> 'cancelled'";
		}
		if ( $item_id ) {
			$sql     .= ' AND i.item_id = %d';
			$params[] = (int) $item_id;
		}
		$sql .= ' ORDER BY i.start_date, i.id';
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore
	}
}
