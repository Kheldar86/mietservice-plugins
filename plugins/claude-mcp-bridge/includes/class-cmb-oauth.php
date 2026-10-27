<?php
defined( 'ABSPATH' ) || exit;

/**
 * OAuth 2.1 Autorisierungsserver (RFC 6749/7636/7591/7009/8414/8707/9207/9728).
 *
 * - Nur Authorization Code + PKCE S256, kein Implicit, kein Password-Grant.
 * - Redirect-URIs nur aus fester Allowlist (Claude-Callback), exakter Vergleich.
 * - Dynamische Registrierung nur innerhalb eines vom Admin geöffneten Kopplungsfensters.
 * - Codes 2 Min., Einmalnutzung; Refresh-Tokens rotieren, Wiederverwendung sperrt die ganze Kette.
 * - Tokens sind an die Ressource (MCP-URL) gebunden.
 */
final class CMB_OAuth {

	const SCOPES = array( 'wp:read', 'wp:propose', 'wp:power', 'offline_access' );

	/** Scopes, die dieser Server gerade anbietet (wp:power nur bei aktivem Expertenmodus). */
	public static function available_scopes() {
		return array_values( array_filter( self::SCOPES, function ( $s ) {
			return 'wp:power' !== $s || CMB_Power::any_enabled();
		} ) );
	}

