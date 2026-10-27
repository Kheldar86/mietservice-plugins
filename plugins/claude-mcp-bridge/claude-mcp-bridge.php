<?php
/**
 * Plugin Name:       Claude MCP Bridge
 * Description:       MCP-Server für Claude (Web, Desktop, Mobile). OAuth 2.1 mit PKCE, Kopplungsfenster, und jede Schreibaktion braucht deine ausdrückliche Freigabe im WordPress-Backend.
 * Version:           0.4.2
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Moritz Kästner
 * License:           GPL-2.0-or-later
 * Text Domain:       claude-mcp-bridge
 */

defined( 'ABSPATH' ) || exit;

define( 'CMB_VERSION', '0.4.2' );
define( 'CMB_DB_VERSION', '2' );
define( 'CMB_FILE', __FILE__ );
define( 'CMB_DIR', plugin_dir_path( __FILE__ ) );
define( 'CMB_NS', 'cmb/v1' );

if ( ! function_exists( 'array_is_list' ) ) { // PHP 8.0
	function array_is_list( array $a ) {
		return array() === $a || array_keys( $a ) === range( 0, count( $a ) - 1 );
	}
}

require_once CMB_DIR . 'includes/class-cmb-settings.php';
require_once CMB_DIR . 'includes/class-cmb-db.php';
require_once CMB_DIR . 'includes/class-cmb-security.php';
require_once CMB_DIR . 'includes/class-cmb-totp.php';
require_once CMB_DIR . 'includes/class-cmb-oauth.php';
require_once CMB_DIR . 'includes/class-cmb-power.php';
require_once CMB_DIR . 'includes/class-cmb-changes.php';
require_once CMB_DIR . 'includes/class-cmb-tools.php';
require_once CMB_DIR . 'includes/class-cmb-mcp-server.php';
require_once CMB_DIR . 'includes/class-cmb-admin.php';

register_activation_hook( __FILE__, array( 'CMB_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CMB_Plugin', 'deactivate' ) );

final class CMB_Plugin {

	public static function activate() {
		CMB_DB::install();
		if ( ! wp_next_scheduled( 'cmb_daily_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'cmb_daily_cleanup' );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'cmb_daily_cleanup' );
		// Beim Deaktivieren alle Verbindungen trennen, damit eine spätere
		// Reaktivierung nicht stillschweigend alte Tokens wieder gültig macht.
		CMB_DB::revoke_all_tokens();
		CMB_Settings::update( array( 'enabled' => false, 'pairing_until' => 0 ) );
		CMB_Security::audit( 'plugin_deactivated' );
	}

	public static function init() {
		CMB_DB::maybe_upgrade();
		CMB_OAuth::init();
		CMB_MCP_Server::init();
		CMB_Admin::init();

		add_action( 'cmb_daily_cleanup', array( 'CMB_DB', 'cleanup' ) );

		// Tokens sofort ungültig machen, wenn sich am Konto etwas Sicherheitsrelevantes ändert.
		add_action( 'delete_user', array( __CLASS__, 'revoke_user' ) );
		add_action( 'set_user_role', array( __CLASS__, 'revoke_user' ) );
		add_action( 'after_password_reset', array( __CLASS__, 'revoke_user_obj' ) );
		add_action( 'profile_update', array( __CLASS__, 'on_profile_update' ), 10, 2 );
	}

	public static function revoke_user( $user_id ) {
		CMB_DB::revoke_tokens_for_user( (int) $user_id );
		CMB_Security::audit( 'tokens_revoked_user_change', array(), (int) $user_id );
	}

	public static function revoke_user_obj( $user ) {
		if ( $user instanceof WP_User ) {
			self::revoke_user( $user->ID );
		}
	}

	public static function on_profile_update( $user_id, $old_user_data ) {
		$user = get_userdata( $user_id );
		if ( $user && $old_user_data && $user->user_pass !== $old_user_data->user_pass ) {
			self::revoke_user( $user_id );
		}
	}
}

add_action( 'plugins_loaded', array( 'CMB_Plugin', 'init' ) );
