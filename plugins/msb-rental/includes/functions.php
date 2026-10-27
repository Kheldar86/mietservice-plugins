<?php
/**
 * Allgemeine Hilfsfunktionen und Einstellungen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Standardwerte aller Einstellungen.
 */
function msb_settings_defaults() {
	$admin_email = get_option( 'admin_email' );
	$site        = get_bloginfo( 'name' );

	return array(
		// Allgemein.
		'currency'             => '€',
		'currency_pos'         => 'after',
		'decimals'             => 2,
		'price_note'           => 'inkl. MwSt.',
		'primary_color'        => '#2f6b3a',
		'booking_prefix'       => 'MB-',

		// Buchungsregeln.
		'count_mode'           => 'days',   // days = Kalendertage inkl. Abhol- und Rückgabetag, nights = Nächte.
		'buffer_days'          => 0,        // Puffertage nach jeder Rückgabe (Reinigung/Prüfung).
		'lead_days'            => 0,        // Vorlaufzeit in Tagen (0 = heute buchbar).
		'max_advance_days'     => 365,      // Wie weit im Voraus buchbar (0 = unbegrenzt).
		'closed_weekdays'      => array(),  // 0 = Sonntag … 6 = Samstag: keine Abholung/Rückgabe.
		'default_status'       => 'pending',
		'checkout_page'        => 0,
		'redirect_to_checkout' => 0,
		'auto_booking_box'     => 1,
		'calendar_months'      => 1,
		'thankyou_message'     => "Vielen Dank für Ihre Buchungsanfrage!\n\nIhre Buchungsnummer lautet <strong>{booking_number}</strong>. Sie erhalten in Kürze eine Bestätigung per E-Mail an {customer_email}.",
		'delete_on_uninstall'  => 0,

		// Preise (globale Preisregeln für alle Artikel).
		'price_rules'          => array(),
		'stack_discounts'      => 0,        // 0 = nur der höchste Rabatt pro Position, 1 = Rabatte addieren.

		// Zahlungen.
		'currency_code'        => 'EUR',
		'prepay_type'          => 'none',   // none | percent | fixed | full.
		'prepay_value'         => 30,
		'security_mode'        => 'onsite', // onsite = Kaution bei Abholung, hold = über Zahlungsanbieter reservieren.
		'security_hold_days'   => 2,        // Kaution X Tage vor Abholung reservieren.
		'security_auto_release'=> 1,        // Bei Status "Zurückgegeben" automatisch freigeben.
		'payment_timeout'      => 60,       // Minuten bis unbezahlte Online-Buchungen storniert werden.
		'offline_enabled'      => 1,
		'offline_label'        => 'Bezahlung bei Abholung (bar oder Karte)',
		'offline_text'         => '',
		'stripe_enabled'       => 0,
		'stripe_mode'          => 'test',
		'stripe_secret_test'   => '',
		'stripe_secret_live'   => '',
		'stripe_webhook_secret'=> '',
		'stripe_label'         => 'Kreditkarte, Apple Pay, Google Pay u. a. (Stripe)',
		'paypal_enabled'       => 0,
		'paypal_mode'          => 'sandbox',
		'paypal_client_id'     => '',
		'paypal_secret'        => '',
		'paypal_client_id_sandbox' => '',
		'paypal_secret_sandbox'    => '',
		'paypal_label'         => 'PayPal',

		// Kundenkonten.
		'accounts_enabled'     => 1,
		'account_page'         => 0,
		'account_required'     => 0,
		'hide_admin_bar'       => 1,

		// E-Mails.
		'admin_email'          => $admin_email,
		'from_name'            => $site,
		'from_email'           => $admin_email,

		'email_admin_enabled'  => 1,
		'email_admin_subject'  => 'Neue Buchung {booking_number} von {customer_name}',
		'email_admin_body'     => "Es ist eine neue Buchung eingegangen.\n\n{items}\n\nMietpreis: {total}\nKaution: {deposit}\n\n{customer_data}\n\nBuchung im Backend öffnen: {admin_link}",

		'email_received_enabled' => 1,
		'email_received_subject' => 'Ihre Buchungsanfrage {booking_number} bei {site_name}',
		'email_received_body'    => "Hallo {customer_name},\n\nvielen Dank für Ihre Anfrage. Wir prüfen die Verfügbarkeit und melden uns schnellstmöglich mit einer Bestätigung.\n\n{items}\n\nMietpreis: {total}\nKaution (bei Abholung): {deposit}\n\n{customer_data}\n\nViele Grüße\nIhr Team von {site_name}",

		'email_confirmed_enabled' => 1,
		'email_confirmed_subject' => 'Buchung {booking_number} bestätigt',
		'email_confirmed_body'    => "Hallo {customer_name},\n\nIhre Buchung ist bestätigt. Wir freuen uns auf Sie!\n\n{items}\n\nMietpreis: {total}\nKaution (bei Abholung): {deposit}\n\nViele Grüße\nIhr Team von {site_name}",

		'email_payment_link_enabled' => 1,
		'email_payment_link_subject' => 'Zahlung zu Ihrer Buchung {booking_number}',
		'email_payment_link_body'    => "Hallo {customer_name},\n\nfür Ihre Buchung {booking_number} ist noch folgender Betrag offen: <strong>{payment_amount}</strong> ({payment_purpose}).\n\nBitte nutzen Sie diesen Link:\n{payment_link}\n\n{items}\n\nViele Grüße\nIhr Team von {site_name}",

		'email_security_released_enabled' => 1,
		'email_security_released_subject' => 'Ihre Kaution zu {booking_number} wurde freigegeben',
		'email_security_released_body'    => "Hallo {customer_name},\n\nvielen Dank für die Rückgabe! Die reservierte Kaution von {deposit} wurde soeben freigegeben. Je nach Bank kann es einige Tage dauern, bis die Reservierung auf Ihrem Konto verschwindet.\n\nWir freuen uns auf Ihre nächste Buchung.\nIhr Team von {site_name}",

		'email_cancelled_enabled' => 1,
		'email_cancelled_subject' => 'Buchung {booking_number} storniert',
		'email_cancelled_body'    => "Hallo {customer_name},\n\nIhre Buchung {booking_number} wurde storniert.\n\nStornogebühr: {cancel_fee}\nErstattung: {refund_amount}\n\nBei Fragen melden Sie sich gerne bei uns.\n\nViele Grüße\nIhr Team von {site_name}",

		// Belege (Rechnungen und Kautionsabrechnungen).
		'doc_logo_id'              => 0,
		'doc_issuer_name'          => 'Mietservice Barnim GbR',
		'doc_issuer_address'       => "Willi-Seng-Str. 4\n16321 Bernau",
		'doc_issuer_contact'       => '',
		'doc_tax_number'           => '065/159/01432',
		'doc_show_tax_number'      => 1,
		'doc_vat_note'             => 'Gemäß § 19 UStG wird keine Umsatzsteuer berechnet (Kleinunternehmerregelung).',
		'doc_footer'               => '',
		'doc_prefix_invoice'       => 'RE',
		'doc_prefix_deposit'       => 'KAU',
		'doc_auto_invoice'         => 1,
		'doc_mail_enabled'         => 1,
		'review_url'               => 'https://g.page/r/Cft8th8ur-CSECE/review',
		'doc_mail_subject_invoice' => 'Ihre {document_type} {document_number} zu Buchung {booking_number}',
		'doc_mail_body_invoice'    => "Hallo {customer_name},\n\nanbei erhalten Sie Ihre {document_type} zu Ihrer Buchung {booking_number}.\n\n{document_summary}\n\n{document_note}\n\nViele Grüße\nIhr Team von {site_name}",
		'doc_mail_subject_deposit' => 'Ihre {document_type} {document_number} zu Buchung {booking_number}',
		'doc_mail_body_deposit'    => "Hallo {customer_name},\n\nanbei erhalten Sie Ihre {document_type} zu Ihrer Buchung {booking_number}.\n\n{document_summary}\n\n{document_note}\n\nBei Fragen melden Sie sich gerne bei uns.\n\nViele Grüße\nIhr Team von {site_name}",
	);
}

