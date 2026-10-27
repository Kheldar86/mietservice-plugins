<?php
defined( 'ABSPATH' ) || exit;

/**
 * Datenbank-Schicht. Tokens und Codes werden ausschließlich als HMAC-Hash gespeichert.
 * Jede Abfrage läuft über $wpdb->prepare().
 */
final class CMB_DB {

	public static function t( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'cmb_' . $name;
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();

		dbDelta( 'CREATE TABLE ' . self::t( 'clients' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id varchar(64) NOT NULL,
			secret_hash char(64) DEFAULT NULL,
			name varchar(190) NOT NULL,
			redirect_uris text NOT NULL,
			source varchar(16) NOT NULL,
			created_at int(10) unsigned NOT NULL,
			last_used_at int(10) unsigned DEFAULT NULL,
			revoked tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY client_id (client_id)
		) $c;" );

		dbDelta( 'CREATE TABLE ' . self::t( 'tokens' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token_hash char(64) NOT NULL,
			type varchar(10) NOT NULL,
			client_id varchar(64) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			scope varchar(190) NOT NULL,
			family char(32) NOT NULL,
			data text DEFAULT NULL,
			expires_at int(10) unsigned NOT NULL,
			used tinyint(1) NOT NULL DEFAULT 0,
			revoked tinyint(1) NOT NULL DEFAULT 0,
			created_at int(10) unsigned NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY family (family),
			KEY client_id (client_id),
			KEY user_id (user_id)
		) $c;" );

		dbDelta( 'CREATE TABLE ' . self::t( 'changes' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			uuid char(32) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			client_id varchar(64) NOT NULL,
			action varchar(40) NOT NULL,
			payload longtext NOT NULL,
			payload_hash char(64) NOT NULL,
			summary varchar(255) NOT NULL,
			risk varchar(10) NOT NULL DEFAULT 'low',
			status varchar(12) NOT NULL,
			created_at int(10) unsigned NOT NULL,
			expires_at int(10) unsigned NOT NULL,
			decided_at int(10) unsigned DEFAULT NULL,
			decided_by bigint(20) unsigned DEFAULT NULL,
			result mediumtext DEFAULT NULL,
			backup longtext DEFAULT NULL,
			reverted tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			KEY status (status),
			KEY user_id (user_id)
		) $c;" );

		dbDelta( 'CREATE TABLE ' . self::t( 'audit' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at int(10) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			client_id varchar(64) NOT NULL DEFAULT '',
			event varchar(40) NOT NULL,
			ip varchar(45) NOT NULL DEFAULT '',
			detail text DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at)
		) $c;" );

		update_option( 'cmb_db_version', CMB_DB_VERSION, false );
		if ( false === get_option( CMB_Settings::OPTION, false ) ) {
			add_option( CMB_Settings::OPTION, CMB_Settings::defaults(), '', true );
		}
	}

	public static function maybe_upgrade() {
		if ( get_option( 'cmb_db_version' ) !== CMB_DB_VERSION ) {
			self::install();
		}
	}

	/* ------------------------------------------------------------------ clients */

	public static function get_client( $client_id ) {
		global $wpdb;
		if ( ! is_string( $client_id ) || ! preg_match( '/^cmb_[A-Za-z0-9_-]{16,60}$/', $client_id ) ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'clients' ) . ' WHERE client_id = %s AND revoked = 0', $client_id ) );
		if ( $row ) {
			$row->redirect_uris = json_decode( $row->redirect_uris, true ) ?: array();
		}
		return $row;
	}

	public static function count_active_clients() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::t( 'clients' ) . ' WHERE revoked = 0' );
	}

	public static function create_client( $name, array $redirect_uris, $with_secret, $source ) {
		global $wpdb;
		$client_id = 'cmb_' . CMB_Security::random_token( 18 );
		$secret    = $with_secret ? CMB_Security::random_token( 32 ) : null;
		$wpdb->insert(
			self::t( 'clients' ),
			array(
				'client_id'     => $client_id,
				'secret_hash'   => $secret ? CMB_Security::hash( $secret ) : null,
				'name'          => $name,
				'redirect_uris' => wp_json_encode( array_values( $redirect_uris ) ),
				'source'        => $source,
				'created_at'    => time(),
				'revoked'       => 0,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
		);
		return array( $client_id, $secret );
	}

	public static function revoke_client( $client_id ) {
		global $wpdb;
		$wpdb->update( self::t( 'clients' ), array( 'revoked' => 1 ), array( 'client_id' => $client_id ), array( '%d' ), array( '%s' ) );
		$wpdb->update( self::t( 'tokens' ), array( 'revoked' => 1 ), array( 'client_id' => $client_id ), array( '%d' ), array( '%s' ) );
	}

	public static function touch_client( $client_id ) {
		global $wpdb;
		$wpdb->update( self::t( 'clients' ), array( 'last_used_at' => time() ), array( 'client_id' => $client_id ), array( '%d' ), array( '%s' ) );
	}

	public static function list_clients() {
		global $wpdb;
		$tokens = self::t( 'tokens' );
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT c.*, (SELECT COUNT(*) FROM ' . $tokens . " t WHERE t.client_id = c.client_id AND t.type = 'refresh' AND t.revoked = 0 AND t.used = 0 AND t.expires_at > %d) AS active_sessions FROM " . self::t( 'clients' ) . ' c WHERE c.revoked = 0 ORDER BY c.created_at DESC',
				time()
			)
		);
	}

	/* ------------------------------------------------------------------- tokens */

	/**
	 * Legt ein Token an und gibt den Klartext genau einmal zurück.
	 */
	public static function issue_token( $type, $client_id, $user_id, $scope, $family, $ttl, array $data = array() ) {
		global $wpdb;
		$plain = CMB_Security::random_token( 32 );
		$wpdb->insert(
			self::t( 'tokens' ),
			array(
				'token_hash' => CMB_Security::hash( $plain ),
				'type'       => $type,
				'client_id'  => $client_id,
				'user_id'    => (int) $user_id,
				'scope'      => $scope,
				'family'     => $family,
				'data'       => $data ? wp_json_encode( $data ) : null,
				'expires_at' => time() + (int) $ttl,
				'used'       => 0,
				'revoked'    => 0,
				'created_at' => time(),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d' )
		);
		return $plain;
	}

	public static function find_token( $plain, $type ) {
		global $wpdb;
		if ( ! is_string( $plain ) || strlen( $plain ) < 20 || strlen( $plain ) > 100 ) {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::t( 'tokens' ) . ' WHERE token_hash = %s AND type = %s', CMB_Security::hash( $plain ), $type )
		);
		if ( $row ) {
			$row->data = $row->data ? ( json_decode( $row->data, true ) ?: array() ) : array();
		}
		return $row;
	}

	/**
	 * Markiert ein Einmal-Token (Code/Refresh) atomar als benutzt.
	 * Gibt nur dann true zurück, wenn genau dieser Aufruf es "gewonnen" hat.
	 */
	public static function consume_token( $id ) {
		global $wpdb;
		$affected = $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::t( 'tokens' ) . ' SET used = 1 WHERE id = %d AND used = 0 AND revoked = 0', $id ) );
		return 1 === (int) $affected;
	}

	public static function revoke_family( $family ) {
		global $wpdb;
		$wpdb->update( self::t( 'tokens' ), array( 'revoked' => 1 ), array( 'family' => $family ), array( '%d' ), array( '%s' ) );
	}

	public static function revoke_tokens_for_user( $user_id ) {
		global $wpdb;
		$wpdb->update( self::t( 'tokens' ), array( 'revoked' => 1 ), array( 'user_id' => (int) $user_id ), array( '%d' ), array( '%d' ) );
	}

	public static function revoke_all_tokens() {
		global $wpdb;
		$table = self::t( 'tokens' );
		// Tabelle existiert evtl. noch nicht (Deaktivierung direkt nach fehlgeschlagener Installation).
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			$wpdb->query( 'UPDATE ' . $table . ' SET revoked = 1' );
		}
	}

	/* ------------------------------------------------------------------ cleanup */

	public static function cleanup() {
		global $wpdb;
		$now = time();
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::t( 'tokens' ) . ' WHERE expires_at < %d', $now - DAY_IN_SECONDS ) );
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::t( 'changes' ) . " SET status = 'expired' WHERE status = 'pending' AND expires_at < %d", $now ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::t( 'changes' ) . " WHERE status <> 'pending' AND created_at < %d", $now - 90 * DAY_IN_SECONDS ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::t( 'audit' ) . ' WHERE created_at < %d', $now - 90 * DAY_IN_SECONDS ) );
		// Verwaiste Registrierungen (nie zu einem Token gekommen) nach einem Tag entfernen.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . self::t( 'clients' ) . " WHERE source = 'dcr' AND last_used_at IS NULL AND created_at < %d AND client_id NOT IN (SELECT client_id FROM " . self::t( 'tokens' ) . ')',
				$now - DAY_IN_SECONDS
			)
		);
	}
}
