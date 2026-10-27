<?php
/**
 * Preisberechnung inkl. dynamischer Preisregeln.
 *
 * Ablauf je Position:
 *  1. Tagespreis aus Grundpreis bzw. Staffelpreis (abhängig von der Mietdauer).
 *  2. Tagesregeln (Wochentage, Saison/Zeitraum) verändern den Preis einzelner Miettage.
 *  3. Buchungsregeln (Wochenende, Frühbucher, Last-Minute, Mietdauer) gelten für die ganze Position.
 *     Ohne "Rabatte addieren" zählt nur der höchste Rabatt, Aufschläge gelten immer.
 *  4. Grundgebühr wird zum Schluss addiert, alles × Anzahl.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Pricing {

	/* ------------------------------------------------------------------
	 * Regeltypen
	 * ------------------------------------------------------------------ */

	public static function rule_types() {
		return array(
			'weekend'    => 'Wochenende',
			'weekdays'   => 'Bestimmte Wochentage',
			'period'     => 'Zeitraum / Saison',
			'duration'   => 'Mietdauer',
			'early'      => 'Frühbucher',
			'lastminute' => 'Kurzfristig (Last Minute)',
		);
	}

	public static function rule_modes() {
		return array(
			'percent_off' => 'Rabatt in %',
			'percent_on'  => 'Aufschlag in %',
			'amount_off'  => 'Rabatt in € (pro Stück)',
			'fixed_total' => 'Pauschalpreis in € (pro Stück)',
			'fixed_day'   => 'Fester Tagespreis in €',
		);
	}

	/**
	 * Erlaubte Modi je Regeltyp.
	 */
	public static function modes_for( $type ) {
		$day     = array( 'percent_off', 'percent_on', 'fixed_day' );
		$booking = array( 'percent_off', 'percent_on', 'amount_off', 'fixed_total' );
		return in_array( $type, array( 'weekdays', 'period' ), true ) ? $day : $booking;
	}

	public static function is_day_rule( $type ) {
		return in_array( $type, array( 'weekdays', 'period' ), true );
	}

	/**
	 * Bereinigt Regeln aus einem Formular.
	 */
	public static function sanitize_rules( $raw ) {
		$out   = array();
		$types = self::rule_types();
		foreach ( (array) $raw as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$type = isset( $r['type'] ) && isset( $types[ $r['type'] ] ) ? $r['type'] : '';
			if ( ! $type ) {
				continue;
			}
			$mode  = isset( $r['mode'] ) && in_array( $r['mode'], self::modes_for( $type ), true ) ? $r['mode'] : 'percent_off';
			$value = isset( $r['value'] ) ? (float) str_replace( ',', '.', (string) $r['value'] ) : 0;
			if ( $value <= 0 && 'fixed_total' !== $mode && 'fixed_day' !== $mode ) {
				continue;
			}
			$days = array();
			foreach ( (array) ( isset( $r['days'] ) ? $r['days'] : array() ) as $d ) {
				$d = (int) $d;
				if ( $d >= 0 && $d <= 6 ) {
					$days[] = $d;
				}
			}
			$out[] = array(
				'active' => ! empty( $r['active'] ) ? 1 : 0,
				'type'   => $type,
				'label'  => isset( $r['label'] ) ? sanitize_text_field( wp_unslash( $r['label'] ) ) : '',
				'mode'   => $mode,
				'value'  => round( max( 0, $value ), 2 ),
				'days'   => array_values( array_unique( $days ) ),
				'from'   => isset( $r['from'] ) ? msb_normalize_date( $r['from'] ) : '',
				'to'     => isset( $r['to'] ) ? msb_normalize_date( $r['to'] ) : '',
				'min'    => isset( $r['min'] ) ? max( 0, (int) $r['min'] ) : 0,
				'max'    => isset( $r['max'] ) ? max( 0, (int) $r['max'] ) : 0,
			);
		}
		return $out;
	}

	/**
	 * Standardbezeichnung einer Regel für Kunden.
	 */
	public static function rule_label( array $r ) {
		if ( ! empty( $r['label'] ) ) {
			return $r['label'];
		}
		$prefix = array(
			'weekend'    => 'Wochenend',
			'weekdays'   => 'Wochentags',
			'period'     => 'Saison',
			'duration'   => 'Langzeit',
			'early'      => 'Frühbucher',
			'lastminute' => 'Last-Minute-',
		);
		$name   = isset( $prefix[ $r['type'] ] ) ? $prefix[ $r['type'] ] : '';
		$word   = function ( $w ) use ( $name ) {
			return $name . ( '-' === substr( $name, -1 ) ? ucfirst( $w ) : $w );
		};
		switch ( $r['mode'] ) {
			case 'percent_off':
				return $word( 'rabatt' ) . ' ' . self::num( $r['value'] ) . ' %';
			case 'percent_on':
				return $word( 'zuschlag' ) . ' ' . self::num( $r['value'] ) . ' %';
			case 'amount_off':
				return $word( 'rabatt' );
			case 'fixed_total':
				return $word( 'pauschale' );
			default:
				return $word( 'preis' );
		}
	}

	private static function num( $v ) {
		return rtrim( rtrim( number_format( (float) $v, 2, ',', '' ), '0' ), ',' );
	}

	/**
	 * Kurzbeschreibung einer Regel (für Preistabellen).
	 */
	public static function rule_summary( array $r ) {
		$wd   = msb_weekday_short();
		$cond = '';
		switch ( $r['type'] ) {
			case 'weekend':
				$cond = 'Abholung Fr/Sa, Rückgabe bis Mo' . ( $r['max'] ? ', max. ' . self::days_label( $r['max'] ) : '' );
				break;
			case 'weekdays':
				$cond = implode( ', ', array_map( function ( $d ) use ( $wd ) {
					return $wd[ $d ];
				}, $r['days'] ) );
				break;
			case 'period':
				$cond = msb_date( $r['from'] ) . ' – ' . msb_date( $r['to'] );
				break;
			case 'duration':
				$cond = 'ab ' . self::days_label( max( 1, $r['min'] ) ) . ( $r['max'] ? ' bis ' . self::days_label( $r['max'] ) : '' );
				break;
			case 'early':
				$cond = 'Buchung mind. ' . self::days_label( $r['min'] ) . ' vor Abholung';
				break;
			case 'lastminute':
				$cond = 'Abholung innerhalb von ' . self::days_label( $r['max'] );
				break;
		}
		switch ( $r['mode'] ) {
			case 'percent_off':
				$val = '−' . self::num( $r['value'] ) . ' %';
				break;
			case 'percent_on':
				$val = '+' . self::num( $r['value'] ) . ' %';
				break;
			case 'amount_off':
				$val = '−' . msb_price( $r['value'] );
				break;
			case 'fixed_total':
				$val = msb_price( $r['value'] ) . ' pauschal';
				break;
			default:
				$val = msb_price( $r['value'] ) . ' / Tag';
		}
		return array( self::rule_label( $r ), $cond, $val );
	}

	/**
	 * Aktive Regeln eines Artikels (eigene + globale).
	 */
	public static function rules_for( array $item ) {
		$rules = isset( $item['rules'] ) ? $item['rules'] : array();
		if ( empty( $item['ignore_global'] ) ) {
			$rules = array_merge( $rules, (array) msb_setting( 'price_rules', array() ) );
		}
		return array_values(
			array_filter(
				$rules,
				function ( $r ) {
					return ! empty( $r['active'] );
				}
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Berechnung
	 * ------------------------------------------------------------------ */

	/**
	 * Abrechnungstage zwischen Abholung und Rückgabe.
	 */
	public static function rental_days( $start, $end ) {
		if ( ! msb_valid_date( $start ) || ! msb_valid_date( $end ) || $end < $start ) {
			return 0;
		}
		$s    = DateTime::createFromFormat( '!Y-m-d', $start );
		$e    = DateTime::createFromFormat( '!Y-m-d', $end );
		$diff = (int) $s->diff( $e )->days;

		return 'nights' === msb_setting( 'count_mode' ) ? max( 1, $diff ) : $diff + 1;
	}

	/**
	 * Die abgerechneten Tage (für Tagesregeln).
	 */
	public static function charged_dates( $start, $end ) {
		$dates = msb_date_range( $start, $end );
		if ( 'nights' === msb_setting( 'count_mode' ) && count( $dates ) > 1 ) {
			array_pop( $dates );
		}
		return $dates;
	}

	public static function day_rate( array $item, $days ) {
		$rate = (float) $item['price_day'];
		foreach ( $item['tiers'] as $min_days => $price ) {
			if ( $days >= $min_days ) {
				$rate = (float) $price;
			}
		}
		return $rate;
	}

	public static function lowest_rate( array $item ) {
		$rates = array_merge( array( (float) $item['price_day'] ), array_values( $item['tiers'] ) );
		$rates = array_filter( $rates, function ( $r ) {
			return $r > 0;
		} );
		return $rates ? min( $rates ) : 0.0;
	}

	/**
	 * Preis einer Position.
	 *
	 * @param int|array $item_id
	 * @param string    $booked_on Buchungsdatum (für Frühbucher/Last-Minute), Standard heute.
	 */
	public static function calculate( $item_id, $start, $end, $qty, $booked_on = '' ) {
		$item      = is_array( $item_id ) ? $item_id : MSB_Items::data( $item_id );
		$qty       = max( 1, (int) $qty );
		$days      = self::rental_days( $start, $end );
		$rate      = self::day_rate( $item, $days );
		$booked_on = $booked_on ? $booked_on : msb_today();
		$rules     = self::rules_for( $item );
		$dates     = self::charged_dates( $start, $end );

		$base        = round( $rate * $days, 2 );
		$adjustments = array();

		// 1. Tagesregeln.
		$day_total = 0.0;
		$day_diff  = array(); // label => Betrag.
		foreach ( $dates as $d ) {
			$day_price = $rate;
			foreach ( $rules as $r ) {
				if ( ! self::is_day_rule( $r['type'] ) || ! self::day_rule_matches( $r, $d ) ) {
					continue;
				}
				$before = $day_price;
				if ( 'percent_off' === $r['mode'] ) {
					$day_price *= ( 1 - min( 100, $r['value'] ) / 100 );
				} elseif ( 'percent_on' === $r['mode'] ) {
					$day_price *= ( 1 + $r['value'] / 100 );
				} else {
					$day_price = $r['value'];
				}
				$label              = self::rule_label( $r );
				$day_diff[ $label ] = ( isset( $day_diff[ $label ] ) ? $day_diff[ $label ] : 0 ) + ( $day_price - $before );
			}
			$day_total += $day_price;
		}
		foreach ( $day_diff as $label => $amount ) {
			if ( abs( $amount ) >= 0.005 ) {
				$adjustments[] = array(
					'label'  => $label,
					'amount' => round( $amount, 2 ),
				);
			}
		}
		$rent = round( $day_total, 2 );

		// 2. Buchungsregeln.
		$discounts  = array();
		$surcharges = array();
		$package    = null;
		foreach ( $rules as $r ) {
			if ( self::is_day_rule( $r['type'] ) || ! self::booking_rule_matches( $r, $start, $end, $days, $booked_on ) ) {
				continue;
			}
			$label = self::rule_label( $r );
			switch ( $r['mode'] ) {
				case 'fixed_total':
					if ( null === $package || $r['value'] < $package['value'] ) {
						$package = array(
							'label' => $label,
							'value' => (float) $r['value'],
						);
					}
					break;
				case 'percent_on':
					$surcharges[] = array( $label, $rent * $r['value'] / 100 );
					break;
				case 'percent_off':
					$discounts[] = array( $label, $rent * min( 100, $r['value'] ) / 100 );
					break;
				case 'amount_off':
					$discounts[] = array( $label, (float) $r['value'] );
					break;
			}
		}

		if ( $package ) {
			$adjustments[] = array(
				'label'  => $package['label'],
				'amount' => round( $package['value'] - $rent, 2 ),
			);
			$rent = $package['value'];
		} elseif ( $discounts ) {
			if ( ! msb_setting( 'stack_discounts' ) ) {
				usort( $discounts, function ( $a, $b ) {
					return $b[1] <=> $a[1];
				} );
				$discounts = array( $discounts[0] );
			}
			foreach ( $discounts as $dsc ) {
				$amount        = min( $rent, $dsc[1] );
				$rent         -= $amount;
				$adjustments[] = array(
					'label'  => $dsc[0],
					'amount' => -round( $amount, 2 ),
				);
			}
		}
		foreach ( $surcharges as $s ) {
			$rent         += $s[1];
			$adjustments[] = array(
				'label'  => $s[0],
				'amount' => round( $s[1], 2 ),
			);
		}

		$rent = max( 0, round( $rent, 2 ) );
		$line = ( $item['base_fee'] + $rent ) * $qty;

		$result = array(
			'days'        => $days,
			'day_rate'    => round( $rate, 2 ),
			'base_fee'    => round( $item['base_fee'], 2 ),
			'quantity'    => $qty,
			'base_total'  => round( ( $base + $item['base_fee'] ) * $qty, 2 ),
			'rent'        => $rent,
			'adjustments' => array_map(
				function ( $a ) use ( $qty ) {
					$a['amount'] = round( $a['amount'] * $qty, 2 );
					return $a;
				},
				$adjustments
			),
			'line_total'  => round( $line, 2 ),
			'deposit'     => round( $item['deposit'] * $qty, 2 ),
		);
		$result['discount'] = round( $result['base_total'] - $result['line_total'], 2 );

		return apply_filters( 'msb_calculate_price', $result, $item, $start, $end, $qty );
	}

	private static function day_rule_matches( array $r, $d ) {
		if ( 'weekdays' === $r['type'] ) {
			return in_array( msb_weekday( $d ), $r['days'], true );
		}
		if ( 'period' === $r['type'] ) {
			return $r['from'] && $r['to'] && $d >= $r['from'] && $d <= $r['to'];
		}
		return false;
	}

	private static function booking_rule_matches( array $r, $start, $end, $days, $booked_on ) {
		switch ( $r['type'] ) {
			case 'weekend':
				// Abholung Fr/Sa, Rückgabe Sa/So/Mo, höchstens max Kalendertage (Standard 4 = Fr–Mo).
				$sw   = msb_weekday( $start );
				$ew   = msb_weekday( $end );
				$span = count( msb_date_range( $start, $end ) );
				$max  = $r['max'] ? $r['max'] : 4;
				return in_array( $sw, array( 5, 6 ), true ) && in_array( $ew, array( 6, 0, 1 ), true ) && $span <= $max && $span >= 2;
			case 'duration':
				return $days >= max( 1, $r['min'] ) && ( ! $r['max'] || $days <= $r['max'] );
			case 'early':
				return self::days_between( $booked_on, $start ) >= $r['min'];
			case 'lastminute':
				return self::days_between( $booked_on, $start ) <= $r['max'];
		}
		return false;
	}

	private static function days_between( $a, $b ) {
		$x = DateTime::createFromFormat( '!Y-m-d', $a );
		$y = DateTime::createFromFormat( '!Y-m-d', $b );
		if ( ! $x || ! $y ) {
			return 0;
		}
		return (int) $x->diff( $y )->format( '%r%a' );
	}

	public static function days_label( $days ) {
		return $days . ' ' . ( 1 === (int) $days ? 'Tag' : 'Tage' );
	}

	/**
	 * HTML-Aufstellung einer berechneten Position (Preisbox, Warenkorb).
	 */
	public static function breakdown_html( array $p ) {
		$qty  = $p['quantity'];
		$html = '<div class="msb-quote__row"><span>' . esc_html( self::days_label( $p['days'] ) . ' × ' . msb_price( $p['day_rate'] ) ) . ( $qty > 1 ? ' × ' . (int) $qty . ' Stück' : '' ) . '</span><span>' . esc_html( msb_price( $p['day_rate'] * $p['days'] * $qty ) ) . '</span></div>';
		foreach ( $p['adjustments'] as $a ) {
			$cls   = $a['amount'] < 0 ? 'is-discount' : 'is-surcharge';
			$html .= '<div class="msb-quote__row ' . $cls . '"><span>' . esc_html( $a['label'] ) . '</span><span>' . ( $a['amount'] < 0 ? '−' : '+' ) . esc_html( msb_price( abs( $a['amount'] ) ) ) . '</span></div>';
		}
		if ( $p['base_fee'] > 0 ) {
			$html .= '<div class="msb-quote__row"><span>Grundgebühr</span><span>' . esc_html( msb_price( $p['base_fee'] * $qty ) ) . '</span></div>';
		}
		return $html;
	}
}
