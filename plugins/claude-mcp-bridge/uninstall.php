<?php
// Entfernt beim Löschen des Plugins alle Tabellen und Optionen.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
foreach ( array( 'clients', 'tokens', 'changes', 'audit' ) as $t ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'cmb_' . $t ); // phpcs:ignore
}
delete_option( 'cmb_settings' );
delete_option( 'cmb_db_version' );
wp_clear_scheduled_hook( 'cmb_daily_cleanup' );
