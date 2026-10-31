<?php
/**
 * Test für MSB_Schema::available() / product() (SEO-02, 1.8.5).
 * Lädt die echten Plugin-Dateien mit nachgebildetem WordPress.
 * Aufruf aus dem Repository: php tests/msb-rental/test-schema-availability.php [Pfad zu plugins/msb-rental]
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', '/' );
define( 'ARRAY_A', 'ARRAY_A' );

$GLOBALS['T'] = array(
	'today'    => '2026-11-02',
	'settings' => array( 'lead_days' => 1, 'buffer_days' => 1, 'max_advance_days' => 365, 'closed_weekdays' => array() ),
	'meta'     => array(),
	'bookings' => array(), // [item_id, status, start, end, qty]
	'filters'  => array(),
);

class WP_Post { public $ID; public $post_type = 'msb_item'; public $post_status = 'publish'; public $post_excerpt = 'Kurztext'; public $post_content = ''; }

function add_action() {}
function add_filter( $tag, $cb ) {}
function apply_filters( $tag, $value, ...$args ) {
	return isset( $GLOBALS['T']['filters'][ $tag ] ) ? $GLOBALS['T']['filters'][ $tag ]( $value, ...$args ) : $value;
}
function get_option( $k, $d = false ) { return 'msb_settings' === $k ? $GLOBALS['T']['settings'] : $d; }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function current_time( $f ) { return $GLOBALS['T']['today']; }
function absint( $v ) { return abs( (int) $v ); }
function get_post( $id ) { $p = new WP_Post(); $p->ID = $id; return $p; }
function get_post_meta( $id, $k, $single = false ) { return isset( $GLOBALS['T']['meta'][ $id ][ $k ] ) ? $GLOBALS['T']['meta'][ $id ][ $k ] : ''; }
function get_the_title( $id ) { return 'Artikel ' . $id; }
function get_permalink( $id ) { return 'https://example.test/artikel/' . $id . '/'; }
function get_post_thumbnail_id( $id ) { return 0; }
function wp_get_attachment_image_url( $id ) { return false; }
function get_the_terms() { return false; }
function is_wp_error( $v ) { return false; }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function wp_html_excerpt( $s, $n, $more = '' ) { return mb_substr( $s, 0, $n ); }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function get_bloginfo( $k = "" ) { return "Test"; }

class FakeWpdb {
	public $prefix = 'wp_';
	public function prepare( $sql, $params ) { return $params; }
	public function get_results( $params ) {
		$item     = array_shift( $params );
		$exclude  = array_pop( $params );
		$from     = array_pop( $params );
		$to       = array_pop( $params );
		$statuses = $params;
		$rows     = array();
		foreach ( $GLOBALS['T']['bookings'] as $i => $b ) {
			if ( $b[0] === $item && in_array( $b[1], $statuses, true ) && $b[2] <= $to && $b[3] >= $from && $i + 1 !== $exclude ) {
				$rows[] = array( 'start_date' => $b[2], 'end_date' => $b[3], 'quantity' => $b[4] );
			}
		}
		return $rows;
	}
}
$GLOBALS['wpdb'] = new FakeWpdb();

$dir = rtrim( isset( $argv[1] ) ? $argv[1] : dirname( __DIR__, 2 ) . '/plugins/msb-rental', '/' ) . '/includes/';
require $dir . 'functions.php';
require $dir . 'class-msb-install.php';
require $dir . 'class-msb-items.php';
require $dir . 'class-msb-availability.php';
require $dir . 'class-msb-schema.php';

$fails = 0;
function scenario( $name, $meta, $bookings, $expect, $setup = null ) {
	global $fails;
	$T = &$GLOBALS['T'];
	$saved = $T;
	$T['meta']     = array( 7 => array_merge( array( '_msb_price_day' => '15', '_msb_stock' => '1' ), $meta ) );
	$T['bookings'] = $bookings;
	if ( $setup ) {
		$setup( $T );
	}
	$p   = MSB_Schema::product( 7 );
	$got = isset( $p['offers'] ) ? basename( $p['offers']['availability'] ) : 'kein Angebot';
	$ok  = $got === $expect;
	$fails += $ok ? 0 : 1;
	printf( "%s  %-62s erwartet %-12s ist %s\n", $ok ? 'OK  ' : 'FEHL', $name, $expect, $got );
	$T = $saved;
}

// Prüfzeitraum bei heute 02.11., Vorlauf 1: 03.11. bis 02.12.2026. Puffer 1 Tag.
scenario( 'Keine Buchung', array(), array(), 'InStock' );
scenario( 'Ausgebucht: 04.11.–01.12. + Puffer deckt alles', array(), array( array( 7, 'confirmed', '2026-11-04', '2026-12-01', 1 ) ), 'OutOfStock' );
scenario( 'Erster Tag frei (03.11.)', array(), array( array( 7, 'confirmed', '2026-11-05', '2026-12-01', 1 ) ), 'InStock' );
scenario( 'Letzter Tag frei (02.12.)', array(), array( array( 7, 'confirmed', '2026-11-04', '2026-11-30', 1 ) ), 'InStock' );
scenario( 'Letzter Tag nur durch Puffer der Folgebuchung belegt', array(), array(
	array( 7, 'confirmed', '2026-11-04', '2026-11-30', 1 ),
	array( 7, 'pending', '2026-12-03', '2026-12-10', 1 ),
), 'OutOfStock' );
scenario( 'Lücke nur durch Puffer geschlossen (zwei Buchungen)', array(), array(
	array( 7, 'confirmed', '2026-11-01', '2026-11-15', 1 ),
	array( 7, 'picked_up', '2026-11-17', '2026-12-05', 1 ),
), 'OutOfStock' );
scenario( 'Stornierte Buchung belegt nicht', array(), array( array( 7, 'cancelled', '2026-11-01', '2026-12-31', 1 ) ), 'InStock' );
scenario( 'Bestand 2, eine Buchung über alles', array( '_msb_stock' => '2' ), array( array( 7, 'confirmed', '2026-11-01', '2026-12-31', 1 ) ), 'InStock' );
scenario( 'Sperrzeit über den ganzen Zeitraum', array( '_msb_blocked' => '03.11.2026 - 02.12.2026 Wartung' ), array(), 'OutOfStock' );
scenario( 'Sperrzeit bis 01.12., 02.12. frei', array( '_msb_blocked' => '03.11.2026 - 01.12.2026' ), array(), 'InStock' );
scenario( 'Nicht buchbar', array( '_msb_bookable' => 'no' ), array(), 'OutOfStock' );
scenario( 'Bestand 0', array( '_msb_stock' => '0' ), array(), 'OutOfStock' );
scenario( 'Preis 0 → kein Angebot', array( '_msb_price_day' => '0' ), array(), 'kein Angebot' );
scenario( 'Alle Wochentage Schliesstag zählen trotzdem als frei', array(), array(), 'InStock', function ( &$T ) {
	$T['settings']['closed_weekdays'] = array( 0, 1, 2, 3, 4, 5, 6, 7 );
} );
scenario( 'Vorlauf 400 Tage liegt hinter max. Vorausbuchung 365', array(), array(), 'OutOfStock', function ( &$T ) {
	$T['settings']['lead_days']        = 400;
} );
scenario( 'Vorlauf 0: heute frei, Rest belegt', array(), array( array( 7, 'confirmed', '2026-11-04', '2026-12-31', 1 ) ), 'InStock', function ( &$T ) {
	$T['settings']['lead_days'] = 0;
} );
scenario( 'Filter 60 Tage: nach 30 Tagen wieder frei', array(), array( array( 7, 'confirmed', '2026-11-01', '2026-12-10', 1 ) ), 'InStock', function ( &$T ) {
	$T['filters']['msb_schema_availability_days'] = function () { return 60; };
} );
scenario( 'Standard 30 Tage: erst nach 30 Tagen wieder frei', array(), array( array( 7, 'confirmed', '2026-11-01', '2026-12-10', 1 ) ), 'OutOfStock' );
scenario( 'Andere Artikel-ID belegt nicht', array(), array( array( 8, 'confirmed', '2026-11-01', '2026-12-31', 1 ) ), 'InStock' );

echo $fails ? "\n$fails Fehler\n" : "\nAlle Tests bestanden\n";
exit( $fails ? 1 : 0 );
