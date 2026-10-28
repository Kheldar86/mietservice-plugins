<?php
/**
 * Plugin Name:       Mietservice Barnim – Vermietung
 * Plugin URI:        https://www.mietservice-barnim.de
 * Description:       Vermietung von Outdoor- und Transportartikeln: Inventar, Buchungskalender, dynamische Preise, Bildergalerien, Online-Zahlung (Stripe, PayPal) mit Anzahlung und Kaution, Kundenverwaltung mit optionalem Kundenkonto, Buchungsübersicht, Shortcodes und KI-Artikelassistent.
 * Version:           1.8.5
 * Author:            Mietservice Barnim
 * Text Domain:       msb-rental
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MSB_VERSION', '1.8.5' );
define( 'MSB_FILE', __FILE__ );
define( 'MSB_DIR', plugin_dir_path( __FILE__ ) );
define( 'MSB_URL', plugin_dir_url( __FILE__ ) );

require_once MSB_DIR . 'includes/functions.php';
require_once MSB_DIR . 'includes/class-msb-install.php';
require_once MSB_DIR . 'includes/class-msb-items.php';
require_once MSB_DIR . 'includes/class-msb-pricing.php';
require_once MSB_DIR . 'includes/class-msb-availability.php';
require_once MSB_DIR . 'includes/class-msb-cart.php';
require_once MSB_DIR . 'includes/class-msb-form-fields.php';
require_once MSB_DIR . 'includes/class-msb-customers.php';
require_once MSB_DIR . 'includes/class-msb-bookings.php';
require_once MSB_DIR . 'includes/gateways/class-msb-gateway-stripe.php';
require_once MSB_DIR . 'includes/gateways/class-msb-gateway-paypal.php';
require_once MSB_DIR . 'includes/class-msb-payments.php';
require_once MSB_DIR . 'includes/class-msb-emails.php';
require_once MSB_DIR . 'includes/class-msb-cancellation.php';
require_once MSB_DIR . 'includes/class-msb-pdf.php';
if ( file_exists( MSB_DIR . 'includes/class-msb-legal-pdf.php' ) ) {
	require_once MSB_DIR . 'includes/class-msb-legal-pdf.php';
}
require_once MSB_DIR . 'includes/class-msb-documents.php';
require_once MSB_DIR . 'includes/class-msb-document-mails.php';
require_once MSB_DIR . 'includes/class-msb-terms-confirm.php';
require_once MSB_DIR . 'includes/class-msb-accounts.php';
require_once MSB_DIR . 'includes/class-msb-retention.php';
require_once MSB_DIR . 'includes/class-msb-euer.php';
require_once MSB_DIR . 'includes/class-msb-ajax.php';
require_once MSB_DIR . 'includes/class-msb-shortcodes.php';
if ( file_exists( MSB_DIR . 'includes/class-msb-schema.php' ) ) {
	require_once MSB_DIR . 'includes/class-msb-schema.php';
}
if ( file_exists( MSB_DIR . 'includes/class-msb-kleinanzeigen.php' ) ) {
	require_once MSB_DIR . 'includes/class-msb-kleinanzeigen.php';
}
if ( file_exists( MSB_DIR . 'includes/class-msb-reviews.php' ) ) {
	require_once MSB_DIR . 'includes/class-msb-reviews.php';
}

if ( is_admin() ) {
	require_once MSB_DIR . 'admin/class-msb-admin.php';
	require_once MSB_DIR . 'admin/class-msb-admin-bookings.php';
	require_once MSB_DIR . 'admin/class-msb-admin-booking-customer.php';
	require_once MSB_DIR . 'admin/class-msb-admin-customers.php';
	require_once MSB_DIR . 'admin/class-msb-docs.php';
	if ( file_exists( MSB_DIR . 'admin/class-msb-assistant.php' ) ) {
		require_once MSB_DIR . 'admin/class-msb-assistant.php';
	}
}

register_activation_hook( __FILE__, array( 'MSB_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MSB_Install', 'deactivate' ) );

add_action( 'plugins_loaded', 'msb_boot' );

function msb_boot() {
	add_action( 'init', array( 'MSB_Install', 'maybe_upgrade' ), 5 );
	add_action( 'init', 'msb_maybe_flush_rewrite', 99 );

	MSB_Items::init();
	MSB_Ajax::init();
	MSB_Shortcodes::init();
	MSB_Emails::init();
	MSB_Payments::init();
	MSB_Cancellation::init();
	MSB_Terms_Confirm::init();
	MSB_Accounts::init();
	MSB_Documents::init();
	MSB_Document_Mails::init();
	MSB_Retention::init();
	MSB_Euer::init();
	if ( class_exists( 'MSB_Schema' ) ) {
		MSB_Schema::init();
	}
	if ( class_exists( 'MSB_Kleinanzeigen' ) ) {
		MSB_Kleinanzeigen::init();
	}
	if ( class_exists( 'MSB_Reviews' ) ) {
		MSB_Reviews::init();
	}

	if ( is_admin() ) {
		MSB_Admin::init();
		MSB_Admin_Bookings::init();
		MSB_Admin_Booking_Customer::init();
		MSB_Admin_Customers::init();
		MSB_Docs::init();
		if ( class_exists( 'MSB_Assistant' ) ) {
			MSB_Assistant::init();
		}
	}
}

/**
 * Permalinks nach einem Update einmalig neu aufbauen.
 */
function msb_maybe_flush_rewrite() {
	if ( get_option( 'msb_flush_rewrite' ) ) {
		delete_option( 'msb_flush_rewrite' );
		flush_rewrite_rules();
	}
}
