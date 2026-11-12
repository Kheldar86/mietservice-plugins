<?php
/**
 * Test für MSB_Expenses (PAY-37 bis PAY-42, 1.10.0): IBAN, Nummern, Zeitraum, Verwendungszweck, GiroCode,
 * Beträge, EÜR-Zeilen und die PDF der Abrechnung. Lädt die echten Plugin-Dateien mit nachgebildetem WordPress.
 * Aufruf aus dem Repository: php tests/msb-rental/test-expenses.php [Pfad zu plugins/msb-rental]
 *
 * Alle Daten sind erfunden. Die IBAN ist eine öffentliche Prüf-IBAN.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', '/' );

function add_action() {}
function is_admin() { return false; }
function current_time( $f ) { return 'Y' === $f ? '2026' : ( 'Y-m-d' === $f ? '2026-11-12' : '2026-11-12 21:30:00' ); }
function mysql2date( $f, $d ) { return date( str_replace( '\u\m', '\u\m', $f ), strtotime( $d ) ); }
function msb_price( $a ) { return number_format( (float) $a, 2, ',', '.' ) . ' €'; }
class MSB_Documents {
	public static function issuer() { return array( 'name' => 'Beispiel GbR', 'address' => array( 'Musterweg 1', '12345 Musterstadt' ), 'contact' => '', 'tax' => '000/000/00000', 'footer' => '' ); }
	public static function logo_jpeg() { return ''; }
}

$dir = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : dirname( __DIR__, 2 ) . '/plugins/msb-rental';
require $dir . '/includes/class-msb-pdf.php';
require $dir . '/includes/class-msb-qr.php';
require $dir . '/includes/class-msb-expenses.php';

$fail = 0;
$n    = 0;
function check( $name, $ok ) {
	global $fail, $n;
	$n++;
	if ( ! $ok ) {
		$fail++;
		echo "FEHLER: {$name}\n";
	}
}

// IBAN.
check( 'gültige Prüf-IBAN', MSB_Expenses::iban_valid( 'DE02120300000000202051' ) );
check( 'mit Leerzeichen und klein', MSB_Expenses::iban_valid( 'de02 1203 0000 0000 2020 51' ) );
check( 'falsche Prüfziffer', ! MSB_Expenses::iban_valid( 'DE03120300000000202051' ) );
check( 'DE mit falscher Länge', ! MSB_Expenses::iban_valid( 'DE0212030000000020205' ) );
check( 'Unsinn', ! MSB_Expenses::iban_valid( 'hallo' ) );
check( 'leer', ! MSB_Expenses::iban_valid( '' ) );
check( 'gültige AT-IBAN', MSB_Expenses::iban_valid( 'AT611904300234573201' ) );
check( 'Format', 'DE02 1203 0000 0000 2020 51' === MSB_Expenses::iban_format( 'DE02120300000000202051' ) );
check( 'Maske', 'DE02 •••• 2051' === MSB_Expenses::iban_mask( 'DE02120300000000202051' ) );

// Nummer, Zeitraum, Verwendungszweck.
check( 'Nummer', 'AUS-2026-0001' === MSB_Expenses::format_number( 2026, 1 ) );
check( 'Nummer vierstellig', 'AUS-2026-0123' === MSB_Expenses::format_number( 2026, 123 ) );
check( 'Zeitraum ein Monat', '11/2026' === MSB_Expenses::period_label( array( '2026-11-05', '2026-11-18', '2026-11-12' ) ) );
check( 'Zeitraum zwei Monate', '10/2026–11/2026' === MSB_Expenses::period_label( array( '2026-11-05', '2026-10-30' ) ) );
check( 'Zeitraum leer', '' === MSB_Expenses::period_label( array() ) );
check( 'Verwendungszweck', 'AUS-2026-0001 Auslagen 11/2026' === MSB_Expenses::purpose( 'AUS-2026-0001', '11/2026' ) );

// Beträge.
check( 'Betrag 45,90', 45.90 === MSB_Expenses::parse_amount( '45,90' ) );
check( 'Betrag 45.90', 45.90 === MSB_Expenses::parse_amount( '45.90' ) );
check( 'Betrag 1.234,56', 1234.56 === MSB_Expenses::parse_amount( '1.234,56' ) );
check( 'Betrag mit Euro', 6.99 === MSB_Expenses::parse_amount( '6,99 €' ) );
check( 'Betrag ungültig', 0.0 === MSB_Expenses::parse_amount( 'abc' ) );
$pos = array(
	array( 'amount' => '45.90' ),
	array( 'amount' => '6.99' ),
	array( 'amount' => '52.90' ),
);
check( 'Summe wie Vorlage 105,79', 105.79 === MSB_Expenses::total( $pos ) );

// GiroCode (EPC-QR).
$epc   = MSB_Expenses::epc_payload( 'Erika Mustermann', 'DE02 1203 0000 0000 2020 51', 105.79, 'AUS-2026-0001 Auslagen 11/2026' );
$lines = explode( "\n", $epc );
check( 'EPC 11 Zeilen', 11 === count( $lines ) );
check( 'EPC Kopf', 'BCD' === $lines[0] && '002' === $lines[1] && '1' === $lines[2] && 'SCT' === $lines[3] && '' === $lines[4] );
check( 'EPC Name', 'Erika Mustermann' === $lines[5] );
check( 'EPC IBAN ohne Leerzeichen', 'DE02120300000000202051' === $lines[6] );
check( 'EPC Betrag', 'EUR105.79' === $lines[7] );
check( 'EPC Zweck', 'AUS-2026-0001 Auslagen 11/2026' === $lines[10] );
check( 'EPC passt in den QR-Code', null !== MSB_QR::matrix( $epc ) );
$long = MSB_Expenses::epc_payload( str_repeat( 'Ä', 90 ), 'DE02120300000000202051', 1, str_repeat( 'x', 200 ) );
check( 'EPC kürzt Name auf 70 Zeichen', 70 === mb_strlen( explode( "\n", $long )[5] ) );

// EÜR-Zeilen.
$claims = array(
	array(
		'number'  => 'AUS-2026-0001',
		'status'  => 'paid',
		'paid_at' => '2026-12-02 00:00:00',
		'data'    => array(
			'name'      => 'Erika Mustermann',
			'positions' => array(
				array( 'spent_on' => '2026-11-05', 'purpose' => 'Baumarkt', 'amount' => 45.9, 'no_receipt_reason' => '' ),
				array( 'spent_on' => '2026-11-12', 'purpose' => 'Parkautomat', 'amount' => 2.5, 'no_receipt_reason' => 'keine Quittung' ),
			),
		),
	),
	array(
		'number'  => 'AUS-2026-0002',
		'status'  => 'cancelled',
		'paid_at' => null,
		'data'    => array(
			'name'      => 'Max Muster',
			'positions' => array( array( 'spent_on' => '2026-11-06', 'purpose' => 'Post', 'amount' => 6.99, 'no_receipt_reason' => '' ) ),
		),
	),
	array(
		'number'  => 'AUS-2027-0001',
		'status'  => 'issued',
		'paid_at' => null,
		'data'    => array(
			'name'      => 'Max Muster',
			'positions' => array(
				array( 'spent_on' => '2026-12-30', 'purpose' => 'Bürobedarf', 'amount' => 52.9, 'no_receipt_reason' => '' ),
				array( 'spent_on' => '2027-01-03', 'purpose' => 'Post', 'amount' => 6.99, 'no_receipt_reason' => '' ),
			),
		),
	),
);
$rows = MSB_Expenses::euer_rows( $claims, 2026 );
check( 'EÜR: 3 Zeilen (ohne Storno, ohne 2027)', 3 === count( $rows ) );
check( 'EÜR: nach Datum sortiert', '2026-11-05' === $rows[0]['date'] && '2026-12-30' === $rows[2]['date'] );
check( 'EÜR: ausgezahlt mit Datum', '2026-12-02' === $rows[0]['paid'] && 'ausgezahlt' === $rows[0]['status'] );
check( 'EÜR: Eigenbeleg markiert', 'ja' === $rows[1]['own'] && '' === $rows[0]['own'] );
check( 'EÜR: offen ohne Datum', '' === $rows[2]['paid'] && 'nicht ausgezahlt' === $rows[2]['status'] );
check( 'EÜR: Vorjahr fehlt in 2027', 1 === count( MSB_Expenses::euer_rows( $claims, 2027 ) ) );

// PDF der Abrechnung mit einem Fotobeleg (falls GD vorhanden) und einem PDF-Beleg.
$tmp = sys_get_temp_dir() . '/msb-expense-test-' . getmypid();
@mkdir( $tmp );
$photo = '';
if ( function_exists( 'imagecreatetruecolor' ) ) {
	$img = imagecreatetruecolor( 300, 500 );
	imagefill( $img, 0, 0, imagecolorallocate( $img, 240, 240, 240 ) );
	$photo = 'beleg-test.jpg';
	imagejpeg( $img, $tmp . '/' . $photo, 80 );
}
function wp_upload_dir() { global $tmp; return array( 'basedir' => $tmp ); }
function trailingslashit( $p ) { return rtrim( $p, '/' ) . '/'; }
function wp_mkdir_p( $d ) { return is_dir( $d ) || mkdir( $d, 0777, true ); }
// Die Ablage liegt unter basedir/msb-belege/auslagen.
$store = $tmp . '/msb-belege/auslagen';
@mkdir( $store, 0777, true );
if ( $photo ) {
	rename( $tmp . '/' . $photo, $store . '/' . $photo );
}
$claim = array(
	'id'            => 1,
	'number'        => 'AUS-2026-0001',
	'status'        => 'issued',
	'amount'        => 105.79,
	'created_at'    => '2026-11-12 21:30:00',
	'cancel_reason' => '',
	'data'          => array(
		'name'       => 'Erika Mustermann',
		'iban'       => 'DE02120300000000202051',
		'period'     => '11/2026',
		'purpose'    => 'AUS-2026-0001 Auslagen 11/2026',
		'created_by' => 'Erika',
		'issuer'     => MSB_Documents::issuer(),
		'positions'  => array(
			array( 'spent_on' => '2026-11-05', 'purpose' => 'Baumarkt (Schrauben & Werkzeug)', 'amount' => 45.9, 'vat_info' => '19 %', 'receipt_file' => $photo, 'receipt_type' => $photo ? 'jpeg' : '', 'no_receipt_reason' => $photo ? '' : 'Test ohne GD' ),
			array( 'spent_on' => '2026-11-12', 'purpose' => 'Deutsche Post (Paketmarken)', 'amount' => 6.99, 'vat_info' => '', 'receipt_file' => 'rechnung.pdf', 'receipt_type' => 'pdf', 'no_receipt_reason' => '' ),
			array( 'spent_on' => '2026-11-18', 'purpose' => 'Bürobedarf Online (Druckerpapier)', 'amount' => 52.9, 'vat_info' => '', 'receipt_file' => '', 'receipt_type' => '', 'no_receipt_reason' => 'Quittung verloren' ),
		),
	),
);
$pdf = MSB_Expenses::render( $claim );
check( 'PDF beginnt mit %PDF', 0 === strpos( $pdf, '%PDF-1.4' ) );
check( 'PDF endet mit %%EOF', '%%EOF' === substr( $pdf, -5 ) );
$pages = preg_match( '/\/Count (\d+)/', $pdf, $m ) ? (int) $m[1] : 0;
check( 'PDF Seiten: 1 + Fotoanlage', $pages === ( $photo ? 2 : 1 ) );
check( 'PDF enthält Foto', ! $photo || false !== strpos( $pdf, '/DCTDecode' ) );
file_put_contents( $tmp . '/AUS-2026-0001.pdf', $pdf );
echo 'Beispiel-PDF: ' . $tmp . "/AUS-2026-0001.pdf\n";

$claim['status']        = 'cancelled';
$claim['cancel_reason'] = 'Testgrund';
check( 'PDF storniert ohne Fehler', 0 === strpos( MSB_Expenses::render( $claim ), '%PDF' ) );

echo ( $fail ? "{$fail} von {$n} Fällen fehlgeschlagen\n" : "{$n} von {$n} Fällen bestanden\n" );
exit( $fail ? 1 : 0 );
