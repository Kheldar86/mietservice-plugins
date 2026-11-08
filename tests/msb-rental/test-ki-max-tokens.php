<?php
/**
 * Test für MSB_Assistant::ajax_generate() (KI-03, 1.8.6): Anfrage an die Claude API und Fehlermeldungen je stop_reason.
 * Lädt die echte Plugin-Datei mit nachgebildetem WordPress. Es geht keine Anfrage ins Netz.
 * Aufruf aus dem Repository: php tests/msb-rental/test-ki-max-tokens.php [Pfad zu plugins/msb-rental]
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', '/' );

class MSB_Items { const POST_TYPE = 'msb_item'; const TAXONOMY = 'msb_category'; }
class JsonDone extends Exception { public $ok; public $data; public function __construct( $ok, $data ) { $this->ok = $ok; $this->data = $data; parent::__construct( 'json' ); } }

$GLOBALS['T'] = array( 'request' => null, 'response' => null );
$img = sys_get_temp_dir() . '/msb-test-bild.jpg';
file_put_contents( $img, 'nur-ein-test' );

function add_action() {}
function check_ajax_referer() { return true; }
function current_user_can() { return true; }
function msb_capability() { return 'manage_options'; }
function get_option( $k, $d = false ) { return 'msb_ai_settings' === $k ? array( 'api_key' => 'platzhalter', 'model' => 'claude-sonnet-5-5' ) : $d; }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_text_field( $v ) { return trim( (string) $v ); }
function sanitize_textarea_field( $v ) { return trim( (string) $v ); }
function wp_attachment_is_image( $id ) { return true; }
function get_attached_file( $id ) { return $GLOBALS['img']; }
function get_post_mime_type( $id ) { return 'image/jpeg'; }
function wp_get_attachment_metadata( $id ) { return array(); }
function get_term( $id, $tax ) { return (object) array( 'name' => 'Transport' ); }
function is_wp_error( $v ) { return false; }
function get_posts() { return array(); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_remote_post( $url, $args ) { $GLOBALS['T']['request'] = $args; return $GLOBALS['T']['response']; }
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function wp_send_json_error( $data = null ) { throw new JsonDone( false, $data ); }
function wp_send_json_success( $data = null ) { throw new JsonDone( true, $data ); }

$dir = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : dirname( __DIR__, 2 ) . '/plugins/msb-rental';
require $dir . '/admin/class-msb-assistant.php';

$_POST = array( 'images' => array( 7 ), 'data' => array( 'category' => '3', 'brand_model' => 'Testanhänger' ) );

function run( $stop, $text ) {
	$GLOBALS['T']['response'] = array(
		'code' => 200,
		'body' => json_encode( array( 'stop_reason' => $stop, 'content' => array( array( 'type' => 'text', 'text' => $text ) ), 'usage' => array( 'input_tokens' => 1, 'output_tokens' => 1 ) ) ),
	);
	try {
		MSB_Assistant::ajax_generate();
	} catch ( JsonDone $e ) {
		return $e;
	}
	return null;
}

$fail = 0;
$n    = 0;
function check( $name, $cond ) {
	global $fail, $n;
	$n++;
	if ( ! $cond ) {
		$fail++;
	}
	echo ( $cond ? 'ok   ' : 'FEHL ' ) . $name . "\n";
}

$r    = run( 'max_tokens', '{"titel":"abgebroch' );
$req  = $GLOBALS['T']['request'];
$body = json_decode( $req['body'], true );
check( 'max_tokens in der Anfrage ist 16000', 16000 === $body['max_tokens'] );
check( 'effort in output_config ist medium', isset( $body['output_config']['effort'] ) && 'medium' === $body['output_config']['effort'] );
check( 'JSON-Schema bleibt in output_config', 'json_schema' === $body['output_config']['format']['type'] );
check( 'kein thinking-Feld (Modelle denken adaptiv)', ! isset( $body['thinking'] ) );
check( 'kein temperature-Feld', ! isset( $body['temperature'] ) );
check( 'Zeitlimit der Anfrage 170 s', 170 === $req['timeout'] );
check( 'max_tokens: Fehler mit eigener Meldung', $r && ! $r->ok && false !== strpos( $r->data['message'], 'zu lang' ) && false !== strpos( $r->data['message'], 'max_tokens' ) );

$r = run( 'refusal', '' );
check( 'refusal: Fehler mit eigener Meldung', $r && ! $r->ok && false !== strpos( $r->data['message'], 'abgelehnt' ) );

$r = run( 'end_turn', 'kein json' );
check( 'end_turn ohne gültiges JSON: unvollständig', $r && ! $r->ok && false !== strpos( $r->data['message'], 'unvollständig (end_turn)' ) );

$r = run( 'end_turn', '' );
check( 'leere Antwort: Fehler', $r && ! $r->ok );

@unlink( $img );
echo "\n" . ( $n - $fail ) . ' von ' . $n . " Fällen bestanden\n";
exit( $fail ? 1 : 0 );
