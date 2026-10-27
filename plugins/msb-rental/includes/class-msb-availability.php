<?php
/**
 * Verfügbarkeitsprüfung.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Availability {

	/**
	 * Gebuchte Menge pro Tag für einen Artikel im Zeitraum [$from, $to].
	 * Puffertage werden vor und nach jeder Buchung als belegt gezählt, damit
	 * zwischen Rückgabe und nächster Abholung immer der Puffer liegt.
	 */
	public static function booked_map( $item_id, $from, $to, $exclude_booking = 0 ) {
		global $wpdb;

		$buffer   = max( 0, (int) msb_setting( 'buffer_days' ) );
		$statuses = msb_blocking_statuses();
		$map      = array();

		if ( empty( $statuses ) ) {
			return $map;
		}

		$bookings = MSB_Install::table( 'bookings' );
		$items    = MSB_Install::table( 'booking_items' );
		$in       = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		$params = array_merge(
			array( (int) $item_id ),
			$statuses,
			array( msb_add_days( $to, $buffer ), msb_add_days( $from, -$buffer ), (int) $exclude_booking )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql  = "SELECT i.start_date, i.end_date, i.quantity FROM {$items} i INNER JOIN {$bookings} b ON b.id = i.booking_id WHERE i.item_id = %d AND b.status IN ({$in}) AND i.start_date <= %s AND i.end_date >= %s AND b.id <> %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore

		foreach ( (array) $rows as $row ) {
			self::add_to_map( $map, $row['start_date'], $row['end_date'], (int) $row['quantity'], $from, $to, $buffer );
		}

		return $map;
	}

	private static function add_to_map( array &$map, $start, $end, $qty, $from, $to, $buffer ) {
		$s = max( msb_add_days( $start, -$buffer ), $from );
		$e = min( msb_add_days( $end, $buffer ), $to );
		foreach ( msb_date_range( $s, $e ) as $d ) {
			$map[ $d ] = ( isset( $map[ $d ] ) ? $map[ $d ] : 0 ) + $qty;
		}
	}

	/**
	 * Freie Stückzahl eines Artikels an einem Tag (ohne Buchungsregeln).
	 */
	public static function free_on( $item_id, $ymd ) {
		$item = MSB_Items::data( $item_id );
		if ( MSB_Items::is_blocked( $item, $ymd ) ) {
			return 0;
		}
		$map = self::booked_map( $item_id, $ymd, $ymd );
		return max( 0, $item['stock'] - ( isset( $map[ $ymd ] ) ? $map[ $ymd ] : 0 ) );
	}

	/**
	 * Kalenderdaten für das Frontend.
	 * Rückgabe je Tag: a = freie Stück, c = keine Abholung/Rückgabe an diesem Wochentag, x = nicht buchbar.
	 *
	 * @param array $extra_lines Zusätzlich belegende Positionen (z. B. Warenkorb des Besuchers).
	 */
	public static function calendar( $item_id, $from, $to, array $extra_lines = array() ) {
		$item   = MSB_Items::data( $item_id );
		$booked = self::booked_map( $item_id, $from, $to );
		$buffer = max( 0, (int) msb_setting( 'buffer_days' ) );

		foreach ( $extra_lines as $line ) {
			if ( (int) $line['item_id'] === (int) $item_id ) {
				self::add_to_map( $booked, $line['start'], $line['end'], (int) $line['qty'], $from, $to, $buffer );
			}
		}

		$today  = msb_today();
		$min    = msb_add_days( $today, max( 0, (int) msb_setting( 'lead_days' ) ) );
		$adv    = (int) msb_setting( 'max_advance_days' );
		$max    = $adv > 0 ? msb_add_days( $today, $adv ) : '';
		$closed = array_map( 'intval', (array) msb_setting( 'closed_weekdays', array() ) );

		$out = array();
		foreach ( msb_date_range( $from, $to ) as $d ) {
			$x = ! $item['bookable'] || $d < $min || ( $max && $d > $max ) || MSB_Items::is_blocked( $item, $d );
			$a = $x ? 0 : max( 0, $item['stock'] - ( isset( $booked[ $d ] ) ? $booked[ $d ] : 0 ) );

			$out[ $d ] = array(
				'a' => $a,
				'c' => in_array( msb_weekday( $d ), $closed, true ) ? 1 : 0,
				'x' => $x ? 1 : 0,
			);
		}
		return $out;
	}

	/**
	 * Prüft, ob eine Buchung möglich ist.
	 *
	 * @param array $args exclude_booking (int), extra_lines (array), ignore_rules (bool).
	 * @return true|WP_Error
	 */
	public static function check( $item_id, $start, $end, $qty, array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'exclude_booking' => 0,
				'extra_lines'     => array(),
				'ignore_rules'    => false,
			)
		);

		if ( ! MSB_Items::exists( $item_id ) ) {
			return new WP_Error( 'msb_item', 'Der Artikel wurde nicht gefunden.' );
		}

		$item  = MSB_Items::data( $item_id );
		$title = $item['title'];
		$qty   = (int) $qty;

		if ( ! $item['bookable'] && ! $args['ignore_rules'] ) {
			return new WP_Error( 'msb_bookable', sprintf( '„%s“ ist derzeit nicht online buchbar.', $title ) );
		}
		if ( ! msb_valid_date( $start ) || ! msb_valid_date( $end ) ) {
			return new WP_Error( 'msb_dates', 'Bitte wählen Sie ein Abhol- und ein Rückgabedatum.' );
		}
		if ( $end < $start ) {
			return new WP_Error( 'msb_dates', 'Das Rückgabedatum liegt vor dem Abholdatum.' );
		}
		if ( 'nights' === msb_setting( 'count_mode' ) && $end === $start ) {
			return new WP_Error( 'msb_dates', 'Abholung und Rückgabe können nicht am selben Tag sein.' );
		}
		if ( $qty < 1 ) {
			return new WP_Error( 'msb_qty', 'Bitte geben Sie eine gültige Anzahl an.' );
		}
		if ( $qty > $item['stock'] ) {
			return new WP_Error( 'msb_qty', sprintf( 'Von „%s“ sind insgesamt nur %d Stück vorhanden.', $title, $item['stock'] ) );
		}

		$days = MSB_Pricing::rental_days( $start, $end );

		if ( ! $args['ignore_rules'] ) {
			if ( $days < $item['min_days'] ) {
				return new WP_Error( 'msb_min', sprintf( '„%s“ kann mindestens %s gemietet werden.', $title, MSB_Pricing::days_label( $item['min_days'] ) ) );
			}
			if ( $item['max_days'] && $days > $item['max_days'] ) {
				return new WP_Error( 'msb_max', sprintf( '„%s“ kann höchstens %s gemietet werden.', $title, MSB_Pricing::days_label( $item['max_days'] ) ) );
			}

			$today = msb_today();
			$lead  = max( 0, (int) msb_setting( 'lead_days' ) );
			if ( $start < msb_add_days( $today, $lead ) ) {
				return new WP_Error( 'msb_lead', $lead ? sprintf( 'Buchungen sind frühestens ab dem %s möglich.', msb_date( msb_add_days( $today, $lead ) ) ) : 'Das Abholdatum liegt in der Vergangenheit.' );
			}
			$adv = (int) msb_setting( 'max_advance_days' );
			if ( $adv > 0 && $end > msb_add_days( $today, $adv ) ) {
				return new WP_Error( 'msb_adv', sprintf( 'Buchungen sind höchstens bis zum %s möglich.', msb_date( msb_add_days( $today, $adv ) ) ) );
			}

			$closed = array_map( 'intval', (array) msb_setting( 'closed_weekdays', array() ) );
			$names  = msb_weekday_names();
			if ( in_array( msb_weekday( $start ), $closed, true ) ) {
				return new WP_Error( 'msb_closed', sprintf( 'Am %s ist keine Abholung möglich.', $names[ msb_weekday( $start ) ] ) );
			}
			if ( in_array( msb_weekday( $end ), $closed, true ) ) {
				return new WP_Error( 'msb_closed', sprintf( 'Am %s ist keine Rückgabe möglich.', $names[ msb_weekday( $end ) ] ) );
			}
		}

		$booked = self::booked_map( $item_id, $start, $end, $args['exclude_booking'] );
		$buffer = max( 0, (int) msb_setting( 'buffer_days' ) );
		foreach ( $args['extra_lines'] as $line ) {
			if ( (int) $line['item_id'] === (int) $item_id ) {
				self::add_to_map( $booked, $line['start'], $line['end'], (int) $line['qty'], $start, $end, $buffer );
			}
		}

		foreach ( msb_date_range( $start, $end ) as $d ) {
			if ( MSB_Items::is_blocked( $item, $d ) ) {
				return new WP_Error( 'msb_blocked', sprintf( '„%s“ ist am %s nicht verfügbar.', $title, msb_date( $d ) ) );
			}
			$free = $item['stock'] - ( isset( $booked[ $d ] ) ? $booked[ $d ] : 0 );
			if ( $free < $qty ) {
				if ( $free <= 0 ) {
					return new WP_Error( 'msb_booked', sprintf( '„%s“ ist am %s bereits ausgebucht.', $title, msb_date( $d ) ) );
				}
				return new WP_Error( 'msb_booked', sprintf( 'Von „%s“ sind am %s nur noch %d Stück frei.', $title, msb_date( $d ), $free ) );
			}
		}

		return true;
	}

	/**
	 * Alle buchbaren Artikel, die im Zeitraum mindestens $qty mal frei sind.
	 */
	public static function available_items( $start, $end, $qty = 1, array $query_args = array() ) {
		$ids = get_posts(
			wp_parse_args(
				$query_args,
				array(
					'post_type'      => MSB_Items::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'orderby'        => 'menu_order title',
					'order'          => 'ASC',
				)
			)
		);
		$cart = MSB_Cart::get();
		return array_values(
			array_filter(
				$ids,
				function ( $id ) use ( $start, $end, $qty, $cart ) {
					return true === self::check( $id, $start, $end, $qty, array( 'extra_lines' => $cart ) );
				}
			)
		);
	}
}