/**
 * Alle Einstellungen (mit Standardwerten ergänzt).
 */
function msb_settings() {
	$saved = get_option( 'msb_settings', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return wp_parse_args( $saved, msb_settings_defaults() );
}

function msb_setting( $key, $default = null ) {
	$s = msb_settings();
	return array_key_exists( $key, $s ) ? $s[ $key ] : $default;
}

/**
 * Berechtigung je Bereich (ROL-03): bookings (Buchungen, Kunden, Kalender, Zahlungen), delete (Buchungen löschen),
 * items (Mietartikel, Kategorien, KI-Assistent), settings (Einstellungen, Buchungsformular, Shortcodes).
 */
function msb_capability( $area = 'bookings' ) {
	$caps = array(
		'bookings' => 'msb_manage_bookings',
		'delete'   => 'msb_delete_bookings',
		'items'    => 'msb_manage_items',
		'settings' => 'msb_manage_settings',
	);
	return apply_filters( 'msb_capability', isset( $caps[ $area ] ) ? $caps[ $area ] : 'manage_options', $area );
}

/**
 * Rechte der Mietartikel (eigener Rechte-Typ statt „Beiträge“).
 */
function msb_item_caps() {
	return array( 'edit_msb_items', 'edit_others_msb_items', 'edit_published_msb_items', 'edit_private_msb_items', 'publish_msb_items', 'read_private_msb_items', 'delete_msb_items', 'delete_others_msb_items', 'delete_published_msb_items', 'delete_private_msb_items' );
}

/**
 * Rechte der Rolle „Gesellschafter“ (ROL-01, ROL-02): Buchungen und Artikel ja; Einstellungen, Löschen von
 * Buchungen und Artikeln, Plugins, Themes, Nutzer und WordPress-Einstellungen nein.
 */
function msb_partner_caps() {
	return array_merge(
		array( 'read', 'upload_files', 'msb_manage_bookings', 'msb_manage_items' ),
		array( 'edit_msb_items', 'edit_others_msb_items', 'edit_published_msb_items', 'edit_private_msb_items', 'publish_msb_items', 'read_private_msb_items' ),
		array( 'rank_math_onpage_general', 'rank_math_onpage_advanced', 'rank_math_onpage_snippet', 'rank_math_onpage_social' )
	);
}

// Rolle „Gesellschafter“ nach Updates aktuell halten.
add_action( 'admin_init', array( 'MSB_Install', 'add_role' ) );

/**
 * Administratoren haben immer alle Rechte der Vermietung, ohne dass ihre Rolle geändert werden muss.
 */
add_filter(
	'user_has_cap',
	function ( $allcaps ) {
		if ( ! empty( $allcaps['manage_options'] ) ) {
			foreach ( array_merge( array( 'msb_manage_bookings', 'msb_delete_bookings', 'msb_manage_items', 'msb_manage_settings' ), msb_item_caps() ) as $cap ) {
				$allcaps[ $cap ] = true;
			}
		}
		return $allcaps;
	}
);

/**
 * Preis formatieren, z. B. "12,50 €".
 */
function msb_price( $amount ) {
	$decimals = (int) msb_setting( 'decimals', 2 );
	$number   = number_format( (float) $amount, $decimals, ',', '.' );
	$currency = msb_setting( 'currency', '€' );

	return 'before' === msb_setting( 'currency_pos' ) ? $currency . ' ' . $number : $number . ' ' . $currency;
}

/**
 * Datum Y-m-d → d.m.Y.
 */
function msb_date( $ymd ) {
	if ( ! $ymd || ! msb_valid_date( $ymd ) ) {
		return '';
	}
	$d = DateTime::createFromFormat( '!Y-m-d', $ymd );
	return $d->format( 'd.m.Y' );
}

function msb_valid_date( $s ) {
	if ( ! is_string( $s ) ) {
		return false;
	}
	$d = DateTime::createFromFormat( '!Y-m-d', $s );
	return $d && $d->format( 'Y-m-d' ) === $s;
}

/**
 * Wandelt d.m.Y oder Y-m-d in Y-m-d um (sonst leerer String).
 */
function msb_normalize_date( $s ) {
	$s = trim( (string) $s );
	if ( preg_match( '/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $s, $m ) ) {
		$s = sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] );
	}
	return msb_valid_date( $s ) ? $s : '';
}

