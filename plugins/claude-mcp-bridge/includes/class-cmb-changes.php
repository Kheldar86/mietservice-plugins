<?php
defined( 'ABSPATH' ) || exit;

/**
 * Änderungsvorschläge ("Pending Changes").
 *
 * Claude kann über MCP NIE direkt schreiben. Jede Schreibaktion wird als Vorschlag
 * gespeichert; ausgeführt wird sie nur, wenn derselbe Administrator sie im
 * WordPress-Backend (eingeloggt, Nonce, Capability) ausdrücklich freigibt.
 * Bei der Freigabe wird alles erneut geprüft (Rechte, Existenz, Veraltung).
 */
final class CMB_Changes {

	const ACTIONS = array( 'create_post', 'update_post', 'trash_post', 'update_settings', 'moderate_comment', 'update_media', 'file_write', 'file_delete', 'php_exec', 'sql_exec', 'wpcli_run', 'plugin_action' );

	/** Kategorie des Expertenmodus, die für eine Aktion eingeschaltet sein muss. */
	const POWER_CAT = array( 'file_write' => 'files', 'file_delete' => 'files', 'php_exec' => 'php', 'sql_exec' => 'sql', 'wpcli_run' => 'wpcli', 'plugin_action' => 'plugins' );

	const RISKS = array( 'low', 'high', 'critical' );

	/** Risikostufe „hoch“ oder „kritisch“? */
	public static function is_risky( $risk ) {
		return in_array( $risk, array( 'high', 'critical' ), true );
	}

	/**
	 * Ab 0.4.0: Freigaben nur mit Zwei-Faktor-Code (ohne Passwort) – nur wenn die
	 * Einstellung aktiv ist UND der Nutzer TOTP eingerichtet hat. Sonst gilt wie bisher das Passwort.
	 */
	public static function totp_only( $user_id ) {
		return $user_id && (bool) CMB_Settings::get( 'approve_totp_only' ) && CMB_TOTP::is_enabled( (int) $user_id );
	}

	/**
	 * Braucht diese Freigabe das WordPress-Passwort?
	 * Ohne $user_id (Altaufrufe) = reine Risikoprüfung wie bis 0.3.0.
	 */
	public static function needs_password( $risk, $user_id = null ) {
		if ( ! self::is_risky( $risk ) ) {
			return false;
		}
		return null === $user_id ? true : ! self::totp_only( $user_id );
	}

	/** Höchste Risikostufe einer Liste von Vorschlägen. */
	public static function max_risk( array $changes ) {
		$rank = array_flip( self::RISKS );
		$max  = 'low';
		foreach ( $changes as $c ) {
			$r = $c->risk ?? 'low';
			if ( isset( $rank[ $r ] ) && $rank[ $r ] > $rank[ $max ] ) {
				$max = $r;
			}
		}
		return $max;
	}

	/**
	 * Ist dieser Vorschlag die Hauptdatei eines Plugins (Kopfzeile „Plugin Name:“)?
	 * Solche Dateien werden bei Sammelfreigaben zuletzt ausgeführt, damit die neue
	 * Versionsnummer erst greift, wenn alle anderen Dateien der Version stehen.
	 */
	public static function is_plugin_main_file( $change ) {
		if ( 'file_write' !== $change->action ) {
			return false;
		}
		$p = is_array( $change->payload ) ? $change->payload : ( json_decode( (string) $change->payload, true ) ?: array() );
		if ( ! preg_match( '#^wp-content/plugins/[^/]+/[^/]+\.php$#i', (string) ( $p['path'] ?? '' ) ) ) {
			return false;
		}
		return (bool) preg_match( '/^[ \t\/*#@]*Plugin Name\s*:/mi', substr( (string) ( $p['content'] ?? '' ), 0, 8192 ) );
	}

	/** Ausführungsreihenfolge: älteste zuerst, Plugin-Hauptdateien zuletzt. */
	public static function sort_for_execution( array $changes ) {
		usort(
			$changes,
			function ( $a, $b ) {
				$ma = self::is_plugin_main_file( $a ) ? 1 : 0;
				$mb = self::is_plugin_main_file( $b ) ? 1 : 0;
				if ( $ma !== $mb ) {
					return $ma - $mb;
				}
				if ( (int) $a->created_at !== (int) $b->created_at ) {
					return (int) $a->created_at - (int) $b->created_at;
				}
				return (int) $a->id - (int) $b->id;
			}
		);
		return $changes;
	}

