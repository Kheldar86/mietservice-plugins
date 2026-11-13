<?php
/**
 * Test für MSB_QR (ADM-15, 1.9.1).
 * Die Prüfsumme stammt aus einem Code, den zxing-cpp am 10.11.2026 korrekt gelesen hat
 * (Text, Fehlerkorrektur Q). Ändert sich der Encoder, Code neu mit einem Scanner prüfen und Summe ersetzen.
 * Aufruf aus dem Repository: php tests/msb-rental/test-qr.php [Pfad zu plugins/msb-rental]
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}
define( 'ABSPATH', '/' );
$dir = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : dirname( __DIR__, 2 ) . '/plugins/msb-rental';
require $dir . '/includes/class-msb-qr.php';

$pass = 0;
$fail = 0;
function check( $name, $ok ) {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "ok   $name\n"; } else { $fail++; echo "FAIL $name\n"; }
}
function flat( $m ) {
	return implode( '', array_map( function ( $r ) { return implode( '', array_map( 'intval', $r ) ); }, $m ) );
}

$m = MSB_QR::matrix( 'https://mietservice-barnim.de/qr/anhaenger-1' );
check( 'Anhänger-Link ergibt Version 4 (33 × 33)', 33 === count( $m ) && 33 === count( $m[0] ) );
check( 'Matrix wie geprüft', '5734ffb45c05cae76fbf18dc4443d8d0' === md5( flat( $m ) ) );
check( 'Finder oben links', '1111111' === implode( '', array_map( 'intval', array_slice( $m[0], 0, 7 ) ) ) && '1011101' === implode( '', array_map( 'intval', array_slice( $m[3], 0, 7 ) ) ) );
check( 'Dunkles Modul', true === $m[ 33 - 8 ][8] );
check( 'Längster Link (17 Zeichen) passt', null !== MSB_QR::matrix( 'https://mietservice-barnim.de/qr/' . str_repeat( 'a', 17 ) ) );
check( 'Zu langer Text ergibt null', null === MSB_QR::matrix( str_repeat( 'x', 152 ) ) );
check( '151 Zeichen ergeben Version 10', 57 === count( MSB_QR::matrix( str_repeat( 'x', 151 ) ) ) );
$svg = MSB_QR::svg( 'https://mietservice-barnim.de/qr/anhaenger-1' );
check( 'SVG mit Ruhezone', 0 === strpos( $svg, '<svg' ) && false !== strpos( $svg, 'viewBox="0 0 41 41"' ) );
check( 'SVG ohne Skript', false === stripos( $svg, 'script' ) );

echo "\n$pass bestanden, $fail fehlgeschlagen\n";
exit( $fail ? 1 : 0 );