function msb_today() {
	return current_time( 'Y-m-d' );
}

function msb_add_days( $ymd, $n ) {
	$d = DateTime::createFromFormat( '!Y-m-d', $ymd );
	$n = (int) $n;
	$d->modify( ( $n >= 0 ? '+' : '' ) . $n . ' days' );
	return $d->format( 'Y-m-d' );
}

/**
 * Alle Tage zwischen $from und $to (inklusive).
 */
function msb_date_range( $from, $to ) {
	$out = array();
	if ( ! msb_valid_date( $from ) || ! msb_valid_date( $to ) || $from > $to ) {
		return $out;
	}
	$d   = DateTime::createFromFormat( '!Y-m-d', $from );
	$end = DateTime::createFromFormat( '!Y-m-d', $to );
	while ( $d <= $end ) {
		$out[] = $d->format( 'Y-m-d' );
		$d->modify( '+1 day' );
	}
	return $out;
}

function msb_weekday( $ymd ) {
	$d = DateTime::createFromFormat( '!Y-m-d', $ymd );
	return (int) $d->format( 'w' );
}

function msb_weekday_names() {
	return array( 'Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag' );
}

/**
 * Buchungsstatus.
 */
function msb_statuses() {
	return apply_filters(
		'msb_statuses',
		array(
			'awaiting_payment' => 'Zahlung ausstehend',
			'pending'   => 'Angefragt',
			'confirmed' => 'Bestätigt',
			'picked_up' => 'Abgeholt',
			'returned'  => 'Zurückgegeben',
			'cancelled' => 'Storniert',
		)
	);
}