	/* ---------------------------------------------------------------- store */

	public static function create( $user_id, $client_id, $action, array $payload, $summary, $risk = 'low' ) {
		global $wpdb;
		if ( ! in_array( $action, self::ACTIONS, true ) || ! in_array( $risk, self::RISKS, true ) ) {
			return new WP_Error( 'bad_action', 'Unknown action.' );
		}
		$ttl = 'critical' === $risk ? CMB_Settings::CHANGE_TTL_CRITICAL : ( 'high' === $risk ? CMB_Settings::CHANGE_TTL_HIGH : CMB_Settings::CHANGE_TTL );
		$pending = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM ' . CMB_DB::t( 'changes' ) . " WHERE user_id = %d AND status = 'pending' AND expires_at > %d", $user_id, time() )
		);
		if ( $pending >= CMB_Settings::MAX_PENDING ) {
			return new WP_Error( 'too_many_pending', 'Too many open proposals. Ask the user to approve or reject the existing ones first.' );
		}
		$json = wp_json_encode( $payload );
		$uuid = bin2hex( random_bytes( 16 ) );
		$wpdb->insert(
			CMB_DB::t( 'changes' ),
			array(
				'uuid'         => $uuid,
				'user_id'      => (int) $user_id,
				'client_id'    => $client_id,
				'action'       => $action,
				'payload'      => $json,
				'payload_hash' => CMB_Security::hash( $action . '|' . $json ),
				'summary'      => mb_substr( $summary, 0, 250 ),
				'risk'         => $risk,
				'status'       => 'pending',
				'created_at'   => time(),
				'expires_at'   => time() + $ttl,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
		);
		CMB_Security::audit( 'change_proposed', array( 'change' => $uuid, 'action' => $action, 'risk' => $risk, 'summary' => $summary ), $user_id, $client_id );
		return self::get( $uuid );
	}

