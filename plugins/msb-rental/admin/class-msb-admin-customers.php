<?php
/**
 * Backend: Kundenverwaltung.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class MSB_Customers_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'kunde',
				'plural'   => 'kunden',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'cb'       => '<input type="checkbox">',
			'name'     => 'Name',
			'email'    => 'Kontakt',
			'city'     => 'Ort',
			'account'  => 'Konto',
			'bookings' => 'Buchungen',
			'last'     => 'Letzte Buchung',
		);
	}

	protected function get_sortable_columns() {
		return array(
			'name'     => array( 'name', false ),
			'email'    => array( 'email', false ),
			'city'     => array( 'city', false ),
			'bookings' => array( 'bookings', true ),
			'last'     => array( 'last', true ),
		);
	}

	protected function get_bulk_actions() {
		return current_user_can( msb_capability( 'delete' ) ) ? array( 'delete' => 'Löschen' ) : array();
	}

	protected function get_views() {
		$cur  = isset( $_GET['account'] ) ? sanitize_key( $_GET['account'] ) : ''; // phpcs:ignore
		$base = admin_url( 'admin.php?page=msb-customers' );
		return array(
			'all' => '<a href="' . esc_url( $base ) . '"' . ( '' === $cur ? ' class="current"' : '' ) . '>Alle</a>',
			'yes' => '<a href="' . esc_url( add_query_arg( 'account', 'yes', $base ) ) . '"' . ( 'yes' === $cur ? ' class="current"' : '' ) . '>Mit Kundenkonto</a>',
			'no'  => '<a href="' . esc_url( add_query_arg( 'account', 'no', $base ) ) . '"' . ( 'no' === $cur ? ' class="current"' : '' ) . '>Gäste</a>',
		);
	}

	protected function extra_tablenav( $which ) {
		if ( 'top' === $which ) {
			echo '<div class="alignleft actions"><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=msb-customers&msb_export=1' ), 'msb_customers_export' ) ) . '">CSV exportieren</a></div>';
		}
	}

	public function prepare_items() {
		// phpcs:disable WordPress.Security.NonceVerification
		$per_page = 25;
		list( $rows, $total ) = MSB_Customers::query(
			array(
				'search'   => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
				'account'  => isset( $_GET['account'] ) ? sanitize_key( $_GET['account'] ) : '',
				'orderby'  => isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'name',
				'order'    => isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'asc',
				'per_page' => $per_page,
				'page'     => $this->get_pagenum(),
			)
		);
		// phpcs:enable
		$this->items           = $rows;
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
			)
		);
	}

	protected function column_cb( $row ) {
		return '<input type="checkbox" name="ids[]" value="' . (int) $row['id'] . '">';
	}

	protected function column_name( $row ) {
		$url  = admin_url( 'admin.php?page=msb-customer&id=' . (int) $row['id'] );
		$name = $row['name'] ? $row['name'] : '(ohne Namen)';
		return '<strong><a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a></strong>' . $this->row_actions(
			array(
				'edit' => '<a href="' . esc_url( $url ) . '">Bearbeiten</a>',
				'book' => '<a href="' . esc_url( admin_url( 'admin.php?page=msb-booking&customer=' . (int) $row['id'] ) ) . '">Neue Buchung</a>',
			)
		);
	}

	protected function column_email( $row ) {
		$out = $row['email'] ? '<a href="mailto:' . esc_attr( $row['email'] ) . '">' . esc_html( $row['email'] ) . '</a>' : '';
		if ( $row['phone'] ) {
			$out .= '<br>' . esc_html( $row['phone'] );
		}
		return $out;
	}

	protected function column_city( $row ) {
		return esc_html( $row['city'] );
	}

	protected function column_account( $row ) {
		if ( ! $row['user_id'] ) {
			return '<span class="msb-muted">Gast</span>';
		}
		$verified = MSB_Accounts::is_verified( $row['user_id'] );
		return '<span class="dashicons dashicons-admin-users"></span> ' . ( $verified ? 'bestätigt' : 'unbestätigt' );
	}

	protected function column_bookings( $row ) {
		return (int) $row['bookings'] ? '<a href="' . esc_url( admin_url( 'admin.php?page=msb-customer&id=' . (int) $row['id'] . '#bookings' ) ) . '">' . (int) $row['bookings'] . '</a>' : '0';
	}

	protected function column_last( $row ) {
		return $row['last_booking'] ? esc_html( mysql2date( 'd.m.Y', $row['last_booking'] ) ) : '–';
	}

	public function no_items() {
		echo 'Noch keine Kunden. Kunden werden bei jeder Buchung automatisch angelegt.';
	}
}

class MSB_Admin_Customers {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'handle' ) );
	}

	public static function handle() {
		if ( ! current_user_can( msb_capability() ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';

		if ( 'msb-customers' === $page && isset( $_GET['msb_export'] ) ) {
			check_admin_referer( 'msb_customers_export' );
			self::export();
		}

		// Kunden löschen (DSGVO-Löschanfragen) nur mit eigenem Recht (ROL-01).
		if ( 'msb-customers' === $page && ! empty( $_REQUEST['ids'] ) && current_user_can( msb_capability( 'delete' ) ) && ( ( isset( $_REQUEST['action'] ) && 'delete' === $_REQUEST['action'] ) || ( isset( $_REQUEST['action2'] ) && 'delete' === $_REQUEST['action2'] ) ) ) {
			check_admin_referer( 'bulk-kunden' );
			foreach ( array_map( 'absint', (array) $_REQUEST['ids'] ) as $id ) {
				MSB_Customers::delete( $id );
			}
			wp_safe_redirect( admin_url( 'admin.php?page=msb-customers&deleted=1' ) );
			exit;
		}

		if ( empty( $_POST['msb_customer_action'] ) ) {
			return;
		}
		// phpcs:enable
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		check_admin_referer( 'msb_customer_' . $id );
		$action = sanitize_key( wp_unslash( $_POST['msb_customer_action'] ) );

		if ( 'delete' === $action && current_user_can( msb_capability( 'delete' ) ) ) {
			MSB_Customers::delete( $id );
			wp_safe_redirect( admin_url( 'admin.php?page=msb-customers&deleted=1' ) );
			exit;
		}

		if ( 'save' === $action ) {
			$input             = isset( $_POST['msb_field'] ) && is_array( $_POST['msb_field'] ) ? wp_unslash( $_POST['msb_field'] ) : array(); // phpcs:ignore
			list( $values )    = MSB_Form_Fields::validate( $input, MSB_Form_Fields::profile_fields() );
			$notes             = isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : ''; // phpcs:ignore
			$existing          = $id ? MSB_Customers::get( $id ) : null;
			$new_id            = MSB_Customers::save( $values, $id, $existing ? (int) $existing['user_id'] : 0, $notes );

			// Konto-E-Mail und Name mitziehen. Konten mit Backend-Rechten nur, wenn man diese Nutzer bearbeiten darf.
			if ( $existing && $existing['user_id'] && ( current_user_can( 'edit_user', (int) $existing['user_id'] ) || ! user_can( (int) $existing['user_id'], 'edit_posts' ) && ! user_can( (int) $existing['user_id'], msb_capability() ) ) ) {
				$c = MSB_Customers::get( $new_id );
				$u = array(
					'ID'         => (int) $existing['user_id'],
					'first_name' => $c['first_name'],
					'last_name'  => $c['last_name'],
				);
				if ( $c['email'] && ! email_exists( $c['email'] ) ) {
					$u['user_email'] = $c['email'];
				}
				wp_update_user( $u );
			}
			wp_safe_redirect( admin_url( 'admin.php?page=msb-customer&id=' . $new_id . '&saved=1' ) );
			exit;
		}

		if ( 'invite' === $action && $id ) {
			$c = MSB_Customers::get( $id );
			if ( $c && ! $c['user_id'] && is_email( $c['email'] ) ) {
				if ( email_exists( $c['email'] ) ) {
					$uid = email_exists( $c['email'] );
				} else {
					$uid = wp_insert_user(
						array(
							'user_login' => $c['email'],
							'user_email' => $c['email'],
							'user_pass'  => wp_generate_password( 24 ),
							'first_name' => $c['first_name'],
							'last_name'  => $c['last_name'],
							'role'       => MSB_Accounts::ROLE,
						)
					);
					if ( ! is_wp_error( $uid ) ) {
						update_user_meta( $uid, 'msb_verified', 1 ); // Admin hat die Adresse aus einer echten Buchung.
						wp_new_user_notification( $uid, null, 'user' ); // E-Mail mit Link zum Passwort setzen.
					}
				}
				if ( ! is_wp_error( $uid ) && $uid ) {
					global $wpdb;
					$wpdb->update( MSB_Install::table( 'customers' ), array( 'user_id' => (int) $uid ), array( 'id' => $id ) );
					MSB_Customers::merge_guests_into( $id );
				}
			}
			wp_safe_redirect( admin_url( 'admin.php?page=msb-customer&id=' . $id . '&invited=1' ) );
			exit;
		}
	}

	private static function export() {
		list( $rows ) = MSB_Customers::query( array( 'per_page' => 0 ) );
		$fields       = MSB_Form_Fields::profile_fields();
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename=kunden-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		$head = array( 'Kunden-Nr.', 'Vorname', 'Nachname', 'E-Mail', 'Telefon' );
		foreach ( $fields as $f ) {
			if ( ! in_array( $f['key'], array( 'first_name', 'last_name', 'email', 'phone' ), true ) ) {
				$head[] = wp_strip_all_tags( $f['label'] );
			}
		}
		$head = array_merge( $head, array( 'Kundenkonto', 'Buchungen', 'Notiz', 'Angelegt' ) );
		fputcsv( $out, $head, ';' );
		foreach ( $rows as $r ) {
			$line = array( $r['id'], $r['first_name'], $r['last_name'], $r['email'], $r['phone'] );
			foreach ( $fields as $f ) {
				if ( ! in_array( $f['key'], array( 'first_name', 'last_name', 'email', 'phone' ), true ) ) {
					$line[] = isset( $r['data'][ $f['key'] ] ) ? $r['data'][ $f['key'] ] : '';
				}
			}
			$line = array_merge( $line, array( $r['user_id'] ? 'ja' : 'nein', $r['bookings'], $r['notes'], mysql2date( 'd.m.Y', $r['created_at'] ) ) );
			$line = array_map(
				function ( $v ) {
					$v = (string) $v;
					return preg_match( '/^[=+\-@\t\r]/', $v ) ? "'" . $v : $v;
				},
				$line
			);
			fputcsv( $out, $line, ';' );
		}
		fclose( $out );
		exit;
	}

	public static function page_list() {
		$table = new MSB_Customers_List_Table();
		$table->prepare_items();
		?>
		<div class="wrap msb-admin">
			<h1 class="wp-heading-inline">Kunden</h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=msb-customer' ) ); ?>" class="page-title-action">Kunde anlegen</a>
			<?php echo msb_help_link( 'kunden' ); // phpcs:ignore ?>
			<hr class="wp-header-end">
			<?php
			if ( isset( $_GET['deleted'] ) ) { // phpcs:ignore
				echo '<div class="notice notice-success is-dismissible"><p>Kunde(n) gelöscht. Die Buchungen bleiben erhalten.</p></div>';
			}
			$table->views();
			?>
			<form method="get">
				<input type="hidden" name="page" value="msb-customers">
				<?php
				$table->search_box( 'Kunden suchen', 'msb-cust-search' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	public static function page_edit() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore
		$c  = $id ? MSB_Customers::get( $id ) : null;
		if ( $id && ! $c ) {
			echo '<div class="wrap"><h1>Kunde nicht gefunden</h1></div>';
			return;
		}
		$values = $c ? $c['data'] : array();
		if ( $c ) {
			foreach ( MSB_Form_Fields::get() as $f ) {
				if ( 'email' === $f['type'] && empty( $values[ $f['key'] ] ) ) {
					$values[ $f['key'] ] = $c['email'];
				}
			}
		}
		$user = $c && $c['user_id'] ? get_userdata( $c['user_id'] ) : null;
		?>
		<div class="wrap msb-admin">
			<h1 class="wp-heading-inline"><?php echo $c ? esc_html( $c['name'] ? $c['name'] : $c['email'] ) : 'Kunde anlegen'; ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=msb-customers' ) ); ?>" class="page-title-action">← Alle Kunden</a>
			<?php if ( $c ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=msb-booking&customer=' . $id ) ); ?>" class="page-title-action">Neue Buchung für diesen Kunden</a>
			<?php endif; ?>
			<hr class="wp-header-end">
			<?php
			if ( isset( $_GET['saved'] ) ) { // phpcs:ignore
				echo '<div class="notice notice-success is-dismissible"><p>Kunde gespeichert.</p></div>';
			}
			if ( isset( $_GET['invited'] ) ) { // phpcs:ignore
				echo '<div class="notice notice-success is-dismissible"><p>Kundenkonto angelegt. Der Kunde hat eine E-Mail zum Festlegen seines Passworts erhalten.</p></div>';
			}
			?>
			<div class="msb-detail">
				<div class="msb-detail__main">
					<form method="post" class="msb-card-box">
						<?php wp_nonce_field( 'msb_customer_' . $id ); ?>
						<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
						<input type="hidden" name="msb_customer_action" value="save">
						<h2>Stammdaten</h2>
						<p class="description">Die Felder kommen aus dem Buchungsformular (Felder mit „Kundenstammdaten“). <?php echo msb_help_link( 'formular', '' ); // phpcs:ignore ?></p>
						<div class="msb-admin-form msb-form">
							<?php
							foreach ( MSB_Form_Fields::profile_fields() as $f ) {
								$v = isset( $values[ $f['key'] ] ) ? $values[ $f['key'] ] : '';
								$n = 'msb_field[' . $f['key'] . ']';
								echo '<p class="' . ( 'half' === $f['width'] ? 'half' : 'full' ) . '"><label>' . esc_html( wp_strip_all_tags( $f['label'] ) ) . '<br>';
								if ( 'textarea' === $f['type'] ) {
									echo '<textarea name="' . esc_attr( $n ) . '" rows="3" class="widefat">' . esc_textarea( $v ) . '</textarea>';
								} elseif ( in_array( $f['type'], array( 'select', 'radio' ), true ) ) {
									echo '<select name="' . esc_attr( $n ) . '" class="widefat"><option value=""></option>';
									foreach ( msb_lines( $f['options'] ) as $o ) {
										echo '<option value="' . esc_attr( $o ) . '"' . selected( $v, $o, false ) . '>' . esc_html( $o ) . '</option>';
									}
									echo '</select>';
								} else {
									$type = in_array( $f['type'], array( 'email', 'tel', 'number', 'date' ), true ) ? $f['type'] : 'text';
									echo '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $n ) . '" value="' . esc_attr( $v ) . '" class="widefat">';
								}
								echo '</label></p>';
							}
							?>
							<p class="full"><label>Interne Notiz<br><textarea name="notes" rows="4" class="widefat" placeholder="Nur für Sie sichtbar, z. B. Stammkunde, Ausweis geprüft …"><?php echo esc_textarea( $c ? $c['notes'] : '' ); ?></textarea></label></p>
						</div>
						<p><button type="submit" class="button button-primary">Speichern</button></p>
					</form>

					<?php if ( $c ) : ?>
					<div class="msb-card-box" id="bookings">
						<h2>Buchungen</h2>
						<?php $bookings = MSB_Customers::bookings( $id ); ?>
						<?php if ( $bookings ) : ?>
							<table class="widefat striped">
								<thead><tr><th>Buchung</th><th>Artikel</th><th>Zeitraum</th><th>Mietpreis</th><th>Status</th></tr></thead>
								<tbody>
								<?php foreach ( $bookings as $b ) : $items = MSB_Bookings::get_items( $b['id'] ); ?>
									<tr>
										<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=msb-booking&id=' . (int) $b['id'] ) ); ?>"><?php echo esc_html( $b['booking_number'] ); ?></a><br><small><?php echo esc_html( mysql2date( 'd.m.Y', $b['created_at'] ) ); ?></small></td>
										<td><?php echo implode( '<br>', array_map( function ( $l ) { return (int) $l['quantity'] . '× ' . esc_html( $l['item_title'] ); }, $items ) ); // phpcs:ignore ?></td>
										<td><?php echo implode( '<br>', array_map( function ( $l ) { return esc_html( msb_date( $l['start_date'] ) . ' – ' . msb_date( $l['end_date'] ) ); }, $items ) ); // phpcs:ignore ?></td>
										<td><?php echo esc_html( msb_price( $b['total'] ) ); ?></td>
										<td><span class="msb-status msb-status--<?php echo esc_attr( $b['status'] ); ?>"><?php echo esc_html( msb_status_label( $b['status'] ) ); ?></span></td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						<?php else : ?>
							<p>Noch keine Buchungen.</p>
						<?php endif; ?>
					</div>
					<?php endif; ?>
				</div>

				<?php if ( $c ) : ?>
				<div class="msb-detail__side">
					<div class="msb-card-box">
						<h2>Kundenkonto</h2>
						<?php if ( $user ) : ?>
							<p><span class="dashicons dashicons-yes-alt" style="color:#2f6b3a"></span> Konto vorhanden: <a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>"><?php echo esc_html( $user->user_login ); ?></a><br>
							E-Mail <?php echo MSB_Accounts::is_verified( $user->ID ) ? 'bestätigt' : '<strong>noch nicht bestätigt</strong>'; ?></p>
						<?php else : ?>
							<p>Gastkunde ohne Konto.</p>
							<form method="post">
								<?php wp_nonce_field( 'msb_customer_' . $id ); ?>
								<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
								<input type="hidden" name="msb_customer_action" value="invite">
								<button type="submit" class="button" <?php disabled( ! is_email( $c['email'] ) ); ?>>Kundenkonto anlegen &amp; Einladung senden</button>
							</form>
						<?php endif; ?>
						<p class="description">Kunde seit <?php echo esc_html( mysql2date( 'd.m.Y', $c['created_at'] ) ); ?> · Kunden-Nr. <?php echo (int) $c['id']; ?></p>
					</div>
					<?php if ( current_user_can( msb_capability( 'delete' ) ) ) : $pre = MSB_Retention::preview_customer( $id ); ?>
					<form method="post" class="msb-card-box" onsubmit="return confirm('Alle Daten dieses Kunden endgültig löschen? Das kann nicht rückgängig gemacht werden.');">
						<?php wp_nonce_field( 'msb_customer_' . $id ); ?>
						<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
						<input type="hidden" name="msb_customer_action" value="delete">
						<h2>Daten löschen (Art. 17 DSGVO)</h2>
						<?php if ( $pre['active'] ) : ?>
							<p>Noch nicht möglich: <?php echo (int) $pre['active']; ?> Buchung(en) laufen noch. Bitte erst abschließen oder stornieren.</p>
						<?php else : ?>
							<p class="description">Für Löschanfragen des Kunden. Gelöscht werden der Kunde<?php echo $user && array( MSB_Accounts::ROLE ) === array_values( (array) $user->roles ) ? ', sein Kundenkonto' : ''; ?> und <?php echo (int) $pre['delete']; ?> Buchung(en) ohne Zahlung oder Beleg.<?php if ( $pre['block'] ) : ?> <?php echo (int) $pre['block']; ?> Buchung(en) mit Zahlung oder Beleg müssen bis <?php echo esc_html( msb_date( $pre['until'] ) ); ?> aufbewahrt werden; sie verlieren den Kundenbezug und werden gesperrt.<?php endif; ?></p>
							<p><button type="submit" class="button-link msb-delete">Daten jetzt löschen</button></p>
						<?php endif; ?>
					</form>
					<?php endif; ?>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
