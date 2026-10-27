<?php
/**
 * Backend: Menü, Einstellungen, Formular-Builder, Shortcode-Übersicht.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu_top' ), 9 );
		add_action( 'admin_menu', array( __CLASS__, 'menu_bottom' ), 11 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_post' ) );
		add_filter( 'parent_file', array( __CLASS__, 'parent_file' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'submenu_file' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MSB_FILE ), array( __CLASS__, 'plugin_links' ) );
	}

	public static function menu_top() {
		$cap     = msb_capability();
		$pending = isset( MSB_Bookings::count_by_status()['pending'] ) ? MSB_Bookings::count_by_status()['pending'] : 0;
		$bubble  = $pending ? ' <span class="awaiting-mod">' . (int) $pending . '</span>' : '';

		add_menu_page( 'Vermietung', 'Vermietung' . $bubble, $cap, 'msb-bookings', array( 'MSB_Admin_Bookings', 'page_list' ), 'dashicons-calendar-alt', 26 );
		add_submenu_page( 'msb-bookings', 'Buchungen', 'Buchungen', $cap, 'msb-bookings', array( 'MSB_Admin_Bookings', 'page_list' ) );
		add_submenu_page( 'msb-bookings', 'Buchung', 'Neue Buchung', $cap, 'msb-booking', array( 'MSB_Admin_Bookings', 'page_booking' ) );
		add_submenu_page( 'msb-bookings', 'Belegungskalender', 'Kalender', $cap, 'msb-calendar', array( 'MSB_Admin_Bookings', 'page_calendar' ) );
		add_submenu_page( 'msb-bookings', 'Inventar & Verfügbarkeit', 'Inventar', $cap, 'msb-inventory', array( 'MSB_Admin_Bookings', 'page_inventory' ) );
		add_submenu_page( 'msb-bookings', 'Kunden', 'Kunden', $cap, 'msb-customers', array( 'MSB_Admin_Customers', 'page_list' ) );
		add_submenu_page( 'msb-bookings', 'Kunde', '', $cap, 'msb-customer', array( 'MSB_Admin_Customers', 'page_edit' ) );
	}

	public static function menu_bottom() {
		$cap = msb_capability( 'settings' );
		add_submenu_page( 'msb-bookings', 'Mietkategorien', 'Kategorien', msb_capability( 'items' ), 'edit-tags.php?taxonomy=' . MSB_Items::TAXONOMY . '&post_type=' . MSB_Items::POST_TYPE );
		add_submenu_page( 'msb-bookings', 'Buchungsformular', 'Buchungsformular', $cap, 'msb-form', array( __CLASS__, 'page_form' ) );
		add_submenu_page( 'msb-bookings', 'Einstellungen', 'Einstellungen', $cap, 'msb-settings', array( __CLASS__, 'page_settings' ) );
		add_submenu_page( 'msb-bookings', 'Shortcodes', 'Shortcodes', $cap, 'msb-shortcodes', array( __CLASS__, 'page_shortcodes' ) );
		add_submenu_page( 'msb-bookings', 'Hilfe & Dokumentation', 'Hilfe & Doku', msb_capability(), 'msb-docs', array( 'MSB_Docs', 'page' ) );
		remove_submenu_page( 'msb-bookings', 'msb-customer' ); // Detailseite ohne eigenen Menüpunkt.
	}

	public static function parent_file( $file ) {
		global $plugin_page, $submenu_file;
		if ( 'msb-customer' === $plugin_page ) {
			$submenu_file = 'msb-customers'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		}
		$screen = get_current_screen();
		if ( $screen && ( MSB_Items::TAXONOMY === $screen->taxonomy || MSB_Items::POST_TYPE === $screen->post_type ) ) {
			return 'msb-bookings';
		}
		return $file;
	}

	public static function submenu_file( $file ) {
		$screen = get_current_screen();
		if ( $screen && MSB_Items::TAXONOMY === $screen->taxonomy ) {
			return 'edit-tags.php?taxonomy=' . MSB_Items::TAXONOMY . '&post_type=' . MSB_Items::POST_TYPE;
		}
		if ( $screen && MSB_Items::POST_TYPE === $screen->post_type ) {
			return 'edit.php?post_type=' . MSB_Items::POST_TYPE;
		}
		return $file;
	}

	public static function plugin_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=msb-settings' ) ) . '">Einstellungen</a>' );
		return $links;
	}

	public static function is_msb_screen() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}
		return false !== strpos( $screen->id, 'msb-' ) || MSB_Items::POST_TYPE === $screen->post_type;
	}

	public static function assets() {
		if ( ! self::is_msb_screen() ) {
			return;
		}
		wp_enqueue_style( 'msb-admin', MSB_URL . 'assets/css/admin.css', array(), MSB_VERSION );
		$screen = get_current_screen();
		if ( $screen && MSB_Items::POST_TYPE === $screen->post_type ) {
			wp_enqueue_media();
		}
		wp_enqueue_script( 'msb-admin', MSB_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable' ), MSB_VERSION, true );
	}

	/* ------------------------------------------------------------------
	 * POST-Verarbeitung (Einstellungen, Formular)
	 * ------------------------------------------------------------------ */

	public static function handle_post() {
		if ( empty( $_POST['msb_action'] ) || ! current_user_can( msb_capability( 'settings' ) ) ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_POST['msb_action'] ) );

		if ( 'save_settings' === $action ) {
			check_admin_referer( 'msb_save_settings' );
			self::save_settings();
		} elseif ( 'save_form' === $action ) {
			check_admin_referer( 'msb_save_form' );
			if ( ! empty( $_POST['msb_reset'] ) ) {
				update_option( 'msb_form_fields', MSB_Form_Fields::defaults() );
			} else {
				$raw = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? $_POST['fields'] : array(); // phpcs:ignore
				update_option( 'msb_form_fields', MSB_Form_Fields::sanitize_definitions( $raw ) );
			}
			wp_safe_redirect( add_query_arg( array( 'page' => 'msb-form', 'saved' => 1 ), admin_url( 'admin.php' ) ) );
			exit;
		} elseif ( 'test_email' === $action ) {
			check_admin_referer( 'msb_save_settings' );
			$to = msb_setting( 'admin_email' );
			$ok = wp_mail( $to, 'Testnachricht – ' . get_bloginfo( 'name' ), 'Dies ist eine Testnachricht des Vermietungs-Plugins. Der E-Mail-Versand funktioniert.' );
			wp_safe_redirect( add_query_arg( array( 'page' => 'msb-settings', 'tab' => 'emails', 'mailtest' => $ok ? 'ok' : 'fail' ), admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	private static function save_settings() {
		$in       = wp_unslash( $_POST ); // phpcs:ignore
		$s        = msb_settings();
		$defaults = msb_settings_defaults();
		$tab      = isset( $in['tab'] ) ? sanitize_key( $in['tab'] ) : 'general';

		$text    = array( 'currency', 'price_note', 'booking_prefix', 'from_name', 'offline_label', 'stripe_label', 'paypal_label', 'paypal_client_id', 'paypal_client_id_sandbox' );
		$int     = array( 'decimals', 'buffer_days', 'lead_days', 'max_advance_days', 'checkout_page', 'calendar_months', 'security_hold_days', 'payment_timeout', 'account_page' );
		$bool    = array( 'redirect_to_checkout', 'auto_booking_box', 'delete_on_uninstall', 'stack_discounts', 'security_auto_release', 'offline_enabled', 'stripe_enabled', 'paypal_enabled', 'accounts_enabled', 'account_required', 'hide_admin_bar' );
		$secrets = array( 'stripe_secret_test', 'stripe_secret_live', 'stripe_webhook_secret', 'paypal_secret', 'paypal_secret_sandbox' );
		foreach ( array_keys( MSB_Emails::types() ) as $t ) {
			$bool[] = 'email_' . $t . '_enabled';
		}

		$fields_by_tab = array(
			'general' => array( 'currency', 'currency_pos', 'decimals', 'price_note', 'primary_color', 'booking_prefix', 'checkout_page', 'redirect_to_checkout', 'auto_booking_box', 'calendar_months', 'thankyou_message', 'delete_on_uninstall' ),
			'rules'    => array( 'count_mode', 'buffer_days', 'lead_days', 'max_advance_days', 'closed_weekdays', 'default_status' ),
			'prices'   => array( 'price_rules', 'stack_discounts' ),
			'payments' => array( 'currency_code', 'prepay_type', 'prepay_value', 'security_mode', 'security_hold_days', 'security_auto_release', 'payment_timeout', 'offline_enabled', 'offline_label', 'offline_text', 'stripe_enabled', 'stripe_mode', 'stripe_secret_test', 'stripe_secret_live', 'stripe_webhook_secret', 'stripe_label', 'paypal_enabled', 'paypal_mode', 'paypal_client_id', 'paypal_secret', 'paypal_client_id_sandbox', 'paypal_secret_sandbox', 'paypal_label' ),
			'accounts' => array( 'accounts_enabled', 'account_page', 'account_required', 'hide_admin_bar' ),
			'emails'   => array( 'admin_email', 'from_name', 'from_email' ),
		);
		foreach ( array_keys( MSB_Emails::types() ) as $t ) {
			$fields_by_tab['emails'][] = 'email_' . $t . '_enabled';
			$fields_by_tab['emails'][] = 'email_' . $t . '_subject';
			$fields_by_tab['emails'][] = 'email_' . $t . '_body';
		}
		$fields = isset( $fields_by_tab[ $tab ] ) ? $fields_by_tab[ $tab ] : array();

		foreach ( $fields as $key ) {
			$v = isset( $in[ $key ] ) ? $in[ $key ] : null;

			if ( in_array( $key, $secrets, true ) ) {
				$v = trim( (string) $v );
				if ( '__delete__' === $v ) {
					$s[ $key ] = '';
				} elseif ( '' !== $v ) {
					$s[ $key ] = sanitize_text_field( $v );
				}
			} elseif ( 'price_rules' === $key ) {
				$s[ $key ] = MSB_Pricing::sanitize_rules( (array) $v );
			} elseif ( 'currency_code' === $key ) {
				$s[ $key ] = preg_match( '/^[A-Za-z]{3}$/', (string) $v ) ? strtoupper( $v ) : 'EUR';
			} elseif ( 'prepay_type' === $key ) {
				$s[ $key ] = in_array( $v, array( 'none', 'percent', 'fixed', 'full' ), true ) ? $v : 'none';
			} elseif ( 'prepay_value' === $key ) {
				$s[ $key ] = max( 0, round( (float) str_replace( ',', '.', (string) $v ), 2 ) );
			} elseif ( 'security_mode' === $key ) {
				$s[ $key ] = 'hold' === $v ? 'hold' : 'onsite';
			} elseif ( 'stripe_mode' === $key ) {
				$s[ $key ] = 'live' === $v ? 'live' : 'test';
			} elseif ( 'paypal_mode' === $key ) {
				$s[ $key ] = 'live' === $v ? 'live' : 'sandbox';
			} elseif ( 'offline_text' === $key ) {
				$s[ $key ] = sanitize_textarea_field( (string) $v );
			} elseif ( in_array( $key, $bool, true ) ) {
				$s[ $key ] = empty( $v ) ? 0 : 1;
			} elseif ( in_array( $key, $int, true ) ) {
				$s[ $key ] = max( 0, (int) $v );
			} elseif ( in_array( $key, $text, true ) ) {
				$s[ $key ] = sanitize_text_field( (string) $v );
			} elseif ( 'currency_pos' === $key ) {
				$s[ $key ] = 'before' === $v ? 'before' : 'after';
			} elseif ( 'count_mode' === $key ) {
				$s[ $key ] = 'nights' === $v ? 'nights' : 'days';
			} elseif ( 'default_status' === $key ) {
				$s[ $key ] = 'confirmed' === $v ? 'confirmed' : 'pending';
			} elseif ( 'primary_color' === $key ) {
				$c         = sanitize_hex_color( (string) $v );
				$s[ $key ] = $c ? $c : $defaults['primary_color'];
			} elseif ( 'closed_weekdays' === $key ) {
				$s[ $key ] = array_values( array_unique( array_filter( array_map( 'intval', (array) $v ), function ( $d ) {
					return $d >= 0 && $d <= 6;
				} ) ) );
			} elseif ( in_array( $key, array( 'admin_email', 'from_email' ), true ) ) {
				$e         = sanitize_email( (string) $v );
				$s[ $key ] = is_email( $e ) ? $e : $defaults[ $key ];
			} elseif ( false !== strpos( $key, '_subject' ) ) {
				$s[ $key ] = sanitize_text_field( (string) $v );
			} else {
				$s[ $key ] = wp_kses_post( (string) $v );
			}
		}
		$s['decimals']        = min( 4, $s['decimals'] );
		$s['calendar_months'] = max( 1, min( 3, $s['calendar_months'] ) );
		$s['payment_timeout'] = max( 15, (int) $s['payment_timeout'] );

		update_option( 'msb_settings', $s );
		wp_safe_redirect( add_query_arg( array( 'page' => 'msb-settings', 'tab' => $tab, 'saved' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Einstellungen
	 * ------------------------------------------------------------------ */

	public static function page_settings() {
		$s    = msb_settings();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general'; // phpcs:ignore
		$tabs = array(
			'general'  => 'Allgemein',
			'rules'    => 'Buchungsregeln',
			'prices'   => 'Preise & Rabatte',
			'payments' => 'Zahlungen & Kaution',
			'accounts' => 'Kundenkonten',
			'emails'   => 'E-Mails',
		);
		$help = array(
			'general'  => 'einstellungen-allgemein',
			'rules'    => 'einstellungen-regeln',
			'prices'   => 'preisregeln',
			'payments' => 'zahlungen',
			'accounts' => 'kundenkonten',
			'emails'   => 'emails',
		);
		$tabs['documents'] = 'Belege';
		$help['documents'] = 'belege';
		$tabs['retention'] = 'Löschfristen';
		$help['retention'] = 'kunden';
		if ( 'documents' === $tab ) {
			MSB_Documents::settings_page( $tabs, $help );
			return;
		}
		if ( 'retention' === $tab ) {
			MSB_Retention::settings_page( $tabs, $help );
			return;
		}
		if ( class_exists( 'MSB_Assistant' ) ) {
			$tabs['ai'] = 'KI-Assistent';
			$help['ai'] = 'ki-assistent';
			if ( 'ai' === $tab ) {
				MSB_Assistant::settings_page( $tabs, $help );
				return;
			}
		}
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'general';
		}
		?>
		<div class="wrap msb-admin">
			<h1>Einstellungen <?php echo msb_help_link( $help[ $tab ], 'Erklärung zu diesem Bereich' ); // phpcs:ignore ?></h1>
			<?php
			if ( isset( $_GET['saved'] ) ) { // phpcs:ignore
				echo '<div class="notice notice-success is-dismissible"><p>Einstellungen gespeichert.</p></div>';
			}
			if ( isset( $_GET['mailtest'] ) ) { // phpcs:ignore
				echo 'ok' === $_GET['mailtest'] // phpcs:ignore
					? '<div class="notice notice-success is-dismissible"><p>Test-E-Mail wurde an ' . esc_html( $s['admin_email'] ) . ' übergeben. Bitte Posteingang (und Spam) prüfen.</p></div>'
					: '<div class="notice notice-error is-dismissible"><p>Die Test-E-Mail konnte nicht versendet werden. Wir empfehlen ein SMTP-Plugin (z. B. „WP Mail SMTP“).</p></div>';
			}
			?>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $k => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=msb-settings&tab=' . $k ) ); ?>" class="nav-tab <?php echo $k === $tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="">
				<?php wp_nonce_field( 'msb_save_settings' ); ?>
				<input type="hidden" name="msb_action" value="save_settings">
				<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">

				<?php if ( 'general' === $tab ) : ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="checkout_page">Buchungsseite</label></th>
							<td>
								<?php
								wp_dropdown_pages(
									array(
										'name'              => 'checkout_page',
										'id'                => 'checkout_page',
										'selected'          => (int) $s['checkout_page'],
										'show_option_none'  => '— Keine —',
										'option_none_value' => 0,
									)
								);
								?>
								<p class="description">Seite mit dem Shortcode <code>[msb_checkout]</code>. Wurde bei der Aktivierung automatisch als „Buchung abschließen“ angelegt.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Nach „Zur Buchung hinzufügen“</th>
							<td>
								<label><input type="checkbox" name="redirect_to_checkout" value="1" <?php checked( $s['redirect_to_checkout'] ); ?>> Direkt zur Buchungsseite weiterleiten</label>
								<p class="description">Ohne Haken bleibt der Kunde auf der Seite und kann weitere Artikel auswählen (Warenkorb).</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Artikelseiten</th>
							<td>
								<label><input type="checkbox" name="auto_booking_box" value="1" <?php checked( $s['auto_booking_box'] ); ?>> Preise, technische Daten und Buchungskalender automatisch unter dem Inhalt jeder Artikelseite anzeigen</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="calendar_months">Monate im Buchungskalender</label></th>
							<td>
								<select name="calendar_months" id="calendar_months">
									<?php for ( $i = 1; $i <= 3; $i++ ) : ?>
										<option value="<?php echo (int) $i; ?>" <?php selected( (int) $s['calendar_months'], $i ); ?>><?php echo (int) $i; ?></option>
									<?php endfor; ?>
								</select>
								<p class="description">Auf Smartphones wird immer ein Monat gezeigt.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Währung</th>
							<td>
								<input type="text" name="currency" value="<?php echo esc_attr( $s['currency'] ); ?>" class="small-text">
								<select name="currency_pos">
									<option value="after" <?php selected( $s['currency_pos'], 'after' ); ?>>nach dem Betrag (12,50 €)</option>
									<option value="before" <?php selected( $s['currency_pos'], 'before' ); ?>>vor dem Betrag (€ 12,50)</option>
								</select>
								<label>Nachkommastellen <input type="number" name="decimals" min="0" max="4" value="<?php echo esc_attr( $s['decimals'] ); ?>" class="small-text"></label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="price_note">Preishinweis</label></th>
							<td><input type="text" id="price_note" name="price_note" value="<?php echo esc_attr( $s['price_note'] ); ?>" class="regular-text">
								<p class="description">z. B. „inkl. MwSt.“ oder „Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.“</p></td>
						</tr>
						<tr>
							<th scope="row"><label for="booking_prefix">Präfix Buchungsnummer</label></th>
							<td><input type="text" id="booking_prefix" name="booking_prefix" value="<?php echo esc_attr( $s['booking_prefix'] ); ?>" class="small-text">
								<p class="description">Ergibt z. B. <?php echo esc_html( $s['booking_prefix'] . current_time( 'Y' ) ); ?>-0001</p></td>
						</tr>
						<tr>
							<th scope="row"><label for="primary_color">Akzentfarbe</label></th>
							<td><input type="color" id="primary_color" name="primary_color" value="<?php echo esc_attr( $s['primary_color'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="thankyou_message">Danke-Nachricht</label></th>
							<td><textarea id="thankyou_message" name="thankyou_message" rows="5" class="large-text"><?php echo esc_textarea( $s['thankyou_message'] ); ?></textarea>
								<p class="description">Wird nach dem Absenden angezeigt. Platzhalter wie in den E-Mails möglich, z. B. <code>{booking_number}</code>.</p></td>
						</tr>
						<tr>
							<th scope="row">Deinstallation</th>
							<td><label><input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( $s['delete_on_uninstall'] ); ?>> Beim Löschen des Plugins alle Buchungen und Einstellungen entfernen</label></td>
						</tr>
					</table>

				<?php elseif ( 'rules' === $tab ) : ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">Berechnung der Miettage</th>
							<td>
								<fieldset>
									<label><input type="radio" name="count_mode" value="days" <?php checked( $s['count_mode'], 'days' ); ?>> <strong>Kalendertage</strong> – Abhol- und Rückgabetag zählen mit (Fr → So = 3 Tage, Abholung und Rückgabe am selben Tag möglich)</label><br>
									<label><input type="radio" name="count_mode" value="nights" <?php checked( $s['count_mode'], 'nights' ); ?>> <strong>Nächte / 24 Stunden</strong> – (Fr → So = 2 Tage)</label>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="buffer_days">Puffertage</label></th>
							<td><input type="number" min="0" id="buffer_days" name="buffer_days" value="<?php echo esc_attr( $s['buffer_days'] ); ?>" class="small-text"> Tage
								<p class="description">Freie Tage zwischen Rückgabe und nächster Abholung (z. B. für Reinigung). 0 = Rückgabe und neue Abholung am Folgetag möglich.</p></td>
						</tr>
						<tr>
							<th scope="row"><label for="lead_days">Vorlaufzeit</label></th>
							<td><input type="number" min="0" id="lead_days" name="lead_days" value="<?php echo esc_attr( $s['lead_days'] ); ?>" class="small-text"> Tage
								<p class="description">0 = Buchung für heute möglich, 1 = frühestens morgen.</p></td>
						</tr>
						<tr>
							<th scope="row"><label for="max_advance_days">Buchbar im Voraus</label></th>
							<td><input type="number" min="0" id="max_advance_days" name="max_advance_days" value="<?php echo esc_attr( $s['max_advance_days'] ); ?>" class="small-text"> Tage
								<p class="description">0 = unbegrenzt.</p></td>
						</tr>
						<tr>
							<th scope="row">Keine Abholung / Rückgabe an</th>
							<td>
								<?php
								$order = array( 1, 2, 3, 4, 5, 6, 0 );
								$names = msb_weekday_names();
								foreach ( $order as $d ) :
									?>
									<label style="margin-right:12px"><input type="checkbox" name="closed_weekdays[]" value="<?php echo (int) $d; ?>" <?php checked( in_array( $d, array_map( 'intval', (array) $s['closed_weekdays'] ), true ) ); ?>> <?php echo esc_html( $names[ $d ] ); ?></label>
								<?php endforeach; ?>
								<p class="description">An diesen Tagen kann nicht abgeholt oder zurückgegeben werden. Die Miete darf diese Tage aber einschließen.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Status neuer Online-Buchungen</th>
							<td>
								<label><input type="radio" name="default_status" value="pending" <?php checked( $s['default_status'], 'pending' ); ?>> <strong>Angefragt</strong> – Sie bestätigen jede Buchung manuell</label><br>
								<label><input type="radio" name="default_status" value="confirmed" <?php checked( $s['default_status'], 'confirmed' ); ?>> <strong>Bestätigt</strong> – Buchung ist sofort verbindlich</label>
								<p class="description">Auch angefragte Buchungen blockieren das Inventar, bis sie storniert werden.</p>
							</td>
						</tr>
					</table>

				<?php elseif ( 'prices' === $tab ) : ?>
					<p>Diese Regeln gelten für <strong>alle</strong> Mietartikel. Eigene Regeln legen Sie direkt im Artikel im Kasten „Dynamische Preise &amp; Rabatte“ an. Im Artikel können Sie die globalen Regeln auch abschalten.</p>
					<?php MSB_Items::render_rules_editor( 'price_rules', (array) $s['price_rules'] ); ?>
					<input type="hidden" name="price_rules[__empty]" value="">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">Mehrere Rabatte</th>
							<td><label><input type="checkbox" name="stack_discounts" value="1" <?php checked( $s['stack_discounts'] ); ?>> Rabatte addieren</label>
								<p class="description">Ohne Haken erhält der Kunde je Artikel nur den höchsten passenden Rabatt (empfohlen). Aufschläge gelten immer. Ein Pauschalpreis ersetzt alle anderen Buchungsrabatte.</p></td>
						</tr>
					</table>

				<?php elseif ( 'payments' === $tab ) : ?>
					<?php self::payment_settings( $s ); ?>

				<?php elseif ( 'accounts' === $tab ) : ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">Kundenkonten</th>
							<td><label><input type="checkbox" name="accounts_enabled" value="1" <?php checked( $s['accounts_enabled'] ); ?>> Kunden dürfen ein Kundenkonto anlegen</label>
								<p class="description">Kunden sehen dann ihre Buchungen, Zahlungen und Kautionen und können ihre Daten ändern.</p></td>
						</tr>
						<tr>
							<th scope="row">Buchen ohne Konto</th>
							<td><label><input type="checkbox" name="account_required" value="1" <?php checked( $s['account_required'] ); ?>> Kundenkonto ist <strong>Pflicht</strong> für Buchungen</label>
								<p class="description">Standard: aus – Gäste können ohne Konto buchen, ein Konto ist freiwillig.</p></td>
						</tr>
						<tr>
							<th scope="row"><label for="account_page">Seite „Mein Konto“</label></th>
							<td>
								<?php
								wp_dropdown_pages(
									array(
										'name'              => 'account_page',
										'id'                => 'account_page',
										'selected'          => (int) $s['account_page'],
										'show_option_none'  => '— Keine —',
										'option_none_value' => 0,
									)
								);
								?>
								<p class="description">Seite mit dem Shortcode <code>[msb_account]</code>. Wurde automatisch angelegt.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Admin-Leiste</th>
							<td><label><input type="checkbox" name="hide_admin_bar" value="1" <?php checked( $s['hide_admin_bar'] ); ?>> Für Mietkunden ausblenden</label>
								<p class="description">Mietkunden haben keinen Zugriff auf das WordPress-Backend.</p></td>
						</tr>
					</table>

				<?php else : ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="admin_email">Benachrichtigungen an</label></th>
							<td><input type="email" id="admin_email" name="admin_email" value="<?php echo esc_attr( $s['admin_email'] ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th scope="row">Absender</th>
							<td>
								<input type="text" name="from_name" value="<?php echo esc_attr( $s['from_name'] ); ?>" class="regular-text" placeholder="Name"><br>
								<input type="email" name="from_email" value="<?php echo esc_attr( $s['from_email'] ); ?>" class="regular-text" placeholder="E-Mail" style="margin-top:6px">
								<p class="description">Die Absenderadresse sollte zu Ihrer Domain gehören (z. B. info@mietservice-barnim.de), sonst landen Mails im Spam.</p>
							</td>
						</tr>
					</table>

					<h2>Vorlagen</h2>
					<details class="msb-placeholders"><summary>Verfügbare Platzhalter</summary>
						<table class="widefat striped"><tbody>
						<?php foreach ( MSB_Emails::placeholders() as $ph => $desc ) : ?>
							<tr><td><code><?php echo esc_html( $ph ); ?></code></td><td><?php echo esc_html( $desc ); ?></td></tr>
						<?php endforeach; ?>
						</tbody></table>
					</details>

					<?php foreach ( MSB_Emails::types() as $type => $label ) : ?>
						<div class="msb-card-box">
							<h3><label><input type="checkbox" name="email_<?php echo esc_attr( $type ); ?>_enabled" value="1" <?php checked( $s[ 'email_' . $type . '_enabled' ] ); ?>> <?php echo esc_html( $label ); ?></label></h3>
							<p><label>Betreff<br><input type="text" name="email_<?php echo esc_attr( $type ); ?>_subject" value="<?php echo esc_attr( $s[ 'email_' . $type . '_subject' ] ); ?>" class="large-text"></label></p>
							<p><label>Nachricht<br><textarea name="email_<?php echo esc_attr( $type ); ?>_body" rows="9" class="large-text code"><?php echo esc_textarea( $s[ 'email_' . $type . '_body' ] ); ?></textarea></label></p>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>

				<p class="submit">
					<button type="submit" class="button button-primary">Einstellungen speichern</button>
					<?php if ( 'emails' === $tab ) : ?>
						<button type="submit" name="msb_action" value="test_email" class="button">Test-E-Mail senden</button>
					<?php endif; ?>
				</p>
			</form>
		</div>
		<?php
	}

	private static function secret_field( $name, $value, $placeholder = '' ) {
		$set = '' !== (string) $value;
		echo '<input type="password" name="' . esc_attr( $name ) . '" value="" class="regular-text code" autocomplete="new-password" placeholder="' . esc_attr( $set ? '•••••••• gespeichert (leer lassen = unverändert)' : $placeholder ) . '">';
		if ( $set ) {
			echo ' <label><input type="checkbox" onchange="this.closest(\'td\').querySelector(\'input[type=password]\').value=this.checked?\'__delete__\':\'\'"> löschen</label>';
		}
	}

	private static function payment_settings( array $s ) {
		$stripe_ok = MSB_Gateway_Stripe::enabled();
		$paypal_ok = MSB_Gateway_PayPal::enabled();
		?>
		<h2>Anzahlung &amp; Kaution</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">Bei der Buchung zahlen</th>
				<td>
					<select name="prepay_type" id="msb-prepay-type">
						<option value="none" <?php selected( $s['prepay_type'], 'none' ); ?>>nichts – alles bei Abholung</option>
						<option value="percent" <?php selected( $s['prepay_type'], 'percent' ); ?>>Anzahlung in % des Mietpreises</option>
						<option value="fixed" <?php selected( $s['prepay_type'], 'fixed' ); ?>>feste Anzahlung in <?php echo esc_html( $s['currency'] ); ?></option>
						<option value="full" <?php selected( $s['prepay_type'], 'full' ); ?>>kompletten Mietpreis</option>
					</select>
					<input type="number" step="0.01" min="0" name="prepay_value" value="<?php echo esc_attr( $s['prepay_value'] ); ?>" class="small-text"> <span class="description">(% bzw. Betrag)</span>
					<p class="description">Gilt nur, wenn der Kunde online bezahlt (Stripe/PayPal). Bei „Bezahlung bei Abholung“ wird nichts vorab fällig.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Kaution</th>
				<td>
					<label><input type="radio" name="security_mode" value="onsite" <?php checked( $s['security_mode'], 'onsite' ); ?>> bei Abholung vor Ort (bar o. Ä.)</label><br>
					<label><input type="radio" name="security_mode" value="hold" <?php checked( $s['security_mode'], 'hold' ); ?>> über Stripe/PayPal <strong>reservieren</strong> (nicht abbuchen) und nach Rückgabe freigeben</label>
					<p class="description">Die Kaution-Höhe legen Sie je Artikel fest. Sie wird immer getrennt vom Mietpreis behandelt. <?php echo msb_help_link( 'kaution', 'Wie funktioniert die Reservierung?' ); // phpcs:ignore ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="security_hold_days">Kaution reservieren</label></th>
				<td><input type="number" min="0" max="5" id="security_hold_days" name="security_hold_days" value="<?php echo esc_attr( $s['security_hold_days'] ); ?>" class="small-text"> Tage vor der Abholung
					<p class="description">Kartenreservierungen verfallen bei Stripe nach 7 Tagen, bei PayPal nach 29 Tagen – deshalb wird erst kurz vor der Abholung reserviert. Empfehlung: 1–2 Tage.</p></td>
			</tr>
			<tr>
				<th scope="row">Freigabe</th>
				<td><label><input type="checkbox" name="security_auto_release" value="1" <?php checked( $s['security_auto_release'] ); ?>> Kaution automatisch freigeben, sobald die Buchung auf „Zurückgegeben“ gesetzt wird</label></td>
			</tr>
			<tr>
				<th scope="row"><label for="payment_timeout">Zahlungsfrist</label></th>
				<td><input type="number" min="15" id="payment_timeout" name="payment_timeout" value="<?php echo esc_attr( $s['payment_timeout'] ); ?>" class="small-text"> Minuten
					<p class="description">So lange werden die Artikel für eine begonnene Online-Zahlung freigehalten. Danach wird die Buchung automatisch storniert.</p></td>
			</tr>
			<tr>
				<th scope="row"><label for="currency_code">Währungscode</label></th>
				<td><input type="text" id="currency_code" name="currency_code" value="<?php echo esc_attr( $s['currency_code'] ); ?>" class="small-text" maxlength="3"> <span class="description">ISO-Code für die Zahlungsanbieter, z. B. EUR</span></td>
			</tr>
		</table>

		<h2>Bezahlung bei Abholung</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">Aktiv</th>
				<td><label><input type="checkbox" name="offline_enabled" value="1" <?php checked( $s['offline_enabled'] ); ?>> Kunden dürfen ohne Online-Zahlung buchen</label></td>
			</tr>
			<tr>
				<th scope="row">Bezeichnung</th>
				<td><input type="text" name="offline_label" value="<?php echo esc_attr( $s['offline_label'] ); ?>" class="regular-text"><br>
					<textarea name="offline_text" rows="2" class="large-text" placeholder="Optionaler Hinweis, z. B. Bankverbindung oder „Bitte Personalausweis mitbringen“"><?php echo esc_textarea( $s['offline_text'] ); ?></textarea></td>
			</tr>
		</table>

		<h2>Stripe <?php echo $stripe_ok ? '<span class="msb-status msb-status--confirmed">bereit</span>' : ''; ?> <?php echo msb_help_link( 'stripe', 'Einrichtung' ); // phpcs:ignore ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">Aktiv</th>
				<td><label><input type="checkbox" name="stripe_enabled" value="1" <?php checked( $s['stripe_enabled'] ); ?>> Zahlung über Stripe (Kreditkarte, Apple Pay, Google Pay, SEPA u. a.)</label></td>
			</tr>
			<tr>
				<th scope="row">Modus</th>
				<td><label><input type="radio" name="stripe_mode" value="test" <?php checked( $s['stripe_mode'], 'test' ); ?>> Testmodus</label> &nbsp; <label><input type="radio" name="stripe_mode" value="live" <?php checked( $s['stripe_mode'], 'live' ); ?>> Live</label></td>
			</tr>
			<tr>
				<th scope="row">Geheimer Schlüssel (Test)</th>
				<td><?php self::secret_field( 'stripe_secret_test', $s['stripe_secret_test'], 'sk_test_…' ); ?></td>
			</tr>
			<tr>
				<th scope="row">Geheimer Schlüssel (Live)</th>
				<td><?php self::secret_field( 'stripe_secret_live', $s['stripe_secret_live'], 'sk_live_… oder rk_live_…' ); ?></td>
			</tr>
			<tr>
				<th scope="row">Webhook</th>
				<td>
					<input type="text" readonly class="large-text code msb-copy" value="<?php echo esc_attr( MSB_Payments::webhook_url() ); ?>" onclick="this.select()">
					<p class="description">In Stripe unter Entwickler → Webhooks diese Adresse mit dem Ereignis <code>checkout.session.completed</code> eintragen und das Signatur-Geheimnis hier speichern. Empfohlen, damit Zahlungen auch erfasst werden, wenn der Kunde das Fenster nach dem Bezahlen schließt.</p>
					<?php self::secret_field( 'stripe_webhook_secret', $s['stripe_webhook_secret'], 'whsec_…' ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row">Bezeichnung im Checkout</th>
				<td><input type="text" name="stripe_label" value="<?php echo esc_attr( $s['stripe_label'] ); ?>" class="regular-text"></td>
			</tr>
		</table>

		<h2>PayPal <?php echo $paypal_ok ? '<span class="msb-status msb-status--confirmed">bereit</span>' : ''; ?> <?php echo msb_help_link( 'paypal', 'Einrichtung' ); // phpcs:ignore ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">Aktiv</th>
				<td><label><input type="checkbox" name="paypal_enabled" value="1" <?php checked( $s['paypal_enabled'] ); ?>> Zahlung über PayPal</label></td>
			</tr>
			<tr>
				<th scope="row">Modus</th>
				<td><label><input type="radio" name="paypal_mode" value="sandbox" <?php checked( $s['paypal_mode'], 'sandbox' ); ?>> Sandbox (Test)</label> &nbsp; <label><input type="radio" name="paypal_mode" value="live" <?php checked( $s['paypal_mode'], 'live' ); ?>> Live</label></td>
			</tr>
			<tr>
				<th scope="row">Sandbox Client-ID / Secret</th>
				<td><input type="text" name="paypal_client_id_sandbox" value="<?php echo esc_attr( $s['paypal_client_id_sandbox'] ); ?>" class="large-text code" placeholder="Client-ID"><br><?php self::secret_field( 'paypal_secret_sandbox', $s['paypal_secret_sandbox'], 'Secret' ); ?></td>
			</tr>
			<tr>
				<th scope="row">Live Client-ID / Secret</th>
				<td><input type="text" name="paypal_client_id" value="<?php echo esc_attr( $s['paypal_client_id'] ); ?>" class="large-text code" placeholder="Client-ID"><br><?php self::secret_field( 'paypal_secret', $s['paypal_secret'], 'Secret' ); ?></td>
			</tr>
			<tr>
				<th scope="row">Bezeichnung im Checkout</th>
				<td><input type="text" name="paypal_label" value="<?php echo esc_attr( $s['paypal_label'] ); ?>" class="regular-text"></td>
			</tr>
		</table>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Formular-Builder
	 * ------------------------------------------------------------------ */

	public static function page_form() {
		$fields = MSB_Form_Fields::get();
		$types  = MSB_Form_Fields::types();
		?>
		<div class="wrap msb-admin">
			<h1>Buchungsformular <?php echo msb_help_link( 'formular' ); // phpcs:ignore ?></h1>
			<?php
			if ( isset( $_GET['saved'] ) ) { // phpcs:ignore
				echo '<div class="notice notice-success is-dismissible"><p>Formular gespeichert.</p></div>';
			}
			?>
			<p>Diese Felder füllt der Kunde beim Abschluss der Buchung aus. Reihenfolge per Ziehen am Griff ändern. Das <strong>erste E-Mail-Feld</strong> wird für die Kundenbestätigung verwendet, die Felder mit dem Schlüssel <code>first_name</code> / <code>last_name</code> (oder <code>name</code>) als Kundenname.</p>

			<form method="post" action="" id="msb-form-builder">
				<?php wp_nonce_field( 'msb_save_form' ); ?>
				<input type="hidden" name="msb_action" value="save_form">

				<table class="widefat msb-fields">
					<thead>
						<tr>
							<th style="width:24px"></th>
							<th>Beschriftung</th>
							<th style="width:150px">Typ</th>
							<th style="width:140px">Schlüssel</th>
							<th style="width:70px">Pflicht</th>
							<th style="width:90px" title="Wert wird im Kundenstamm gespeichert und beim nächsten Mal vorausgefüllt">Kunden&shy;stamm</th>
							<th style="width:100px">Breite</th>
							<th style="width:40px"></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $fields as $i => $f ) {
							self::field_row( $i, $f, $types );
						}
						?>
					</tbody>
				</table>

				<script type="text/html" id="tmpl-msb-field-row">
					<?php
					self::field_row(
						'__i__',
						array(
							'key'         => '',
							'label'       => '',
							'type'        => 'text',
							'required'    => 0,
							'width'       => 'full',
							'options'     => '',
							'placeholder' => '',
							'profile'     => 1,
						),
						$types
					);
					?>
				</script>

				<p><button type="button" class="button" id="msb-add-field">+ Feld hinzufügen</button></p>

				<p class="submit">
					<button type="submit" class="button button-primary">Formular speichern</button>
					<button type="submit" name="msb_reset" value="1" class="button" onclick="return confirm('Formular wirklich auf die Standardfelder zurücksetzen?');">Standardfelder wiederherstellen</button>
				</p>
			</form>
		</div>
		<?php
	}

	private static function field_row( $i, $f, $types ) {
		$n = 'fields[' . $i . ']';
		?>
		<tr class="msb-field-row">
			<td class="msb-handle" title="Ziehen zum Sortieren"><span class="dashicons dashicons-menu"></span><button type="button" class="button msb-move" data-dir="-1" title="Nach oben"><span class="dashicons dashicons-arrow-up-alt2"></span></button><button type="button" class="button msb-move" data-dir="1" title="Nach unten"><span class="dashicons dashicons-arrow-down-alt2"></span></button></td>
			<td data-label="Beschriftung">
				<input type="text" name="<?php echo esc_attr( $n ); ?>[label]" value="<?php echo esc_attr( $f['label'] ); ?>" class="widefat" placeholder="Beschriftung (HTML-Links erlaubt)">
				<div class="msb-field-extra">
					<input type="text" name="<?php echo esc_attr( $n ); ?>[placeholder]" value="<?php echo esc_attr( $f['placeholder'] ); ?>" class="widefat msb-ph" placeholder="Platzhaltertext (optional)">
					<textarea name="<?php echo esc_attr( $n ); ?>[options]" rows="3" class="widefat msb-opts" placeholder="Auswahlmöglichkeiten – eine pro Zeile"><?php echo esc_textarea( $f['options'] ); ?></textarea>
					<?php $opt_count = count( msb_lines( $f['options'] ) ); ?>
					<p class="description msb-opts">Eine Auswahl pro Zeile (Enter-Taste) oder mit „;“ trennen.<?php echo $opt_count ? ' Gespeichert: ' . (int) $opt_count . ( 1 === $opt_count ? ' Auswahlmöglichkeit.' : ' Auswahlmöglichkeiten.' ) : ''; ?></p>
				</div>
			</td>
			<td data-label="Typ">
				<select name="<?php echo esc_attr( $n ); ?>[type]" class="msb-type">
					<?php foreach ( $types as $k => $label ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $f['type'], $k ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td data-label="Schlüssel"><input type="text" name="<?php echo esc_attr( $n ); ?>[key]" value="<?php echo esc_attr( $f['key'] ); ?>" class="widefat code" placeholder="automatisch"></td>
			<td class="msb-cb"><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[required]" value="1" <?php checked( ! empty( $f['required'] ) ); ?>><span class="msb-m">Pflichtfeld</span></label></td>
			<td class="msb-cb"><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[profile]" value="1" <?php checked( ! empty( $f['profile'] ) ); ?>><span class="msb-m">Im Kundenstamm speichern</span></label></td>
			<td data-label="Breite">
				<select name="<?php echo esc_attr( $n ); ?>[width]">
					<option value="full" <?php selected( $f['width'], 'full' ); ?>>ganze Zeile</option>
					<option value="half" <?php selected( $f['width'], 'half' ); ?>>halbe Zeile</option>
				</select>
			</td>
			<td class="msb-field-row__remove"><button type="button" class="button-link msb-remove-field" title="Feld entfernen"><span class="dashicons dashicons-trash"></span><span class="msb-m">Entfernen</span></button></td>
		</tr>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Shortcode-Übersicht
	 * ------------------------------------------------------------------ */

	public static function page_shortcodes() {
		$items = get_posts(
			array(
				'post_type'      => MSB_Items::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$terms = get_terms(
			array(
				'taxonomy'   => MSB_Items::TAXONOMY,
				'hide_empty' => false,
			)
		);

		$codes = array(
			array(
				'[msb_items]',
				'Artikelübersicht als Kacheln oder Liste.',
				'category="kraxen,anhaenger" (Titelform/Slug der Kategorie) · ids="12,15" · exclude="9" · limit="6" · columns="3" · layout="grid|list" · orderby="menu_order|title|price|date|rand" · order="ASC|DESC" · show_price · show_deposit · show_excerpt · show_specs · show_stock · show_gallery · show_button="yes|no" · button_text="Jetzt mieten" · bookable="yes" (nur buchbare)',
			),
			array( '[msb_item id="12"]', 'Komplette Artikelansicht: Bild, Beschreibung, technische Daten, Preise und Buchungskalender.', 'show_image · show_title · show_content · show_specs · show_prices="yes|no" · months="1–3"' ),
			array( '[msb_booking id="12"]', 'Nur die Buchungsbox: Kalender, Anzahl, Preisberechnung, „Zur Buchung hinzufügen“.', 'months="1–3" · title="Zeitraum wählen" · button="Zur Buchung hinzufügen"' ),
			array( '[msb_calendar id="12"]', 'Reiner Verfügbarkeitskalender (nur anzeigen).', 'months="1–3" · legend="yes|no"' ),
			array( '[msb_price id="12"]', 'Preis des Artikels.', 'show="from" (ab-Preis) · "day" (Tagespreis) · "table" (Preistabelle mit Staffeln, Kaution, Mindestdauer)' ),
			array( '[msb_specs id="12"]', 'Tabelle der technischen Daten.', '' ),
			array( '[msb_availability id="12"]', 'Text „Heute 2 von 3 verfügbar“.', 'date="24.12.2026"' ),
			array( '[msb_categories]', 'Kategorien als Kacheln oder Liste mit Link.', 'layout="tiles|list" · columns="3" · show_count · show_description · hide_empty="yes|no" · parent="ID"' ),
			array( '[msb_search]', 'Verfügbarkeitssuche: Kunde wählt Zeitraum und sieht alle freien Artikel. Der Zeitraum wird auf die Artikelseite übernommen.', 'category="kraxen" · columns · layout · show_category="yes|no" · button="…" sowie alle Optionen von [msb_items]' ),
			array( '[msb_cart]', 'Warenkorb mit Button „Weiter zur Buchung“.', '' ),
			array( '[msb_checkout]', 'Buchungsseite: Warenkorb + Buchungsformular + Absenden.', 'button="Verbindlich anfragen" · title="Ihre Auswahl"' ),
			array( '[msb_cart_link]', 'Link zur Buchungsseite mit Anzahl gewählter Artikel (z. B. für Header oder Menü-Widget).', 'text="Meine Auswahl"' ),
			array( '[msb_gallery id="12"]', 'Bildergalerie eines Artikels (Beitragsbild + Galerie).', 'mode="full|card" · size="large|medium_large|…"' ),
			array( '[msb_account]', 'Kundenkonto: Anmeldung, Registrierung, „Meine Buchungen“, „Meine Daten“.', '' ),
		);
		?>
		<div class="wrap msb-admin">
			<h1>Shortcodes <?php echo msb_help_link( 'shortcodes', 'Ausführliche Beschreibung' ); // phpcs:ignore ?></h1>
			<p>Fügen Sie die Shortcodes in Seiten oder Beiträge ein (Block „Shortcode“ im Block-Editor). Alle Optionen sind optional. <code>id</code> kann auf der Artikelseite selbst weggelassen werden.</p>

			<table class="widefat striped msb-sc-table">
				<thead><tr><th style="width:220px">Shortcode</th><th>Funktion</th><th>Optionen</th></tr></thead>
				<tbody>
				<?php foreach ( $codes as $c ) : ?>
					<tr>
						<td><input type="text" readonly class="msb-copy code" value="<?php echo esc_attr( $c[0] ); ?>" onclick="this.select()"></td>
						<td><?php echo esc_html( $c[1] ); ?></td>
						<td><small><?php echo esc_html( $c[2] ); ?></small></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2>Beispiele</h2>
			<ul class="msb-examples">
				<li><code>[msb_items category="kraxen" columns="3"]</code> – alle Kraxen in drei Spalten</li>
				<li><code>[msb_items layout="list" show_specs="yes" show_deposit="yes"]</code> – Liste mit technischen Daten und Kaution</li>
				<li><code>[msb_items orderby="price" limit="4" show_excerpt="no"]</code> – die 4 günstigsten Artikel</li>
				<li><code>[msb_search columns="2"]</code> – Startseite mit Zeitraumsuche</li>
			</ul>

			<div class="msb-columns">
				<div>
					<h2>Ihre Artikel</h2>
					<?php if ( $items ) : ?>
						<table class="widefat striped"><thead><tr><th>ID</th><th>Artikel</th><th>Shortcode</th></tr></thead><tbody>
						<?php foreach ( $items as $p ) : ?>
							<tr><td><?php echo (int) $p->ID; ?></td><td><a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a></td><td><input type="text" readonly class="msb-copy code" value="<?php echo esc_attr( '[msb_item id="' . $p->ID . '"]' ); ?>" onclick="this.select()"></td></tr>
						<?php endforeach; ?>
						</tbody></table>
					<?php else : ?>
						<p>Noch keine Artikel. <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . MSB_Items::POST_TYPE ) ); ?>">Ersten Artikel anlegen</a></p>
					<?php endif; ?>
				</div>
				<div>
					<h2>Ihre Kategorien</h2>
					<?php if ( ! is_wp_error( $terms ) && $terms ) : ?>
						<table class="widefat striped"><thead><tr><th>Kategorie</th><th>Shortcode</th></tr></thead><tbody>
						<?php foreach ( $terms as $t ) : ?>
							<tr><td><?php echo esc_html( $t->name ); ?></td><td><input type="text" readonly class="msb-copy code" value="<?php echo esc_attr( '[msb_items category="' . $t->slug . '"]' ); ?>" onclick="this.select()"></td></tr>
						<?php endforeach; ?>
						</tbody></table>
					<?php else : ?>
						<p>Noch keine Kategorien.</p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
