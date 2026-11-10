<?php
/**
 * Werbe-Kurzlinks (1.9.0): /qr/<name> für QR-Codes und Aufkleber, z. B. am Mietanhänger.
 * Leitet mit UTM-Parametern auf ein frei wählbares Ziel der eigenen Seite weiter und zählt die Aufrufe je Monat.
 * Landet der Besucher direkt auf einem Artikel und legt ihn in den Warenkorb, trägt die Buchung die Quelle
 * „Webseite über QR-Code“ (Herkunft hängt am Warenkorb, kein eigenes Cookie, wie bei den Kleinanzeigen-Kurzlinks).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Promo_Links {

	const OPTION     = 'msb_promo_links';
	const QUERY_VAR  = 'msb_qr';
	const SOURCE     = 'qr';
	const PREFIX     = 'qr-';
	const RULES      = '1.9.0';
	const BOT_AGENTS = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|curl|wget|python|headless/i';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'redirect' ), 1 );

		add_action( 'wp_ajax_msb_cart_add', array( __CLASS__, 'remember_ref' ), 1 );
		add_action( 'wp_ajax_nopriv_msb_cart_add', array( __CLASS__, 'remember_ref' ), 1 );
		add_action( 'msb_booking_created', array( __CLASS__, 'on_booking_created' ), 10, 2 );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'menu' ), 12 );
			add_action( 'admin_post_msb_promo_save', array( __CLASS__, 'handle_save' ) );
			add_action( 'admin_post_msb_promo_delete', array( __CLASS__, 'handle_delete' ) );
		}
	}

	/* ------------------------------------------------------------------
	 * Daten
	 * ------------------------------------------------------------------ */

	/**
	 * Alle Kurzlinks: name => array( label, target, clicks => array( 'JJJJ-MM' => Anzahl ) ).
	 * Beim ersten Aufruf nach dem Update wird der Link für den ersten Anhänger angelegt (weitere: anhaenger-2 …).
	 */
	public static function links() {
		$links = get_option( self::OPTION, null );
		if ( null === $links ) {
			$links = array(
				'anhaenger-1' => array(
					'label'  => 'Aufkleber PKW-Anhänger 1 (Neptun N7-236)',
					'target' => '/#neu',
					'clicks' => array(),
				),
			);
			update_option( self::OPTION, $links, false );
		}
		return is_array( $links ) ? $links : array();
	}

	private static function store( array $links ) {
		ksort( $links );
		update_option( self::OPTION, $links, false );
	}

	public static function sanitize_slug( $slug ) {
		return substr( sanitize_title( (string) $slug ), 0, 17 ); // channel hat 20 Zeichen, davon 3 für „qr-“.
	}

	/**
	 * Ziel nur auf der eigenen Seite: Pfad („/#neu“, „/mieten/…“) oder volle Adresse mit eigenem Host.
	 */
	public static function sanitize_target( $target ) {
		$target = trim( (string) $target );
		if ( '' === $target ) {
			return '/';
		}
		if ( '/' === $target[0] && ( strlen( $target ) < 2 || '/' !== $target[1] ) ) {
			return esc_url_raw( $target ) ? $target : '/';
		}
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$url  = esc_url_raw( $target, array( 'https', 'http' ) );
		if ( $url && wp_parse_url( $url, PHP_URL_HOST ) === $host ) {
			return $url;
		}
		return '';
	}

	public static function target_url( $target ) {
		return '/' === substr( $target, 0, 1 ) ? home_url( $target ) : $target;
	}

	public static function short_url( $slug ) {
		return home_url( '/qr/' . $slug );
	}

	public static function utm_url( $url, $slug ) {
		return add_query_arg(
			array(
				'utm_source'   => self::SOURCE,
				'utm_medium'   => 'offline',
				'utm_campaign' => $slug,
			),
			$url
		);
	}

	/* ------------------------------------------------------------------
	 * Weiterleitung /qr/<name>
	 * ------------------------------------------------------------------ */

	public static function rewrite() {
		add_rewrite_rule( '^qr/([a-z0-9-]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
		if ( self::RULES !== get_option( 'msb_qr_rules' ) ) {
			update_option( 'msb_qr_rules', self::RULES );
			update_option( 'msb_flush_rewrite', 1 );
		}
	}

	public static function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public static function redirect() {
		$slug = self::sanitize_slug( get_query_var( self::QUERY_VAR ) );
		if ( '' === $slug ) {
			return;
		}
		$links = self::links();
		if ( ! isset( $links[ $slug ] ) ) {
			wp_redirect( home_url( '/' ), 302 );
			exit;
		}
		if ( self::countable() ) {
			$month = current_time( 'Y-m' );
			$c     = isset( $links[ $slug ]['clicks'] ) && is_array( $links[ $slug ]['clicks'] ) ? $links[ $slug ]['clicks'] : array();
			$c[ $month ] = ( isset( $c[ $month ] ) ? (int) $c[ $month ] : 0 ) + 1;
			$links[ $slug ]['clicks'] = $c;
			self::store( $links );
		}
		wp_redirect( self::utm_url( self::target_url( $links[ $slug ]['target'] ), $slug ), 302 );
		exit;
	}

	/**
	 * Gezählt wird ohne HEAD-Anfragen, Bots, Vorschauen und ohne Nutzer, die Artikel bearbeiten dürfen.
	 */
	private static function countable() {
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : ''; // phpcs:ignore
		$head  = isset( $_SERVER['REQUEST_METHOD'] ) && 'HEAD' === $_SERVER['REQUEST_METHOD'];
		return ! ( $head || '' === $agent || preg_match( self::BOT_AGENTS, $agent ) || current_user_can( 'edit_msb_items' ) );
	}

	public static function total( array $link, $month = '' ) {
		$c = isset( $link['clicks'] ) && is_array( $link['clicks'] ) ? $link['clicks'] : array();
		if ( '' !== $month ) {
			return isset( $c[ $month ] ) ? (int) $c[ $month ] : 0;
		}
		return (int) array_sum( array_map( 'intval', $c ) );
	}

	/* ------------------------------------------------------------------
	 * Herkunft bis zur Buchung
	 * ------------------------------------------------------------------ */

	public static function remember_ref() {
		$ref = wp_get_referer();
		if ( ! $ref ) {
			return;
		}
		parse_str( (string) wp_parse_url( $ref, PHP_URL_QUERY ), $args );
		if ( ! isset( $args['utm_source'], $args['utm_campaign'] ) || self::SOURCE !== sanitize_key( $args['utm_source'] ) ) {
			return;
		}
		$slug = self::sanitize_slug( $args['utm_campaign'] );
		if ( '' !== $slug && isset( self::links()[ $slug ] ) && method_exists( 'MSB_Cart', 'set_ref' ) ) {
			MSB_Cart::set_ref( self::PREFIX . $slug );
		}
	}

	public static function on_booking_created( $booking_id, $args ) {
		global $wpdb;
		if ( isset( $args['source'] ) && 'admin' === $args['source'] ) {
			return;
		}
		$ref = method_exists( 'MSB_Cart', 'ref' ) ? (string) MSB_Cart::ref() : '';
		if ( self::PREFIX !== substr( $ref, 0, 3 ) ) {
			return;
		}
		$wpdb->update( MSB_Install::table( 'bookings' ), array( 'channel' => substr( $ref, 0, 20 ) ), array( 'id' => (int) $booking_id ) );
	}

	/**
	 * Text für die Quelle einer Buchung, z. B. „Webseite über QR-Code (Aufkleber am PKW-Anhänger)“.
	 */
	public static function channel_label( $channel ) {
		$slug  = substr( (string) $channel, 3 );
		$links = self::links();
		$name  = isset( $links[ $slug ]['label'] ) && '' !== $links[ $slug ]['label'] ? $links[ $slug ]['label'] : $slug;
		return 'Webseite über QR-Code (' . $name . ')';
	}

	public static function booking_count( $slug ) {
		global $wpdb;
		$b = MSB_Install::table( 'bookings' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$b} WHERE channel = %s AND is_test = 0 AND status NOT IN ('awaiting_payment','cancelled')", self::PREFIX . $slug ) ); // phpcs:ignore
	}

	/* ------------------------------------------------------------------
	 * Backend: Vermietung → Werbe-Links
	 * ------------------------------------------------------------------ */

	public static function menu() {
		add_submenu_page( 'msb-bookings', 'Werbe-Links (QR-Codes)', 'Werbe-Links', msb_capability( 'settings' ), 'msb-promo-links', array( __CLASS__, 'page' ) );
	}

	private static function page_url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => 'msb-promo-links' ), $args ), admin_url( 'admin.php' ) );
	}

	public static function page() {
		if ( ! current_user_can( msb_capability( 'settings' ) ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		$links = self::links();
		$month = current_time( 'Y-m' );
		$edit  = isset( $_GET['edit'] ) ? self::sanitize_slug( wp_unslash( $_GET['edit'] ) ) : ''; // phpcs:ignore
		$cur   = '' !== $edit && isset( $links[ $edit ] ) ? $links[ $edit ] : null;
		$msg   = isset( $_GET['msg'] ) ? sanitize_key( $_GET['msg'] ) : ''; // phpcs:ignore
		$texts = array(
			'saved'   => 'Gespeichert.',
			'deleted' => 'Gelöscht.',
			'invalid' => 'Nicht gespeichert: Der Name braucht Buchstaben oder Ziffern, das Ziel muss auf dieser Webseite liegen.',
			'exists'  => 'Nicht gespeichert: Diesen Namen gibt es schon.',
		);
		?>
		<div class="wrap msb-admin">
			<h1>Werbe-Links (QR-Codes)</h1>
			<p>Kurze Adressen für QR-Codes und Aufkleber. Jeder Aufruf leitet auf das Ziel weiter und wird gezählt (ohne Bots und ohne eigene Aufrufe im Backend). Ein gedruckter Link bleibt gültig, das Ziel kannst du jederzeit ändern.</p>
			<?php if ( isset( $texts[ $msg ] ) ) : ?>
				<div class="notice notice-<?php echo in_array( $msg, array( 'saved', 'deleted' ), true ) ? 'success' : 'error'; ?> is-dismissible"><p><?php echo esc_html( $texts[ $msg ] ); ?></p></div>
			<?php endif; ?>
			<table class="widefat striped msb-promo-table">
				<thead><tr><th>Bezeichnung</th><th>Kurzlink</th><th>Ziel</th><th>Aufrufe <?php echo esc_html( date_i18n( 'F', strtotime( $month . '-01' ) ) ); ?></th><th>Aufrufe gesamt</th><th>Buchungen</th><th>Aktionen</th></tr></thead>
				<tbody>
				<?php if ( ! $links ) : ?>
					<tr><td colspan="7">Noch keine Werbe-Links.</td></tr>
				<?php endif; ?>
				<?php foreach ( $links as $slug => $l ) : ?>
					<tr>
						<td data-label="Bezeichnung"><strong><?php echo esc_html( $l['label'] ); ?></strong></td>
						<td data-label="Kurzlink"><code><?php echo esc_html( self::short_url( $slug ) ); ?></code></td>
						<td data-label="Ziel"><?php echo esc_html( $l['target'] ); ?></td>
						<td data-label="Aufrufe diesen Monat"><?php echo (int) self::total( $l, $month ); ?></td>
						<td data-label="Aufrufe gesamt"><?php echo (int) self::total( $l ); ?></td>
						<td data-label="Buchungen"><?php echo (int) self::booking_count( $slug ); ?></td>
						<td data-label="Aktionen" style="white-space:nowrap">
							<a class="button" href="<?php echo esc_url( self::short_url( $slug ) ); ?>" target="_blank" rel="noopener">Öffnen</a>
							<a class="button" href="<?php echo esc_url( self::page_url( array( 'edit' => $slug ) ) ); ?>">Bearbeiten</a>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;margin-left:24px" onsubmit="return confirm('Werbe-Link wirklich löschen? Gedruckte QR-Codes führen danach auf die Startseite.');">
								<input type="hidden" name="action" value="msb_promo_delete">
								<input type="hidden" name="slug" value="<?php echo esc_attr( $slug ); ?>">
								<?php wp_nonce_field( 'msb_promo_delete_' . $slug ); ?>
								<button type="submit" class="button button-link-delete">Löschen</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php echo $cur ? 'Werbe-Link bearbeiten' : 'Neuer Werbe-Link'; ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="msb_promo_save">
				<input type="hidden" name="original" value="<?php echo esc_attr( $cur ? $edit : '' ); ?>">
				<?php wp_nonce_field( 'msb_promo_save' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="msb-promo-slug">Name im Link</label></th>
						<td>
							<?php if ( $cur ) : ?>
								<code><?php echo esc_html( self::short_url( $edit ) ); ?></code>
								<input type="hidden" name="slug" value="<?php echo esc_attr( $edit ); ?>">
								<p class="description">Der Name bleibt fest, weil er schon gedruckt sein kann.</p>
							<?php else : ?>
								<code><?php echo esc_html( home_url( '/qr/' ) ); ?></code><input type="text" id="msb-promo-slug" name="slug" maxlength="17" class="regular-text" placeholder="z. B. flyer" required>
								<p class="description">Kleinbuchstaben, Ziffern und Bindestrich, höchstens 17 Zeichen.</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="msb-promo-label">Bezeichnung</label></th>
						<td><input type="text" id="msb-promo-label" name="label" class="regular-text" value="<?php echo esc_attr( $cur ? $cur['label'] : '' ); ?>" placeholder="z. B. Flyer Baumarkt" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="msb-promo-target">Ziel</label></th>
						<td>
							<input type="text" id="msb-promo-target" name="target" class="regular-text code" value="<?php echo esc_attr( $cur ? $cur['target'] : '/' ); ?>" required>
							<p class="description">Pfad auf dieser Seite, z. B. <code>/#neu</code> oder <code>/mieten/pkw-anhaenger-mieten-neptun-n7-236/</code>. Buchungen werden nur zugeordnet, wenn das Ziel direkt ein Mietartikel ist.</p>
						</td>
					</tr>
				</table>
				<p>
					<?php submit_button( 'Speichern', 'primary', 'submit', false ); ?>
					<?php if ( $cur ) : ?>
						<a class="button" href="<?php echo esc_url( self::page_url() ); ?>">Abbrechen</a>
					<?php endif; ?>
				</p>
			</form>
		</div>
		<style>
			@media (max-width: 782px) {
				.msb-promo-table thead { display: none; }
				.msb-promo-table tr { display: block; margin: 0 0 12px; border: 1px solid #dcdcde; background: #fff; }
				.msb-promo-table td { display: block; padding: 6px 12px; }
				.msb-promo-table td::before { content: attr(data-label); display: block; font-size: 12px; color: #646970; }
			}
		</style>
		<?php
	}

	public static function handle_save() {
		if ( ! current_user_can( msb_capability( 'settings' ) ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		check_admin_referer( 'msb_promo_save' );
		$links    = self::links();
		$original = isset( $_POST['original'] ) ? self::sanitize_slug( wp_unslash( $_POST['original'] ) ) : '';
		$slug     = '' !== $original ? $original : ( isset( $_POST['slug'] ) ? self::sanitize_slug( wp_unslash( $_POST['slug'] ) ) : '' );
		$label    = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$target   = isset( $_POST['target'] ) ? self::sanitize_target( wp_unslash( $_POST['target'] ) ) : '';
		if ( '' === $slug || '' === $target ) {
			wp_safe_redirect( self::page_url( array( 'msg' => 'invalid' ) ) );
			exit;
		}
		if ( '' === $original && isset( $links[ $slug ] ) ) {
			wp_safe_redirect( self::page_url( array( 'msg' => 'exists' ) ) );
			exit;
		}
		$links[ $slug ] = array(
			'label'  => '' !== $label ? $label : $slug,
			'target' => $target,
			'clicks' => isset( $links[ $slug ]['clicks'] ) ? $links[ $slug ]['clicks'] : array(),
		);
		self::store( $links );
		wp_safe_redirect( self::page_url( array( 'msg' => 'saved' ) ) );
		exit;
	}

	public static function handle_delete() {
		if ( ! current_user_can( msb_capability( 'settings' ) ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		$slug = isset( $_POST['slug'] ) ? self::sanitize_slug( wp_unslash( $_POST['slug'] ) ) : '';
		check_admin_referer( 'msb_promo_delete_' . $slug );
		$links = self::links();
		unset( $links[ $slug ] );
		self::store( $links );
		wp_safe_redirect( self::page_url( array( 'msg' => 'deleted' ) ) );
		exit;
	}
}
