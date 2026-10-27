<?php
/**
 * Entfernt Daten nur, wenn dies in den Einstellungen ausdrücklich aktiviert wurde.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$msb_settings = get_option( 'msb_settings', array() );
if ( empty( $msb_settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}msb_booking_items" ); // phpcs:ignore
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}msb_bookings" ); // phpcs:ignore
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}msb_customers" ); // phpcs:ignore
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}msb_payments" ); // phpcs:ignore
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}msb_sequences" ); // phpcs:ignore
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}msb_documents" ); // phpcs:ignore
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}msb_deletion_log" ); // phpcs:ignore
remove_role( 'msb_customer' );
remove_role( 'msb_partner' );
delete_option( 'msb_roles_version' );
wp_clear_scheduled_hook( 'msb_cron_hourly' );
wp_clear_scheduled_hook( 'msb_cron_daily' );
delete_option( 'msb_retention_since' );
delete_option( 'msb_retention_last' );

delete_option( 'msb_settings' );
delete_option( 'msb_form_fields' );
delete_option( 'msb_db_version' );
delete_option( 'msb_ai_settings' );
delete_option( 'msb_ka_rules' );

$msb_posts = get_posts(
	array(
		'post_type'      => 'msb_item',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $msb_posts as $msb_id ) {
	wp_delete_post( $msb_id, true );
}
