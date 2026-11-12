<?php
/**
 * Löschfristen (1.7.0, DSG-01 bis DSG-07, ADM-06, KTO-08).
 *
 * Ein täglicher Cron löscht abgelaufene Daten. Im Probelauf (Standard) wird nur gezählt und protokolliert.
 * Das Protokoll enthält nur Datum, Regel und Anzahl, keine personenbezogenen Daten.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Retention {

	const CRON = 'msb_cron_daily';

	/** Zahlungsstatus, bei denen Geld geflossen ist (Aufbewahrungspflicht). */
	const MONEY = "'paid','refunded','captured'";

	/** Laufende Buchungen, die nie automatisch gelöscht werden. */
	const ACTIVE = "'awaiting_payment','pending','confirmed','picked_up'";

	public static function init() {
		add_action( self::CRON, array( __CLASS__, 'cron' ) );
		if ( is_admin() ) {
			add_action( 'admin_init', array( __CLASS__, 'handle_admin' ), 7 );
			add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		}
	}

	public static function rules() {
		return array(
			'abort'           => array( 'Abgebrochene Buchungen ohne Zahlung', '30 Tage nach Storno', 'Buchung, Positionen und Zahlungsversuche werden gelöscht.' ),
			'unverified'      => array( 'Nie bestätigte Kundenkonten', '30 Tage nach Anmeldung', 'Das WordPress-Konto wird gelöscht, der Kundendatensatz bleibt als Gast für Regel 3.' ),
			'customer'        => array( 'Kundendaten ohne Aufbewahrungspflicht', '3 Jahre nach Ende des Jahres der letzten Buchung oder Anmeldung', 'Kunde und Konto werden gelöscht. Buchungen mit Zahlung oder Beleg bleiben ohne Kundenbezug und gesperrt.' ),
			'booking_nomoney' => array( 'Abgeschlossene Buchungen ohne Zahlung und Beleg', '3 Jahre nach Ende des Jahres der Rückgabe', 'Buchung und Positionen werden gelöscht.' ),
			'retention'       => array( 'Buchungen mit Zahlung oder Beleg', '8 Jahre nach Ende des Jahres des letzten Belegs bzw. der letzten Zahlung', 'Buchung, Positionen, Zahlungen, Belege und PDFs werden gelöscht.' ),
		);
	}

	public static function live() {
		return (bool) msb_setting( 'retention_live', 0 );
	}

	private static function t( $name ) {
		return MSB_Install::table( $name );
	}

	/* ------------------------------------------------------------------
	 * Aufbewahrung je Buchung
	 * ------------------------------------------------------------------ */

	/**
	 * Letzter Tag der Aufbewahrungsfrist (Y-m-d) für eine Buchung mit Zahlung oder Beleg, sonst ''.
	 */
	public static function retained_until( $booking_id ) {
		global $wpdb;
		$id   = (int) $booking_id;
		$year = (int) $wpdb->get_var( 'SELECT GREATEST( IFNULL( ( SELECT MAX( YEAR( issued_at ) ) FROM ' . self::t( 'documents' ) . " WHERE booking_id = {$id} ), 0 ), IFNULL( ( SELECT MAX( YEAR( GREATEST( created_at, updated_at ) ) ) FROM " . self::t( 'payments' ) . " WHERE booking_id = {$id} AND status IN (" . self::MONEY . ') ), 0 ) )' ); // phpcs:ignore
		return $year ? ( $year + 8 ) . '-12-31' : '';
	}

	/**
	 * Löscht eine Buchung samt Positionen, Zahlungen, Belegen und PDFs. Nur für Fristablauf und Art. 17.
	 */
	private static function purge_booking( $id ) {
		global $wpdb;
		$id  = (int) $id;
		$up  = wp_upload_dir();
		$dir = trailingslashit( $up['basedir'] ) . 'msb-belege/';
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT file FROM ' . self::t( 'documents' ) . ' WHERE booking_id = %d', $id ) ) as $file ) { // phpcs:ignore
			$file = basename( (string) $file );
			if ( $file && file_exists( $dir . $file ) ) {
				wp_delete_file( $dir . $file );
			}
		}
		$wpdb->delete( self::t( 'documents' ), array( 'booking_id' => $id ), array( '%d' ) );
		$wpdb->delete( self::t( 'booking_items' ), array( 'booking_id' => $id ), array( '%d' ) );
		$wpdb->delete( self::t( 'payments' ), array( 'booking_id' => $id ), array( '%d' ) );
		$wpdb->delete( self::t( 'bookings' ), array( 'id' => $id ), array( '%d' ) );
		do_action( 'msb_booking_deleted', $id );
	}

	/**
	 * WordPress-Konto nur löschen, wenn es ein reines Kundenkonto ist (nie Admin oder Gesellschafter).
	 */
	private static function delete_user( $user_id ) {
		$user = $user_id ? get_userdata( $user_id ) : null;
		if ( ! $user || array( MSB_Accounts::ROLE ) !== array_values( (array) $user->roles ) ) {
			return false;
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		return (bool) wp_delete_user( $user->ID );
	}

	/* ------------------------------------------------------------------
	 * Regeln: liefern die betroffenen IDs
	 * ------------------------------------------------------------------ */

	private static function find( $rule ) {
		global $wpdb;
		$b     = self::t( 'bookings' );
		$p     = self::t( 'payments' );
		$d     = self::t( 'documents' );
		$i     = self::t( 'booking_items' );
		$c     = self::t( 'customers' );
		$now   = current_time( 'mysql' );
		$year  = (int) current_time( 'Y' );
		$month = gmdate( 'Y-m-d H:i:s', strtotime( $now ) - 30 * DAY_IN_SECONDS );
		$nomoney = "NOT EXISTS (SELECT 1 FROM {$p} p WHERE p.booking_id = b.id AND p.status IN (" . self::MONEY . ")) AND NOT EXISTS (SELECT 1 FROM {$d} d WHERE d.booking_id = b.id)";

		switch ( $rule ) {
			case 'abort':
				return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT b.id FROM {$b} b WHERE b.status = 'cancelled' AND COALESCE(b.cancelled_at, b.updated_at) < %s AND {$nomoney}", $month ) ) ); // phpcs:ignore

			case 'unverified':
				$users = get_users(
					array(
						'role'       => MSB_Accounts::ROLE,
						'meta_key'   => 'msb_verified', // phpcs:ignore
						'meta_value' => '0', // phpcs:ignore
						'fields'     => array( 'ID', 'user_registered' ),
					)
				);
				$ids = array();
				foreach ( $users as $u ) {
					if ( $u->user_registered < $month ) {
						$ids[] = (int) $u->ID;
					}
				}
				return $ids;

			case 'customer':
				$rows = (array) $wpdb->get_results( "SELECT c.id, c.user_id, YEAR(c.created_at) AS y1, (SELECT MAX(YEAR(GREATEST(b.created_at, IFNULL(i.end_date, b.created_at)))) FROM {$b} b LEFT JOIN {$i} i ON i.booking_id = b.id WHERE b.customer_id = c.id) AS y2, (SELECT COUNT(*) FROM {$b} b WHERE b.customer_id = c.id AND b.status IN (" . self::ACTIVE . ")) AS active FROM {$c} c", ARRAY_A ); // phpcs:ignore
				$ids  = array();
				foreach ( $rows as $r ) {
					$last = max( (int) $r['y1'], (int) $r['y2'] );
					if ( $r['user_id'] ) {
						$u    = get_userdata( (int) $r['user_id'] );
						$last = $u ? max( $last, (int) substr( $u->user_registered, 0, 4 ) ) : $last;
					}
					if ( ! (int) $r['active'] && $last && $year > $last + 3 ) {
						$ids[] = (int) $r['id'];
					}
				}
				return $ids;

			case 'booking_nomoney':
				return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT b.id FROM {$b} b WHERE b.status IN ('returned','cancelled') AND {$nomoney} AND YEAR(GREATEST(b.updated_at, IFNULL((SELECT MAX(i.end_date) FROM {$i} i WHERE i.booking_id = b.id), b.created_at))) + 3 < %d", $year ) ) ); // phpcs:ignore

			case 'retention':
				$ids = array();
				foreach ( (array) $wpdb->get_col( "SELECT b.id FROM {$b} b WHERE b.status NOT IN (" . self::ACTIVE . ") AND NOT ({$nomoney})" ) as $id ) { // phpcs:ignore
					$until = self::retained_until( $id );
					if ( $until && $until < current_time( 'Y-m-d' ) ) {
						$ids[] = (int) $id;
					}
				}
				return $ids;
		}
		return array();
	}

	/**
	 * Wendet eine Regel auf die gefundenen IDs an.
	 */
	private static function apply( $rule, array $ids ) {
		global $wpdb;
		foreach ( $ids as $id ) {
			switch ( $rule ) {
				case 'abort':
				case 'booking_nomoney':
				case 'retention':
					self::purge_booking( $id );
					break;
				case 'unverified':
					if ( self::delete_user( $id ) ) {
						$wpdb->update( self::t( 'customers' ), array( 'user_id' => 0 ), array( 'user_id' => (int) $id ) );
					}
					break;
				case 'customer':
					self::erase_customer( $id, false );
					break;
			}
		}
	}

	/**
	 * Täglicher Lauf. Im Probelauf wird nichts gelöscht, nur gezählt.
	 *
	 * @param bool|null $live null = Einstellung verwenden.
	 * @return array Regel => Anzahl
	 */
	public static function run( $live = null ) {
		global $wpdb;
		$live   = null === $live ? self::live() : (bool) $live;
		$counts = array();
		foreach ( array_keys( self::rules() ) as $rule ) {
			$ids             = self::find( $rule );
			$counts[ $rule ] = count( $ids );
			if ( $live && $ids ) {
				self::apply( $rule, $ids );
			}
			if ( $ids ) {
				$wpdb->insert(
					self::t( 'deletion_log' ),
					array(
						'run_at' => current_time( 'mysql' ),
						'rule'   => $rule,
						'count'  => count( $ids ),
						'dry'    => $live ? 0 : 1,
					)
				);
			}
		}
		update_option(
			'msb_retention_last',
			array(
				'time'   => current_time( 'mysql' ),
				'live'   => $live,
				'counts' => $counts,
			),
			false
		);
		return $counts;
	}

	public static function cron() {
		self::run();
	}

	/* ------------------------------------------------------------------
	 * Art. 17 DSGVO: Kunde löschen, Aufbewahrungspflichtiges sperren
	 * ------------------------------------------------------------------ */

	/**
	 * Was beim Löschen eines Kunden passiert.
	 *
	 * @return array active, delete, block (Anzahl Buchungen), until (spätestes Fristende)
	 */
	public static function preview_customer( $customer_id ) {
		$out = array(
			'active' => 0,
			'delete' => 0,
			'block'  => 0,
			'until'  => '',
		);
		foreach ( MSB_Customers::bookings( $customer_id ) as $b ) {
			$until = self::retained_until( $b['id'] );
			if ( in_array( $b['status'], array( 'awaiting_payment', 'pending', 'confirmed', 'picked_up' ), true ) ) {
				$out['active']++;
			} elseif ( $until ) {
				$out['block']++;
				$out['until'] = max( $out['until'], $until );
			} else {
				$out['delete']++;
			}
		}
		return $out;
	}

	/**
	 * Löscht Kunde, reines Kundenkonto und Buchungen ohne Aufbewahrungspflicht. Buchungen mit Zahlung oder Beleg
	 * verlieren den Kundenbezug und werden bis Fristende gesperrt (DSG-03, DSG-06, KTO-08).
	 *
	 * @return array|WP_Error Vorschau-Zahlen
	 */
	public static function erase_customer( $customer_id, $log = true ) {
		global $wpdb;
		$c = MSB_Customers::get( $customer_id );
		if ( ! $c ) {
			return new WP_Error( 'msb_retention', 'Kunde nicht gefunden.' );
		}
		$pre = self::preview_customer( $customer_id );
		if ( $pre['active'] ) {
			return new WP_Error( 'msb_retention', 'Der Kunde hat noch laufende Buchungen. Bitte erst abschließen oder stornieren.' );
		}
		foreach ( MSB_Customers::bookings( $customer_id ) as $b ) {
			$until = self::retained_until( $b['id'] );
			if ( $until ) {
				$wpdb->update(
					self::t( 'bookings' ),
					array(
						'customer_id'   => 0,
						'blocked_until' => $until,
					),
					array( 'id' => (int) $b['id'] )
				);
			} else {
				self::purge_booking( $b['id'] );
			}
		}
		if ( $c['user_id'] ) {
			self::delete_user( (int) $c['user_id'] );
		}
		$wpdb->delete( self::t( 'customers' ), array( 'id' => (int) $customer_id ), array( '%d' ) );
		if ( $log ) {
			$wpdb->insert(
				self::t( 'deletion_log' ),
				array(
					'run_at' => current_time( 'mysql' ),
					'rule'   => 'art17',
					'count'  => 1,
					'dry'    => 0,
				)
			);
		}
		return $pre;
	}

	/* ------------------------------------------------------------------
	 * Backend
	 * ------------------------------------------------------------------ */

	public static function handle_admin() {
		// phpcs:disable WordPress.Security.NonceVerification
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';

		// Kunden löschen (einzeln und Sammelaktion) läuft über Art. 17 statt einfachem Löschen.
		$single = isset( $_POST['msb_customer_action'] ) && 'delete' === $_POST['msb_customer_action'];
		$bulk   = 'msb-customers' === $page && ! empty( $_REQUEST['ids'] ) && ( ( isset( $_REQUEST['action'] ) && 'delete' === $_REQUEST['action'] ) || ( isset( $_REQUEST['action2'] ) && 'delete' === $_REQUEST['action2'] ) );
		if ( $single || $bulk ) {
			$ids = $single ? array( isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0 ) : array_map( 'absint', (array) $_REQUEST['ids'] );
			check_admin_referer( $single ? 'msb_customer_' . $ids[0] : 'bulk-kunden' );
			if ( ! current_user_can( msb_capability( 'delete' ) ) ) {
				wp_die( 'Keine Berechtigung.' );
			}
			$done  = 0;
			$skip  = 0;
			$block = 0;
			foreach ( $ids as $id ) {
				$r = self::erase_customer( $id );
				if ( is_wp_error( $r ) ) {
					$skip++;
				} else {
					$done++;
					$block += $r['block'];
				}
			}
			if ( $single && $skip ) {
				wp_safe_redirect( admin_url( 'admin.php?page=msb-customer&id=' . $ids[0] . '&msb_erase=active' ) );
				exit;
			}
			wp_safe_redirect( admin_url( 'admin.php?page=msb-customers&msb_erased=' . $done . '&msb_blocked=' . $block . '&msb_skipped=' . $skip ) );
			exit;
		}

		if ( empty( $_POST['msb_retention_action'] ) ) {
			return;
		}
		// phpcs:enable
		check_admin_referer( 'msb_retention' );
		if ( ! current_user_can( msb_capability( 'settings' ) ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		$url = admin_url( 'admin.php?page=msb-settings&tab=retention' );
		if ( 'save' === $_POST['msb_retention_action'] ) {
			$s                   = get_option( 'msb_settings', array() );
			$s                   = is_array( $s ) ? $s : array();
			$s['retention_live'] = empty( $_POST['retention_live'] ) ? 0 : 1;
			update_option( 'msb_settings', $s );
			wp_safe_redirect( add_query_arg( 'saved', 1, $url ) );
			exit;
		}
		if ( 'check' === $_POST['msb_retention_action'] ) {
			self::run( false );
			wp_safe_redirect( add_query_arg( 'checked', 1, $url ) );
			exit;
		}
	}

	public static function notices() {
		// phpcs:disable WordPress.Security.NonceVerification
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		if ( 'msb-customers' === $page && isset( $_GET['msb_erased'] ) ) {
			$done  = absint( $_GET['msb_erased'] );
			$block = isset( $_GET['msb_blocked'] ) ? absint( $_GET['msb_blocked'] ) : 0;
			$skip  = isset( $_GET['msb_skipped'] ) ? absint( $_GET['msb_skipped'] ) : 0;
			$msg   = sprintf( '%d Kunde(n) gelöscht (Art. 17 DSGVO).', $done );
			if ( $block ) {
				$msg .= sprintf( ' %d Buchung(en) mit Zahlung oder Beleg bleiben wegen der Aufbewahrungspflicht ohne Kundenbezug gesperrt.', $block );
			}
			if ( $skip ) {
				$msg .= sprintf( ' %d Kunde(n) übersprungen, weil noch Buchungen laufen.', $skip );
			}
			echo '<div class="notice notice-' . ( $skip ? 'warning' : 'success' ) . ' is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}
		if ( 'msb-customer' === $page && isset( $_GET['msb_erase'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>Der Kunde hat noch laufende Buchungen. Bitte diese erst abschließen oder stornieren, dann die Daten löschen.</p></div>';
		}
		if ( 'msb-booking' === $page && isset( $_GET['id'] ) ) {
			$b = MSB_Bookings::get( absint( $_GET['id'] ) );
			if ( $b && ! empty( $b['blocked_until'] ) ) {
				echo '<div class="notice notice-info"><p>Diese Buchung ist gesperrt (Art. 18 DSGVO): Die Kundendaten wurden gelöscht, auf Antrag oder nach Ablauf der Frist. Die Buchung wird nur noch wegen der Aufbewahrungspflicht bis ' . esc_html( msb_date( $b['blocked_until'] ) ) . ' gespeichert und danach automatisch gelöscht. Bitte nicht für andere Zwecke verwenden.</p></div>';
			}
		}
		// phpcs:enable
	}

	/**
	 * Reiter „Löschfristen“ unter Vermietung → Einstellungen.
	 */
	public static function settings_page( array $tabs, array $help ) {
		global $wpdb;
		$live  = self::live();
		$last  = get_option( 'msb_retention_last' );
		$since = get_option( 'msb_retention_since' );
		$log   = (array) $wpdb->get_results( 'SELECT * FROM ' . self::t( 'deletion_log' ) . ' ORDER BY id DESC LIMIT 50', ARRAY_A ); // phpcs:ignore
		$rules = self::rules();
		$names = wp_list_pluck( $rules, 0 );
		$names['art17'] = 'Löschung auf Antrag (Art. 17)';
		$next  = wp_next_scheduled( self::CRON );
		?>
		<div class="wrap msb-admin">
			<h1>Einstellungen <?php echo msb_help_link( isset( $help['retention'] ) ? $help['retention'] : 'kunden', 'Erklärung zu diesem Bereich' ); // phpcs:ignore ?></h1>
			<?php
			if ( isset( $_GET['saved'] ) ) { // phpcs:ignore
				echo '<div class="notice notice-success is-dismissible"><p>Einstellungen gespeichert.</p></div>';
			}
			if ( isset( $_GET['checked'] ) ) { // phpcs:ignore
				echo '<div class="notice notice-success is-dismissible"><p>Prüfung abgeschlossen. Es wurde nichts gelöscht, die Zahlen stehen unten.</p></div>';
			}
			?>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $k => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=msb-settings&tab=' . $k ) ); ?>" class="nav-tab <?php echo 'retention' === $k ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="">
				<?php wp_nonce_field( 'msb_retention' ); ?>
				<input type="hidden" name="msb_retention_action" value="save">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Modus</th>
						<td>
							<label><input type="radio" name="retention_live" value="0" <?php checked( ! $live ); ?>> <strong>Probelauf</strong>: nur zählen und protokollieren, nichts löschen</label><br>
							<label><input type="radio" name="retention_live" value="1" <?php checked( $live ); ?>> <strong>Löschung ausführen</strong>: abgelaufene Daten werden täglich endgültig gelöscht</label>
							<p class="description">
								<?php if ( $since ) : ?>Probelauf seit <?php echo esc_html( mysql2date( 'd.m.Y', $since ) ); ?>. <?php endif; ?>
								Empfehlung: das Protokoll etwa 30 Tage beobachten und erst dann umschalten. Gelöschte Daten lassen sich nur über ein netcup-Backup zurückholen.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Täglicher Lauf</th>
						<td>
							<?php if ( $last ) : ?>
								Zuletzt am <?php echo esc_html( mysql2date( 'd.m.Y H:i', $last['time'] ) ); ?> (<?php echo $last['live'] ? 'Löschung' : 'Probelauf'; ?>).
							<?php else : ?>
								Noch nicht gelaufen.
							<?php endif; ?>
							<?php if ( $next ) : ?>
								Nächster Lauf etwa <?php echo esc_html( wp_date( 'd.m.Y H:i', $next ) ); ?>.
							<?php endif; ?>
							<p class="description">WordPress startet geplante Aufgaben beim nächsten Seitenaufruf. Für feste Zeiten kann bei netcup ein Cronjob auf wp-cron.php eingerichtet werden.</p>
						</td>
					</tr>
				</table>
				<p><button type="submit" class="button button-primary">Speichern</button></p>
			</form>

			<h2>Regeln</h2>
			<table class="widefat striped msb-cards" data-aside="4">
				<thead><tr><th>Regel</th><th>Frist</th><th>Was passiert</th><th>Letzter Lauf</th></tr></thead>
				<tbody>
				<?php foreach ( $rules as $k => $r ) : ?>
					<tr>
						<td><?php echo esc_html( $r[0] ); ?></td>
						<td><?php echo esc_html( $r[1] ); ?></td>
						<td><?php echo esc_html( $r[2] ); ?></td>
						<td><?php echo $last && isset( $last['counts'][ $k ] ) ? (int) $last['counts'][ $k ] . ( $last['live'] ? ' gelöscht' : ' fällig' ) : '–'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">Laufende Buchungen (wartet auf Zahlung, offen, bestätigt, abgeholt) werden nie automatisch gelöscht. Buchungen mit Zahlung oder Beleg lassen sich vor Fristende auch von Hand nicht löschen.</p>
			<form method="post" action="">
				<?php wp_nonce_field( 'msb_retention' ); ?>
				<input type="hidden" name="msb_retention_action" value="check">
				<p><button type="submit" class="button">Jetzt prüfen (ohne Löschen)</button></p>
			</form>

			<h2>Protokoll</h2>
			<?php if ( $log ) : ?>
				<table class="widefat striped msb-cards" data-aside="3">
					<thead><tr><th>Datum</th><th>Regel</th><th>Anzahl</th><th>Modus</th></tr></thead>
					<tbody>
					<?php foreach ( $log as $l ) : ?>
						<tr>
							<td><?php echo esc_html( mysql2date( 'd.m.Y H:i', $l['run_at'] ) ); ?></td>
							<td><?php echo esc_html( isset( $names[ $l['rule'] ] ) ? $names[ $l['rule'] ] : $l['rule'] ); ?></td>
							<td><?php echo (int) $l['count']; ?></td>
							<td><?php echo $l['dry'] ? 'Probelauf' : 'gelöscht'; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description">Das Protokoll enthält keine personenbezogenen Daten, nur Datum, Regel und Anzahl. Es zeigt die letzten 50 Einträge.</p>
			<?php else : ?>
				<p>Noch keine Einträge. Ein Eintrag entsteht, sobald eine Regel etwas findet.</p>
			<?php endif; ?>
		</div>
		<?php
	}
}