	public static function resource_scopes() {
		return array_values( array_diff( self::available_scopes(), array( 'offline_access' ) ) );
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'route_front' ), 1 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		// Verhindert, dass WordPress' Application-Password-Prüfung den Basic-Header
		// des Token-Endpunkts (client_secret_basic) abfängt.
		add_filter( 'application_password_is_api_request', array( __CLASS__, 'skip_app_passwords' ) );
	}

	public static function skip_app_passwords( $is_api ) {
		return self::is_own_rest_request() ? false : $is_api;
	}

	public static function is_own_rest_request() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		return false !== strpos( $uri, '/' . CMB_NS . '/' ) || false !== strpos( $uri, 'rest_route=%2F' . rawurlencode( CMB_NS ) ) || false !== strpos( $uri, 'rest_route=/' . CMB_NS );
	}

	/* ------------------------------------------------------------- metadata */

	public static function as_metadata() {
		return array(
			'issuer'                                         => CMB_Settings::issuer(),
			'authorization_endpoint'                         => CMB_Settings::authorize_url(),
			'token_endpoint'                                 => rest_url( CMB_NS . '/oauth/token' ),
			'registration_endpoint'                          => rest_url( CMB_NS . '/oauth/register' ),
			'revocation_endpoint'                            => rest_url( CMB_NS . '/oauth/revoke' ),
			'response_types_supported'                       => array( 'code' ),
			'response_modes_supported'                       => array( 'query' ),
			'grant_types_supported'                          => array( 'authorization_code', 'refresh_token' ),
			'code_challenge_methods_supported'               => array( 'S256' ),
			'token_endpoint_auth_methods_supported'          => array( 'none', 'client_secret_post', 'client_secret_basic' ),
			'revocation_endpoint_auth_methods_supported'     => array( 'none', 'client_secret_post', 'client_secret_basic' ),
			'scopes_supported'                               => self::available_scopes(),
			'authorization_response_iss_parameter_supported' => true,
			'service_documentation'                          => admin_url( 'admin.php?page=cmb' ),
		);
	}

	public static function prm_metadata() {
		return array(
			'resource'                 => CMB_Settings::mcp_url(),
			'authorization_servers'    => array( CMB_Settings::issuer() ),
			'scopes_supported'         => self::resource_scopes(),
			'bearer_methods_supported' => array( 'header' ),
			'resource_name'            => get_bloginfo( 'name' ) . ' (WordPress)',
		);
	}

	/* --------------------------------------------- front-end routing (no REST) */

	private static function request_path() {
		$path = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH );
		return '/' . trim( (string) $path, '/' );
	}

	public static function route_front() {
		$path = self::request_path();
		if ( false === strpos( $path, '/.well-known/' ) && false === strpos( $path, '/cmb-oauth/' ) ) {
			return;
		}
		$home_path   = '/' . trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		$home_path   = '/' === $home_path ? '' : $home_path;
		$issuer_path = rtrim( (string) wp_parse_url( CMB_Settings::issuer(), PHP_URL_PATH ), '/' );
		$mcp_path    = rtrim( (string) wp_parse_url( CMB_Settings::mcp_url(), PHP_URL_PATH ), '/' );

		$as_paths  = array(
			'/.well-known/oauth-authorization-server' . $issuer_path,
			'/.well-known/openid-configuration' . $issuer_path,
			'/.well-known/oauth-authorization-server',
		);
		$prm_paths = array(
			'/.well-known/oauth-protected-resource' . $mcp_path,
			'/.well-known/oauth-protected-resource',
		);

		if ( in_array( $path, $as_paths, true ) ) {
			self::send_json( CMB_Settings::is_enabled() ? self::as_metadata() : array( 'error' => 'disabled' ), CMB_Settings::is_enabled() ? 200 : 404 );
		}
		if ( in_array( $path, $prm_paths, true ) ) {
			self::send_json( CMB_Settings::is_enabled() ? self::prm_metadata() : array( 'error' => 'disabled' ), CMB_Settings::is_enabled() ? 200 : 404 );
		}
		if ( $home_path . '/cmb-oauth/authorize' === $path ) {
			self::authorize();
		}
	}

	private static function send_json( $data, $status = 200 ) {
		status_header( $status );
		CMB_Security::no_store_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES );
		exit;
	}

	/* --------------------------------------------------------- REST routes */

	public static function register_routes() {
		$open = '__return_true'; // Authentifizierung erfolgt in den Callbacks selbst (OAuth-Client bzw. Bearer).

		register_rest_route( CMB_NS, '/oauth-protected-resource', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'rest_prm' ), 'permission_callback' => $open ) );
		register_rest_route( CMB_NS, '/.well-known/oauth-authorization-server', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'rest_as' ), 'permission_callback' => $open ) );
		register_rest_route( CMB_NS, '/.well-known/openid-configuration', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'rest_as' ), 'permission_callback' => $open ) );
		register_rest_route( CMB_NS, '/oauth/register', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_register' ), 'permission_callback' => $open ) );
		register_rest_route( CMB_NS, '/oauth/token', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_token' ), 'permission_callback' => $open ) );
		register_rest_route( CMB_NS, '/oauth/revoke', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_revoke' ), 'permission_callback' => $open ) );
	}

	private static function json( $data, $status = 200 ) {
		$res = new WP_REST_Response( $data, $status );
		$res->header( 'Cache-Control', 'no-store' );
		$res->header( 'Pragma', 'no-cache' );
		return $res;
	}

	private static function oauth_error( $error, $desc, $status = 400 ) {
		return self::json( array( 'error' => $error, 'error_description' => $desc ), $status );
	}

	public static function rest_prm() {
		return CMB_Settings::is_enabled() ? self::json( self::prm_metadata() ) : self::oauth_error( 'disabled', 'MCP bridge is disabled.', 404 );
	}

	public static function rest_as() {
		return CMB_Settings::is_enabled() ? self::json( self::as_metadata() ) : self::oauth_error( 'disabled', 'MCP bridge is disabled.', 404 );
	}

	/* ------------------------------------------------- dynamic registration */

	public static function rest_register( WP_REST_Request $req ) {
		if ( ! CMB_Settings::is_enabled() || ! CMB_Security::ip_allowed() ) {
			return self::oauth_error( 'access_denied', 'Not available.', 403 );
		}
		if ( ! CMB_Security::rate_limit( 'reg|' . CMB_Security::client_ip(), 10, HOUR_IN_SECONDS ) ) {
			return self::oauth_error( 'slow_down', 'Too many registrations.', 429 );
		}
		if ( ! CMB_Settings::pairing_open() ) {
			CMB_Security::audit( 'dcr_rejected_closed', array(), 0 );
			return self::oauth_error( 'access_denied', 'Registration is closed. Open a pairing window in WordPress (Claude MCP > Einstellungen > Kopplung starten).', 403 );
		}
		if ( CMB_DB::count_active_clients() >= CMB_Settings::MAX_CLIENTS ) {
			return self::oauth_error( 'access_denied', 'Too many clients. Remove unused connections in WordPress.', 403 );
		}

		$body = json_decode( $req->get_body(), true );
		if ( ! is_array( $body ) || strlen( $req->get_body() ) > 8192 ) {
			return self::oauth_error( 'invalid_client_metadata', 'Body must be a JSON object.' );
		}

		$uris = $body['redirect_uris'] ?? null;
		if ( ! is_array( $uris ) || count( $uris ) < 1 || count( $uris ) > 5 ) {
			return self::oauth_error( 'invalid_redirect_uri', 'redirect_uris required (1-5).' );
		}
		foreach ( $uris as $u ) {
			if ( ! CMB_Security::redirect_uri_allowed( $u ) ) {
				CMB_Security::audit( 'dcr_bad_redirect', array( 'uri' => is_string( $u ) ? substr( $u, 0, 200 ) : 'non-string' ), 0 );
				return self::oauth_error( 'invalid_redirect_uri', 'redirect_uri not allowed.' );
			}
		}

		$grants = $body['grant_types'] ?? array( 'authorization_code', 'refresh_token' );
		if ( ! is_array( $grants ) || array_diff( $grants, array( 'authorization_code', 'refresh_token' ) ) ) {
			return self::oauth_error( 'invalid_client_metadata', 'Unsupported grant_types.' );
		}
		$rtypes = $body['response_types'] ?? array( 'code' );
		if ( ! is_array( $rtypes ) || array( 'code' ) !== array_values( $rtypes ) ) {
			return self::oauth_error( 'invalid_client_metadata', 'Unsupported response_types.' );
		}
		$auth_method = $body['token_endpoint_auth_method'] ?? 'none';
		if ( ! in_array( $auth_method, array( 'none', 'client_secret_post', 'client_secret_basic' ), true ) ) {
			return self::oauth_error( 'invalid_client_metadata', 'Unsupported token_endpoint_auth_method.' );
		}

		$name = isset( $body['client_name'] ) && is_string( $body['client_name'] ) ? sanitize_text_field( $body['client_name'] ) : '';
		$name = mb_substr( '' !== $name ? $name : 'Unbenannter Client', 0, 100 );

		list( $client_id, $secret ) = CMB_DB::create_client( $name, $uris, 'none' !== $auth_method, 'dcr' );

		// Kopplungsfenster ist einmalig: nach der ersten erfolgreichen Registrierung schließen.
		CMB_Settings::update( array( 'pairing_until' => 0 ) );
		CMB_Security::audit( 'dcr_registered', array( 'name' => $name, 'redirect_uris' => $uris ), 0, $client_id );

		$out = array(
			'client_id'                  => $client_id,
			'client_id_issued_at'        => time(),
			'client_name'                => $name,
			'redirect_uris'              => array_values( $uris ),
			'grant_types'                => array_values( $grants ),
			'response_types'             => array( 'code' ),
			'token_endpoint_auth_method' => $auth_method,
		);
		if ( $secret ) {
			$out['client_secret']            = $secret;
			$out['client_secret_expires_at'] = 0;
		}
		return self::json( $out, 201 );
	}

	/* ----------------------------------------------------------- authorize */

	private static function error_page( $title, $message, $status = 400 ) {
		status_header( $status );
		CMB_Security::no_store_headers( true );
		header( 'Content-Type: text/html; charset=utf-8' );
		self::page_open( $title );
		echo '<p>' . esc_html( $message ) . '</p>';
		self::page_close();
		exit;
	}

	private static function redirect_with( $redirect_uri, array $params ) {
		$params['iss'] = CMB_Settings::issuer();
		$sep           = false === strpos( $redirect_uri, '?' ) ? '?' : '&';
		$location      = $redirect_uri . $sep . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
		CMB_Security::no_store_headers();
		// Bewusst kein wp_safe_redirect: Ziel wurde zuvor gegen die registrierte URI geprüft.
		header( 'Location: ' . $location, true, 302 );
		exit;
	}

	/**
	 * Liest und validiert die Autorisierungsparameter.
	 * Fehler VOR erfolgreicher Prüfung von client_id/redirect_uri zeigen eine Fehlerseite
	 * (kein Redirect – sonst Open Redirect).
	 */
	private static function read_authorize_params( array $src ) {
		$get = function ( $k ) use ( $src ) {
			return isset( $src[ $k ] ) && is_string( $src[ $k ] ) ? wp_unslash( $src[ $k ] ) : '';
		};

		$client = CMB_DB::get_client( $get( 'client_id' ) );
		if ( ! $client ) {
			self::error_page( 'Unbekannter Client', 'Diese Verbindung ist nicht (mehr) registriert. Bitte die Verbindung in Claude neu anlegen und in WordPress vorher "Kopplung starten" klicken.' );
		}

		$redirect_uri = $get( 'redirect_uri' );
		$match        = false;
		foreach ( $client->redirect_uris as $registered ) {
			if ( CMB_Security::redirect_uri_matches( $redirect_uri, $registered ) && CMB_Security::redirect_uri_allowed( $redirect_uri ) ) {
				$match = true;
				break;
			}
		}
		if ( ! $match ) {
			CMB_Security::audit( 'authorize_bad_redirect', array(), 0, $client->client_id );
			self::error_page( 'Ungültige Weiterleitung', 'Die angefragte redirect_uri ist für diesen Client nicht registriert.' );
		}

		$state = $get( 'state' );
		if ( strlen( $state ) > 1024 ) {
			self::error_page( 'Ungültige Anfrage', 'state ist zu lang.' );
		}

		$fail = function ( $error, $desc ) use ( $redirect_uri, $state ) {
			$p = array( 'error' => $error, 'error_description' => $desc );
			if ( '' !== $state ) {
				$p['state'] = $state;
			}
			self::redirect_with( $redirect_uri, $p );
		};

		if ( 'code' !== $get( 'response_type' ) ) {
			$fail( 'unsupported_response_type', 'Only response_type=code is supported.' );
		}
		$challenge = $get( 'code_challenge' );
		if ( 'S256' !== $get( 'code_challenge_method' ) || ! preg_match( '/^[A-Za-z0-9_-]{43}$/', $challenge ) ) {
			$fail( 'invalid_request', 'PKCE with code_challenge_method=S256 is required.' );
		}
		$resource = $get( 'resource' );
		if ( '' !== $resource && untrailingslashit( $resource ) !== CMB_Settings::mcp_url() ) {
			$fail( 'invalid_target', 'Unknown resource.' );
		}

		$scope = self::normalize_scope( $get( 'scope' ) );
		if ( null === $scope ) {
			$fail( 'invalid_scope', 'Unsupported scope.' );
		}

		return array(
			'client'         => $client,
			'redirect_uri'   => $redirect_uri,
			'state'          => $state,
			'code_challenge' => $challenge,
			'scope'          => $scope,
			'fail'           => $fail,
		);
	}

	/** null = ungültig. Leerer Scope ergibt die Standard-Scopes. */
	public static function normalize_scope( $scope ) {
		$scope = trim( (string) $scope );
		if ( '' === $scope ) {
			return implode( ' ', self::available_scopes() );
		}
		$parts = array_unique( preg_split( '/\s+/', $scope ) );
		if ( array_diff( $parts, self::SCOPES ) ) {
			return null;
		}
		// wp:power wird nur vergeben, wenn der Expertenmodus aktiv ist (sonst stillschweigend weggelassen).
		$ordered = array_values( array_intersect( self::available_scopes(), $parts ) );
		return $ordered ? implode( ' ', $ordered ) : null;
	}

	public static function authorize() {
		if ( ! CMB_Settings::is_enabled() ) {
			self::error_page( 'Deaktiviert', 'Die Claude MCP Bridge ist auf dieser Website deaktiviert.', 403 );
		}
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';
		$src    = 'POST' === $method ? $_POST : $_GET; // phpcs:ignore WordPress.Security.NonceVerification -- Nonce wird unten geprüft.
		$p      = self::read_authorize_params( $src );

		if ( ! is_user_logged_in() ) {
			auth_redirect(); // leitet zum Login und danach hierher zurück, beendet die Ausführung
			exit;
		}
		$user = wp_get_current_user();
		if ( ! user_can( $user, CMB_Settings::REQUIRED_CAP ) ) {
			CMB_Security::audit( 'authorize_no_cap', array(), $user->ID, $p['client']->client_id );
			self::error_page( 'Keine Berechtigung', 'Nur Administratoren können Claude mit dieser Website verbinden.', 403 );
		}
		if ( ! CMB_Security::rate_limit( 'authz|' . $user->ID, 20, HOUR_IN_SECONDS ) ) {
			self::error_page( 'Zu viele Versuche', 'Bitte später erneut versuchen.', 429 );
		}

		if ( 'POST' === $method ) {
			$nonce = isset( $_POST['_cmb_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_cmb_nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'cmb_authorize|' . $p['client']->client_id . '|' . $p['code_challenge'] ) ) {
				self::error_page( 'Sitzung abgelaufen', 'Die Sicherheitsprüfung ist fehlgeschlagen. Bitte den Verbindungsvorgang in Claude neu starten.', 403 );
			}
			$decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
			$state    = $p['state'];
			$out      = array();
			if ( 'allow' !== $decision ) {
				CMB_Security::audit( 'authorize_denied', array(), $user->ID, $p['client']->client_id );
				$out = array( 'error' => 'access_denied', 'error_description' => 'The site owner denied access.' );
			} else {
				$family = bin2hex( random_bytes( 16 ) );
				$code   = CMB_DB::issue_token(
					'code',
					$p['client']->client_id,
					$user->ID,
					$p['scope'],
					$family,
					CMB_Settings::CODE_TTL,
					array(
						'redirect_uri'   => $p['redirect_uri'],
						'code_challenge' => $p['code_challenge'],
					)
				);
				CMB_Security::audit( 'authorize_granted', array( 'scope' => $p['scope'] ), $user->ID, $p['client']->client_id );
				$out = array( 'code' => $code );
			}
			if ( '' !== $state ) {
				$out['state'] = $state;
			}
			self::redirect_with( $p['redirect_uri'], $out );
		}

		self::render_consent( $p, $user );
		exit;
	}

	private static function render_consent( array $p, WP_User $user ) {
		CMB_Security::no_store_headers( true );
		header( 'Content-Type: text/html; charset=utf-8' );
		$client   = $p['client'];
		$host     = wp_parse_url( $p['redirect_uri'], PHP_URL_HOST );
		$loopback = in_array( $host, array( 'localhost', '127.0.0.1' ), true );
		$scopes   = explode( ' ', $p['scope'] );
		$labels   = array(
			'wp:read'        => 'Inhalte, Medien, Kommentare und Website-Infos <strong>lesen</strong>',
			'wp:propose'     => '<strong>Änderungen vorschlagen</strong> – ausgeführt wird erst, nachdem du jede einzelne hier in WordPress freigegeben hast',
			'wp:power'       => '<strong class="danger">Expertenmodus: Dateien, Datenbank, PHP, WP-CLI, Plugins</strong> – Lesen sofort, jede Änderung nur nach deiner Freigabe mit Passwort',
			'offline_access' => 'Verbunden bleiben (bis zu 30 Tage ohne erneute Anmeldung, jederzeit trennbar)',
		);

		self::page_open( 'Claude mit WordPress verbinden' );
		echo '<p>Angemeldet als <strong>' . esc_html( $user->user_login ) . '</strong> auf <strong>' . esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ) . '</strong>.</p>';
		echo '<div class="box"><p class="k">Anwendung (Name vom Client selbst angegeben)</p><p>' . esc_html( $client->name ) . '</p>';
		echo '<p class="k">Weiterleitung nach Zustimmung an</p><p class="host">' . esc_html( (string) $host ) . '</p></div>';
		if ( $loopback ) {
			echo '<p class="warn">Achtung: Die Weiterleitung geht an ein Programm auf einem lokalen Computer. Nur zustimmen, wenn du gerade selbst Claude Code verbindest.</p>';
		} elseif ( 'claude.ai' !== $host ) {
			echo '<p class="warn">Achtung: Die Weiterleitung geht nicht an claude.ai.</p>';
		}
		echo '<p>Die Anwendung erhält folgende Rechte:</p><ul>';
		foreach ( $scopes as $s ) {
			echo '<li>' . wp_kses( $labels[ $s ] ?? esc_html( $s ), array( 'strong' => array( 'class' => array() ) ) ) . '</li>';
		}
		echo '</ul>';
		if ( in_array( 'wp:power', $scopes, true ) ) {
			echo '<p class="warn">Mit dem Expertenmodus kann Claude die komplette Website lesen (außer Zugangsdaten) und weitreichende Änderungen vorschlagen. Prüfe jeden Vorschlag sorgfältig, bevor du ihn freigibst.</p>';
		} else {
			echo '<p class="small">Die Anwendung kann nichts direkt ändern, keine Dateien schreiben, keinen Code ausführen und keine Plugins installieren.</p>';
		}

		echo '<form method="post" action="' . esc_url( CMB_Settings::authorize_url() ) . '">';
		foreach ( array( 'client_id', 'redirect_uri', 'state', 'code_challenge', 'code_challenge_method', 'response_type', 'scope', 'resource' ) as $k ) {
			$v = isset( $_GET[ $k ] ) && is_string( $_GET[ $k ] ) ? wp_unslash( $_GET[ $k ] ) : ''; // phpcs:ignore
			if ( 'scope' === $k ) {
				$v = $p['scope'];
			}
			echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		echo '<input type="hidden" name="_cmb_nonce" value="' . esc_attr( wp_create_nonce( 'cmb_authorize|' . $client->client_id . '|' . $p['code_challenge'] ) ) . '">';
		echo '<div class="btns"><button class="deny" name="decision" value="deny">Ablehnen</button><button class="allow" name="decision" value="allow">Erlauben</button></div></form>';
		self::page_close();
	}

	public static function page_open( $title ) {
		echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . esc_html( $title ) . '</title><style>
		:root{color-scheme:light dark;--bg:#f6f6f4;--card:#fff;--fg:#1d1d1b;--mut:#6b6b66;--acc:#c15f3c;--bd:#e3e2dc;--warn:#9a3412;--warnbg:#fff4ed}
		@media (prefers-color-scheme:dark){:root{--bg:#191917;--card:#242422;--fg:#ecebe6;--mut:#a3a29b;--bd:#3a3a36;--warn:#fdba74;--warnbg:#3b2416}}
		body{margin:0;background:var(--bg);color:var(--fg);font:16px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
		main{max-width:460px;margin:0 auto;padding:28px 16px}.card{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:22px}
		h1{font-size:20px;margin:0 0 14px}.box{border:1px solid var(--bd);border-radius:10px;padding:10px 14px;margin:14px 0}.box p{margin:2px 0 8px}
		.k{font-size:12px;color:var(--mut);text-transform:uppercase;letter-spacing:.04em;margin-bottom:0!important}.host{font-family:ui-monospace,monospace;font-weight:600}
		.warn{background:var(--warnbg);color:var(--warn);padding:10px 12px;border-radius:8px}.danger{color:#b42318}.small{font-size:13px;color:var(--mut)}ul{padding-left:20px}li{margin:6px 0}
		.btns{display:flex;gap:10px;margin-top:18px}button{flex:1;font-size:16px;padding:13px;border-radius:10px;border:1px solid var(--bd);background:transparent;color:var(--fg);cursor:pointer}
		button.allow{background:var(--acc);border-color:var(--acc);color:#fff;font-weight:600}
		</style></head><body><main><div class="card"><h1>' . esc_html( $title ) . '</h1>';
	}

	public static function page_close() {
		echo '</div></main></body></html>';
	}

	/* -------------------------------------------------------------- token */

	/** Liefert [client, error_response]. */
	private static function authenticate_client( WP_REST_Request $req, array $params ) {
		$client_id = isset( $params['client_id'] ) && is_string( $params['client_id'] ) ? $params['client_id'] : '';
		$secret    = isset( $params['client_secret'] ) && is_string( $params['client_secret'] ) ? $params['client_secret'] : '';

		$auth = (string) $req->get_header( 'authorization' );
		if ( 0 === stripos( $auth, 'basic ' ) ) {
			$dec = base64_decode( substr( $auth, 6 ), true );
			if ( false !== $dec && false !== strpos( $dec, ':' ) ) {
				list( $bid, $bsec ) = explode( ':', $dec, 2 );
				$client_id          = rawurldecode( $bid );
				$secret             = rawurldecode( $bsec );
			}
		}

		$client = CMB_DB::get_client( $client_id );
		if ( ! $client ) {
			return array( null, self::oauth_error( 'invalid_client', 'Unknown client.', 401 ) );
		}
		if ( $client->secret_hash ) {
			if ( '' === $secret || ! hash_equals( $client->secret_hash, CMB_Security::hash( $secret ) ) ) {
				CMB_Security::audit( 'token_bad_secret', array(), 0, $client->client_id );
				return array( null, self::oauth_error( 'invalid_client', 'Client authentication failed.', 401 ) );
			}
		}
		return array( $client, null );
	}

	private static function token_params( WP_REST_Request $req ) {
		$params = $req->get_body_params();
		if ( ! $params ) {
			parse_str( (string) $req->get_body(), $params );
		}
		return is_array( $params ) ? $params : array();
	}

	public static function rest_token( WP_REST_Request $req ) {
		if ( ! CMB_Settings::is_enabled() || ! CMB_Security::ip_allowed() ) {
			return self::oauth_error( 'access_denied', 'Not available.', 403 );
		}
		if ( ! CMB_Security::rate_limit( 'tok|' . CMB_Security::client_ip(), 60, 60 ) ) {
			return self::oauth_error( 'slow_down', 'Too many requests.', 429 );
		}
		$params                 = self::token_params( $req );
		list( $client, $error ) = self::authenticate_client( $req, $params );
		if ( $error ) {
			return $error;
		}
		$grant = isset( $params['grant_type'] ) ? (string) $params['grant_type'] : '';

		if ( 'authorization_code' === $grant ) {
			return self::grant_code( $client, $params );
		}
		if ( 'refresh_token' === $grant ) {
			return self::grant_refresh( $client, $params );
		}
		return self::oauth_error( 'unsupported_grant_type', 'Unsupported grant_type.' );
	}

	private static function grant_code( $client, array $params ) {
		$row = CMB_DB::find_token( $params['code'] ?? '', 'code' );
		if ( ! $row || $row->client_id !== $client->client_id ) {
			return self::oauth_error( 'invalid_grant', 'Invalid authorization code.' );
		}
		if ( (int) $row->used || (int) $row->revoked ) {
			// Wiederverwendung eines Codes: alles aus diesem Vorgang widerrufen (RFC 6749 4.1.2).
			CMB_DB::revoke_family( $row->family );
			CMB_Security::audit( 'code_reuse_detected', array(), (int) $row->user_id, $client->client_id );
			return self::oauth_error( 'invalid_grant', 'Authorization code already used.' );
		}
		if ( (int) $row->expires_at < time() ) {
			return self::oauth_error( 'invalid_grant', 'Authorization code expired.' );
		}
		$redirect = isset( $params['redirect_uri'] ) ? (string) $params['redirect_uri'] : '';
		if ( $redirect !== ( $row->data['redirect_uri'] ?? null ) ) {
			return self::oauth_error( 'invalid_grant', 'redirect_uri mismatch.' );
		}
		if ( ! CMB_Security::pkce_valid( $params['code_verifier'] ?? '', $row->data['code_challenge'] ?? '' ) ) {
			CMB_Security::audit( 'pkce_failed', array(), (int) $row->user_id, $client->client_id );
			return self::oauth_error( 'invalid_grant', 'PKCE verification failed.' );
		}
		if ( isset( $params['resource'] ) && '' !== $params['resource'] && untrailingslashit( (string) $params['resource'] ) !== CMB_Settings::mcp_url() ) {
			return self::oauth_error( 'invalid_target', 'Unknown resource.' );
		}
		if ( ! CMB_DB::consume_token( (int) $row->id ) ) {
			CMB_DB::revoke_family( $row->family );
			return self::oauth_error( 'invalid_grant', 'Authorization code already used.' );
		}
		if ( ! user_can( (int) $row->user_id, CMB_Settings::REQUIRED_CAP ) ) {
			return self::oauth_error( 'invalid_grant', 'User is no longer authorized.' );
		}
		CMB_DB::touch_client( $client->client_id );
		CMB_Security::audit( 'token_issued', array( 'grant' => 'authorization_code' ), (int) $row->user_id, $client->client_id );
		return self::issue_pair( $client->client_id, (int) $row->user_id, $row->scope, $row->family );
	}

	private static function grant_refresh( $client, array $params ) {
		$row = CMB_DB::find_token( $params['refresh_token'] ?? '', 'refresh' );
		if ( ! $row || $row->client_id !== $client->client_id ) {
			return self::oauth_error( 'invalid_grant', 'Invalid refresh token.' );
		}
		if ( (int) $row->used || (int) $row->revoked ) {
			// Refresh-Token-Wiederverwendung = mögliches Token-Leck: ganze Kette sperren.
			CMB_DB::revoke_family( $row->family );
			CMB_Security::audit( 'refresh_reuse_detected', array(), (int) $row->user_id, $client->client_id );
			return self::oauth_error( 'invalid_grant', 'Refresh token is no longer valid.' );
		}
		if ( (int) $row->expires_at < time() ) {
			return self::oauth_error( 'invalid_grant', 'Refresh token expired.' );
		}
		$scope = $row->scope;
		if ( isset( $params['scope'] ) && '' !== trim( (string) $params['scope'] ) ) {
			$req_scope = self::normalize_scope( $params['scope'] );
			if ( null === $req_scope || array_diff( explode( ' ', $req_scope ), explode( ' ', $row->scope ) ) ) {
				return self::oauth_error( 'invalid_scope', 'Scope exceeds original grant.' );
			}
			$scope = $req_scope;
		}
		if ( ! CMB_DB::consume_token( (int) $row->id ) ) {
			CMB_DB::revoke_family( $row->family );
			return self::oauth_error( 'invalid_grant', 'Refresh token is no longer valid.' );
		}
		if ( ! user_can( (int) $row->user_id, CMB_Settings::REQUIRED_CAP ) ) {
			CMB_DB::revoke_family( $row->family );
			return self::oauth_error( 'invalid_grant', 'User is no longer authorized.' );
		}
		CMB_DB::touch_client( $client->client_id );
		return self::issue_pair( $client->client_id, (int) $row->user_id, $scope, $row->family );
	}

	private static function issue_pair( $client_id, $user_id, $scope, $family ) {
		$resource = CMB_Settings::mcp_url();
		$access   = CMB_DB::issue_token( 'access', $client_id, $user_id, $scope, $family, CMB_Settings::ACCESS_TTL, array( 'resource' => $resource ) );
		$refresh  = CMB_DB::issue_token( 'refresh', $client_id, $user_id, $scope, $family, CMB_Settings::REFRESH_TTL, array( 'resource' => $resource ) );
		return self::json(
			array(
				'access_token'  => $access,
				'token_type'    => 'Bearer',
				'expires_in'    => CMB_Settings::ACCESS_TTL,
				'refresh_token' => $refresh,
				'scope'         => $scope,
			)
		);
	}

	/* ------------------------------------------------------------- revoke */

	public static function rest_revoke( WP_REST_Request $req ) {
		if ( ! CMB_Settings::is_enabled() ) {
			return self::json( new stdClass() );
		}
		if ( ! CMB_Security::rate_limit( 'rev|' . CMB_Security::client_ip(), 30, 60 ) ) {
			return self::oauth_error( 'slow_down', 'Too many requests.', 429 );
		}
		$params                 = self::token_params( $req );
		list( $client, $error ) = self::authenticate_client( $req, $params );
		if ( $error ) {
			return $error;
		}
		$token = $params['token'] ?? '';
		foreach ( array( 'refresh', 'access' ) as $type ) {
			$row = CMB_DB::find_token( $token, $type );
			if ( $row && $row->client_id === $client->client_id ) {
				CMB_DB::revoke_family( $row->family );
				CMB_Security::audit( 'token_revoked_by_client', array(), (int) $row->user_id, $client->client_id );
				break;
			}
		}
		return self::json( new stdClass() ); // RFC 7009: immer 200
	}
}
