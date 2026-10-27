<?php
/**
 * Optionale Kundenkonten: Registrierung, Anmeldung, "Mein Konto".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Accounts {

	const ROLE = 'msb_customer';

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'handle_requests' ), 6 );
		add_shortcode( 'msb_account', array( __CLASS__, 'shortcode' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
		add_action( 'admin_init', array( __CLASS__, 'block_admin' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'admin_bar' ) );
	}

	public static function enabled() {
		return (bool) msb_setting( 'accounts_enabled' );
	}

	public static function is_customer( $user = null ) {
		$user = $user ? $user : wp_get_current_user();
		return $user && $user->exists() && in_array( self::ROLE, (array) $user->roles, true );
	}

	public static function login_redirect( $redirect, $requested, $user ) {
		if ( $user instanceof WP_User && self::is_customer( $user ) && ( ! $requested || false !== strpos( $requested, 'wp-admin' ) ) ) {
			$url = msb_account_url();
			return $url ? $url : home_url( '/' );
		}
		return $redirect;
	}

	public static function block_admin() {
		if ( self::is_customer() && ! wp_doing_ajax() && ! current_user_can( 'edit_posts' ) ) {
			$url = msb_account_url();
			wp_safe_redirect( $url ? $url : home_url( '/' ) );
			exit;
		}
	}

	public static function admin_bar( $show ) {
		return ( self::is_customer() && msb_setting( 'hide_admin_bar' ) ) ? false : $show;
	}

	/* ------------------------------------------------------------------
	 * Konto anlegen / bestätigen
	 * ------------------------------------------------------------------ */

	/**
	 * Legt ein WordPress-Benutzerkonto für einen Mietkunden an.
	 *
	 * @return int|WP_Error Benutzer-ID
	 */
	public static function create_user( $email, $password, $first = '', $last = '' ) {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'msb_account', 'Bitte geben Sie eine gültige E-Mail-Adresse ein.' );
		}
		if ( email_exists( $email ) || username_exists( $email ) ) {
			return new WP_Error( 'msb_account_exists', 'Zu dieser E-Mail-Adresse gibt es bereits ein Konto. Bitte melden Sie sich an oder buchen Sie ohne Konto.' );
		}
		if ( strlen( (string) $password ) < 8 ) {
			return new WP_Error( 'msb_password', 'Das Passwort muss mindestens 8 Zeichen lang sein.' );
		}
		$user_id = wp_insert_user(
			array(
				'user_login'   => $email,
				'user_email'   => $email,
				'user_pass'    => $password,
				'first_name'   => $first,
				'last_name'    => $last,
				'display_name' => trim( $first . ' ' . $last ) ? trim( $first . ' ' . $last ) : $email,
				'role'         => self::ROLE,
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		update_user_meta( $user_id, 'msb_verified', 0 );
		self::send_verification( $user_id );
		do_action( 'msb_account_created', $user_id );
		return $user_id;
	}

	public static function login( $user_id ) {
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );
	}

	public static function send_verification( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		$key = wp_generate_password( 20, false );
		update_user_meta( $user_id, 'msb_verify_key', wp_hash_password( $key ) );
		$url  = add_query_arg(
			array(
				'msb_verify' => 1,
				'u'          => $user_id,
				'k'          => $key,
			),
			msb_account_url() ? msb_account_url() : home_url( '/' )
		);
		$body = '<p>Hallo ' . esc_html( $user->first_name ? $user->first_name : $user->display_name ) . ',</p>'
			. '<p>bitte bestätigen Sie Ihre E-Mail-Adresse für Ihr Kundenkonto bei ' . esc_html( get_bloginfo( 'name' ) ) . ':</p>'
			. '<p><a href="' . esc_url( $url ) . '">E-Mail-Adresse bestätigen</a></p>'
			. '<p>Danach sehen Sie in Ihrem Konto auch frühere Buchungen, die Sie ohne Konto mit dieser E-Mail-Adresse gemacht haben.</p>';
		MSB_Emails::send_raw( $user->user_email, 'Bitte bestätigen Sie Ihre E-Mail-Adresse', $body );
	}

	public static function is_verified( $user_id ) {
		return (bool) get_user_meta( $user_id, 'msb_verified', true ) || ! self::is_customer( get_userdata( $user_id ) );
	}

	/* ------------------------------------------------------------------
	 * Formulare verarbeiten
	 * ------------------------------------------------------------------ */

	private static function redirect_with( $args ) {
		$url = msb_account_url() ? msb_account_url() : home_url( '/' );
		wp_safe_redirect( add_query_arg( $args, $url ) );
		exit;
	}

	private static function flash( $message, $type = 'success', $values = array() ) {
		$key = 'msb_flash_' . ( is_user_logged_in() ? get_current_user_id() : md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' ) );
		set_transient( $key, array( $message, $type, $values ), 120 );
	}

	private static function take_flash() {
		$key = 'msb_flash_' . ( is_user_logged_in() ? get_current_user_id() : md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' ) );
		$f   = get_transient( $key );
		delete_transient( $key );
		return is_array( $f ) ? $f : null;
	}

	public static function handle_requests() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_GET['msb_verify'], $_GET['u'], $_GET['k'] ) ) {
			$uid  = absint( $_GET['u'] );
			$hash = get_user_meta( $uid, 'msb_verify_key', true );
			if ( $hash && wp_check_password( sanitize_text_field( wp_unslash( $_GET['k'] ) ), $hash ) ) {
				update_user_meta( $uid, 'msb_verified', 1 );
				delete_user_meta( $uid, 'msb_verify_key' );
				$c = MSB_Customers::get_by_user( $uid );
				if ( $c ) {
					MSB_Customers::merge_guests_into( $c['id'] );
				}
				self::flash( 'Vielen Dank, Ihre E-Mail-Adresse ist bestätigt.' );
			} else {
				self::flash( 'Der Bestätigungslink ist ungültig oder bereits verwendet.', 'error' );
			}
			self::redirect_with( array() );
		}

		if ( empty( $_POST['msb_account_action'] ) ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_POST['msb_account_action'] ) );
		check_admin_referer( 'msb_account_' . $action );

		if ( 'register' === $action && ! is_user_logged_in() && self::enabled() ) {
			$input                   = isset( $_POST['msb_field'] ) && is_array( $_POST['msb_field'] ) ? wp_unslash( $_POST['msb_field'] ) : array(); // phpcs:ignore
			list( $values, $errors ) = MSB_Form_Fields::validate( $input, MSB_Form_Fields::profile_fields() );
			$email                   = self::email_from( $values );
			$pass                    = isset( $_POST['msb_password'] ) ? (string) wp_unslash( $_POST['msb_password'] ) : ''; // phpcs:ignore
			$pass2                   = isset( $_POST['msb_password2'] ) ? (string) wp_unslash( $_POST['msb_password2'] ) : ''; // phpcs:ignore
			if ( ! $email ) {
				$errors['email'] = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
			}
			if ( $pass !== $pass2 ) {
				$errors['password'] = 'Die Passwörter stimmen nicht überein.';
			}
			if ( empty( $_POST['msb_privacy'] ) ) {
				$errors['privacy'] = 'Bitte bestätigen Sie die Datenschutzerklärung.';
			}
			if ( '' !== ( isset( $_POST['msb_website'] ) ? sanitize_text_field( wp_unslash( $_POST['msb_website'] ) ) : '' ) ) {
				$errors['spam'] = 'Ihre Anfrage konnte nicht verarbeitet werden.';
			}
			if ( ! $errors ) {
				$uid = self::create_user( $email, $pass, isset( $values['first_name'] ) ? $values['first_name'] : '', isset( $values['last_name'] ) ? $values['last_name'] : '' );
				if ( is_wp_error( $uid ) ) {
					$errors['account'] = $uid->get_error_message();
				} else {
					MSB_Customers::save( $values, 0, $uid );
					self::login( $uid );
					self::flash( 'Ihr Konto wurde angelegt. Wir haben Ihnen eine E-Mail zur Bestätigung Ihrer Adresse geschickt.' );
					self::redirect_with( array() );
				}
			}
			unset( $values['msb_password'] );
			self::flash( implode( ' ', array_unique( $errors ) ), 'error', $values );
			self::redirect_with( array( 'tab' => 'register' ) );
		}

		if ( ! is_user_logged_in() ) {
			return;
		}
		$user = wp_get_current_user();

		if ( 'resend' === $action ) {
			self::send_verification( $user->ID );
			self::flash( 'Wir haben Ihnen die Bestätigungs-E-Mail erneut gesendet.' );
			self::redirect_with( array() );
		}

		// Stornierung durch den Kunden (KTO-07).
		if ( 'cancel' === $action && class_exists( 'MSB_Cancellation' ) ) {
			$booking = self::own_booking( $user, isset( $_POST['booking'] ) ? absint( $_POST['booking'] ) : 0 );
			if ( ! $booking || ! MSB_Cancellation::customer_can_cancel( $booking ) ) {
				self::flash( 'Diese Buchung kann nicht mehr online storniert werden. Bitte melden Sie sich bei uns.', 'error' );
				self::redirect_with( array() );
			}
			$r = MSB_Cancellation::execute( $booking['id'], 'customer' );
			if ( is_wp_error( $r ) ) {
				self::flash( $r->get_error_message(), 'error' );
				self::redirect_with( array() );
			}
			$msg = sprintf( 'Ihre Buchung %s wurde storniert.', $booking['booking_number'] );
			if ( $r['refunded'] > 0 ) {
				$msg .= sprintf( ' %s werden auf Ihr Zahlungsmittel erstattet.', msb_price( $r['refunded'] ) );
			}
			if ( $r['errors'] || $r['manual'] > 0 ) {
				$msg .= sprintf( ' Die Erstattung von %s klären wir persönlich mit Ihnen.', msb_price( $r['refund'] - $r['refunded'] ) );
			}
			self::flash( $msg );
			self::redirect_with( array() );
		}

		if ( 'profile' === $action ) {
			$input                   = isset( $_POST['msb_field'] ) && is_array( $_POST['msb_field'] ) ? wp_unslash( $_POST['msb_field'] ) : array(); // phpcs:ignore
			list( $values, $errors ) = MSB_Form_Fields::validate( $input, MSB_Form_Fields::profile_fields() );
			$email                   = self::email_from( $values );
			$new                     = isset( $_POST['msb_new_password'] ) ? (string) wp_unslash( $_POST['msb_new_password'] ) : ''; // phpcs:ignore
			$current                 = isset( $_POST['msb_current_password'] ) ? (string) wp_unslash( $_POST['msb_current_password'] ) : ''; // phpcs:ignore

			if ( $email && strtolower( $email ) !== strtolower( $user->user_email ) ) {
				$other = email_exists( $email );
				if ( $other && (int) $other !== (int) $user->ID ) {
					$errors['email'] = 'Diese E-Mail-Adresse wird bereits von einem anderen Konto verwendet.';
				}
			}
			if ( '' !== $new ) {
				if ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
					$errors['password'] = 'Das aktuelle Passwort ist nicht korrekt.';
				} elseif ( strlen( $new ) < 8 ) {
					$errors['password'] = 'Das neue Passwort muss mindestens 8 Zeichen lang sein.';
				}
			}
			if ( $errors ) {
				self::flash( implode( ' ', array_unique( $errors ) ), 'error', $values );
				self::redirect_with( array( 'tab' => 'profile' ) );
			}

			$customer = MSB_Customers::get_by_user( $user->ID );
			MSB_Customers::save( $values, $customer ? $customer['id'] : 0, $user->ID );

			$update = array(
				'ID'         => $user->ID,
				'first_name' => isset( $values['first_name'] ) ? $values['first_name'] : $user->first_name,
				'last_name'  => isset( $values['last_name'] ) ? $values['last_name'] : $user->last_name,
			);
			$email_changed = $email && strtolower( $email ) !== strtolower( $user->user_email );
			if ( $email_changed ) {
				$update['user_email'] = $email;
			}
			if ( '' !== $new ) {
				$update['user_pass'] = $new;
			}
			wp_update_user( $update );
			if ( '' !== $new ) {
				self::login( $user->ID ); // Sitzung nach Passwortwechsel erneuern.
			}
			if ( $email_changed && self::is_customer( $user ) ) {
				update_user_meta( $user->ID, 'msb_verified', 0 );
				self::send_verification( $user->ID );
			}
			self::flash( 'Ihre Daten wurden gespeichert.' . ( $email_changed ? ' Bitte bestätigen Sie Ihre neue E-Mail-Adresse.' : '' ) );
			self::redirect_with( array( 'tab' => 'profile' ) );
		}
		// phpcs:enable
	}

	private static function email_from( array $values ) {
		foreach ( MSB_Form_Fields::get() as $f ) {
			if ( 'email' === $f['type'] && ! empty( $values[ $f['key'] ] ) && is_email( $values[ $f['key'] ] ) ) {
				return $values[ $f['key'] ];
			}
		}
		return '';
	}

	/**
	 * Formularwerte für den eingeloggten Kunden (zum Vorausfüllen).
	 */
	public static function prefill_values() {
		if ( ! is_user_logged_in() ) {
			return array();
		}
		$user     = wp_get_current_user();
		$customer = MSB_Customers::get_by_user( $user->ID );
		$values   = $customer ? $customer['data'] : array();
		$values   = wp_parse_args(
			$values,
			array(
				'first_name' => $user->first_name,
				'last_name'  => $user->last_name,
			)
		);
		foreach ( MSB_Form_Fields::get() as $f ) {
			if ( 'email' === $f['type'] && empty( $values[ $f['key'] ] ) ) {
				$values[ $f['key'] ] = $user->user_email;
				break;
			}
		}
		return $values;
	}

	/* ------------------------------------------------------------------
	 * [msb_account]
	 * ------------------------------------------------------------------ */

	public static function shortcode( $atts ) {
		MSB_Shortcodes::enqueue_assets();
		$flash = self::take_flash();
		$html  = '<div class="msb msb-account">';
		if ( $flash ) {
			$html .= '<div class="msb-msg msb-msg--' . ( 'error' === $flash[1] ? 'error' : 'success' ) . '">' . esc_html( $flash[0] ) . '</div>';
		}

		if ( ! is_user_logged_in() ) {
			if ( ! self::enabled() ) {
				return $html . '<p>Kundenkonten sind derzeit nicht verfügbar.</p></div>';
			}
			return $html . self::logged_out_view( $flash ? $flash[2] : array() ) . '</div>';
		}

		$user = wp_get_current_user();
		$tab  = isset( $_GET['tab'] ) && 'profile' === $_GET['tab'] ? 'profile' : 'bookings'; // phpcs:ignore
		$base = msb_account_url() ? msb_account_url() : get_permalink();

		$html .= '<div class="msb-account__head"><p>Hallo <strong>' . esc_html( $user->first_name ? $user->first_name : $user->display_name ) . '</strong></p>';
		$html .= '<nav class="msb-account__nav">';
		$html .= '<a href="' . esc_url( $base ) . '" class="' . ( 'bookings' === $tab ? 'is-active' : '' ) . '">Meine Buchungen</a>';
		$html .= '<a href="' . esc_url( add_query_arg( 'tab', 'profile', $base ) ) . '" class="' . ( 'profile' === $tab ? 'is-active' : '' ) . '">Meine Daten</a>';
		$html .= '<a href="' . esc_url( wp_logout_url( $base ) ) . '">Abmelden</a>';
		$html .= '</nav></div>';

		if ( ! self::is_verified( $user->ID ) ) {
			$html .= '<form method="post" class="msb-msg msb-msg--hintbox">' . wp_nonce_field( 'msb_account_resend', '_wpnonce', true, false ) . '<input type="hidden" name="msb_account_action" value="resend"><span>Bitte bestätigen Sie Ihre E-Mail-Adresse über den Link in unserer E-Mail. Erst dann zeigen wir hier auch Buchungen, die Sie früher ohne Konto gemacht haben.</span> <button type="submit" class="msb-btn msb-btn--small">Link erneut senden</button></form>';
		}

		$cancel = isset( $_GET['cancel'] ) ? absint( $_GET['cancel'] ) : 0; // phpcs:ignore
		if ( 'bookings' === $tab && $cancel && class_exists( 'MSB_Cancellation' ) ) {
			return $html . self::cancel_view( $user, $cancel, $base ) . '</div>';
		}
		$html .= 'profile' === $tab ? self::profile_view( $user, $flash ? $flash[2] : array() ) : self::bookings_view( $user );
		return $html . '</div>';
	}

	/**
	 * Buchung des angemeldeten Kunden (oder null).
	 */
	private static function own_booking( WP_User $user, $booking_id ) {
		$customer = MSB_Customers::get_by_user( $user->ID );
		$booking  = $booking_id ? MSB_Bookings::get( $booking_id ) : null;
		if ( ! $customer || ! $booking || (int) $booking['customer_id'] !== (int) $customer['id'] ) {
			return null;
		}
		return $booking;
	}

	/**
	 * Bestätigungsseite vor dem Stornieren: zeigt Gebühr und Erstattung (KTO-07).
	 */
	private static function cancel_view( WP_User $user, $booking_id, $base ) {
		$booking = self::own_booking( $user, $booking_id );
		$back    = '<p><a href="' . esc_url( $base ) . '">← Zurück zu meinen Buchungen</a></p>';
		if ( ! $booking || ! MSB_Cancellation::customer_can_cancel( $booking ) ) {
			return '<div class="msb-msg msb-msg--error">Diese Buchung kann nicht mehr online storniert werden. Bitte melden Sie sich bei uns.</div>' . $back;
		}
		$q    = MSB_Cancellation::quote( $booking );
		$pct  = rtrim( rtrim( number_format( $q['percent'], 2, ',', '' ), '0' ), ',' );
		$html = '<section class="msb-account__box is-primary"><h2>Buchung ' . esc_html( $booking['booking_number'] ) . ' stornieren</h2><ul>';
		foreach ( $booking['items'] as $l ) {
			$html .= '<li>' . (int) $l['quantity'] . '× ' . esc_html( $l['item_title'] ) . ' <span class="msb-muted">' . esc_html( msb_date( $l['start_date'] ) . ' – ' . msb_date( $l['end_date'] ) ) . '</span></li>';
		}
		$html .= '</ul><dl class="msb-account__facts">';
		$html .= '<div><dt>Stornogebühr</dt><dd>' . esc_html( msb_price( $q['fee'] ) ) . ' <span class="msb-muted">(' . esc_html( $pct ) . ' % der Tagesmiete)</span></dd></div>';
		$html .= '<div><dt>Bezahlt</dt><dd>' . esc_html( msb_price( $q['paid'] ) ) . '</dd></div>';
		$html .= '<div><dt>Erstattung</dt><dd><strong>' . esc_html( msb_price( $q['refund'] ) ) . '</strong></dd></div>';
		$html .= '</dl>';
		$html .= '<p class="msb-muted">Bis 7 Tage vor Mietbeginn ist die Stornierung kostenlos, ab 48 Stunden vorher kostet sie 25 %, danach 50 % der Tagesmiete (ohne Grundgebühr). Die Fristen zählen ab 0:00 Uhr am Abholtag.';
		if ( $q['refund'] > 0 ) {
			$html .= ' Die Erstattung geht auf das Zahlungsmittel, mit dem Sie bezahlt haben.';
		}
		if ( (float) $booking['deposit'] > 0 ) {
			$html .= ' Eine reservierte Kaution wird freigegeben.';
		}
		$html .= '</p>';
		$html .= '<form method="post">' . wp_nonce_field( 'msb_account_cancel', '_wpnonce', true, false );
		$html .= '<input type="hidden" name="msb_account_action" value="cancel"><input type="hidden" name="booking" value="' . (int) $booking['id'] . '">';
		$html .= '<button type="submit" class="msb-btn msb-btn--primary">Jetzt verbindlich stornieren</button> <a class="msb-btn" href="' . esc_url( $base ) . '">Abbrechen</a></form></section>';
		return $html;
	}

	private static function logged_out_view( array $values ) {
		$tab  = isset( $_GET['tab'] ) && 'register' === $_GET['tab'] ? 'register' : 'login'; // phpcs:ignore
		$html = '<div class="msb-account__auth">';

		$html .= '<section class="msb-account__box' . ( 'login' === $tab ? ' is-primary' : '' ) . '"><h2>Anmelden</h2>';
		$html .= wp_login_form(
			array(
				'echo'           => false,
				'redirect'       => msb_account_url() ? msb_account_url() : home_url( '/' ),
				'label_username' => 'E-Mail-Adresse',
				'label_password' => 'Passwort',
				'label_remember' => 'Angemeldet bleiben',
				'label_log_in'   => 'Anmelden',
				'remember'       => true,
			)
		);
		$html .= '<p><a href="' . esc_url( wp_lostpassword_url( msb_account_url() ) ) . '">Passwort vergessen?</a></p></section>';

		$html .= '<section class="msb-account__box' . ( 'register' === $tab ? ' is-primary' : '' ) . '" id="msb-register"><h2>Neues Kundenkonto</h2>';
		$html .= '<p class="msb-muted">Optional – Sie können auch ohne Konto buchen. Mit Konto sehen Sie Ihre Buchungen und müssen Ihre Daten nicht jedes Mal neu eingeben.</p>';
		$html .= '<form method="post" class="msb-form msb-register-form">' . wp_nonce_field( 'msb_account_register', '_wpnonce', true, false );
		$html .= '<input type="hidden" name="msb_account_action" value="register">';
		$html .= '<div class="msb-form__grid">' . MSB_Form_Fields::render( $values, MSB_Form_Fields::profile_fields() );
		$html .= '<div class="msb-field msb-field--half"><label for="msb-pass">Passwort <span class="msb-req">*</span></label><input type="password" id="msb-pass" name="msb_password" minlength="8" required autocomplete="new-password"></div>';
		$html .= '<div class="msb-field msb-field--half"><label for="msb-pass2">Passwort wiederholen <span class="msb-req">*</span></label><input type="password" id="msb-pass2" name="msb_password2" minlength="8" required autocomplete="new-password"></div>';
		$privacy = get_privacy_policy_url();
		$html   .= '<div class="msb-field msb-field--full"><label class="msb-choice"><input type="checkbox" name="msb_privacy" value="1" required> <span>Ich habe die ' . ( $privacy ? '<a href="' . esc_url( $privacy ) . '" target="_blank">Datenschutzerklärung</a>' : 'Datenschutzerklärung' ) . ' zur Kenntnis genommen. <span class="msb-req">*</span></span></label></div>';
		$html   .= '</div><div class="msb-hp" aria-hidden="true"><input type="text" name="msb_website" tabindex="-1" autocomplete="off"></div>';
		$html   .= '<button type="submit" class="msb-btn msb-btn--primary">Konto anlegen</button></form></section>';

		return $html . '</div>';
	}

	private static function bookings_view( WP_User $user ) {
		$customer = MSB_Customers::get_by_user( $user->ID );
		$bookings = $customer ? MSB_Bookings::by_customer( $customer['id'] ) : array();
		if ( ! $bookings ) {
			$archive = get_post_type_archive_link( MSB_Items::POST_TYPE );
			return '<div class="msb-empty">Sie haben noch keine Buchungen.' . ( $archive ? ' <a href="' . esc_url( $archive ) . '">Jetzt Artikel entdecken</a>' : '' ) . '</div>';
		}
		$today = msb_today();
		$html  = '<div class="msb-account__bookings">';
		foreach ( $bookings as $b ) {
			$sum    = MSB_Payments::summary( $b );
			$start  = '';
			$end    = '';
			foreach ( $b['items'] as $l ) {
				$start = $start ? min( $start, $l['start_date'] ) : $l['start_date'];
				$end   = max( $end, $l['end_date'] );
			}
			$upcoming = $end >= $today && ! in_array( $b['status'], array( 'cancelled', 'returned' ), true );
			$html    .= '<article class="msb-account__booking' . ( $upcoming ? ' is-upcoming' : '' ) . '">';
			$html    .= '<header><strong>' . esc_html( $b['booking_number'] ) . '</strong> <span class="msb-badge msb-badge--' . esc_attr( $b['status'] ) . '">' . esc_html( msb_status_label( $b['status'] ) ) . '</span><span class="msb-muted">' . esc_html( msb_date( $start ) . ' – ' . msb_date( $end ) ) . '</span></header>';
			$html    .= '<ul>';
			foreach ( $b['items'] as $l ) {
				$html .= '<li>' . (int) $l['quantity'] . '× <a href="' . esc_url( get_permalink( $l['item_id'] ) ) . '">' . esc_html( $l['item_title'] ) . '</a> <span class="msb-muted">' . esc_html( msb_date( $l['start_date'] ) . ' – ' . msb_date( $l['end_date'] ) ) . '</span></li>';
			}
			$html .= '</ul><dl class="msb-account__facts">';
			$html .= '<div><dt>Mietpreis</dt><dd>' . esc_html( msb_price( $b['total'] ) ) . '</dd></div>';
			$html .= '<div><dt>Bezahlt</dt><dd>' . esc_html( msb_price( $sum['paid'] ) ) . '</dd></div>';
			if ( (float) $b['deposit'] > 0 ) {
				$html .= '<div><dt>Kaution</dt><dd>' . esc_html( msb_price( $b['deposit'] ) ) . ' <span class="msb-muted">(' . esc_html( $sum['security_label'] ) . ')</span></dd></div>';
			}
			if ( 'cancelled' === $b['status'] && isset( $b['cancel_fee'] ) && class_exists( 'MSB_Cancellation' ) ) {
				$html .= '<div><dt>Stornogebühr</dt><dd>' . esc_html( msb_price( $b['cancel_fee'] ) ) . '</dd></div>';
				$html .= '<div><dt>Erstattet</dt><dd>' . esc_html( msb_price( MSB_Cancellation::refunded_total( $b['id'] ) ) ) . '</dd></div>';
			}
			$html .= '</dl>';
			$html .= class_exists( 'MSB_Documents' ) ? MSB_Documents::account_links( $b ) : '';
			// Barzahlung: Miete und Kaution bei Abholung, kein Online-Knopf. Online: erst die offene Miete, dann die Kaution.
			$for = MSB_Payments::is_cash( $b ) ? '' : MSB_Payments::default_for( $b );
			if ( 'security' === $for && MSB_Emails::needs_online_payment( $b ) ) {
				$for = 'rest';
			}
			if ( $upcoming && $for && MSB_Payments::online_providers() ) {
				$labels = MSB_Payments::type_labels();
				$html  .= '<p><a class="msb-btn msb-btn--small msb-btn--primary" href="' . esc_url( MSB_Payments::pay_url( $b, $for ) ) . '">' . esc_html( $labels[ $for ] ) . ' jetzt online ' . ( 'security' === $for ? 'hinterlegen' : 'bezahlen' ) . '</a></p>';
			}
			if ( class_exists( 'MSB_Cancellation' ) && MSB_Cancellation::customer_can_cancel( $b ) ) {
				$html .= '<p><a class="msb-btn msb-btn--small" href="' . esc_url( add_query_arg( 'cancel', (int) $b['id'], msb_account_url() ? msb_account_url() : get_permalink() ) ) . '">Stornieren</a></p>';
			}
			$html .= '</article>';
		}
		return $html . '</div>';
	}

	private static function profile_view( WP_User $user, array $values ) {
		$values = $values ? $values : self::prefill_values();
		$html   = '<form method="post" class="msb-form msb-profile-form">' . wp_nonce_field( 'msb_account_profile', '_wpnonce', true, false );
		$html  .= '<input type="hidden" name="msb_account_action" value="profile">';
		$html  .= '<div class="msb-form__grid">' . MSB_Form_Fields::render( $values, MSB_Form_Fields::profile_fields() );
		$html  .= '<h3 class="msb-form__heading msb-field--full">Passwort ändern <small class="msb-muted">(optional)</small></h3>';
		$html  .= '<div class="msb-field msb-field--half"><label for="msb-cur">Aktuelles Passwort</label><input type="password" id="msb-cur" name="msb_current_password" autocomplete="current-password"></div>';
		$html  .= '<div class="msb-field msb-field--half"><label for="msb-new">Neues Passwort</label><input type="password" id="msb-new" name="msb_new_password" minlength="8" autocomplete="new-password"></div>';
		$html  .= '</div><button type="submit" class="msb-btn msb-btn--primary">Speichern</button></form>';
		return $html;
	}
}