	public static function get( $uuid ) {
		global $wpdb;
		if ( ! is_string( $uuid ) || ! preg_match( '/^[a-f0-9]{32}$/', $uuid ) ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . CMB_DB::t( 'changes' ) . ' WHERE uuid = %s', $uuid ) );
		if ( $row ) {
			$row->payload_raw = $row->payload;
			$row->payload     = json_decode( $row->payload, true ) ?: array();
			if ( 'pending' === $row->status && (int) $row->expires_at < time() ) {
				$row->status = 'expired';
			}
		}
		return $row;
	}

	public static function list_for_user( $user_id, $status = 'pending', $limit = 50 ) {
		global $wpdb;
		$table = CMB_DB::t( 'changes' );
		if ( 'pending' === $status ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE user_id = %d AND status = 'pending' AND expires_at > %d ORDER BY created_at DESC LIMIT %d", $user_id, time(), $limit ) );
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d", $user_id, $limit ) );
	}

	public static function approval_url( $uuid ) {
		return admin_url( 'admin.php?page=cmb-approvals&change=' . rawurlencode( $uuid ) );
	}

	/** Atomarer Statuswechsel pending -> $to. Verhindert doppelte Ausführung. */
	private static function transition( $change, $to, $user_id, $result = null ) {
		global $wpdb;
		$affected = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . CMB_DB::t( 'changes' ) . " SET status = %s, decided_at = %d, decided_by = %d, result = %s WHERE id = %d AND status = 'pending'",
				$to,
				time(),
				$user_id,
				null === $result ? '' : $result,
				$change->id
			)
		);
		return 1 === (int) $affected;
	}

	private static function set_result( $change, $status, $result, $backup = null ) {
		global $wpdb;
		$data = array( 'status' => $status, 'result' => $result );
		$fmt  = array( '%s', '%s' );
		if ( null !== $backup ) {
			$data['backup'] = wp_json_encode( $backup );
			$fmt[]          = '%s';
		}
		$wpdb->update( CMB_DB::t( 'changes' ), $data, array( 'id' => $change->id ), $fmt, array( '%d' ) );
	}

	/** Braucht diese Freigabe einen TOTP-Code (Aegis o. ä.)? */
	public static function needs_totp( $risk, $user_id ) {
		$enabled = CMB_TOTP::is_enabled( $user_id );
		if ( self::is_risky( $risk ) ) {
			return $enabled || (bool) CMB_Settings::get( 'totp_required' );
		}
		return $enabled && (bool) CMB_Settings::get( 'totp_all' );
	}

	/**
	 * Erneute Bestätigung für Freigaben (Schutz bei gekaperter Sitzung):
	 * Passwort ab Risiko „hoch“, zusätzlich TOTP-Code, wenn eingerichtet bzw. erzwungen.
	 * Mit $allow_totp_only (nur bei Freigaben) entfällt das Passwort, wenn „Freigaben nur
	 * mit Zwei-Faktor-Code“ aktiv und TOTP eingerichtet ist – der Code ist dann Pflicht.
	 * Sicherheitseinstellungen, Rückgängig und Expertenmodus verlangen weiterhin das Passwort.
	 * Fehlversuche (Passwort oder Code) werden gemeinsam gezählt: 5 Fehler = 15 Min. Sperre.
	 */
	public static function reauth( WP_User $user, $password, $code, $risk, $allow_totp_only = false ) {
		$fails = (int) get_transient( 'cmb_reauth_fail_' . $user->ID );
		if ( $fails >= 5 ) {
			return new WP_Error( 'locked', 'Zu viele Fehlversuche. Bitte 15 Minuten warten.' );
		}
		$fail = function ( $msg, $event ) use ( $user, $fails ) {
			set_transient( 'cmb_reauth_fail_' . $user->ID, $fails + 1, 900 );
			CMB_Security::audit( $event, array(), $user->ID );
			return new WP_Error( 'reauth', $msg );
		};
		$skip_password = $allow_totp_only && self::is_risky( $risk ) && self::totp_only( $user->ID );
		if ( self::is_risky( $risk ) && ! $skip_password ) {
			$fresh = get_userdata( $user->ID );
			if ( ! is_string( $password ) || '' === $password || ! $fresh || ! wp_check_password( $password, $fresh->user_pass, $user->ID ) ) {
				return $fail( 'Passwort ist falsch.', 'reauth_failed' );
			}
		}
		if ( $skip_password || self::needs_totp( $risk, $user->ID ) ) {
			if ( ! CMB_TOTP::is_enabled( $user->ID ) ) {
				return new WP_Error( 'totp_missing', 'Für diese Freigabe ist ein Zwei-Faktor-Code Pflicht. Richte ihn unter Claude MCP → Einstellungen ein.' );
			}
			if ( ! CMB_TOTP::verify( $user->ID, $code ) ) {
				return $fail( 'Der Zwei-Faktor-Code ist falsch oder wurde schon benutzt.', 'totp_failed' );
			}
		}
		delete_transient( 'cmb_reauth_fail_' . $user->ID );
		return true;
	}

	/** Kompatibilität: Passwort (+ ggf. TOTP) wie bei kritischen Aktionen. */
	public static function verify_password( WP_User $user, $password, $code = null ) {
		return self::reauth( $user, $password, $code, 'critical' );
	}

	public static function cancel( $change, $user_id ) {
		if ( ! $change || (int) $change->user_id !== (int) $user_id || 'pending' !== $change->status ) {
			return false;
		}
		$ok = self::transition( $change, 'cancelled', $user_id, 'Cancelled via MCP.' );
		if ( $ok ) {
			CMB_Security::audit( 'change_cancelled', array( 'change' => $change->uuid ), $user_id, $change->client_id );
		}
		return $ok;
	}

	/**
	 * Wird ausschließlich aus dem Admin-Handler (eingeloggt + Nonce) aufgerufen.
	 */
	public static function decide( $change, $approve, WP_User $user, $password = null, $code = null ) {
		$check = self::check_decidable( $change, $user );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		if ( ! $approve ) {
			return self::reject( $change, $user );
		}
		$ok = self::reauth( $user, $password, $code, $change->risk ?? 'low', true );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		return self::run( $change, $user );
	}

	/** Offen, eigener Vorschlag, Rechte, Integrität. */
	private static function check_decidable( $change, WP_User $user ) {
		if ( ! $change || 'pending' !== $change->status ) {
			return new WP_Error( 'not_pending', 'Dieser Vorschlag ist nicht mehr offen.' );
		}
		if ( (int) $change->user_id !== (int) $user->ID || ! user_can( $user, CMB_Settings::REQUIRED_CAP ) ) {
			return new WP_Error( 'forbidden', 'Nur der Administrator, der Claude verbunden hat, kann diesen Vorschlag entscheiden.' );
		}
		if ( ! hash_equals( $change->payload_hash, CMB_Security::hash( $change->action . '|' . $change->payload_raw ) ) ) {
			self::transition( $change, 'failed', $user->ID, 'Integrity check failed.' );
			CMB_Security::audit( 'change_integrity_failed', array( 'change' => $change->uuid ), $user->ID, $change->client_id );
			return new WP_Error( 'integrity', 'Integritätsprüfung fehlgeschlagen – der Vorschlag wurde verändert und wird nicht ausgeführt.' );
		}
		return true;
	}

	private static function reject( $change, WP_User $user ) {
		self::transition( $change, 'rejected', $user->ID, 'Rejected by user.' );
		CMB_Security::audit( 'change_rejected', array( 'change' => $change->uuid ), $user->ID, $change->client_id );
		return 'Abgelehnt.';
	}

	/**
	 * Sammelfreigabe (ab 0.4.0): mehrere Vorschläge mit EINER Bestätigung.
	 * Die Bestätigung richtet sich nach dem höchsten Risiko der Auswahl. Ausgeführt wird
	 * in sicherer Reihenfolge (Plugin-Hauptdateien zuletzt); beim ersten Fehler wird
	 * angehalten, die übrigen Vorschläge bleiben offen.
	 *
	 * @return array|WP_Error Liste [uuid, summary, ok, message]
	 */
	public static function decide_many( array $uuids, $approve, WP_User $user, $password = null, $code = null ) {
		$uuids = array_slice( array_values( array_unique( array_filter( array_map( 'strval', $uuids ) ) ) ), 0, CMB_Settings::MAX_PENDING );
		if ( ! $uuids ) {
			return new WP_Error( 'none', 'Keine Vorschläge ausgewählt.' );
		}
		$changes = array();
		foreach ( $uuids as $uuid ) {
			$c     = self::get( $uuid );
			$check = self::check_decidable( $c, $user );
			if ( is_wp_error( $check ) ) {
				return new WP_Error( $check->get_error_code(), ( $c ? '„' . $c->summary . '“: ' : '' ) . $check->get_error_message() . ' Es wurde nichts ausgeführt.' );
			}
			$changes[] = $c;
		}
		$out = array();
		if ( ! $approve ) {
			foreach ( $changes as $c ) {
				$out[] = array( 'uuid' => $c->uuid, 'summary' => $c->summary, 'ok' => true, 'message' => self::reject( $c, $user ) );
			}
			return $out;
		}
		$ok = self::reauth( $user, $password, $code, self::max_risk( $changes ), true );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		CMB_Security::audit( 'batch_approve', array( 'count' => count( $changes ), 'risk' => self::max_risk( $changes ) ), $user->ID );
		$stopped = false;
		foreach ( self::sort_for_execution( $changes ) as $c ) {
			if ( $stopped ) {
				$out[] = array( 'uuid' => $c->uuid, 'summary' => $c->summary, 'ok' => false, 'message' => 'Nicht ausgeführt (angehalten nach Fehler) – bleibt offen.' );
				continue;
			}
			$r = self::run( $c, $user );
			if ( is_wp_error( $r ) ) {
				$stopped = true;
				$out[]   = array( 'uuid' => $c->uuid, 'summary' => $c->summary, 'ok' => false, 'message' => $r->get_error_message() );
				continue;
			}
			$out[] = array( 'uuid' => $c->uuid, 'summary' => $c->summary, 'ok' => true, 'message' => (string) $r );
		}
		return $out;
	}

	/** Führt einen bereits geprüften und bestätigten Vorschlag aus. */
	private static function run( $change, WP_User $user ) {
		if ( isset( self::POWER_CAT[ $change->action ] ) && ! CMB_Power::enabled( self::POWER_CAT[ $change->action ] ) ) {
			return new WP_Error( 'power_off', 'Diese Funktion ist im Expertenmodus inzwischen ausgeschaltet. Vorschlag wird nicht ausgeführt.' );
		}

		if ( ! self::transition( $change, 'running', $user->ID ) ) {
			return new WP_Error( 'race', 'Dieser Vorschlag wird bereits verarbeitet.' );
		}

		$prev_user = get_current_user_id();
		wp_set_current_user( $user->ID );
		try {
			$result = self::execute( $change->action, $change->payload );
		} catch ( Throwable $e ) {
			$result = new WP_Error( 'exception', 'Unerwarteter Fehler bei der Ausführung.' );
		}
		wp_set_current_user( $prev_user );

		if ( is_wp_error( $result ) ) {
			self::set_result( $change, 'failed', $result->get_error_message() );
			CMB_Security::audit( 'change_failed', array( 'change' => $change->uuid, 'error' => $result->get_error_message() ), $user->ID, $change->client_id );
			return $result;
		}
		$backup = null;
		if ( is_array( $result ) ) {
			$backup = $result['backup'] ?? null;
			$result = $result['message'];
		}
		self::set_result( $change, 'approved', $result, $backup );
		CMB_Security::audit( 'change_approved', array( 'change' => $change->uuid, 'action' => $change->action, 'risk' => $change->risk ?? 'low' ), $user->ID, $change->client_id );
		return $result;
	}

	/** Kann eine ausgeführte Dateiänderung zurückgenommen werden? */
	public static function revertable( $change ) {
		return $change && 'approved' === $change->status && ! (int) $change->reverted && in_array( $change->action, array( 'file_write', 'file_delete' ), true ) && ! empty( $change->backup );
	}

	/**
	 * Stellt den Zustand vor einer Dateiänderung wieder her – nur wenn die Datei
	 * seitdem nicht erneut verändert wurde. Eingeloggt + Nonce + Passwort (im Admin geprüft).
	 */
	public static function revert( $change, WP_User $user, $password, $code = null ) {
		global $wpdb;
		if ( ! self::revertable( $change ) || (int) $change->user_id !== (int) $user->ID || ! user_can( $user, CMB_Settings::REQUIRED_CAP ) ) {
			return new WP_Error( 'revert', 'Diese Änderung kann nicht rückgängig gemacht werden.' );
		}
		$ok = self::verify_password( $user, $password, $code );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		if ( ! CMB_Power::enabled( 'files' ) ) {
			return new WP_Error( 'power_off', 'Dateifunktionen sind ausgeschaltet.' );
		}
		$b    = json_decode( $change->backup, true );
		$path = $change->payload['path'];
		$abs  = CMB_Power::resolve( $path, false );
		if ( is_wp_error( $abs ) ) {
			return $abs;
		}
		if ( 'file_write' === $change->action ) {
			$now_sha = is_file( $abs ) ? hash_file( 'sha256', $abs ) : null;
			if ( $now_sha !== ( $b['after_sha'] ?? false ) ) {
				return new WP_Error( 'stale', 'Die Datei wurde seitdem erneut verändert – automatische Wiederherstellung abgebrochen.' );
			}
			if ( empty( $b['existed'] ) ) {
				$r = CMB_Power::delete( $path, $now_sha );
			} else {
				$r = CMB_Power::write( $path, (string) $b['content'], $now_sha );
			}
		} else {
			$r = CMB_Power::write( $path, (string) $b['content'], null );
		}
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$wpdb->update( CMB_DB::t( 'changes' ), array( 'reverted' => 1 ), array( 'id' => $change->id ), array( '%d' ), array( '%d' ) );
		CMB_Security::audit( 'change_reverted', array( 'change' => $change->uuid, 'path' => $path ), $user->ID, $change->client_id );
		return 'Wiederhergestellt: ' . $path;
	}

	/* --------------------------------------------------------- sanitizing */

	/** Inhalt immer durch wp_kses_post – auch für Admins mit unfiltered_html (Schutz vor Stored XSS via Prompt Injection). */
	public static function clean_html( $html ) {
		return wp_kses_post( (string) $html );
	}

	public static function clean_text( $text, $max = 300 ) {
		return mb_substr( sanitize_text_field( (string) $text ), 0, $max );
	}

	/* -------------------------------------------------------------- execute */

	private static function stale( $post, $snapshot ) {
		return isset( $snapshot['modified_gmt'] ) && $post->post_modified_gmt !== $snapshot['modified_gmt'];
	}

	private static function execute( $action, array $p ) {
		switch ( $action ) {
			case 'create_post':
				return self::exec_create_post( $p );
			case 'update_post':
				return self::exec_update_post( $p );
			case 'trash_post':
				return self::exec_trash_post( $p );
			case 'update_settings':
				return self::exec_update_settings( $p );
			case 'moderate_comment':
				return self::exec_moderate_comment( $p );
			case 'update_media':
				return self::exec_update_media( $p );
			case 'file_write':
				$r = CMB_Power::write( $p['path'], (string) $p['content'], $p['expected_sha'] ?? null );
				if ( is_wp_error( $r ) ) {
					return $r;
				}
				return array(
					'message' => ( null === $r['before'] ? 'Angelegt: ' : 'Gespeichert: ' ) . $p['path'],
					'backup'  => array( 'existed' => null !== $r['before'], 'content' => $r['before'], 'after_sha' => $r['after_sha'] ),
				);
			case 'file_delete':
				$r = CMB_Power::delete( $p['path'], $p['expected_sha'] ?? '' );
				if ( is_wp_error( $r ) ) {
					return $r;
				}
				return array( 'message' => 'Gelöscht: ' . $p['path'], 'backup' => array( 'existed' => true, 'content' => $r['before'] ) );
			case 'php_exec':
				$r = CMB_Power::run_php( (string) $p['code'] );
				if ( is_wp_error( $r ) ) {
					return $r;
				}
				$msg = ( $r['error'] ? "FEHLER: {$r['error']}\n" : "OK\n" ) . ( '' !== $r['output'] ? "Ausgabe:\n{$r['output']}\n" : '' ) . ( null !== $r['return'] ? "Rückgabewert:\n{$r['return']}" : '' );
				return $r['error'] ? new WP_Error( 'php', $msg ) : $msg;
			case 'sql_exec':
				$r = CMB_Power::sql_execute( (string) $p['sql'] );
				return is_wp_error( $r ) ? $r : sprintf( 'OK – %d Zeilen betroffen%s', $r['rows_affected'], $r['insert_id'] ? ', insert_id ' . $r['insert_id'] : '' );
			case 'wpcli_run':
				$r = CMB_Power::cli_run( (array) $p['args'] );
				if ( is_wp_error( $r ) ) {
					return $r;
				}
				$msg = "Exit-Code {$r['exit_code']}" . ( $r['timed_out'] ? ' (Zeitlimit überschritten)' : '' ) . "\n" . $r['stdout'] . ( '' !== trim( $r['stderr'] ) ? "\nSTDERR:\n" . $r['stderr'] : '' );
				return 0 === $r['exit_code'] ? $msg : new WP_Error( 'cli', $msg );
			case 'plugin_action':
				$r = CMB_Power::plugin_action( $p['action'], $p['plugin'], ! empty( $p['activate'] ) );
				return $r;
		}
		return new WP_Error( 'bad_action', 'Unbekannte Aktion.' );
	}

	private static function post_fields( array $f ) {
		$out = array();
		if ( array_key_exists( 'title', $f ) ) {
			$out['post_title'] = self::clean_text( $f['title'], 300 );
		}
		if ( array_key_exists( 'content', $f ) ) {
			$out['post_content'] = self::clean_html( $f['content'] );
		}
		if ( array_key_exists( 'excerpt', $f ) ) {
			$out['post_excerpt'] = self::clean_html( $f['excerpt'] );
		}
		if ( array_key_exists( 'slug', $f ) ) {
			$out['post_name'] = sanitize_title( (string) $f['slug'] );
		}
		if ( array_key_exists( 'status', $f ) ) {
			$out['post_status'] = (string) $f['status'];
		}
		return $out;
	}

	private static function check_status_cap( $status, $post_type ) {
		$pto = get_post_type_object( $post_type );
		if ( ! $pto ) {
			return new WP_Error( 'type', 'Unbekannter Inhaltstyp.' );
		}
		if ( ! in_array( $status, array( 'draft', 'pending', 'publish', 'private' ), true ) ) {
			return new WP_Error( 'status', 'Ungültiger Status.' );
		}
		if ( in_array( $status, array( 'publish', 'private' ), true ) && ! current_user_can( $pto->cap->publish_posts ) ) {
			return new WP_Error( 'cap', 'Keine Berechtigung zum Veröffentlichen.' );
		}
		return true;
	}

	private static function apply_terms( $post_id, $post_type, array $p ) {
		if ( 'post' !== $post_type ) {
			return;
		}
		if ( isset( $p['categories'] ) && is_array( $p['categories'] ) ) {
			$ids = array_values( array_filter( array_map( 'absint', $p['categories'] ), function ( $id ) {
				return (bool) term_exists( $id, 'category' );
			} ) );
			wp_set_post_terms( $post_id, $ids, 'category' );
		}
		if ( isset( $p['tags'] ) && is_array( $p['tags'] ) ) {
			$tags = array_slice( array_filter( array_map( array( __CLASS__, 'clean_text' ), $p['tags'] ) ), 0, 20 );
			wp_set_post_terms( $post_id, $tags, 'post_tag' );
		}
	}

	private static function exec_create_post( array $p ) {
		$type = $p['post_type'] ?? '';
		if ( ! in_array( $type, array( 'post', 'page' ), true ) ) {
			return new WP_Error( 'type', 'Ungültiger Inhaltstyp.' );
		}
		$pto = get_post_type_object( $type );
		if ( ! current_user_can( $pto->cap->create_posts ) ) {
			return new WP_Error( 'cap', 'Keine Berechtigung.' );
		}
		$fields = self::post_fields( $p );
		$ok     = self::check_status_cap( $fields['post_status'] ?? 'draft', $type );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$fields['post_type']   = $type;
		$fields['post_author'] = get_current_user_id();
		$fields['post_status'] = $fields['post_status'] ?? 'draft';
		// wp_insert_post erwartet geslashte Daten.
		$id = wp_insert_post( wp_slash( $fields ), true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		self::apply_terms( $id, $type, $p );
		return sprintf( 'Erstellt: %s #%d (%s)', $type, $id, $fields['post_status'] );
	}

	private static function exec_update_post( array $p ) {
		$post = get_post( absint( $p['id'] ?? 0 ) );
		if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) || 'trash' === $post->post_status ) {
			return new WP_Error( 'missing', 'Inhalt existiert nicht mehr.' );
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'cap', 'Keine Berechtigung.' );
		}
		if ( self::stale( $post, $p['snapshot'] ?? array() ) ) {
			return new WP_Error( 'stale', 'Der Inhalt wurde seit dem Vorschlag geändert. Bitte Claude einen neuen Vorschlag erstellen lassen.' );
		}
		$fields = self::post_fields( $p['fields'] ?? array() );
		if ( isset( $fields['post_status'] ) ) {
			$ok = self::check_status_cap( $fields['post_status'], $post->post_type );
			if ( is_wp_error( $ok ) ) {
				return $ok;
			}
		}
		if ( $fields ) {
			$fields['ID'] = $post->ID;
			$r            = wp_update_post( wp_slash( $fields ), true );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
		self::apply_terms( $post->ID, $post->post_type, $p['fields'] ?? array() );
		return sprintf( 'Aktualisiert: %s #%d', $post->post_type, $post->ID );
	}

	private static function exec_trash_post( array $p ) {
		$post = get_post( absint( $p['id'] ?? 0 ) );
		if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) || 'trash' === $post->post_status ) {
			return new WP_Error( 'missing', 'Inhalt existiert nicht mehr.' );
		}
		if ( ! current_user_can( 'delete_post', $post->ID ) ) {
			return new WP_Error( 'cap', 'Keine Berechtigung.' );
		}
		if ( self::stale( $post, $p['snapshot'] ?? array() ) ) {
			return new WP_Error( 'stale', 'Der Inhalt wurde seit dem Vorschlag geändert.' );
		}
		// Nur Papierkorb – endgültiges Löschen ist über MCP nicht möglich.
		if ( ! wp_trash_post( $post->ID ) ) {
			return new WP_Error( 'trash', 'Konnte nicht in den Papierkorb verschoben werden.' );
		}
		return sprintf( 'In den Papierkorb verschoben: %s #%d (wiederherstellbar)', $post->post_type, $post->ID );
	}

	private static function exec_update_settings( array $p ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'cap', 'Keine Berechtigung.' );
		}
		$map  = array( 'title' => 'blogname', 'tagline' => 'blogdescription' );
		$done = array();
		foreach ( $map as $key => $option ) {
			if ( array_key_exists( $key, $p['fields'] ?? array() ) ) {
				$before = $p['before'][ $key ] ?? null;
				if ( null !== $before && get_option( $option ) !== $before ) {
					return new WP_Error( 'stale', 'Die Einstellung wurde seit dem Vorschlag geändert.' );
				}
				update_option( $option, self::clean_text( $p['fields'][ $key ], 200 ) );
				$done[] = $option;
			}
		}
		return 'Einstellungen aktualisiert: ' . implode( ', ', $done );
	}

	private static function exec_moderate_comment( array $p ) {
		$comment = get_comment( absint( $p['id'] ?? 0 ) );
		if ( ! $comment ) {
			return new WP_Error( 'missing', 'Kommentar existiert nicht mehr.' );
		}
		if ( ! current_user_can( 'edit_comment', $comment->comment_ID ) ) {
			return new WP_Error( 'cap', 'Keine Berechtigung.' );
		}
		if ( isset( $p['snapshot']['approved'] ) && (string) $comment->comment_approved !== (string) $p['snapshot']['approved'] ) {
			return new WP_Error( 'stale', 'Der Kommentarstatus hat sich seit dem Vorschlag geändert.' );
		}
		$map = array( 'approve' => 'approve', 'unapprove' => 'hold', 'spam' => 'spam', 'trash' => 'trash' );
		$to  = $map[ $p['action'] ?? '' ] ?? null;
		if ( ! $to ) {
			return new WP_Error( 'bad', 'Ungültige Aktion.' );
		}
		$r = wp_set_comment_status( $comment->comment_ID, $to, true );
		if ( is_wp_error( $r ) || ! $r ) {
			return new WP_Error( 'fail', 'Statusänderung fehlgeschlagen.' );
		}
		return sprintf( 'Kommentar #%d: %s', $comment->comment_ID, $to );
	}

	private static function exec_update_media( array $p ) {
		$post = get_post( absint( $p['id'] ?? 0 ) );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return new WP_Error( 'missing', 'Mediendatei existiert nicht mehr.' );
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'cap', 'Keine Berechtigung.' );
		}
		if ( self::stale( $post, $p['snapshot'] ?? array() ) ) {
			return new WP_Error( 'stale', 'Die Mediendatei wurde seit dem Vorschlag geändert.' );
		}
		$f   = $p['fields'] ?? array();
		$upd = array( 'ID' => $post->ID );
		if ( array_key_exists( 'title', $f ) ) {
			$upd['post_title'] = self::clean_text( $f['title'], 300 );
		}
		if ( array_key_exists( 'caption', $f ) ) {
			$upd['post_excerpt'] = self::clean_html( $f['caption'] );
		}
		if ( count( $upd ) > 1 ) {
			$r = wp_update_post( wp_slash( $upd ), true );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
		if ( array_key_exists( 'alt', $f ) ) {
			update_post_meta( $post->ID, '_wp_attachment_image_alt', wp_slash( self::clean_text( $f['alt'], 300 ) ) );
		}
		return sprintf( 'Mediendatei #%d aktualisiert', $post->ID );
	}
}
