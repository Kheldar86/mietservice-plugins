<?php
/**
 * Zahlungsexport für die EÜR (1.7.1): alle Geldflüsse eines Jahres einzeln, getrennt nach Bar, Stripe und PayPal.
 *
 * Ergänzt den Kontoauszug (Finom), in dem Bareinnahmen fehlen und Stripe nur als Sammelauszahlung nach Gebühren erscheint.
 * Stripe-Gebühren kennt das Plugin nicht; sie stehen im Stripe-Auszahlungsabgleich.
 * Testbuchungen (1.7.2): Bargeld fehlt, weil kein echtes Geld geflossen ist. Online-Zahlungen sind echtes Geld
 * und bleiben drin, mit Vermerk „Test“, damit die Summen zu den Auszahlungen passen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Euer {

	public static function init() {
		if ( is_admin() ) {
			add_action( 'admin_init', array( __CLASS__, 'handle' ), 8 );
		}
	}

	public static function channel( $provider ) {
		$map = array(
			'stripe' => 'Stripe',
			'paypal' => 'PayPal',
		);
		return isset( $map[ $provider ] ) ? $map[ $provider ] : 'Bar';
	}

	/**
	 * Alle Geldflüsse des Jahres, nach Datum sortiert.
	 *
	 * @return array Zeilen mit date, kind, channel, amount, booking, doc, tax, ref, note, test
	 */
	public static function rows( $year ) {
		global $wpdb;
		$year     = (int) $year;
		$payments = (array) $wpdb->get_results( 'SELECT * FROM ' . MSB_Install::table( 'payments' ) . " WHERE status IN ('paid','refunded','captured') ORDER BY created_at, id", ARRAY_A ); // phpcs:ignore
		$numbers  = array();
		$tests    = array();
		$docs     = array();
		$rows     = array();
		$kinds    = MSB_Payments::type_labels();

		foreach ( $payments as $p ) {
			$p['data'] = json_decode( (string) $p['data'], true );
			$p['data'] = is_array( $p['data'] ) ? $p['data'] : array();
			$bid       = (int) $p['booking_id'];
			if ( ! isset( $numbers[ $bid ] ) ) {
				$bk              = $wpdb->get_row( $wpdb->prepare( 'SELECT booking_number, is_test FROM ' . MSB_Install::table( 'bookings' ) . ' WHERE id = %d', $bid ), ARRAY_A ); // phpcs:ignore
				$numbers[ $bid ] = $bk ? (string) $bk['booking_number'] : '';
				$tests[ $bid ]   = $bk && ! empty( $bk['is_test'] );
				$docs[ $bid ]    = array(
					'invoice' => implode( ', ', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT number FROM ' . MSB_Install::table( 'documents' ) . " WHERE booking_id = %d AND series <> 'deposit' AND status <> 'corrected' ORDER BY id", $bid ) ) ), // phpcs:ignore
					'deposit' => implode( ', ', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT number FROM ' . MSB_Install::table( 'documents' ) . " WHERE booking_id = %d AND series = 'deposit' AND status <> 'corrected' ORDER BY id", $bid ) ) ), // phpcs:ignore
				);
			}
			if ( $tests[ $bid ] && 'Bar' === self::channel( $p['provider'] ) ) {
				continue;
			}
			$base = array(
				'test'    => $tests[ $bid ] ? 'ja' : '',
				'channel' => self::channel( $p['provider'] ),
				'booking' => $numbers[ $bid ] ? $numbers[ $bid ] : '#' . $bid,
				'ref'     => 0 === strpos( (string) $p['provider_ref'], 'pi_' ) ? $p['provider_ref'] : '',
				'note'    => isset( $p['data']['note'] ) ? (string) $p['data']['note'] : '',
			);

			if ( 'security' === $p['type'] ) {
				// Nur der einbehaltene Teil der Kaution ist eine Einnahme (Schadenersatz), der Rest ist durchlaufendes Geld.
				if ( 'captured' === $p['status'] && ! empty( $p['data']['captured'] ) ) {
					$rows[] = $base + array(
						'date'   => substr( isset( $p['data']['captured_at'] ) ? $p['data']['captured_at'] : $p['updated_at'], 0, 10 ),
						'kind'   => 'Kautionseinbehalt',
						'amount' => (float) $p['data']['captured'],
						'doc'    => $docs[ $bid ]['deposit'],
						'tax'    => 'Schadenersatz, nicht umsatzsteuerbar',
					);
				}
				continue;
			}

			$rows[] = $base + array(
				'date'   => substr( $p['created_at'], 0, 10 ),
				'kind'   => isset( $kinds[ $p['type'] ] ) ? $kinds[ $p['type'] ] : 'Mietzahlung',
				'amount' => (float) $p['amount'],
				'doc'    => $docs[ $bid ]['invoice'],
				'tax'    => 'Umsatz, § 19 UStG',
			);

			// Erstattungen: ab 1.7.1 einzeln mit Datum, davor eine Summe zum Zeitpunkt der letzten Änderung.
			$refunds = isset( $p['data']['refunds'] ) && is_array( $p['data']['refunds'] ) ? $p['data']['refunds'] : array();
			$logged  = 0.0;
			foreach ( $refunds as $r ) {
				$logged += (float) $r[1];
			}
			$total = isset( $p['data']['refunded'] ) ? (float) $p['data']['refunded'] : ( 'refunded' === $p['status'] ? (float) $p['amount'] : 0.0 );
			if ( round( $total - $logged, 2 ) > 0 ) {
				array_unshift( $refunds, array( $p['updated_at'], round( $total - $logged, 2 ) ) );
			}
			foreach ( $refunds as $r ) {
				$rows[] = $base + array(
					'date'   => substr( (string) $r[0], 0, 10 ),
					'kind'   => 'Erstattung',
					'amount' => -1 * (float) $r[1],
					'doc'    => $docs[ $bid ]['invoice'],
					'tax'    => 'Umsatz, § 19 UStG (Minderung)',
				);
			}
		}

		$rows = array_values(
			array_filter(
				$rows,
				function ( $r ) use ( $year ) {
					return (int) substr( $r['date'], 0, 4 ) === $year;
				}
			)
		);
		usort(
			$rows,
			function ( $a, $b ) {
				return strcmp( $a['date'], $b['date'] );
			}
		);
		return $rows;
	}

	/**
	 * Summen je Zahlungsweg (Einnahmen und Erstattungen ohne Kaution) und Kautionseinbehalte.
	 */
	public static function totals( array $rows ) {
		$t = array(
			'Bar'    => 0.0,
			'Stripe' => 0.0,
			'PayPal' => 0.0,
			'refund' => 0.0,
			'kau'    => 0.0,
			'test'   => 0.0,
		);
		foreach ( $rows as $r ) {
			if ( $r['test'] ) {
				$t['test'] += $r['amount'];
			}
			if ( 'Kautionseinbehalt' === $r['kind'] ) {
				$t['kau'] += $r['amount'];
			} elseif ( $r['amount'] < 0 ) {
				$t['refund'] += $r['amount'];
			} else {
				$t[ $r['channel'] ] += $r['amount'];
			}
		}
		return $t;
	}

	/**
	 * Kasten für die Seite „Belege“: Summen des Jahres und Download.
	 */
	public static function box( $year ) {
		$t   = self::totals( self::rows( $year ) );
		$url = wp_nonce_url( admin_url( 'admin.php?page=msb-documents&euer=csv&year=' . (int) $year ), 'msb_euer_export' );
		ob_start();
		?>
		<div class="msb-card-box">
			<h2>Zahlungen <?php echo (int) $year; ?> für die EÜR</h2>
			<p>Bar: <strong><?php echo esc_html( msb_price( $t['Bar'] ) ); ?></strong> · Stripe brutto: <strong><?php echo esc_html( msb_price( $t['Stripe'] ) ); ?></strong><?php if ( $t['PayPal'] ) : ?> · PayPal: <strong><?php echo esc_html( msb_price( $t['PayPal'] ) ); ?></strong><?php endif; ?> · Erstattungen: <strong><?php echo esc_html( msb_price( $t['refund'] ) ); ?></strong> · Kautionseinbehalte: <strong><?php echo esc_html( msb_price( $t['kau'] ) ); ?></strong><?php if ( abs( $t['test'] ) >= 0.005 ) : ?><br>Darin enthalten aus Testbuchungen (netto): <strong><?php echo esc_html( msb_price( $t['test'] ) ); ?></strong><?php endif; ?></p>
			<p><a class="button" href="<?php echo esc_url( $url ); ?>">Zahlungen <?php echo (int) $year; ?> als CSV (EÜR)</a></p>
			<p class="description">Jede Einnahme, Erstattung und jeder Kautionseinbehalt einzeln mit Datum, Zahlungsweg und Beleg. Damit ergänzen Sie den Kontoauszug: Bareinnahmen fehlen dort, Stripe erscheint nur als Sammelauszahlung. Stripe-Gebühren stehen nicht im Plugin, sondern bei Stripe unter Berichte → Auszahlungsabgleich.</p>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function handle() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! isset( $_GET['page'], $_GET['euer'] ) || 'msb-documents' !== $_GET['page'] ) {
			return;
		}
		check_admin_referer( 'msb_euer_export' );
		if ( ! current_user_can( msb_capability() ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		$year = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : (int) current_time( 'Y' );
		// phpcs:enable
		$rows = self::rows( $year );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="zahlungen-euer-' . $year . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore
		fputcsv( $out, array( 'Datum', 'Art', 'Zahlungsweg', 'Betrag', 'Buchung', 'Beleg', 'Steuerliche Einordnung', 'Stripe-Zahlung', 'Notiz', 'Test' ), ';' );
		foreach ( $rows as $r ) {
			fputcsv(
				$out,
				array(
					mysql2date( 'd.m.Y', $r['date'] ),
					$r['kind'],
					$r['channel'],
					number_format( $r['amount'], 2, ',', '' ),
					$r['booking'],
					$r['doc'],
					$r['tax'],
					$r['ref'],
					$r['note'],
					$r['test'],
				),
				';'
			);
		}
		fclose( $out ); // phpcs:ignore
		exit;
	}
}
