<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin-Oberfläche: Einstellungen, Kopplung, Freigaben, Verbindungen, Protokoll.
 * Alle Aktionen laufen als POST über admin-post.php mit Nonce und Capability-Prüfung.
 */
final class CMB_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CMB_FILE ), array( __CLASS__, 'action_links' ) );
		foreach ( array( 'save_settings', 'save_power', 'start_pairing', 'stop_pairing', 'decide', 'decide_many', 'revert', 'revoke_client', 'revoke_all', 'create_client', 'totp_start', 'totp_confirm', 'totp_policy' ) as $a ) {
			add_action( 'admin_post_cmb_' . $a, array( __CLASS__, 'post_' . $a ) );
		}
	}

	private static function cap() {
		return CMB_Settings::REQUIRED_CAP;
	}

	public static function menu() {
		$count = 0;
		if ( current_user_can( self::cap() ) ) {
			$count = count( CMB_Changes::list_for_user( get_current_user_id(), 'pending' ) );
		}
		$badge = $count ? ' <span class="awaiting-mod">' . (int) $count . '</span>' : '';
		add_menu_page( 'Claude MCP', 'Claude MCP' . $badge, self::cap(), 'cmb', array( __CLASS__, 'page_settings' ), 'dashicons-shield', 81 );
		add_submenu_page( 'cmb', 'Einstellungen', 'Einstellungen', self::cap(), 'cmb', array( __CLASS__, 'page_settings' ) );
		add_submenu_page( 'cmb', 'Freigaben', 'Freigaben' . $badge, self::cap(), 'cmb-approvals', array( __CLASS__, 'page_approvals' ) );
		add_submenu_page( 'cmb', 'Verbindungen', 'Verbindungen', self::cap(), 'cmb-clients', array( __CLASS__, 'page_clients' ) );
		add_submenu_page( 'cmb', 'Protokoll', 'Protokoll', self::cap(), 'cmb-log', array( __CLASS__, 'page_log' ) );
		add_submenu_page( 'cmb', 'Handbuch', 'Handbuch', self::cap(), 'cmb-docs', array( __CLASS__, 'page_docs' ) );
		add_submenu_page( 'cmb', 'Release Notes', 'Release Notes', self::cap(), 'cmb-release-notes', array( __CLASS__, 'page_release_notes' ) );
	}

	public static function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=cmb' ) ) . '">Einstellungen</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=cmb-docs' ) ) . '">Handbuch</a>'
		);
		return $links;
	}

	/* ------------------------------------------------------------------ docs */

	private static function render_doc( $title, $file ) {
		if ( ! current_user_can( self::cap() ) ) {
			return;
		}
		self::styles();
		echo '<style>.cmb-doc{max-width:860px;font-size:14px;line-height:1.6}.cmb-doc h2{margin-top:2em;padding-top:.6em;border-top:1px solid #dcdcde}.cmb-doc h2:first-of-type{border-top:0}.cmb-doc code{word-break:break-word}.cmb-doc table{margin:10px 0 16px}.cmb-doc dt{font-weight:600;margin-top:10px}.cmb-doc dd{margin-left:16px}.cmb-toc{background:#f6f7f7;border-radius:8px;padding:12px 18px;margin:12px 0}.cmb-toc ol{margin:6px 0 0 18px}.cmb-doc h2 small{font-weight:400;color:#646970}</style>';
		echo '<div class="wrap cmb-wrap"><h1>' . esc_html( $title ) . ' <small style="font-size:13px;color:#646970">Claude MCP Bridge ' . esc_html( CMB_VERSION ) . '</small></h1>';
		$path = CMB_DIR . 'docs/' . $file;
		$html = is_readable( $path ) ? (string) file_get_contents( $path ) : '<p>Dokument nicht gefunden.</p>';
		$allowed = wp_kses_allowed_html( 'post' );
		$allowed['nav'] = array( 'class' => true );
		foreach ( array( 'h2', 'h3', 'table' ) as $t ) {
			$allowed[ $t ]['id']    = true;
			$allowed[ $t ]['class'] = true;
		}
		echo '<div class="cmb-card cmb-doc">' . wp_kses( $html, $allowed ) . '</div></div>';
	}

	public static function page_docs() {
		self::render_doc( 'Handbuch', 'handbuch.html' );
	}

	public static function page_release_notes() {
		self::render_doc( 'Release Notes', 'release-notes.html' );
	}

	/* ---------------------------------------------------------------- helpers */

	private static function guard( $action ) {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( 'Keine Berechtigung.', 403 );
		}
		check_admin_referer( $action );
	}

	private static function back( $page, array $args = array() ) {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=' . $page ) ) );
		exit;
	}

	private static function form_open( $action, $nonce_action = null ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		wp_nonce_field( $nonce_action ?? $action );
	}

	/** Eingabefeld für den 6-stelligen Code aus Aegis o. ä. */
	private static function code_field( $label = 'Code aus der Authenticator-App (Aegis)' ) {
		echo '<label class="cmb-pw">' . esc_html( $label ) . '<input type="text" name="cmb_code" inputmode="numeric" autocomplete="one-time-code" maxlength="9" pattern="[0-9]{6}|[0-9a-fA-F]{4}-[0-9a-fA-F]{4}" placeholder="123456"></label>';
	}

	private static function posted_code() {
		return isset( $_POST['cmb_code'] ) ? sanitize_text_field( wp_unslash( $_POST['cmb_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- Nonce wird im Handler geprüft.
	}

	private static function posted_password() {
		return isset( $_POST['cmb_password'] ) ? (string) wp_unslash( $_POST['cmb_password'] ) : ''; // phpcs:ignore -- wird nur geprüft, nie gespeichert.
	}

	private static function notice() {
		$msg  = isset( $_GET['cmb_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['cmb_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$type = isset( $_GET['cmb_type'] ) && 'error' === $_GET['cmb_type'] ? 'error' : 'success'; // phpcs:ignore
		if ( $msg ) {
			echo '<div class="notice notice-' . esc_attr( $type ) . '"><p>' . esc_html( $msg ) . '</p></div>';
		}
	}

	private static function styles() {
		echo '<style>
		.cmb-wrap{max-width:900px}.cmb-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 18px;margin:16px 0}
		.cmb-card h2{margin-top:0}.cmb-url{display:block;font-family:monospace;background:#f6f7f7;padding:10px;border-radius:6px;word-break:break-all;user-select:all}
		.cmb-ok{color:#007017}.cmb-bad{color:#b32d2e}.cmb-pill{display:inline-block;padding:2px 8px;border-radius:10px;background:#f0f0f1;font-size:12px}
		.cmb-pill.pending{background:#fcf0e3;color:#8a4b0f}.cmb-pill.approved{background:#e7f6ea;color:#007017}.cmb-pill.rejected,.cmb-pill.failed{background:#fbeaea;color:#b32d2e}
		.cmb-pre{white-space:pre-wrap;word-break:break-word;background:#f6f7f7;padding:10px;border-radius:6px;max-height:420px;overflow:auto;font-size:13px}
		.cmb-scroll{overflow-x:auto}.cmb-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}.cmb-actions .button{min-height:44px;padding:6px 22px;font-size:15px}
		.cmb-kv th{text-align:left;padding:6px 12px 6px 0;vertical-align:top;white-space:nowrap}.cmb-kv td{padding:6px 0;word-break:break-word}
		table.diff{width:100%;table-layout:fixed}table.diff td{word-break:break-word;font-size:13px}
		.cmb-list li{border-bottom:1px solid #f0f0f1;padding:10px 0}.cmb-list li:last-child{border:0}
.cmb-risk{padding:10px 12px;border-radius:6px;margin:10px 0;font-weight:600}.cmb-risk.low{background:#f0f6fc;color:#0a4b78}.cmb-risk.high{background:#fcf0e3;color:#8a4b0f}.cmb-risk.critical{background:#fbeaea;color:#b32d2e}
		.cmb-pw{display:block;margin-top:14px}.cmb-pw input{width:100%;max-width:340px;min-height:40px;font-size:16px}
		.cmb-code{white-space:pre;overflow:auto;background:#1d2327;color:#f0f0f1;padding:12px;border-radius:6px;max-height:520px;font:13px/1.45 ui-monospace,Menlo,Consolas,monospace}
		.cmb-batch li{display:flex;gap:10px;align-items:flex-start}.cmb-batch input[type=checkbox]{margin-top:4px;min-width:20px;min-height:20px}.cmb-batch .cmb-pill{margin-right:4px}
		.cmb-pill.risk-low{background:#f0f6fc;color:#0a4b78}.cmb-pill.risk-high{background:#fcf0e3;color:#8a4b0f}.cmb-pill.risk-critical{background:#fbeaea;color:#b32d2e}
		.cmb-results li.ok::before{content:"✔ ";color:#007017}.cmb-results li.bad::before{content:"✘ ";color:#b32d2e}
		@media (max-width:782px){.cmb-actions .button{flex:1;text-align:center}}
		</style>';
	}

	private static function status_label( $s ) {
		$map = array( 'pending' => 'offen', 'approved' => 'ausgeführt', 'rejected' => 'abgelehnt', 'failed' => 'fehlgeschlagen', 'expired' => 'abgelaufen', 'cancelled' => 'zurückgezogen', 'running' => 'läuft' );
		return '<span class="cmb-pill ' . esc_attr( $s ) . '">' . esc_html( $map[ $s ] ?? $s ) . '</span>';
	}

	/* ------------------------------------------------------------- settings */

	public static function page_settings() {
		if ( ! current_user_can( self::cap() ) ) {
			return;
		}
		$s = CMB_Settings::all();
		self::styles();
		echo '<div class="wrap cmb-wrap"><h1>Claude MCP Bridge <small style="font-size:13px;color:#646970">' . esc_html( CMB_VERSION ) . ' · <a href="' . esc_url( admin_url( 'admin.php?page=cmb-docs' ) ) . '">Handbuch</a> · <a href="' . esc_url( admin_url( 'admin.php?page=cmb-release-notes' ) ) . '">Release Notes</a></small></h1>';
		self::notice();

		$new = get_transient( 'cmb_new_client_' . get_current_user_id() );
		if ( $new ) {
			delete_transient( 'cmb_new_client_' . get_current_user_id() );
			echo '<div class="cmb-card" style="border-color:#c15f3c"><h2>Neuer OAuth-Client – nur jetzt sichtbar</h2><p>Trage diese Werte in Claude unter „Erweiterte Einstellungen“ des Connectors ein. Das Secret wird nicht erneut angezeigt.</p>';
			echo '<p><strong>Client ID</strong><code class="cmb-url">' . esc_html( $new['id'] ) . '</code></p>';
			echo '<p><strong>Client Secret</strong><code class="cmb-url">' . esc_html( $new['secret'] ) . '</code></p></div>';
		}

		echo '<div class="cmb-card"><h2>Status</h2><table class="cmb-kv">';
		$checks = array(
			'Bridge aktiv'          => $s['enabled'],
			'HTTPS'                 => CMB_Settings::https_ok(),
			'Permalinks (nicht „Einfach“)' => CMB_Settings::permalinks_ok(),
		);
		foreach ( $checks as $label => $ok ) {
			echo '<tr><th>' . esc_html( $label ) . '</th><td class="' . ( $ok ? 'cmb-ok' : 'cmb-bad' ) . '">' . ( $ok ? '✔ ja' : '✘ nein' ) . '</td></tr>';
		}
		echo '</table>';
		if ( ! CMB_Settings::https_ok() || ! CMB_Settings::permalinks_ok() ) {
			echo '<p class="cmb-bad">Die Bridge lässt sich erst aktivieren, wenn HTTPS und sprechende Permalinks eingerichtet sind.</p>';
		}
		echo '<p><strong>Connector-URL für Claude</strong><code class="cmb-url">' . esc_html( CMB_Settings::mcp_url() ) . '</code></p></div>';

		// Kopplung.
		echo '<div class="cmb-card"><h2>Mit Claude verbinden</h2>';
		if ( ! $s['enabled'] ) {
			echo '<p>Aktiviere zuerst die Bridge (unten).</p>';
		} elseif ( CMB_Settings::pairing_open() ) {
			$left = (int) $s['pairing_until'] - time();
			echo '<p class="cmb-ok"><strong>Kopplungsfenster offen</strong> – noch ca. ' . (int) ceil( $left / 60 ) . ' Min. Es schließt automatisch nach der ersten Registrierung.</p>';
			self::form_open( 'cmb_stop_pairing' );
			submit_button( 'Kopplung abbrechen', 'secondary', 'submit', false );
			echo '</form>';
		} else {
			echo '<ol><li>„Kopplung starten“ klicken (öffnet die Registrierung für 15 Minuten).</li><li>In Claude: <em>Einstellungen → Connectors → Benutzerdefinierten Connector hinzufügen</em>, die Connector-URL oben einfügen.</li><li>Auf „Verbinden“ tippen, bei WordPress anmelden und auf der Zustimmungsseite „Erlauben“ wählen.</li></ol>';
			self::form_open( 'cmb_start_pairing' );
			submit_button( 'Kopplung starten (15 Min.)', 'primary', 'submit', false );
			echo '</form>';
		}
		echo '<details style="margin-top:14px"><summary>Alternative: eigenen OAuth-Client anlegen (ohne Kopplungsfenster)</summary>';
		self::form_open( 'cmb_create_client' );
		echo '<p><label>Name <input type="text" name="name" maxlength="100" value="Claude" class="regular-text"></label></p>';
		submit_button( 'Client mit Secret erzeugen', 'secondary', 'submit', false );
		echo '</form></details></div>';

		// Einstellungen.
		echo '<div class="cmb-card"><h2>Einstellungen</h2>';
		self::form_open( 'cmb_save_settings' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th>Bridge</th><td><label><input type="checkbox" name="enabled" value="1" ' . checked( $s['enabled'], true, false ) . '> Aktiv</label><p class="description">Aus = alle MCP- und OAuth-Endpunkte sind gesperrt.</p></td></tr>';
		echo '<tr><th>IP-Allowlist</th><td><label><input type="checkbox" name="ip_allowlist_on" value="1" ' . checked( $s['ip_allowlist_on'], true, false ) . '> Nur diese Netze dürfen MCP-, Token- und Registrierungs-Endpunkte aufrufen</label>';
		echo '<p><input type="text" name="ip_allowlist" class="regular-text" value="' . esc_attr( $s['ip_allowlist'] ) . '"></p><p class="description">Standard: Anthropic-Egress ' . esc_html( CMB_Settings::ANTHROPIC_EGRESS ) . '. Nicht aktivieren, wenn die Seite hinter einem CDN/Proxy liegt (dann sieht WordPress die Proxy-IP).</p></td></tr>';
		echo '<tr><th>Claude Code</th><td><label><input type="checkbox" name="allow_loopback" value="1" ' . checked( $s['allow_loopback'], true, false ) . '> Loopback-Weiterleitungen (http://localhost) erlauben</label><p class="description">Nur nötig für Claude Code im Terminal. Für die Claude-App (Handy, Web, Desktop) nicht erforderlich.</p></td></tr>';
		echo '<tr><th>Kundendaten</th><td><label><input type="checkbox" name="redact_pii" value="1" ' . checked( $s['redact_pii'], true, false ) . '> Kundendaten schwärzen (empfohlen)</label><p class="description">Namen, E-Mail-Adressen, Telefonnummern, Anschriften und Anmerkungen von Kunden kommen in SQL-Ergebnissen nur als „[geschwärzt]“ bei Claude an; Belege (PDF/CSV) in uploads/msb-belege werden nicht ausgeliefert. Nur kurzzeitig ausschalten, wenn Claude einen konkreten Kundendatensatz prüfen muss.</p></td></tr>';
		echo '<tr><th>Zusätzliche Origins</th><td><input type="text" name="extra_origins" class="regular-text" value="' . esc_attr( $s['extra_origins'] ) . '"><p class="description">Nur für Browser-basierte MCP-Clients. Normalerweise leer lassen.</p></td></tr>';
		echo '</table>';
		submit_button( 'Speichern' );
		echo '</form></div>';
		self::render_totp_settings( $s );
		self::render_power_settings( $s );
		echo '</div>';
	}

	private static function render_power_settings( array $s ) {
		$help = array(
			'files'   => 'Dateien lesen/durchsuchen sofort; anlegen, ändern, löschen nur nach Freigabe (mit Backup und Rückgängig).',
			'php'     => 'PHP-Code in WordPress ausführen – nur nach Freigabe. Kritisch.',
			'sql'     => 'Lesende SQL-Abfragen sofort (Passwort-Hashes und Geheimnisse geschwärzt); Änderungen nur nach Freigabe.',
			'wpcli'   => 'Lesende WP-CLI-Befehle sofort; alle anderen nur nach Freigabe. Braucht WP-CLI auf dem Server.',
			'plugins' => 'Plugins (nur von wordpress.org) installieren, aktivieren, aktualisieren, löschen – nur nach Freigabe.',
		);
		echo '<div class="cmb-card" style="border-color:#d63638"><h2>Expertenmodus</h2>';
		if ( CMB_Power::kill_switch() ) {
			echo '<p class="cmb-bad">Durch <code>CMB_DISABLE_POWER_TOOLS</code> in wp-config.php komplett gesperrt.</p></div>';
			return;
		}
		echo '<p>Jede ändernde Aktion wird zum Vorschlag und muss hier mit deinem <strong>WordPress-Passwort</strong> bzw. – wenn eingestellt – deinem <strong>Zwei-Faktor-Code</strong> freigegeben werden. Zugangsdaten (wp-config.php, .env, Schlüssel, Backups), der WordPress-Core und dieses Plugin selbst sind immer gesperrt.</p>';
		echo '<p><strong>Wichtig:</strong> Nach dem Einschalten den Connector in Claude einmal trennen und neu verbinden, damit Claude die neue Berechtigung erhält.</p>';
		self::form_open( 'cmb_save_power' );
		echo '<table class="form-table" role="presentation">';
		foreach ( CMB_Power::CATEGORIES as $cat => $label ) {
			$why = CMB_Power::blocked_reason( $cat );
			echo '<tr><th>' . esc_html( $label ) . '</th><td><label><input type="checkbox" name="power_' . esc_attr( $cat ) . '" value="1" ' . checked( $s[ 'power_' . $cat ], true, false ) . '> Aktiv</label>';
			if ( $why && ! ( 'wpcli' === $cat && false !== strpos( $why, 'Pfad' ) ) ) {
				echo ' <span class="cmb-bad">– nicht verfügbar: ' . esc_html( $why ) . '</span>';
			}
			echo '<p class="description">' . esc_html( $help[ $cat ] ) . '</p>';
			if ( 'wpcli' === $cat ) {
				echo '<p><label>Pfad zu WP-CLI <input type="text" name="wp_cli_path" class="regular-text" placeholder="/usr/local/bin/wp" value="' . esc_attr( $s['wp_cli_path'] ) . '"></label></p>';
				echo '<p><label>PHP-CLI (nur bei .phar ohne Shebang) <input type="text" name="php_cli_path" class="regular-text" placeholder="/usr/bin/php" value="' . esc_attr( $s['php_cli_path'] ) . '"></label></p>';
			}
			echo '</td></tr>';
		}
		echo '<tr><th>Passwort</th><td><input type="password" name="cmb_password" autocomplete="current-password" class="regular-text"><p class="description">Zum Einschalten von Funktionen oder Ändern der Pfade erforderlich.</p></td></tr>';
		if ( CMB_TOTP::is_enabled( get_current_user_id() ) ) {
			echo '<tr><th>Zwei-Faktor-Code</th><td><input type="text" name="cmb_code" inputmode="numeric" autocomplete="one-time-code" class="regular-text" maxlength="9"></td></tr>';
		}
		echo '</table>';
		submit_button( 'Expertenmodus speichern' );
		echo '</form></div>';
	}

	public static function post_save_power() {
		self::guard( 'cmb_save_power' );
		$cur     = CMB_Settings::all();
		$new     = array();
		$raising = false;
		foreach ( array_keys( CMB_Power::CATEGORIES ) as $cat ) {
			$new[ 'power_' . $cat ] = ! empty( $_POST[ 'power_' . $cat ] );
			if ( $new[ 'power_' . $cat ] && ! $cur[ 'power_' . $cat ] ) {
				$raising = true;
			}
		}
		$paths = array();
		foreach ( array( 'wp_cli_path', 'php_cli_path' ) as $k ) {
			$v = isset( $_POST[ $k ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) ) : '';
			if ( '' !== $v && ( ! preg_match( '#^/[A-Za-z0-9._/\-]+$#', $v ) || ! is_file( $v ) ) ) {
				self::back( 'cmb', array( 'cmb_msg' => "Pfad $v existiert nicht oder ist ungültig.", 'cmb_type' => 'error' ) );
			}
			$paths[ $k ] = $v;
			if ( $v !== $cur[ $k ] ) {
				$raising = true;
			}
		}
		if ( $raising ) {
			$ok = CMB_Changes::verify_password( wp_get_current_user(), self::posted_password(), self::posted_code() );
			if ( is_wp_error( $ok ) ) {
				self::back( 'cmb', array( 'cmb_msg' => $ok->get_error_message(), 'cmb_type' => 'error' ) );
			}
		}
		CMB_Settings::update( array_merge( $new, $paths ) );
		CMB_Security::audit( 'power_settings_saved', $new + $paths );
		self::back( 'cmb', array( 'cmb_msg' => 'Expertenmodus gespeichert.' . ( $raising ? ' Bitte den Connector in Claude neu verbinden.' : '' ) ) );
	}

	public static function post_save_settings() {
		self::guard( 'cmb_save_settings' );
		$enabled = ! empty( $_POST['enabled'] );
		if ( $enabled && ( ! CMB_Settings::https_ok() || ! CMB_Settings::permalinks_ok() ) ) {
			self::back( 'cmb', array( 'cmb_msg' => 'Aktivierung nicht möglich: HTTPS und Permalinks erforderlich.', 'cmb_type' => 'error' ) );
		}
		$ips = isset( $_POST['ip_allowlist'] ) ? sanitize_text_field( wp_unslash( $_POST['ip_allowlist'] ) ) : '';
		if ( ! CMB_Security::valid_cidr_list( $ips ) ) {
			self::back( 'cmb', array( 'cmb_msg' => 'Ungültige IP-Liste.', 'cmb_type' => 'error' ) );
		}
		$ip_on = ! empty( $_POST['ip_allowlist_on'] );
		if ( $ip_on && ! CMB_Security::split_list( $ips ) ) {
			self::back( 'cmb', array( 'cmb_msg' => 'IP-Allowlist ist leer.', 'cmb_type' => 'error' ) );
		}
		$origins = array();
		foreach ( CMB_Security::split_list( isset( $_POST['extra_origins'] ) ? sanitize_text_field( wp_unslash( $_POST['extra_origins'] ) ) : '' ) as $o ) {
			if ( ! preg_match( '#^https://[a-z0-9.-]+(:\d+)?$#i', $o ) ) {
				self::back( 'cmb', array( 'cmb_msg' => 'Origins müssen https://host sein.', 'cmb_type' => 'error' ) );
			}
			$origins[] = $o;
		}
		$was = CMB_Settings::is_enabled();
		CMB_Settings::update(
			array(
				'enabled'         => $enabled,
				'ip_allowlist_on' => $ip_on,
				'ip_allowlist'    => $ips,
				'allow_loopback'  => ! empty( $_POST['allow_loopback'] ),
				'redact_pii'      => ! empty( $_POST['redact_pii'] ),
				'extra_origins'   => implode( ' ', $origins ),
				'pairing_until'   => $enabled ? (int) CMB_Settings::get( 'pairing_until' ) : 0,
			)
		);
		CMB_Security::audit( 'settings_saved', array( 'enabled' => $enabled, 'ip_allowlist_on' => $ip_on, 'loopback' => ! empty( $_POST['allow_loopback'] ), 'redact_pii' => ! empty( $_POST['redact_pii'] ) ) );
		if ( $was && ! $enabled ) {
			CMB_DB::revoke_all_tokens();
			CMB_Security::audit( 'all_tokens_revoked_disable' );
		}
		self::back( 'cmb', array( 'cmb_msg' => 'Gespeichert.' ) );
	}

	public static function post_start_pairing() {
		self::guard( 'cmb_start_pairing' );
		if ( ! CMB_Settings::is_enabled() ) {
			self::back( 'cmb', array( 'cmb_msg' => 'Bridge ist nicht aktiv.', 'cmb_type' => 'error' ) );
		}
		CMB_Settings::update( array( 'pairing_until' => time() + CMB_Settings::PAIRING_TTL ) );
		CMB_Security::audit( 'pairing_started' );
		self::back( 'cmb', array( 'cmb_msg' => 'Kopplungsfenster für 15 Minuten geöffnet.' ) );
	}

	public static function post_stop_pairing() {
		self::guard( 'cmb_stop_pairing' );
		CMB_Settings::update( array( 'pairing_until' => 0 ) );
		CMB_Security::audit( 'pairing_stopped' );
		self::back( 'cmb', array( 'cmb_msg' => 'Kopplung beendet.' ) );
	}

	public static function post_create_client() {
		self::guard( 'cmb_create_client' );
		if ( CMB_DB::count_active_clients() >= CMB_Settings::MAX_CLIENTS ) {
			self::back( 'cmb', array( 'cmb_msg' => 'Zu viele Clients.', 'cmb_type' => 'error' ) );
		}
		$name = isset( $_POST['name'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['name'] ) ), 0, 100 ) : 'Claude';
		list( $id, $secret ) = CMB_DB::create_client( '' !== $name ? $name : 'Claude', array( CMB_Settings::CLAUDE_CALLBACK ), true, 'manual' );
		// Secret nicht in die URL, sondern einmalig über Transient anzeigen.
		set_transient( 'cmb_new_client_' . get_current_user_id(), array( 'id' => $id, 'secret' => $secret ), 300 );
		CMB_Security::audit( 'client_created_manual', array( 'name' => $name ), null, $id );
		self::back( 'cmb' );
	}

	/* ----------------------------------------------------------------- TOTP */

	private static function render_totp_settings( array $s ) {
		$uid     = get_current_user_id();
		$user    = wp_get_current_user();
		$enabled = CMB_TOTP::is_enabled( $uid );
		echo '<div class="cmb-card" id="cmb-2fa"><h2>Zwei-Faktor-Bestätigung (Aegis &amp; Co.)</h2>';
		echo '<p>Riskante Freigaben (Dateien, SQL, Plugins, PHP, WP-CLI), das Einschalten des Expertenmodus und „Rückgängig“ verlangen dann zusätzlich einen 6-stelligen Code aus deiner Authenticator-App. Optional reicht für Freigaben der Code allein (ohne Passwort). Unabhängig vom 2FA beim WordPress-Login.</p>';

		$codes = get_transient( 'cmb_totp_codes_' . $uid );
		if ( $codes ) {
			delete_transient( 'cmb_totp_codes_' . $uid );
			echo '<div class="cmb-risk high"><p>Backup-Codes – jetzt sicher aufbewahren (z. B. im Passwortmanager). Jeder Code funktioniert einmal, falls das Handy fehlt. Sie werden nicht erneut angezeigt.</p><div class="cmb-code" style="white-space:pre-wrap">' . esc_html( implode( '   ', $codes ) ) . '</div></div>';
		}

		$pending = CMB_TOTP::pending_secret( $uid );
		if ( ! $enabled && null !== $pending ) {
			$b32 = CMB_TOTP::base32_encode( $pending );
			$uri = CMB_TOTP::otpauth_uri( $user, $b32 );
			echo '<h3>Schritt 2: In Aegis hinzufügen</h3>';
			echo '<p><strong>Auf dem Handy:</strong> <a class="button button-primary" href="' . esc_attr( $uri ) . '">In Authenticator-App öffnen</a> (öffnet Aegis direkt).</p>';
			echo '<p><strong>Oder manuell</strong> in Aegis: + → „Manuell eingeben“, Typ TOTP, SHA1, 6 Stellen, 30 Sekunden, Schlüssel:</p>';
			echo '<code class="cmb-url">' . esc_html( trim( chunk_split( $b32, 4, ' ' ) ) ) . '</code>';
			echo '<h3>Schritt 3: Bestätigen</h3>';
			self::form_open( 'cmb_totp_confirm' );
			self::code_field( 'Aktueller Code aus Aegis' );
			echo '<div class="cmb-actions"><button class="button button-primary">Aktivieren</button></div></form>';
			echo '<p class="description">Der Schlüssel ist 15 Minuten gültig. Danach Einrichtung neu starten.</p></div>';
			return;
		}

		if ( ! $enabled ) {
			echo '<p><strong>Status:</strong> <span class="cmb-bad">nicht eingerichtet</span></p>';
			self::form_open( 'cmb_totp_start' );
			echo '<label class="cmb-pw">WordPress-Passwort<input type="password" name="cmb_password" autocomplete="current-password"></label>';
			echo '<div class="cmb-actions"><button class="button button-primary">Einrichtung starten</button></div></form></div>';
			return;
		}

		echo '<p><strong>Status:</strong> <span class="cmb-ok">aktiv</span> · Backup-Codes übrig: ' . (int) CMB_TOTP::backup_count( $uid ) . '</p>';
		self::form_open( 'cmb_totp_policy' );
		echo '<p><label><input type="checkbox" name="totp_required" value="1" ' . checked( $s['totp_required'], true, false ) . '> Code für riskante Freigaben <strong>erzwingen</strong> (auch für Administratoren ohne Einrichtung – sie können dann nicht freigeben)</label></p>';
		echo '<p><label><input type="checkbox" name="totp_all" value="1" ' . checked( $s['totp_all'], true, false ) . '> Code auch für normale Inhaltsfreigaben verlangen</label></p>';
		echo '<p><label><input type="checkbox" name="approve_totp_only" value="1" ' . checked( $s['approve_totp_only'], true, false ) . '> <strong>Freigaben nur mit Zwei-Faktor-Code</strong> bestätigen (ohne WordPress-Passwort)</label><br><span class="description">Gilt für Freigaben und Sammelfreigaben. Rückgängig, Expertenmodus und diese Einstellungen verlangen weiterhin Passwort und Code. Wird Zwei-Faktor deaktiviert, gilt automatisch wieder das Passwort.</span></p>';
		echo '<label class="cmb-pw">WordPress-Passwort<input type="password" name="cmb_password" autocomplete="current-password"></label>';
		self::code_field();
		echo '<div class="cmb-actions"><button class="button button-primary" name="cmb_totp_do" value="policy">Richtlinie speichern</button>';
		echo '<button class="button" name="cmb_totp_do" value="backup">Neue Backup-Codes</button>';
		echo '<button class="button button-link-delete" name="cmb_totp_do" value="disable">Zwei-Faktor deaktivieren</button></div></form></div>';
	}

	private static function totp_back( $msg, $error = false ) {
		wp_safe_redirect( add_query_arg( array( 'cmb_msg' => $msg, 'cmb_type' => $error ? 'error' : 'success' ), admin_url( 'admin.php?page=cmb' ) ) . '#cmb-2fa' );
		exit;
	}

	public static function post_totp_start() {
		self::guard( 'cmb_totp_start' );
		$user = wp_get_current_user();
		if ( CMB_TOTP::is_enabled( $user->ID ) ) {
			self::totp_back( 'Zwei-Faktor ist bereits aktiv.', true );
		}
		$ok = CMB_Changes::reauth( $user, self::posted_password(), null, 'high' );
		if ( is_wp_error( $ok ) ) {
			self::totp_back( $ok->get_error_message(), true );
		}
		CMB_TOTP::start_setup( $user->ID );
		CMB_Security::audit( 'totp_setup_started' );
		self::totp_back( 'Schlüssel erzeugt – jetzt in Aegis hinzufügen und mit einem Code bestätigen.' );
	}

	public static function post_totp_confirm() {
		self::guard( 'cmb_totp_confirm' );
		$uid   = get_current_user_id();
		$codes = CMB_TOTP::confirm_setup( $uid, self::posted_code() );
		if ( is_wp_error( $codes ) ) {
			self::totp_back( $codes->get_error_message(), true );
		}
		set_transient( 'cmb_totp_codes_' . $uid, $codes, 300 );
		CMB_Security::audit( 'totp_enabled' );
		self::totp_back( 'Zwei-Faktor-Bestätigung ist aktiv.' );
	}

	/** Passwort + gültiger Code (oder Backup-Code) für Änderungen an der 2FA selbst. */
	private static function totp_strict_check() {
		$user = wp_get_current_user();
		$ok   = CMB_Changes::reauth( $user, self::posted_password(), self::posted_code(), 'critical' );
		if ( is_wp_error( $ok ) ) {
			self::totp_back( $ok->get_error_message(), true );
		}
		return $user;
	}

	public static function post_totp_policy() {
		self::guard( 'cmb_totp_policy' );
		$do = isset( $_POST['cmb_totp_do'] ) ? sanitize_key( wp_unslash( $_POST['cmb_totp_do'] ) ) : 'policy';
		if ( 'backup' === $do ) {
			self::post_totp_backup();
		}
		if ( 'disable' === $do ) {
			self::post_totp_disable();
		}
		$user = self::totp_strict_check();
		CMB_Settings::update( array( 'totp_required' => ! empty( $_POST['totp_required'] ), 'totp_all' => ! empty( $_POST['totp_all'] ), 'approve_totp_only' => ! empty( $_POST['approve_totp_only'] ) ) ); // phpcs:ignore
		CMB_Security::audit( 'totp_policy_saved', array( 'required' => ! empty( $_POST['totp_required'] ), 'all' => ! empty( $_POST['totp_all'] ), 'totp_only' => ! empty( $_POST['approve_totp_only'] ) ), $user->ID ); // phpcs:ignore
		self::totp_back( 'Richtlinie gespeichert.' );
	}

	/** Nur über post_totp_policy (Nonce bereits geprüft) erreichbar. */
	private static function post_totp_backup() {
		$user = self::totp_strict_check();
		set_transient( 'cmb_totp_codes_' . $user->ID, CMB_TOTP::new_backup_codes( $user->ID ), 300 );
		CMB_Security::audit( 'totp_backup_regenerated', array(), $user->ID );
		self::totp_back( 'Neue Backup-Codes erzeugt, alte sind ungültig.' );
	}

	private static function post_totp_disable() {
		$user = self::totp_strict_check();
		CMB_TOTP::disable( $user->ID );
		CMB_Security::audit( 'totp_disabled', array(), $user->ID );
		self::totp_back( 'Zwei-Faktor-Bestätigung deaktiviert.' . ( CMB_Settings::get( 'totp_required' ) ? ' Achtung: Die Pflicht-Einstellung ist aktiv – riskante Freigaben sind jetzt blockiert, bis du neu einrichtest.' : '' ) );
	}

	/* ------------------------------------------------------------ approvals */

	public static function page_approvals() {
		if ( ! current_user_can( self::cap() ) ) {
			return;
		}
		self::styles();
		echo '<div class="wrap cmb-wrap"><h1>Freigaben</h1>';
		self::notice();
		$uuid = isset( $_GET['change'] ) ? sanitize_key( wp_unslash( $_GET['change'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $uuid ) {
			self::render_change( $uuid );
			echo '</div>';
			return;
		}
		self::render_batch_results();
		$pending = CMB_Changes::sort_for_execution( CMB_Changes::list_for_user( get_current_user_id(), 'pending' ) );
		echo '<div class="cmb-card"><h2>Offen (' . count( $pending ) . ')</h2>';
		if ( ! $pending ) {
			echo '<p>Keine offenen Vorschläge.</p>';
		} else {
			self::render_batch_form( $pending );
		}
		echo '</div>';
		$hist = array_filter( CMB_Changes::list_for_user( get_current_user_id(), 'all', 30 ), function ( $c ) {
			return 'pending' !== $c->status;
		} );
		echo '<div class="cmb-card"><h2>Verlauf</h2><ul class="cmb-list">';
		foreach ( $hist as $c ) {
			echo '<li>' . self::status_label( $c->status ) . ' <a href="' . esc_url( CMB_Changes::approval_url( $c->uuid ) ) . '">' . esc_html( $c->summary ) . '</a><br><small>' . esc_html( wp_date( 'd.m.Y H:i', (int) $c->created_at ) ) . '</small></li>'; // phpcs:ignore
		}
		echo '</ul></div></div>';
	}

	private static function kv( array $rows ) {
		echo '<table class="cmb-kv">';
		foreach ( $rows as $k => $v ) {
			echo '<tr><th>' . esc_html( $k ) . '</th><td>' . esc_html( (string) $v ) . '</td></tr>';
		}
		echo '</table>';
	}

	private static function before_after( array $fields, array $before, array $labels ) {
		echo '<div class="cmb-scroll"><table class="widefat striped"><thead><tr><th>Feld</th><th>Vorher</th><th>Nachher</th></tr></thead><tbody>';
		foreach ( $fields as $k => $v ) {
			if ( 'content' === $k ) {
				continue;
			}
			$old = $before[ $k ] ?? '';
			$new = is_array( $v ) ? implode( ', ', $v ) : (string) $v;
			echo '<tr><td>' . esc_html( $labels[ $k ] ?? $k ) . '</td><td>' . esc_html( is_array( $old ) ? implode( ', ', $old ) : (string) $old ) . '</td><td><strong>' . esc_html( $new ) . '</strong></td></tr>';
		}
		echo '</tbody></table></div>';
	}

	private static function risk_label( $risk ) {
		$map = array( 'low' => 'normal', 'high' => 'hoch', 'critical' => 'kritisch' );
		return '<span class="cmb-pill risk-' . esc_attr( $risk ) . '">' . esc_html( $map[ $risk ] ?? $risk ) . '</span>';
	}

	/** Passwort- und/oder Code-Feld passend zur (höchsten) Risikostufe. */
	private static function confirm_fields( $risk ) {
		$uid = get_current_user_id();
		if ( CMB_Changes::needs_password( $risk, $uid ) ) {
			echo '<label class="cmb-pw">WordPress-Passwort zur Bestätigung<input type="password" name="cmb_password" autocomplete="current-password"></label>';
		}
		if ( CMB_Changes::totp_only( $uid ) && CMB_Changes::is_risky( $risk ) || CMB_Changes::needs_totp( $risk, $uid ) ) {
			if ( CMB_TOTP::is_enabled( $uid ) ) {
				self::code_field();
			} else {
				echo '<p class="cmb-bad">Für diese Freigabe ist ein Zwei-Faktor-Code Pflicht, aber noch nicht eingerichtet (Claude MCP → Einstellungen).</p>';
			}
		}
	}

	/** Wie wird bestätigt? Für die Hinweistexte. */
	private static function confirm_text( $risk ) {
		$uid = get_current_user_id();
		if ( ! CMB_Changes::is_risky( $risk ) ) {
			return CMB_Changes::needs_totp( $risk, $uid ) ? 'Freigabe mit Zwei-Faktor-Code.' : '';
		}
		if ( ! CMB_Changes::needs_password( $risk, $uid ) ) {
			return 'Freigabe mit Zwei-Faktor-Code.';
		}
		return CMB_Changes::needs_totp( $risk, $uid ) ? 'Freigabe mit Passwort und Zwei-Faktor-Code.' : 'Freigabe nur mit Passwort.';
	}

	private static function render_batch_form( array $pending ) {
		$max = CMB_Changes::max_risk( $pending );
		self::form_open( 'cmb_decide_many' );
		echo '<p class="description">Vorschläge in Ausführungsreihenfolge. Plugin-Hauptdateien (mit neuer Versionsnummer) kommen automatisch zuletzt. Bitte jeden Vorschlag vorher über den Link ansehen.</p>';
		echo '<p><label><input type="checkbox" onclick="var c=this.checked;this.form.querySelectorAll(\'input[name=&quot;changes[]&quot;]\').forEach(function(e){e.checked=c;});"> <strong>Alle auswählen</strong></label></p>';
		echo '<ul class="cmb-list cmb-batch">';
		foreach ( $pending as $c ) {
			$risk = $c->risk ?? 'low';
			$main = CMB_Changes::is_plugin_main_file( $c ) ? ' <span class="cmb-pill">Hauptdatei – zuletzt</span>' : '';
			echo '<li><input type="checkbox" name="changes[]" value="' . esc_attr( $c->uuid ) . '" id="cmb-c-' . esc_attr( $c->uuid ) . '"><div><label for="cmb-c-' . esc_attr( $c->uuid ) . '">' . self::risk_label( $risk ) . '<strong>' . esc_html( $c->summary ) . '</strong></label>' . $main . '<br><small>' . esc_html( wp_date( 'd.m.Y H:i', (int) $c->created_at ) ) . ' · <a href="' . esc_url( CMB_Changes::approval_url( $c->uuid ) ) . '">ansehen</a></small></div></li>'; // phpcs:ignore
		}
		echo '</ul>';
		$txt = self::confirm_text( $max );
		if ( $txt ) {
			echo '<p class="description">Höchstes Risiko der offenen Vorschläge: ' . self::risk_label( $max ) . ' – ' . esc_html( $txt ) . '</p>'; // phpcs:ignore
		}
		self::confirm_fields( $max );
		echo '<div class="cmb-actions"><button class="button button-secondary" name="decision" value="reject" formnovalidate onclick="return confirm(\'Markierte Vorschläge ablehnen?\')">Markierte ablehnen</button><button class="button button-primary" name="decision" value="approve">Markierte freigeben</button></div></form>';
	}

	private static function render_batch_results() {
		$key = 'cmb_batch_' . get_current_user_id();
		$res = get_transient( $key );
		if ( ! is_array( $res ) ) {
			return;
		}
		delete_transient( $key );
		echo '<div class="cmb-card"><h2>Ergebnis der Sammelfreigabe</h2><ul class="cmb-list cmb-results">';
		foreach ( $res as $r ) {
			echo '<li class="' . ( $r['ok'] ? 'ok' : 'bad' ) . '"><a href="' . esc_url( CMB_Changes::approval_url( $r['uuid'] ) ) . '">' . esc_html( $r['summary'] ) . '</a><br><small>' . esc_html( mb_substr( (string) $r['message'], 0, 300 ) ) . '</small></li>';
		}
		echo '</ul></div>';
	}

	public static function post_decide_many() {
		self::guard( 'cmb_decide_many' );
		$uuids   = isset( $_POST['changes'] ) && is_array( $_POST['changes'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['changes'] ) ) : array();
		$approve = isset( $_POST['decision'] ) && 'approve' === $_POST['decision'];
		$r       = CMB_Changes::decide_many( $uuids, $approve, wp_get_current_user(), self::posted_password(), self::posted_code() );
		if ( is_wp_error( $r ) ) {
			self::back( 'cmb-approvals', array( 'cmb_msg' => $r->get_error_message(), 'cmb_type' => 'error' ) );
		}
		set_transient( 'cmb_batch_' . get_current_user_id(), $r, 600 );
		$failed = count( array_filter( $r, function ( $x ) {
			return ! $x['ok'];
		} ) );
		$done   = count( $r ) - $failed;
		$msg    = $approve ? sprintf( '%d von %d Vorschlägen ausgeführt.', $done, count( $r ) ) : sprintf( '%d Vorschläge abgelehnt.', $done );
		self::back( 'cmb-approvals', array( 'cmb_msg' => $msg . ( $failed ? ' Details siehe unten.' : '' ), 'cmb_type' => $failed ? 'error' : 'success' ) );
	}

	private static function render_change( $uuid ) {
		$c = CMB_Changes::get( $uuid );
		if ( ! $c || (int) $c->user_id !== get_current_user_id() ) {
			echo '<div class="cmb-card"><p>Vorschlag nicht gefunden.</p></div>';
			return;
		}
		$p      = $c->payload;
		$labels = array( 'title' => 'Titel', 'excerpt' => 'Auszug', 'status' => 'Status', 'slug' => 'Slug', 'categories' => 'Kategorien (IDs)', 'tags' => 'Schlagwörter', 'tagline' => 'Untertitel', 'alt' => 'Alt-Text', 'caption' => 'Beschriftung' );

		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=cmb-approvals' ) ) . '">← Alle Freigaben</a></p>';
		echo '<div class="cmb-card"><h2>' . esc_html( $c->summary ) . '</h2>';
		echo '<p>' . self::status_label( $c->status ) . ' · vorgeschlagen ' . esc_html( wp_date( 'd.m.Y H:i', (int) $c->created_at ) ) . ' · gültig bis ' . esc_html( wp_date( 'd.m.Y H:i', (int) $c->expires_at ) ) . '</p>'; // phpcs:ignore
		$risk_txt = array(
			'low'      => 'Normales Risiko – Inhaltsänderung.',
			'high'     => 'Hohes Risiko – kann die Website beeinträchtigen.',
			'critical' => 'KRITISCH – führt Code aus bzw. verändert ausführbare Dateien. Nur freigeben, wenn du jede Zeile verstehst.',
		);
		$risk = $c->risk ?? 'low';
		echo '<div class="cmb-risk ' . esc_attr( $risk ) . '">' . esc_html( trim( ( $risk_txt[ $risk ] ?? $risk ) . ' ' . ( 'pending' === $c->status ? self::confirm_text( $risk ) : '' ) ) ) . '</div>';
		if ( ! empty( $p['purpose'] ) ) {
			echo '<p><strong>Begründung von Claude:</strong> ' . esc_html( $p['purpose'] ) . '</p>';
		}

		switch ( $c->action ) {
			case 'create_post':
				self::kv(
					array(
						'Typ'          => 'page' === $p['post_type'] ? 'Seite' : 'Beitrag',
						'Titel'        => $p['title'] ?? '',
						'Status'       => $p['status'] ?? 'draft',
						'Slug'         => $p['slug'] ?? '(automatisch)',
						'Kategorien'   => isset( $p['categories'] ) ? implode( ', ', array_map( function ( $id ) {
							$t = get_term( (int) $id, 'category' );
							return $t && ! is_wp_error( $t ) ? $t->name : '#' . (int) $id;
						}, $p['categories'] ) ) : '–',
						'Schlagwörter' => isset( $p['tags'] ) ? implode( ', ', $p['tags'] ) : '–',
					)
				);
				if ( ! empty( $p['excerpt'] ) ) {
					echo '<h3>Auszug</h3><div class="cmb-pre">' . esc_html( $p['excerpt'] ) . '</div>';
				}
				echo '<h3>Inhalt (Quelltext, wie er gespeichert wird)</h3><div class="cmb-pre">' . esc_html( $p['content'] ?? '' ) . '</div>';
				break;

			case 'update_post':
				$post = get_post( (int) $p['id'] );
				echo '<p>Ziel: ' . ( $post ? '<a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">#' . (int) $post->ID . ' ' . esc_html( $post->post_title ) . '</a>' : 'nicht mehr vorhanden' ) . '</p>';
				if ( $post && $post->post_modified_gmt !== ( $p['snapshot']['modified_gmt'] ?? '' ) ) {
					echo '<p class="cmb-bad"><strong>Achtung:</strong> Der Inhalt wurde seit dem Vorschlag verändert. Eine Freigabe wird abgelehnt.</p>';
				}
				self::before_after( $p['fields'], $p['before'] ?? array(), $labels );
				if ( array_key_exists( 'content', $p['fields'] ) ) {
					echo '<h3>Inhalt – Änderungen</h3><div class="cmb-scroll">';
					$diff = wp_text_diff( (string) ( $p['before']['content'] ?? '' ), (string) $p['fields']['content'], array( 'title_left' => 'Vorher', 'title_right' => 'Nachher' ) );
					echo $diff ? wp_kses_post( $diff ) : '<p>Keine Änderungen am Inhalt.</p>';
					echo '</div>';
				}
				break;

			case 'trash_post':
				$post = get_post( (int) $p['id'] );
				echo '<p>Folgender Inhalt wird in den <strong>Papierkorb</strong> verschoben (wiederherstellbar):</p>';
				self::kv( array( 'ID' => (int) $p['id'], 'Titel' => $post ? $post->post_title : ( $p['title'] ?? '' ), 'Typ' => $post ? $post->post_type : '?' ) );
				break;

			case 'update_settings':
				self::before_after( $p['fields'], $p['before'] ?? array(), $labels );
				break;

			case 'moderate_comment':
				$actions = array( 'approve' => 'Freischalten', 'unapprove' => 'Zurück in Moderation', 'spam' => 'Als Spam markieren', 'trash' => 'In den Papierkorb' );
				self::kv( array( 'Kommentar' => '#' . (int) $p['id'], 'Autor' => $p['author'] ?? '', 'Aktion' => $actions[ $p['action'] ] ?? $p['action'] ) );
				echo '<div class="cmb-pre">' . esc_html( $p['preview'] ?? '' ) . '</div>';
				break;

			case 'update_media':
				echo '<p>Mediendatei #' . (int) $p['id'] . '</p>';
				self::before_after( $p['fields'], $p['before'] ?? array(), $labels );
				break;

			case 'file_write':
				echo '<p>Datei: <code>' . esc_html( $p['path'] ) . '</code> ' . ( null === $p['expected_sha'] ? '<strong>(neu)</strong>' : '' ) . '</p>';
				if ( null === $p['before'] ) {
					echo '<h3>Inhalt</h3><div class="cmb-code">' . esc_html( $p['content'] ) . '</div>';
				} else {
					$diff = wp_text_diff( (string) $p['before'], (string) $p['content'], array( 'title_left' => 'Vorher', 'title_right' => 'Nachher' ) );
					echo '<h3>Änderungen</h3><div class="cmb-scroll">' . ( $diff ? wp_kses_post( $diff ) : '<p>Keine Unterschiede.</p>' ) . '</div>';
				}
				break;

			case 'file_delete':
				echo '<p>Datei wird <strong>gelöscht</strong>: <code>' . esc_html( $p['path'] ) . '</code> (Backup wird gespeichert)</p>';
				echo '<details><summary>Aktueller Inhalt</summary><div class="cmb-code">' . esc_html( CMB_Power::clip( (string) $p['before'], 20000 ) ) . '</div></details>';
				break;

			case 'php_exec':
				echo '<h3>PHP-Code, der ausgeführt wird</h3><div class="cmb-code">' . esc_html( $p['code'] ) . '</div>';
				break;

			case 'sql_exec':
				global $wpdb;
				echo '<p>Datenbank: <code>' . esc_html( DB_NAME ) . '</code>, Tabellen-Präfix <code>' . esc_html( $wpdb->prefix ) . '</code></p>';
				echo '<h3>SQL-Anweisung</h3><div class="cmb-code">' . esc_html( $p['sql'] ) . '</div>';
				echo '<p class="description">Datenbankänderungen lassen sich nicht automatisch rückgängig machen. Bei größeren Änderungen vorher ein Backup anlegen.</p>';
				break;

			case 'wpcli_run':
				echo '<h3>Befehl</h3><div class="cmb-code">' . esc_html( CMB_Tools::cli_display( (array) $p['args'] ) ) . '</div>';
				break;

			case 'plugin_action':
				$labels_pa = array( 'install' => 'Installieren (von wordpress.org)', 'activate' => 'Aktivieren', 'deactivate' => 'Deaktivieren', 'update' => 'Aktualisieren', 'delete' => 'Löschen' );
				$rows      = array( 'Aktion' => $labels_pa[ $p['action'] ] ?? $p['action'], 'Plugin' => $p['info']['name'] ?? ( $p['name'] ?? $p['plugin'] ) );
				if ( isset( $p['info'] ) ) {
					$rows += array(
						'Version'             => $p['info']['version'],
						'Autor'               => $p['info']['author'],
						'Aktive Installationen' => number_format_i18n( (int) $p['info']['active_installs'] ),
						'Zuletzt aktualisiert' => $p['info']['last_updated'],
						'Getestet bis WP'     => $p['info']['tested'],
						'Danach aktivieren'   => ! empty( $p['activate'] ) ? 'ja' : 'nein',
					);
				} else {
					$rows['Datei']   = $p['plugin'];
					$rows['Version'] = $p['version'] ?? '';
				}
				self::kv( $rows );
				if ( ! empty( $p['info']['description'] ) ) {
					echo '<p>' . esc_html( $p['info']['description'] ) . '</p>';
				}
				break;
		}

		if ( 'pending' === $c->status ) {
			self::form_open( 'cmb_decide', 'cmb_decide_' . $c->uuid );
			echo '<input type="hidden" name="change" value="' . esc_attr( $c->uuid ) . '">';
			self::confirm_fields( $risk );
			echo '<div class="cmb-actions"><button class="button button-secondary" name="decision" value="reject" formnovalidate>Ablehnen</button><button class="button button-primary" name="decision" value="approve">Freigeben</button></div></form>';
		} else {
			if ( $c->result ) {
				echo '<h3>Ergebnis</h3><div class="cmb-code">' . esc_html( CMB_Power::clip( $c->result, 20000 ) ) . '</div>';
			}
			if ( (int) $c->reverted ) {
				echo '<p><strong>Wurde rückgängig gemacht.</strong></p>';
			} elseif ( CMB_Changes::revertable( $c ) ) {
				self::form_open( 'cmb_revert', 'cmb_revert_' . $c->uuid );
				echo '<input type="hidden" name="change" value="' . esc_attr( $c->uuid ) . '">';
				echo '<label class="cmb-pw">WordPress-Passwort<input type="password" name="cmb_password" autocomplete="current-password"></label>';
				if ( CMB_TOTP::is_enabled( get_current_user_id() ) ) {
					self::code_field();
				}
				echo '<div class="cmb-actions"><button class="button">Rückgängig machen (Datei wiederherstellen)</button></div></form>';
			}
		}
		echo '</div>';
	}

	public static function post_decide() {
		$uuid = isset( $_POST['change'] ) ? sanitize_key( wp_unslash( $_POST['change'] ) ) : '';
		self::guard( 'cmb_decide_' . $uuid );
		$approve = isset( $_POST['decision'] ) && 'approve' === $_POST['decision'];
		$c       = CMB_Changes::get( $uuid );
		$r       = CMB_Changes::decide( $c, $approve, wp_get_current_user(), self::posted_password(), self::posted_code() );
		if ( is_wp_error( $r ) ) {
			self::back( 'cmb-approvals', array( 'change' => $uuid, 'cmb_msg' => $r->get_error_message(), 'cmb_type' => 'error' ) );
		}
		$left = count( CMB_Changes::list_for_user( get_current_user_id(), 'pending' ) );
		self::back( 'cmb-approvals', array( 'change' => $uuid, 'cmb_msg' => ( $approve ? 'Freigegeben: ' . mb_substr( (string) $r, 0, 200 ) : 'Abgelehnt.' ) . ( $left ? ' Noch ' . $left . ' offen – unter „Alle Freigaben“ gesammelt freigeben.' : '' ) ) );
	}

	public static function post_revert() {
		$uuid = isset( $_POST['change'] ) ? sanitize_key( wp_unslash( $_POST['change'] ) ) : '';
		self::guard( 'cmb_revert_' . $uuid );
		$r = CMB_Changes::revert( CMB_Changes::get( $uuid ), wp_get_current_user(), self::posted_password(), self::posted_code() );
		if ( is_wp_error( $r ) ) {
			self::back( 'cmb-approvals', array( 'change' => $uuid, 'cmb_msg' => $r->get_error_message(), 'cmb_type' => 'error' ) );
		}
		self::back( 'cmb-approvals', array( 'change' => $uuid, 'cmb_msg' => $r ) );
	}

	/* ------------------------------------------------------------- clients */

	public static function page_clients() {
		if ( ! current_user_can( self::cap() ) ) {
			return;
		}
		self::styles();
		echo '<div class="wrap cmb-wrap"><h1>Verbindungen</h1>';
		self::notice();
		$clients = CMB_DB::list_clients();
		echo '<div class="cmb-card cmb-scroll"><table class="widefat striped"><thead><tr><th>Name</th><th>Art</th><th>Angelegt</th><th>Zuletzt genutzt</th><th>Sitzungen</th><th></th></tr></thead><tbody>';
		if ( ! $clients ) {
			echo '<tr><td colspan="6">Keine Verbindungen.</td></tr>';
		}
		foreach ( $clients as $cl ) {
			echo '<tr><td>' . esc_html( $cl->name ) . '<br><code>' . esc_html( $cl->client_id ) . '</code></td><td>' . esc_html( 'dcr' === $cl->source ? 'Kopplung' : 'Manuell' ) . '</td>';
			echo '<td>' . esc_html( wp_date( 'd.m.Y H:i', (int) $cl->created_at ) ) . '</td><td>' . esc_html( $cl->last_used_at ? wp_date( 'd.m.Y H:i', (int) $cl->last_used_at ) : '–' ) . '</td><td>' . (int) $cl->active_sessions . '</td><td>';
			self::form_open( 'cmb_revoke_client', 'cmb_revoke_' . $cl->client_id );
			echo '<input type="hidden" name="client_id" value="' . esc_attr( $cl->client_id ) . '"><button class="button button-link-delete">Trennen</button></form></td></tr>';
		}
		echo '</tbody></table></div>';
		echo '<div class="cmb-card"><h2>Notfall</h2><p>Trennt sofort alle Verbindungen und macht sämtliche Tokens ungültig.</p>';
		self::form_open( 'cmb_revoke_all' );
		submit_button( 'Alle Verbindungen trennen', 'delete', 'submit', false );
		echo '</form></div></div>';
	}

	public static function post_revoke_client() {
		$id = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';
		self::guard( 'cmb_revoke_' . $id );
		CMB_DB::revoke_client( $id );
		CMB_Security::audit( 'client_revoked', array(), null, $id );
		self::back( 'cmb-clients', array( 'cmb_msg' => 'Verbindung getrennt.' ) );
	}

	public static function post_revoke_all() {
		self::guard( 'cmb_revoke_all' );
		global $wpdb;
		$wpdb->query( 'UPDATE ' . CMB_DB::t( 'clients' ) . ' SET revoked = 1' );
		CMB_DB::revoke_all_tokens();
		CMB_Settings::update( array( 'pairing_until' => 0 ) );
		CMB_Security::audit( 'all_revoked' );
		self::back( 'cmb-clients', array( 'cmb_msg' => 'Alle Verbindungen getrennt.' ) );
	}

	/* ----------------------------------------------------------------- log */

	public static function page_log() {
		if ( ! current_user_can( self::cap() ) ) {
			return;
		}
		global $wpdb;
		self::styles();
		$rows = $wpdb->get_results( 'SELECT * FROM ' . CMB_DB::t( 'audit' ) . ' ORDER BY id DESC LIMIT 200' );
		echo '<div class="wrap cmb-wrap"><h1>Protokoll</h1><p>Letzte 200 Ereignisse (werden nach 90 Tagen gelöscht, IPs gekürzt).</p><div class="cmb-card cmb-scroll"><table class="widefat striped"><thead><tr><th>Zeit</th><th>Ereignis</th><th>Nutzer</th><th>Client</th><th>IP</th><th>Details</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$u = $r->user_id ? get_userdata( (int) $r->user_id ) : null;
			echo '<tr><td>' . esc_html( wp_date( 'd.m. H:i:s', (int) $r->created_at ) ) . '</td><td>' . esc_html( $r->event ) . '</td><td>' . esc_html( $u ? $u->user_login : '–' ) . '</td><td><code>' . esc_html( substr( $r->client_id, 0, 14 ) ) . '</code></td><td>' . esc_html( $r->ip ) . '</td><td><code>' . esc_html( (string) $r->detail ) . '</code></td></tr>';
		}
		echo '</tbody></table></div></div>';
	}
}
