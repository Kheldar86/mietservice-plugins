<?php
defined( 'ABSPATH' ) || exit;

/**
 * Plugin-Einstellungen. Alles ist standardmäßig auf "geschlossen" gestellt.
 */
final class CMB_Settings {

	const OPTION = 'cmb_settings';

	/** Einzige Callback-URL der gehosteten Claude-Apps (Web, Desktop, Mobile). */
	const CLAUDE_CALLBACK = 'https://claude.ai/api/mcp/auth_callback';

	/** Veröffentlichter Egress-Bereich von Anthropic. */
	const ANTHROPIC_EGRESS = '160.79.104.0/21';

	/** Nur Administratoren dürfen verbinden und freigeben. Bewusst nicht konfigurierbar. */
	const REQUIRED_CAP = 'manage_options';

	const ACCESS_TTL    = 3600;           // 1 Stunde
	const REFRESH_TTL   = 2592000;        // 30 Tage
	const CODE_TTL      = 120;            // 2 Minuten
	const PAIRING_TTL   = 900;            // 15 Minuten
	const CHANGE_TTL    = 86400;          // Vorschläge (normal) verfallen nach 24 Stunden
	const CHANGE_TTL_HIGH     = 21600;    // hohes Risiko: 6 Stunden
	const CHANGE_TTL_CRITICAL = 3600;     // kritisch: 1 Stunde
	const MAX_PENDING   = 25;             // offene Vorschläge pro Nutzer
	const MAX_CLIENTS   = 20;             // aktive OAuth-Clients
	const MAX_BODY      = 1048576;        // 1 MB pro MCP-Request
	const MAX_CONTENT   = 262144;         // 256 KB Beitragsinhalt

	public static function defaults() {
		return array(
			'enabled'          => false,
			'pairing_until'    => 0,
			'allow_loopback'   => false,
			'ip_allowlist_on'  => false,
			'ip_allowlist'     => self::ANTHROPIC_EGRESS,
			'extra_origins'    => '',
			'power_files'      => false,
			'power_php'        => false,
			'power_sql'        => false,
			'power_wpcli'      => false,
			'power_plugins'    => false,
			'wp_cli_path'      => '',
			'php_cli_path'     => '',
			'totp_required'    => false,
			'totp_all'         => false,
			'approve_totp_only' => false,      // ab 0.4.0: Freigaben nur mit Zwei-Faktor-Code (ohne Passwort)
			'redact_pii'       => true,       // ab 0.4.2: Kundendaten in SQL-Ergebnissen und Belegen schwärzen
		);
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	public static function get( $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	public static function update( array $values ) {
		$all = array_merge( self::all(), array_intersect_key( $values, self::defaults() ) );
		update_option( self::OPTION, $all, true );
	}

	public static function is_enabled() {
		return (bool) self::get( 'enabled' );
	}

	public static function pairing_open() {
		return self::is_enabled() && (int) self::get( 'pairing_until' ) > time();
	}

	/** Der MCP-Endpunkt, den man in Claude als Connector-URL einträgt. */
	public static function mcp_url() {
		return untrailingslashit( rest_url( CMB_NS . '/mcp' ) );
	}

	public static function issuer() {
		return untrailingslashit( rest_url( CMB_NS ) );
	}

	public static function prm_url() {
		return untrailingslashit( rest_url( CMB_NS . '/oauth-protected-resource' ) );
	}

	public static function authorize_url() {
		return home_url( '/cmb-oauth/authorize' );
	}

	/** Pretty permalinks sind nötig, weil /.well-known und /cmb-oauth über index.php laufen. */
	public static function permalinks_ok() {
		return '' !== (string) get_option( 'permalink_structure' );
	}

	public static function https_ok() {
		$home = home_url();
		if ( 0 === strpos( $home, 'https://' ) ) {
			return true;
		}
		$host = wp_parse_url( $home, PHP_URL_HOST );
		return in_array( $host, array( 'localhost', '127.0.0.1' ), true );
	}
}
