<?php
/**
 * Test für MSB_Promo_Links (IF-20, BKG-22, ADM-15, 1.9.0).
 * Lädt die echte Plugin-Datei mit nachgebildetem WordPress.
 * Aufruf aus dem Repository: php tests/msb-rental/test-promo-links.php [Pfad zu plugins/msb-rental]
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', '/' );

$GLOBALS['T'] = array(
	'options'  => array(),
	'qv'       => '',
	'redirect' => null,
	'can'      => false,
	'referer'  => '',
	'ref'      => '',
	'updates'  => array(),
);

class Redirected extends Exception {}

function add_action() {}
function add_filter() {}
function add_rewrite_rule() {}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['T']['options'] ) ? $GLOBALS['T']['options'][ $k ] : $d; }
function update_option( $k, $v ) { $GLOBALS['T']['options'][ $k ] = $v; return true; }
function current_time( $f ) { return '2026-11'; }
function home_url( $p = '' ) { return 'https://mietservice-barnim.de' . $p; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function sanitize_title( $s ) { $s = strtolower( (string) $s ); $s = preg_replace( '/[^a-z0-9]+/', '-', $s ); return trim( $s, '-' ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function esc_url_raw( $u, $p = null ) { return preg_match( '#^(https?://|/)#', $u ) ? $u : ''; }
function get_query_var( $k ) { return $GLOBALS['T']['qv']; }
function wp_unslash( $v ) { return $v; }
function current_user_can( $c ) { return $GLOBALS['T']['can']; }
function wp_get_referer() { return $GLOBALS['T']['referer']; }
function wp_redirect( $u, $s ) { $GLOBALS['T']['redirect'] = $u; throw new Redirected(); }
function add_query_arg( $args, $url ) {
	$frag = '';
	if ( false !== strpos( $url, '#' ) ) {
		list( $url, $frag ) = explode( '#', $url, 2 );
		$frag = '#' . $frag;
	}
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ) . $frag;
}
class MSB_Cart {
	public static function set_ref( $r ) { $GLOBALS['T']['ref'] = $r; }
	public static function ref() { return $GLOBALS['T']['ref']; }
}
class MSB_Install { public static function table( $t ) { return 'wp_msb_' . $t; } }
class FakeDb { public function update( $t, $d, $w ) { $GLOBALS['T']['updates'][] = array( $t, $d, $w ); } }
$GLOBALS['wpdb'] = new FakeDb();

$dir = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : dirname( __DIR__, 2 ) . '/plugins/msb-rental';
require $dir . '/includes/class-msb-promo-links.php';

$pass = 0;
$fail = 0;
function check( $name, $ok ) {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "ok   $name\n"; } else { $fail++; echo "FAIL $name\n"; }
}
function visit( $slug, $agent = 'Mozilla/5.0 (iPhone)', $method = 'GET' ) {
	$GLOBALS['T']['qv']       = $slug;
	$GLOBALS['T']['redirect'] = null;
	$_SERVER['HTTP_USER_AGENT'] = $agent;
	$_SERVER['REQUEST_METHOD']  = $method;
	try { MSB_Promo_Links::redirect(); } catch ( Redirected $e ) {}
	return $GLOBALS['T']['redirect'];
}
function clicks() { $l = get_option( 'msb_promo_links' ); return MSB_Promo_Links::total( $l['anhaenger'] ); }

// Anlegen beim ersten Aufruf
$links = MSB_Promo_Links::links();
check( 'Anhänger-Link wird angelegt', isset( $links['anhaenger'] ) && '/#neu' === $links['anhaenger']['target'] );

// Weiterleitung mit UTM und Anker
$to = visit( 'anhaenger' );
check( 'Weiterleitung auf Startseite #neu mit UTM', 'https://mietservice-barnim.de/?utm_source=qr&utm_medium=offline&utm_campaign=anhaenger#neu' === $to );
check( 'Aufruf gezählt', 1 === clicks() );

visit( 'anhaenger', 'Googlebot/2.1' );
check( 'Bot zählt nicht', 1 === clicks() );
visit( 'anhaenger', 'Mozilla/5.0', 'HEAD' );
check( 'HEAD zählt nicht', 1 === clicks() );
visit( 'anhaenger', 'WhatsApp/2.23' );
check( 'Vorschau zählt nicht', 1 === clicks() );
$GLOBALS['T']['can'] = true;
visit( 'anhaenger' );
check( 'Eigener Aufruf im Backend zählt nicht', 1 === clicks() );
$GLOBALS['T']['can'] = false;
visit( 'ANHAENGER' );
check( 'Großschreibung wird erkannt und gezählt', 2 === clicks() );

check( 'Unbekannter Link führt auf Startseite', 'https://mietservice-barnim.de/' === visit( 'gibtsnicht' ) );
check( 'Ohne Link keine Weiterleitung', null === visit( '' ) );

// Ziele nur auf der eigenen Seite
check( 'Pfad erlaubt', '/mieten/x/' === MSB_Promo_Links::sanitize_target( '/mieten/x/' ) );
check( 'Eigene Adresse erlaubt', 'https://mietservice-barnim.de/kontakt/' === MSB_Promo_Links::sanitize_target( 'https://mietservice-barnim.de/kontakt/' ) );
check( 'Fremde Adresse abgelehnt', '' === MSB_Promo_Links::sanitize_target( 'https://example.com/' ) );
check( 'Protokoll-relative Adresse abgelehnt', '' === MSB_Promo_Links::sanitize_target( '//example.com/x' ) );
check( 'javascript: abgelehnt', '' === MSB_Promo_Links::sanitize_target( 'javascript:alert(1)' ) );
check( 'Name auf 17 Zeichen gekürzt', 17 === strlen( MSB_Promo_Links::sanitize_slug( str_repeat( 'a', 30 ) ) ) );

// Herkunft bis zur Buchung
$GLOBALS['T']['referer'] = 'https://mietservice-barnim.de/mieten/x/?utm_source=qr&utm_medium=offline&utm_campaign=anhaenger';
MSB_Promo_Links::remember_ref();
check( 'Herkunft am Warenkorb gemerkt', 'qr-anhaenger' === $GLOBALS['T']['ref'] );
MSB_Promo_Links::on_booking_created( 7, array( 'source' => 'web' ) );
check( 'Buchung bekommt channel qr-anhaenger', isset( $GLOBALS['T']['updates'][0] ) && 'qr-anhaenger' === $GLOBALS['T']['updates'][0][1]['channel'] && 7 === $GLOBALS['T']['updates'][0][2]['id'] );
MSB_Promo_Links::on_booking_created( 8, array( 'source' => 'admin' ) );
check( 'Manuelle Buchung bleibt ohne Quelle', 1 === count( $GLOBALS['T']['updates'] ) );
$GLOBALS['T']['ref']     = '';
$GLOBALS['T']['referer'] = 'https://mietservice-barnim.de/?utm_source=qr&utm_campaign=unbekannt';
MSB_Promo_Links::remember_ref();
check( 'Unbekannter Link setzt keine Herkunft', '' === $GLOBALS['T']['ref'] );
$GLOBALS['T']['referer'] = 'https://mietservice-barnim.de/?utm_source=kleinanzeigen&utm_campaign=anhaenger';
MSB_Promo_Links::remember_ref();
check( 'Kleinanzeigen-Herkunft bleibt unberührt', '' === $GLOBALS['T']['ref'] );
check( 'Quelle als Text', 'Webseite über QR-Code (Aufkleber am PKW-Anhänger)' === MSB_Promo_Links::channel_label( 'qr-anhaenger' ) );

echo "\n$pass bestanden, $fail fehlgeschlagen\n";
exit( $fail ? 1 : 0 );
