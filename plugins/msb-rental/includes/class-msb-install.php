<?php
/**
 * Installation & Updates: Datenbanktabellen, Standardoptionen, Seiten, Benutzerrolle, Cron.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Install {

	const DB_VERSION = '1.8.1';

	public static function activate() {
		self::create_tables();

		if ( false === get_option( 'msb_settings' ) ) {
			add_option( 'msb_settings', msb_settings_defaults() );
		}
		if ( false === get_option( 'msb_form_fields' ) ) {
			add_option( 'msb_form_fields', MSB_Form_Fields::defaults() );
		}

		self::create_pages();
		self::add_role();
		self::schedule_cron();

		MSB_Items::register();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'msb_cron_hourly' );
		wp_clear_scheduled_hook( 'msb_cron_daily' );
		flush_rewrite_rules();
	}

	/**
	 * Wird bei jedem Seitenaufruf geprüft: führt nach einem Plugin-Update die nötigen Schritte aus.
	 */
	public static function maybe_upgrade() {
		$installed = get_option( 'msb_db_version' );
		if ( self::DB_VERSION === $installed ) {
			return;
		}
		self::create_tables();
		self::create_pages();
		self::add_role();
		self::schedule_cron();

		// 1.0.x → 1.1.0: Formularfelder um "Kundenstammdaten" ergänzen.
		if ( $installed && version_compare( $installed, '1.1.0', '<' ) ) {
			$fields = get_option( 'msb_form_fields' );
			if ( is_array( $fields ) ) {
				foreach ( $fields as &$f ) {
					if ( ! isset( $f['profile'] ) ) {
						$f['profile'] = MSB_Form_Fields::default_profile_flag( $f );
					}
				}
				unset( $f );
				update_option( 'msb_form_fields', $fields );
			}
			// Bestehenden Buchungen einen Zugriffs-Token geben.
			global $wpdb;
			$ids = $wpdb->get_col( 'SELECT id FROM ' . self::table( 'bookings' ) . " WHERE token = '' OR token IS NULL" ); // phpcs:ignore
			foreach ( $ids as $id ) {
				$wpdb->update( self::table( 'bookings' ), array( 'token' => msb_random_token() ), array( 'id' => (int) $id ) );
			}
			update_option( 'msb_flush_rewrite', 1 );
		}

		// 1.1.x → 1.2.0: Jahreszähler für Buchungsnummern (BKG-07). Der Zähler des laufenden Jahres
		// startet hinter der höchsten vorhandenen Nummer, damit keine Nummer doppelt vergeben wird.
		if ( $installed && version_compare( $installed, '1.2.0', '<' ) ) {
			global $wpdb;
			$year = (int) current_time( 'Y' );
			$max  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(CAST(SUBSTRING_INDEX(booking_number, \'-\', -1) AS UNSIGNED)) FROM ' . self::table( 'bookings' ) . ' WHERE booking_number LIKE %s', '%' . $wpdb->esc_like( $year . '-' ) . '%' ) ); // phpcs:ignore
			if ( $max > 0 ) {
				$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::table( 'sequences' ) . ' (name, year, value) VALUES (%s, %d, %d) ON DUPLICATE KEY UPDATE value = GREATEST(value, %d)', 'booking', $year, $max, $max ) ); // phpcs:ignore
			}
		}

		// 1.6.0: Löschfristen starten im Probelauf (DSG-07).
		if ( ! get_option( 'msb_retention_since' ) ) {
			add_option( 'msb_retention_since', current_time( 'mysql' ), '', 'no' );
		}
	}

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'msb_' . $name;
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset   = $wpdb->get_charset_collate();
		$bookings  = self::table( 'bookings' );
		$items     = self::table( 'booking_items' );
		$customers = self::table( 'customers' );
		$payments  = self::table( 'payments' );
		$sequences = self::table( 'sequences' );
		$documents = self::table( 'documents' );
		$deletion  = self::table( 'deletion_log' );

		$sql = "CREATE TABLE {$bookings} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_number varchar(40) NOT NULL DEFAULT '',
			token varchar(64) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pending',
			customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			customer_name varchar(200) NOT NULL DEFAULT '',
			customer_email varchar(200) NOT NULL DEFAULT '',
			customer_phone varchar(60) NOT NULL DEFAULT '',
			form_data longtext NULL,
			total decimal(12,2) NOT NULL DEFAULT 0,
			discount decimal(12,2) NOT NULL DEFAULT 0,
			deposit decimal(12,2) NOT NULL DEFAULT 0,
			prepay decimal(12,2) NOT NULL DEFAULT 0,
			payment_method varchar(20) NOT NULL DEFAULT '',
			admin_notes text NULL,
			source varchar(20) NOT NULL DEFAULT 'web',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			cancel_fee decimal(12,2) NULL DEFAULT NULL,
			cancelled_by varchar(20) NOT NULL DEFAULT '',
			cancelled_at datetime NULL DEFAULT NULL,
			blocked_until date NULL DEFAULT NULL,
			is_test tinyint(1) NOT NULL DEFAULT 0,
			channel varchar(20) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY token (token),
			KEY customer_id (customer_id),
			KEY created_at (created_at)
		) {$charset};
		CREATE TABLE {$items} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			item_id bigint(20) unsigned NOT NULL,
			item_title varchar(255) NOT NULL DEFAULT '',
			quantity int(11) NOT NULL DEFAULT 1,
			start_date date NOT NULL,
			end_date date NOT NULL,
			days int(11) NOT NULL DEFAULT 1,
			day_rate decimal(12,2) NOT NULL DEFAULT 0,
			base_fee decimal(12,2) NOT NULL DEFAULT 0,
			base_total decimal(12,2) NOT NULL DEFAULT 0,
			line_total decimal(12,2) NOT NULL DEFAULT 0,
			deposit decimal(12,2) NOT NULL DEFAULT 0,
			adjustments longtext NULL,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY item_dates (item_id,start_date,end_date)
		) {$charset};
		CREATE TABLE {$customers} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			email varchar(200) NOT NULL DEFAULT '',
			first_name varchar(100) NOT NULL DEFAULT '',
			last_name varchar(100) NOT NULL DEFAULT '',
			phone varchar(60) NOT NULL DEFAULT '',
			city varchar(100) NOT NULL DEFAULT '',
			data longtext NULL,
			notes text NULL,
			stripe_customer varchar(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY email (email)
		) {$charset};
		CREATE TABLE {$payments} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			provider varchar(20) NOT NULL DEFAULT '',
			type varchar(20) NOT NULL DEFAULT '',
			amount decimal(12,2) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'pending',
			provider_ref varchar(191) NOT NULL DEFAULT '',
			data longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY provider_ref (provider_ref),
			KEY status (status)
		) {$charset};
		CREATE TABLE {$sequences} (
			name varchar(40) NOT NULL,
			year smallint(5) unsigned NOT NULL,
			value int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (name,year)
		) {$charset};
		CREATE TABLE {$documents} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL DEFAULT 0,
			type varchar(20) NOT NULL DEFAULT '',
			series varchar(20) NOT NULL DEFAULT '',
			number varchar(40) NOT NULL DEFAULT '',
			issued_at datetime NOT NULL,
			amount decimal(12,2) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'issued',
			ref_id bigint(20) unsigned NOT NULL DEFAULT 0,
			data longtext NULL,
			file varchar(255) NOT NULL DEFAULT '',
			sent_at datetime NULL DEFAULT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY number (number)
		) {$charset};
		CREATE TABLE {$deletion} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			run_at datetime NOT NULL,
			rule varchar(40) NOT NULL DEFAULT '',
			count int(10) unsigned NOT NULL DEFAULT 0,
			dry tinyint(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			KEY run_at (run_at)
		) {$charset};";

		dbDelta( $sql );
		update_option( 'msb_db_version', self::DB_VERSION );
	}

	/**
	 * Legt "Buchung abschließen" ([msb_checkout]) und "Mein Konto" ([msb_account]) an, falls nicht vorhanden.
	 */
	public static function create_pages() {
		$settings = get_option( 'msb_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		$changed  = false;

		$pages = array(
			'checkout_page' => array( 'Buchung abschließen', 'buchung', '[msb_checkout]' ),
			'account_page'  => array( 'Mein Konto', 'mein-konto', '[msb_account]' ),
		);

		foreach ( $pages as $key => $p ) {
			$id = isset( $settings[ $key ] ) ? (int) $settings[ $key ] : 0;
			if ( $id && get_post( $id ) && 'trash' !== get_post_status( $id ) ) {
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_title'   => $p[0],
					'post_name'    => $p[1],
					'post_content' => $p[2],
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				$settings[ $key ] = (int) $id;
				$changed          = true;
			}
		}
		if ( $changed ) {
			update_option( 'msb_settings', $settings );
		}
	}

	public static function add_role() {
		if ( ! get_role( 'msb_customer' ) ) {
			add_role( 'msb_customer', 'Mietkunde', array( 'read' => true ) );
		}

		// Rolle „Gesellschafter“ (ROL-01). Die Rechte werden nach jedem Plugin-Update einmal genau auf msb_partner_caps() gesetzt.
		if ( MSB_VERSION === get_option( 'msb_roles_version' ) ) {
			return;
		}
		$role = get_role( 'msb_partner' );
		if ( ! $role ) {
			$role = add_role( 'msb_partner', 'Gesellschafter', array() );
		}
		if ( $role ) {
			$want = msb_partner_caps();
			foreach ( array_keys( (array) $role->capabilities ) as $cap ) {
				if ( ! in_array( $cap, $want, true ) ) {
					$role->remove_cap( $cap );
				}
			}
			foreach ( $want as $cap ) {
				$role->add_cap( $cap );
			}
			update_option( 'msb_roles_version', MSB_VERSION );
		}
	}

	public static function schedule_cron() {
		if ( ! wp_next_scheduled( 'msb_cron_hourly' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'msb_cron_hourly' );
		}
		if ( ! wp_next_scheduled( 'msb_cron_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'msb_cron_daily' );
		}
	}
}