function msb_status_label( $status ) {
	$all = msb_statuses();
	return isset( $all[ $status ] ) ? $all[ $status ] : $status;
}

/**
 * Status, die Inventar blockieren.
 */
function msb_blocking_statuses() {
	return apply_filters( 'msb_blocking_statuses', array( 'awaiting_payment', 'pending', 'confirmed', 'picked_up' ) );
}

/**
 * Erlaubte Statuswechsel (BKG-16). Schlüssel = aktueller Status, Werte = mögliche Ziele.
 */
function msb_status_transitions() {
	return apply_filters(
		'msb_status_transitions',
		array(
			'awaiting_payment' => array( 'pending', 'confirmed', 'cancelled' ),
			'pending'          => array( 'confirmed', 'cancelled' ),
			'confirmed'        => array( 'picked_up', 'cancelled' ),
			'picked_up'        => array( 'returned' ),
			'returned'         => array(),
			'cancelled'        => array(),
		)
	);
}

/**
 * Wege zurück aus „Zurückgegeben“ und „Storniert“. Nur für Administratoren (BKG-16).
 */
function msb_status_admin_transitions() {
	return apply_filters(
		'msb_status_admin_transitions',
		array(
			'returned'  => array( 'picked_up' ),
			'cancelled' => array( 'pending', 'confirmed' ),
		)
	);
}

/**
 * Ziele, die von $from aus erlaubt sind. Administratoren dürfen zusätzlich zurück aus „Zurückgegeben“ und „Storniert“.
 */
function msb_allowed_statuses( $from ) {
	$all     = msb_status_transitions();
	$allowed = isset( $all[ $from ] ) ? $all[ $from ] : array();
	if ( current_user_can( 'manage_options' ) ) {
		$admin   = msb_status_admin_transitions();
		$allowed = array_merge( $allowed, isset( $admin[ $from ] ) ? $admin[ $from ] : array() );
	}
	return array_values( array_unique( array_intersect( $allowed, array_keys( msb_statuses() ) ) ) );
}

function msb_status_allowed( $from, $to ) {
	return in_array( $to, msb_allowed_statuses( $from ), true );
}

/**
 * Nächster Wert eines Jahreszählers, z. B. für Buchungs- und Rechnungsnummern (BKG-07).
 * Atomar über die Tabelle msb_sequences, beginnt jedes Jahr bei 1. Gibt 0 bei einem Datenbankfehler zurück.
 */
function msb_next_sequence( $name, $year = 0 ) {
	global $wpdb;
	$year = $year ? (int) $year : (int) current_time( 'Y' );
	$ok   = $wpdb->query( $wpdb->prepare( 'INSERT INTO ' . MSB_Install::table( 'sequences' ) . ' (name, year, value) VALUES (%s, %d, LAST_INSERT_ID(1)) ON DUPLICATE KEY UPDATE value = LAST_INSERT_ID(value + 1)', sanitize_key( $name ), $year ) ); // phpcs:ignore
	if ( false === $ok ) {
		return 0;
	}
	return (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
}

function msb_random_token() {
	return bin2hex( random_bytes( 16 ) );
}

function msb_account_url() {
	$page = (int) msb_setting( 'account_page' );
	return $page ? get_permalink( $page ) : '';
}

/**
 * Link zur Dokumentation (Backend).
 */
function msb_help_link( $anchor, $text = 'Hilfe' ) {
	return '<a class="msb-help-link" href="' . esc_url( admin_url( 'admin.php?page=msb-docs#' . $anchor ) ) . '" title="In der Dokumentation nachlesen"><span class="dashicons dashicons-editor-help"></span>' . ( $text ? ' ' . esc_html( $text ) : '' ) . '</a>';
}

/**
 * Betrag in kleinster Währungseinheit (Cent).
 */
function msb_cents( $amount ) {
	return (int) round( (float) $amount * 100 );
}

function msb_weekday_short() {
	return array( 'So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa' );
}

function msb_checkout_url() {
	$page = (int) msb_setting( 'checkout_page' );
	return $page ? get_permalink( $page ) : '';
}

/**
 * Hilfsfunktion: Zeilen eines Textfeldes.
 */
function msb_lines( $text ) {
	$lines = preg_split( '/\r\n|\r|\n/', (string) $text );
	return array_values( array_filter( array_map( 'trim', $lines ), 'strlen' ) );
}
