<?php
/**
 * Backend: Buchungsübersicht, Buchungsdetails, manuelle Buchung, Kalender, Inventar, CSV-Export.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class MSB_Bookings_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'buchung',
				'plural'   => 'buchungen',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'cb'             => '<input type="checkbox">',
			'booking_number' => 'Buchung',
			'customer_name'  => 'Kunde',
			'items'          => 'Artikel',
			'start'          => 'Zeitraum',
			'total'          => 'Mietpreis',
			'payment'        => 'Zahlung / Kaution',
			'status'         => 'Status',
			'created_at'     => 'Eingegangen',
		);
	}

	protected function get_sortable_columns() {
		return array(
			'booking_number' => array( 'booking_number', false ),
			'customer_name'  => array( 'customer_name', false ),
			'start'          => array( 'start', false ),
			'total'          => array( 'total', false ),
			'status'         => array( 'status', false ),
			'created_at'     => array( 'created_at', true ),
		);
	}

	protected function get_bulk_actions() {
		$actions = array();
		foreach ( msb_statuses() as $k => $label ) {
			$actions[ 'status_' . $k ] = 'Status: ' . $label;
		}
		if ( current_user_can( msb_capability( 'delete' ) ) ) {
			$actions['delete'] = 'Endgültig löschen';
		}
		return $actions;
	}

	protected function get_views() {
		$counts  = MSB_Bookings::count_by_status();
		$current = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore
		$base    = admin_url( 'admin.php?page=msb-bookings' );
		$views   = array(
			'all' => sprintf( '<a href="%s"%s>Alle <span class="count">(%d)</span></a>', esc_url( $base ), '' === $current ? ' class="current"' : '', array_sum( $counts ) ),
		);
		foreach ( msb_statuses() as $k => $label ) {
			if ( empty( $counts[ $k ] ) ) {
				continue;
			}
			$views[ $k ] = sprintf( '<a href="%s"%s>%s <span class="count">(%d)</span></a>', esc_url( add_query_arg( 'status', $k, $base ) ), $current === $k ? ' class="current"' : '', esc_html( $label ), $counts[ $k ] );
		}
		return $views;
	}

	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$item  = isset( $_GET['item_id'] ) ? absint( $_GET['item_id'] ) : 0; // phpcs:ignore
		$from  = isset( $_GET['from'] ) ? msb_normalize_date( sanitize_text_field( wp_unslash( $_GET['from'] ) ) ) : ''; // phpcs:ignore
		$to    = isset( $_GET['to'] ) ? msb_normalize_date( sanitize_text_field( wp_unslash( $_GET['to'] ) ) ) : ''; // phpcs:ignore
		$src   = isset( $_GET['source'] ) ? sanitize_key( $_GET['source'] ) : ''; // phpcs:ignore
		$test  = isset( $_GET['test'] ) ? sanitize_key( $_GET['test'] ) : ''; // phpcs:ignore
		$items = get_posts(
			array(
				'post_type'      => MSB_Items::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		echo '<div class="alignleft actions">';
		echo '<select name="item_id"><option value="">Alle Artikel</option>';
		foreach ( $items as $p ) {
			echo '<option value="' . (int) $p->ID . '"' . selected( $item, $p->ID, false ) . '>' . esc_html( get_the_title( $p ) ) . '</option>';
		}
		echo '</select>';
		echo ' <select name="source"><option value="">Alle Quellen</option><option value="web"' . selected( $src, 'web', false ) . '>Webseite</option><option value="admin"' . selected( $src, 'admin', false ) . '>Manuell angelegt</option></select>';
		echo ' <select name="test"><option value="">Mit Testbuchungen</option><option value="hide"' . selected( $test, 'hide', false ) . '>Tests ausblenden</option><option value="only"' . selected( $test, 'only', false ) . '>Nur Tests</option></select>';
		echo ' <label>Mietzeitraum <input type="date" name="from" value="' . esc_attr( $from ) . '"></label> – <input type="date" name="to" value="' . esc_attr( $to ) . '">';
		submit_button( 'Filtern', '', 'filter_action', false );
		$export = wp_nonce_url( add_query_arg( array_merge( array( 'page' => 'msb-bookings', 'msb_export' => 1 ), array_filter( array( 'item_id' => $item, 'from' => $from, 'to' => $to, 'source' => $src, 'test' => $test ) ) ), admin_url( 'admin.php' ) ), 'msb_export' );
		if ( isset( $_GET['status'] ) ) { // phpcs:ignore
			$export = add_query_arg( 'status', sanitize_key( $_GET['status'] ), $export ); // phpcs:ignore
		}
		echo ' <a class="button" href="' . esc_url( $export ) . '">CSV exportieren</a>';
		echo '</div>';
	}

	public function prepare_items() {
		$per_page = $this->get_items_per_page( 'msb_bookings_per_page', 20 );
		$args     = self::filter_args();
		$args     = array_merge(
			$args,
			array(
				'per_page' => $per_page,
				'page'     => $this->get_pagenum(),
			)
		);
		list( $rows, $total ) = MSB_Bookings::query( $args );

		$this->items           = $rows;
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
			)
		);
	}

	public static function filter_args() {
		// phpcs:disable WordPress.Security.NonceVerification
		$from = isset( $_GET['from'] ) ? msb_normalize_date( sanitize_text_field( wp_unslash( $_GET['from'] ) ) ) : '';
		$to   = isset( $_GET['to'] ) ? msb_normalize_date( sanitize_text_field( wp_unslash( $_GET['to'] ) ) ) : '';
		if ( $from && ! $to ) {
			$to = $from;
		}
		if ( $to && ! $from ) {
			$from = $to;
		}
		return array(
			'status'  => isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '',
			'search'  => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'item_id' => isset( $_GET['item_id'] ) ? absint( $_GET['item_id'] ) : ( isset( $_GET['item'] ) ? absint( $_GET['item'] ) : 0 ),
			'source'  => isset( $_GET['source'] ) ? sanitize_key( $_GET['source'] ) : '',
			'test'    => isset( $_GET['test'] ) ? sanitize_key( $_GET['test'] ) : '',
			'from'    => $from,
			'to'      => $to,
			'orderby' => isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'created_at',
			'order'   => isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'desc',
		);
		// phpcs:enable
	}

	protected function column_cb( $row ) {
		return '<input type="checkbox" name="ids[]" value="' . (int) $row['id'] . '">';
	}

	protected function column_booking_number( $row ) {
		$url     = admin_url( 'admin.php?page=msb-booking&id=' . (int) $row['id'] );
		$out     = '<strong><a href="' . esc_url( $url ) . '">' . esc_html( $row['booking_number'] ) . '</a></strong>' . ( empty( $row['is_test'] ) ? '' : ' ' . MSB_Bookings::test_badge() );
		$actions = array(
			'view' => '<a href="' . esc_url( $url ) . '">Öffnen</a>',
		);
		if ( 'pending' === $row['status'] && msb_status_allowed( $row['status'], 'confirmed' ) ) {
			$actions['confirm'] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=msb-bookings&msb_quick=confirmed&id=' . (int) $row['id'] ), 'msb_quick_' . $row['id'] ) ) . '">Bestätigen</a>';
		}
		if ( msb_status_allowed( $row['status'], 'cancelled' ) ) {
			$actions['cancel'] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=msb-bookings&msb_quick=cancelled&id=' . (int) $row['id'] ), 'msb_quick_' . $row['id'] ) ) . '" onclick="return confirm(\'Buchung stornieren? Der Kunde erhält eine E-Mail.\')">Stornieren</a>';
		}
		return $out . $this->row_actions( $actions );
	}

	protected function column_customer_name( $row ) {
		$out = esc_html( $row['customer_name'] );
		if ( $row['customer_email'] ) {
			$out .= '<br><a href="mailto:' . esc_attr( $row['customer_email'] ) . '">' . esc_html( $row['customer_email'] ) . '</a>';
		}
		if ( $row['customer_phone'] ) {
			$out .= '<br><a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $row['customer_phone'] ) ) . '">' . esc_html( $row['customer_phone'] ) . '</a>';
		}
		return $out;
	}

	protected function column_items( $row ) {
		$lines = array();
		foreach ( MSB_Bookings::get_items( $row['id'] ) as $l ) {
			$lines[] = (int) $l['quantity'] . '× ' . esc_html( $l['item_title'] );
		}
		return implode( '<br>', $lines );
	}

	protected function column_start( $row ) {
		if ( ! $row['start_min'] ) {
			return '–';
		}
		$out   = esc_html( msb_date( $row['start_min'] ) . ' – ' . msb_date( $row['end_max'] ) );
		$today = msb_today();
		if ( in_array( $row['status'], array( 'pending', 'confirmed' ), true ) && $row['start_min'] === $today ) {
			$out .= '<br><span class="msb-tag msb-tag--today">Abholung heute</span>';
		} elseif ( 'picked_up' === $row['status'] && $row['end_max'] === $today ) {
			$out .= '<br><span class="msb-tag msb-tag--today">Rückgabe heute</span>';
		} elseif ( 'picked_up' === $row['status'] && $row['end_max'] < $today ) {
			$out .= '<br><span class="msb-tag msb-tag--late">Rückgabe überfällig</span>';
		}
		return $out;
	}

	protected function column_payment( $row ) {
		$sum = MSB_Payments::summary( $row );
		$out = '';
		if ( $sum['paid'] > 0 ) {
			$out .= esc_html( msb_price( $sum['paid'] ) ) . ' bezahlt';
			if ( $sum['open'] > 0 ) {
				$out .= '<br><small>' . esc_html( msb_price( $sum['open'] ) ) . ' offen</small>';
			}
		} else {
			$out .= '<small>' . esc_html( MSB_Emails::method_label( $row['payment_method'] ) ) . '</small>';
		}
		if ( (float) $row['deposit'] > 0 && $sum['security_label'] ) {
			$cls  = $sum['security'] ? 'msb-pay--' . $sum['security']['status'] : '';
			$out .= '<br><span class="msb-pay ' . esc_attr( $cls ) . '">Kaution: ' . esc_html( $sum['security_label'] ) . '</span>';
		}
		return $out;
	}

	protected function column_total( $row ) {
		$out = esc_html( msb_price( $row['total'] ) );
		if ( (float) $row['deposit'] > 0 ) {
			$out .= '<br><small>Kaution ' . esc_html( msb_price( $row['deposit'] ) ) . '</small>';
		}
		return $out;
	}

	protected function column_status( $row ) {
		return '<span class="msb-status msb-status--' . esc_attr( $row['status'] ) . '">' . esc_html( msb_status_label( $row['status'] ) ) . '</span>' . ( 'admin' === $row['source'] || ! empty( $row['channel'] ) ? '<br><small>' . esc_html( MSB_Bookings::source_label( $row ) ) . '</small>' : '' );
	}

	protected function column_created_at( $row ) {
		return esc_html( mysql2date( 'd.m.Y H:i', $row['created_at'] ) );
	}

	public function no_items() {
		echo 'Keine Buchungen gefunden.';
	}
}

class MSB_Admin_Bookings {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
	}

	/* ------------------------------------------------------------------
	 * Aktionen
	 * ------------------------------------------------------------------ */

	public static function handle_actions() {
		if ( ! current_user_can( msb_capability() ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';

		// CSV-Export.
		if ( 'msb-bookings' === $page && isset( $_GET['msb_export'] ) ) {
			check_admin_referer( 'msb_export' );
			self::export_csv();
		}

		// Schnellaktion aus der Liste.
		if ( 'msb-bookings' === $page && isset( $_GET['msb_quick'], $_GET['id'] ) ) {
			$id = absint( $_GET['id'] );
			check_admin_referer( 'msb_quick_' . $id );
			$r = MSB_Bookings::update_status( $id, sanitize_key( $_GET['msb_quick'] ) );
			self::redirect( admin_url( 'admin.php?page=msb-bookings' ), is_wp_error( $r ) ? $r->get_error_message() : 'Status geändert.', is_wp_error( $r ) ? 'error' : 'success' );
		}

		// Sammelaktionen.
		if ( 'msb-bookings' === $page && ! empty( $_REQUEST['ids'] ) ) {
			$action = '';
			foreach ( array( 'action', 'action2' ) as $a ) {
				if ( ! empty( $_REQUEST[ $a ] ) && '-1' !== $_REQUEST[ $a ] ) {
					$action = sanitize_key( $_REQUEST[ $a ] );
				}
			}
			if ( $action ) {
				check_admin_referer( 'bulk-buchungen' );
				$ids  = array_map( 'absint', (array) $_REQUEST['ids'] );
				$n    = 0;
				$skip = 0;
				$kept = 0;
				foreach ( $ids as $id ) {
					if ( 'delete' === $action ) {
						if ( current_user_can( msb_capability( 'delete' ) ) ) { // Löschen nur mit eigenem Recht (ROL-01).
							if ( true === MSB_Bookings::delete( $id ) ) {
								$n++;
							} else {
								$kept++;
							}
						}
					} elseif ( 0 === strpos( $action, 'status_' ) ) {
						$r = MSB_Bookings::update_status( $id, substr( $action, 7 ) );
						if ( true === $r ) {
							$n++;
						} elseif ( is_wp_error( $r ) ) {
							$skip++;
						}
					}
				}
				$msg = sprintf( '%d Buchung(en) bearbeitet.', $n );
				if ( $skip ) {
					$msg .= sprintf( ' %d übersprungen, weil dieser Statuswechsel nicht erlaubt ist oder der Artikel nicht mehr frei ist.', $skip );
				}
				if ( $kept ) {
					$msg .= sprintf( ' %d nicht gelöscht, weil Buchungen mit Zahlung oder Beleg aufbewahrt werden müssen.', $kept );
				}
				self::redirect( remove_query_arg( array( 'action', 'action2', 'ids', '_wpnonce', '_wp_http_referer' ), wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=msb-bookings' ) ), $msg, $skip || $kept ? 'warning' : 'success' );
			}
		}

		if ( empty( $_POST['msb_booking_action'] ) ) {
			return;
		}
		// phpcs:enable

		$action = sanitize_key( wp_unslash( $_POST['msb_booking_action'] ) );
		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( 'create' === $action ) {
			check_admin_referer( 'msb_create_booking' );
			self::create_from_admin();
			return;
		}

		check_admin_referer( 'msb_booking_' . $id );
		$url = admin_url( 'admin.php?page=msb-booking&id=' . $id );

		switch ( $action ) {
			case 'update':
				MSB_Bookings::update_notes( $id, isset( $_POST['admin_notes'] ) ? wp_unslash( $_POST['admin_notes'] ) : '' ); // phpcs:ignore
				$status = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';
				$r      = MSB_Bookings::update_status( $id, $status, ! empty( $_POST['notify'] ) );
				if ( is_wp_error( $r ) ) {
					self::redirect( $url, $r->get_error_message(), 'error' );
				}
				self::redirect( $url, 'Buchung gespeichert.' );
				break;

			case 'resend':
				$type = isset( $_POST['email_type'] ) ? sanitize_key( $_POST['email_type'] ) : 'received';
				$ok   = MSB_Emails::send( $id, $type );
				self::redirect( $url, $ok ? 'E-Mail wurde versendet.' : 'E-Mail konnte nicht versendet werden (Vorlage deaktiviert oder keine gültige Adresse).', $ok ? 'success' : 'error' );
				break;

			case 'delete':
				if ( ! current_user_can( msb_capability( 'delete' ) ) ) {
					self::redirect( $url, 'Sie dürfen keine Buchungen löschen.', 'error' );
				}
				$r = MSB_Bookings::delete( $id );
				if ( is_wp_error( $r ) ) {
					self::redirect( $url, $r->get_error_message(), 'error' );
				}
				self::redirect( admin_url( 'admin.php?page=msb-bookings' ), 'Buchung gelöscht.' );
				break;

			case 'pay':
				self::payment_action( $id, $url );
				break;
		}
	}

	/**
	 * Zahlungsaktionen aus der Buchungsdetailseite.
	 */
	private static function payment_action( $id, $url ) {
		// phpcs:disable WordPress.Security.NonceVerification -- Nonce wurde in handle_actions geprüft.
		$do  = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : '';
		$pid = isset( $_POST['pid'] ) ? absint( $_POST['pid'] ) : 0;
		$amt = isset( $_POST['amount'] ) ? (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['amount'] ) ) ) : 0;
		$p   = $pid ? MSB_Payments::get( $pid ) : null;
		if ( $pid && ( ! $p || (int) $p['booking_id'] !== $id ) ) {
			self::redirect( $url, 'Zahlung nicht gefunden.', 'error' );
		}
		switch ( $do ) {
			case 'release':
				$r   = MSB_Payments::release_security( $pid, ! empty( $_POST['notify'] ) );
				$msg = 'Kaution freigegeben.';
				break;
			case 'capture':
				$r   = MSB_Payments::capture_security( $pid, $amt, isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '' );
				$msg = sprintf( '%s der Kaution einbehalten, der Rest wurde freigegeben.', msb_price( $amt ) );
				break;
			case 'refund':
				$r   = MSB_Payments::refund( $pid, $amt );
				$msg = sprintf( '%s erstattet.', msb_price( $amt ) );
				break;
			case 'hold':
				$r   = MSB_Payments::maybe_hold_security( $id, true );
				$msg = 'Kaution: Reservierung ausgelöst bzw. Zahlungslink gesendet.';
				if ( null === $r ) {
					$r = new WP_Error( 'msb', 'Nicht möglich – Kaution ist bereits reserviert, die Buchung ist nicht aktiv oder Kautions-Reservierung ist in den Einstellungen nicht aktiviert.' );
				}
				break;
			case 'link':
				$for = isset( $_POST['for'] ) ? sanitize_key( $_POST['for'] ) : 'prepay';
				$r   = MSB_Emails::send( $id, 'payment_link', array( 'for' => $for ) ) ? true : new WP_Error( 'msb', 'Zahlungslink konnte nicht gesendet werden (nichts offen, Vorlage deaktiviert oder kein Online-Anbieter aktiv).' );
				if ( true === $r && 'security' === $for ) {
					$booking = MSB_Bookings::get( $id );
					MSB_Payments::add( $id, '', 'security', $booking['deposit'], 'requested', '', array( 'sent' => current_time( 'mysql' ) ) );
				}
				$msg = 'Zahlungslink an den Kunden gesendet.';
				break;
			case 'manual':
				$type = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : 'rest';
				$type = in_array( $type, array( 'prepay', 'rest', 'security' ), true ) ? $type : 'rest';
				if ( $amt <= 0 ) {
					self::redirect( $url, 'Bitte einen Betrag eingeben.', 'error' );
				}
				MSB_Payments::add( $id, 'manual', $type, $amt, 'security' === $type ? 'authorized' : 'paid', '', array( 'note' => isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '', 'by' => get_current_user_id() ) );
				$r   = true;
				$msg = 'Zahlung erfasst.';
				break;
			case 'manual_release':
				MSB_Payments::update( $pid, array( 'status' => 'released' ), array( 'released_at' => current_time( 'mysql' ) ) );
				$r   = true;
				$msg = 'Bar-Kaution als zurückgegeben vermerkt.';
				break;
			default:
				$r   = new WP_Error( 'msb', 'Unbekannte Aktion.' );
				$msg = '';
		}
		// phpcs:enable
		if ( is_wp_error( $r ) ) {
			self::redirect( $url, $r->get_error_message(), 'error' );
		}
		self::redirect( $url, $msg );
	}

	private static function redirect( $url, $message, $type = 'success' ) {
		set_transient( 'msb_notice_' . get_current_user_id(), array( $message, $type ), 60 );
		wp_safe_redirect( $url );
		exit;
	}

	private static function notices() {
		$key = 'msb_notice_' . get_current_user_id();
		$n   = get_transient( $key );
		if ( $n ) {
			delete_transient( $key );
			echo '<div class="notice notice-' . esc_attr( in_array( $n[1], array( 'error', 'warning' ), true ) ? $n[1] : 'success' ) . ' is-dismissible"><p>' . esc_html( $n[0] ) . '</p></div>';
		}
	}

	private static function create_from_admin() {
		$in    = wp_unslash( $_POST ); // phpcs:ignore
		$lines = array();
		foreach ( (array) ( isset( $in['lines'] ) ? $in['lines'] : array() ) as $l ) {
			if ( empty( $l['item_id'] ) ) {
				continue;
			}
			$lines[] = array(
				'item_id' => absint( $l['item_id'] ),
				'start'   => msb_normalize_date( isset( $l['start'] ) ? $l['start'] : '' ),
				'end'     => msb_normalize_date( isset( $l['end'] ) ? $l['end'] : '' ),
				'qty'     => max( 1, absint( isset( $l['qty'] ) ? $l['qty'] : 1 ) ),
			);
		}

		list( $values, $errors, $data ) = MSB_Form_Fields::validate( isset( $in['msb_field'] ) ? $in['msb_field'] : array() );
		list( $name, $email, $phone )   = MSB_Form_Fields::customer_from_data( $data );

		$customer_id = ! empty( $in['customer_id'] ) ? absint( $in['customer_id'] ) : 0;
		$existing    = $customer_id ? MSB_Customers::get( $customer_id ) : null;
		$customer_id = MSB_Customers::save( $values, $existing ? $customer_id : 0, $existing ? (int) $existing['user_id'] : 0 );

		$force  = ! empty( $in['force'] );
		$method = isset( $in['payment_method'] ) ? sanitize_key( $in['payment_method'] ) : 'offline';
		$method = isset( MSB_Payments::online_providers()[ $method ] ) ? $method : 'offline';
		$id     = MSB_Bookings::create(
			array(
				'lines'          => $lines,
				'customer_id'    => $customer_id,
				'payment_method' => $method,
				'status'         => isset( $in['status'] ) ? sanitize_key( $in['status'] ) : 'confirmed',
				'customer_name'  => $name,
				'customer_email' => $email,
				'customer_phone' => $phone,
				'form_data'      => $data,
				'admin_notes'    => isset( $in['admin_notes'] ) ? $in['admin_notes'] : '',
				'source'         => 'admin',
				'ignore_rules'   => true,
				'skip_check'     => $force,
				'send_emails'    => ! empty( $in['notify'] ),
			)
		);

		if ( is_wp_error( $id ) ) {
			set_transient( 'msb_admin_form_' . get_current_user_id(), $in, 300 );
			self::redirect( admin_url( 'admin.php?page=msb-booking' ), $id->get_error_message(), 'error' );
		}
		self::redirect( admin_url( 'admin.php?page=msb-booking&id=' . $id ), 'Buchung angelegt.' );
	}

	private static function export_csv() {
		$args             = MSB_Bookings_List_Table::filter_args();
		$args['per_page'] = 0;
		list( $rows )     = MSB_Bookings::query( $args );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename=buchungen-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM für Excel.

		// Alle Formularfelder als Spalten.
		$field_cols = array();
		foreach ( MSB_Form_Fields::get() as $f ) {
			if ( ! in_array( $f['type'], array( 'heading', 'paragraph' ), true ) ) {
				$field_cols[ $f['key'] ] = wp_strip_all_tags( $f['label'] );
			}
		}

		fputcsv( $out, array_merge( array( 'Buchungsnummer', 'Status', 'Eingegangen', 'Artikel', 'Anzahl', 'Abholung', 'Rückgabe', 'Tage', 'Positionspreis', 'Mietpreis gesamt', 'Kaution gesamt', 'Name', 'E-Mail', 'Telefon' ), array_values( $field_cols ), array( 'Interne Notiz' ) ), ';' );

		foreach ( $rows as $r ) {
			$b = MSB_Bookings::get( $r['id'] );
			foreach ( $b['items'] as $l ) {
				$fields = array();
				foreach ( array_keys( $field_cols ) as $k ) {
					$fields[] = isset( $b['form_data'][ $k ]['value'] ) ? $b['form_data'][ $k ]['value'] : '';
				}
				$row = array_merge(
					array(
						$b['booking_number'],
						msb_status_label( $b['status'] ),
						mysql2date( 'd.m.Y H:i', $b['created_at'] ),
						$l['item_title'],
						$l['quantity'],
						msb_date( $l['start_date'] ),
						msb_date( $l['end_date'] ),
						$l['days'],
						number_format( (float) $l['line_total'], 2, ',', '' ),
						number_format( (float) $b['total'], 2, ',', '' ),
						number_format( (float) $b['deposit'], 2, ',', '' ),
						$b['customer_name'],
						$b['customer_email'],
						$b['customer_phone'],
					),
					$fields,
					array( $b['admin_notes'] )
				);
				// Schutz gegen Formel-Injection in Tabellenkalkulationen.
				$row = array_map(
					function ( $v ) {
						$v = (string) $v;
						return preg_match( '/^[=+\-@\t\r]/', $v ) ? "'" . $v : $v;
					},
					$row
				);
				fputcsv( $out, $row, ';' );
			}
		}
		fclose( $out );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Seiten
	 * ------------------------------------------------------------------ */

	public static function page_list() {
		$table = new MSB_Bookings_List_Table();
		$table->prepare_items();
		?>
		<div class="wrap msb-admin">
			<h1 class="wp-heading-inline">Buchungen</h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=msb-booking' ) ); ?>" class="page-title-action">Buchung manuell anlegen</a>
			<hr class="wp-header-end">
			<?php self::notices(); ?>
			<?php self::today_box(); ?>
			<?php $table->views(); ?>
			<form method="get">
				<input type="hidden" name="page" value="msb-bookings">
				<?php
				if ( isset( $_GET['status'] ) ) { // phpcs:ignore
					echo '<input type="hidden" name="status" value="' . esc_attr( sanitize_key( $_GET['status'] ) ) . '">'; // phpcs:ignore
				}
				$table->search_box( 'Suchen', 'msb-search' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Übersicht: Abholungen und Rückgaben heute / morgen.
	 */
	private static function today_box() {
		$today    = msb_today();
		$tomorrow = msb_add_days( $today, 1 );
		$rows     = MSB_Bookings::items_in_range( msb_add_days( $today, -60 ), $tomorrow );
		$groups   = array(
			'Abholungen heute'  => array(),
			'Rückgaben heute'   => array(),
			'Abholungen morgen' => array(),
			'Rückgaben morgen'  => array(),
		);
		foreach ( $rows as $r ) {
			$link = '<a href="' . esc_url( admin_url( 'admin.php?page=msb-booking&id=' . (int) $r['booking_id'] ) ) . '">' . (int) $r['quantity'] . '× ' . esc_html( $r['item_title'] ) . '</a> <span class="description">' . esc_html( $r['customer_name'] ) . '</span>';
			if ( $r['start_date'] === $today && in_array( $r['status'], array( 'pending', 'confirmed' ), true ) ) {
				$groups['Abholungen heute'][] = $link;
			}
			if ( $r['end_date'] === $today && in_array( $r['status'], array( 'confirmed', 'picked_up' ), true ) ) {
				$groups['Rückgaben heute'][] = $link;
			}
			if ( $r['start_date'] === $tomorrow && in_array( $r['status'], array( 'pending', 'confirmed' ), true ) ) {
				$groups['Abholungen morgen'][] = $link;
			}
			if ( $r['end_date'] === $tomorrow && in_array( $r['status'], array( 'confirmed', 'picked_up' ), true ) ) {
				$groups['Rückgaben morgen'][] = $link;
			}
		}
		echo '<div class="msb-today">';
		foreach ( $groups as $label => $list ) {
			echo '<div class="msb-today__box"><h3>' . esc_html( $label ) . ' <span class="count">' . count( $list ) . '</span></h3>';
			echo $list ? '<ul><li>' . implode( '</li><li>', $list ) . '</li></ul>' : '<p class="description">—</p>'; // phpcs:ignore
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Detailansicht oder neue Buchung.
	 */
	public static function page_booking() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore
		if ( ! $id ) {
			self::page_new();
			return;
		}
		$b = MSB_Bookings::get( $id );
		if ( ! $b ) {
			echo '<div class="wrap"><h1>Buchung nicht gefunden</h1></div>';
			return;
		}
		?>
		<div class="wrap msb-admin">
			<h1 class="wp-heading-inline">Buchung <?php echo esc_html( $b['booking_number'] ); ?></h1>
			<span class="msb-status msb-status--<?php echo esc_attr( $b['status'] ); ?>"><?php echo esc_html( msb_status_label( $b['status'] ) ); ?></span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=msb-bookings' ) ); ?>" class="page-title-action">← Zur Übersicht</a>
			<hr class="wp-header-end">
			<?php self::notices(); ?>

			<div class="msb-detail">
				<div class="msb-detail__main">
					<div class="msb-card-box">
						<h2>Gemietete Artikel</h2>
						<table class="widefat striped">
							<thead><tr><th>Artikel</th><th>Abholung</th><th>Rückgabe</th><th>Tage</th><th>Anzahl</th><th>Tagespreis</th><th style="text-align:right">Preis</th></tr></thead>
							<tbody>
							<?php foreach ( $b['items'] as $l ) : ?>
								<tr>
									<td><a href="<?php echo esc_url( get_edit_post_link( $l['item_id'] ) ? get_edit_post_link( $l['item_id'] ) : '#' ); ?>"><?php echo esc_html( $l['item_title'] ); ?></a>
										<?php foreach ( $l['adjustments'] as $adj ) : ?>
											<br><small class="<?php echo $adj['amount'] < 0 ? 'msb-green' : 'msb-orange'; ?>"><?php echo esc_html( $adj['label'] . ': ' . ( $adj['amount'] < 0 ? '−' : '+' ) . msb_price( abs( $adj['amount'] ) ) ); ?></small>
										<?php endforeach; ?>
									</td>
									<td><?php echo esc_html( msb_date( $l['start_date'] ) ); ?></td>
									<td><?php echo esc_html( msb_date( $l['end_date'] ) ); ?></td>
									<td><?php echo (int) $l['days']; ?></td>
									<td><?php echo (int) $l['quantity']; ?></td>
									<td><?php echo esc_html( msb_price( $l['day_rate'] ) ); ?><?php echo (float) $l['base_fee'] > 0 ? '<br><small>+ ' . esc_html( msb_price( $l['base_fee'] ) ) . ' Grundgebühr</small>' : ''; ?></td>
									<td style="text-align:right"><?php echo esc_html( msb_price( $l['line_total'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
							<tfoot>
								<tr><th colspan="6">Mietpreis gesamt</th><th style="text-align:right"><?php echo esc_html( msb_price( $b['total'] ) ); ?></th></tr>
								<tr><td colspan="6">Kaution</td><td style="text-align:right"><?php echo esc_html( msb_price( $b['deposit'] ) ); ?></td></tr>
							</tfoot>
						</table>
					</div>

					<div class="msb-card-box">
						<h2>Kundenangaben <?php echo $b['customer_id'] ? '<a class="page-title-action" href="' . esc_url( admin_url( 'admin.php?page=msb-customer&id=' . (int) $b['customer_id'] ) ) . '">Kundenstamm öffnen</a>' : ''; ?></h2>
						<?php echo MSB_Form_Fields::data_table( $b['form_data'] ); // phpcs:ignore ?>
						<?php if ( empty( $b['form_data'] ) ) : ?>
							<p><?php echo esc_html( $b['customer_name'] ); ?><br><?php echo esc_html( $b['customer_email'] ); ?><br><?php echo esc_html( $b['customer_phone'] ); ?></p>
						<?php endif; ?>
						<p>
							<?php if ( $b['customer_email'] ) : ?>
								<a class="button" href="mailto:<?php echo esc_attr( $b['customer_email'] ); ?>?subject=<?php echo rawurlencode( 'Ihre Buchung ' . $b['booking_number'] ); ?>">E-Mail schreiben</a>
							<?php endif; ?>
							<?php if ( $b['customer_phone'] ) : ?>
								<a class="button" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $b['customer_phone'] ) ); ?>">Anrufen</a>
							<?php endif; ?>
							<button type="button" class="button" onclick="window.print()">Drucken</button>
						</p>
						<?php MSB_Admin_Booking_Customer::box( $b ); ?>
					</div>

					<?php self::payments_box( $b ); ?>

					<?php MSB_Documents::admin_box( $b ); ?>
				</div>

				<div class="msb-detail__side">
					<form method="post" class="msb-card-box">
						<?php wp_nonce_field( 'msb_booking_' . $id ); ?>
						<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
						<input type="hidden" name="msb_booking_action" value="update">
						<h2>Status</h2>
						<p>
							<select name="status" class="widefat">
								<?php
								$msb_targets = array_merge( array( $b['status'] ), msb_allowed_statuses( $b['status'] ) ); // nur erlaubte Wechsel (BKG-16)
								foreach ( msb_statuses() as $k => $label ) :
									if ( ! in_array( $k, $msb_targets, true ) || ( 'cancelled' === $k && 'cancelled' !== $b['status'] ) ) { // Stornieren über den eigenen Kasten.
										continue;
									}
									?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $b['status'], $k ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
						<p><label><input type="checkbox" name="notify" value="1" checked> Kunden bei „Bestätigt“ / „Storniert“ per E-Mail informieren</label></p>
						<?php if ( MSB_Payments::is_cash( $b ) && in_array( $b['status'], array( 'pending', 'confirmed' ), true ) ) : ?>
							<p class="description">Beim Wechsel auf „Abgeholt“:</p>
							<p>
								<label><input type="checkbox" name="msb_cash_rent" value="1" checked> Miete bar erhalten</label><br>
								<?php if ( (float) $b['deposit'] > 0 ) : ?>
									<label><input type="checkbox" name="msb_cash_deposit" value="1" checked> Kaution bar erhalten</label>
								<?php endif; ?>
							</p>
						<?php endif; ?>
						<h2>Interne Notiz</h2>
						<p><textarea name="admin_notes" rows="5" class="widefat" placeholder="Nur für Sie sichtbar, z. B. Kaution erhalten, Zustand bei Rückgabe …"><?php echo esc_textarea( $b['admin_notes'] ); ?></textarea></p>
						<p><button type="submit" class="button button-primary">Speichern</button></p>
						<p class="description">Eingegangen: <?php echo esc_html( mysql2date( 'd.m.Y H:i', $b['created_at'] ) ); ?><br>Zuletzt geändert: <?php echo esc_html( mysql2date( 'd.m.Y H:i', $b['updated_at'] ) ); ?><br>Quelle: <?php echo esc_html( MSB_Bookings::source_label( $b ) ); ?></p>
					</form>

					<?php MSB_Cancellation::admin_box( $b ); ?>

					<form method="post" class="msb-card-box">
						<?php wp_nonce_field( 'msb_booking_' . $id ); ?>
						<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
						<input type="hidden" name="msb_booking_action" value="resend">
						<h2>E-Mail erneut senden</h2>
						<p>
							<select name="email_type" class="widefat">
								<?php foreach ( MSB_Emails::types() as $k => $label ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
						<p><button type="submit" class="button">Senden</button></p>
					</form>

					<?php if ( current_user_can( msb_capability( 'delete' ) ) ) : ?>
					<form method="post" class="msb-card-box" onsubmit="return confirm('Buchung endgültig löschen? Dies kann nicht rückgängig gemacht werden.');">
						<?php wp_nonce_field( 'msb_booking_' . $id ); ?>
						<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
						<input type="hidden" name="msb_booking_action" value="delete">
						<button type="submit" class="button-link msb-delete">Buchung endgültig löschen</button>
						<p class="description">Tipp: Stornieren statt löschen, damit die Buchung nachvollziehbar bleibt.</p>
					</form>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Zahlungen & Kaution in der Buchungsdetailseite.
	 */
	private static function payments_box( array $b ) {
		$id       = (int) $b['id'];
		$payments = MSB_Payments::for_booking( $id );
		$sum      = MSB_Payments::summary( $b );
		$types    = MSB_Payments::type_labels();
		$stati    = MSB_Payments::status_labels();
		$online   = MSB_Payments::online_providers();
		$form     = function ( $do, $fields, $button, $class = 'button', $confirm = '' ) use ( $id ) {
			$h  = '<form method="post" class="msb-inline-form"' . ( $confirm ? ' onsubmit="return confirm(\'' . esc_js( $confirm ) . '\')"' : '' ) . '>';
			$h .= wp_nonce_field( 'msb_booking_' . $id, '_wpnonce', true, false );
			$h .= '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="msb_booking_action" value="pay"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">';
			$h .= $fields . '<button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $button ) . '</button></form>';
			return $h;
		};
		?>
		<div class="msb-card-box" id="payments">
			<h2>Zahlungen &amp; Kaution <?php echo msb_help_link( 'zahlungen-backend', '' ); // phpcs:ignore ?></h2>
			<div class="msb-pay-summary">
				<div><span>Mietpreis</span><strong><?php echo esc_html( msb_price( $b['total'] ) ); ?></strong></div>
				<div><span>Bezahlt</span><strong><?php echo esc_html( msb_price( $sum['paid'] ) ); ?></strong></div>
				<div><span>Offen</span><strong class="<?php echo $sum['open'] > 0 ? 'msb-orange' : 'msb-green'; ?>"><?php echo esc_html( msb_price( $sum['open'] ) ); ?></strong></div>
				<div><span>Kaution</span><strong><?php echo esc_html( msb_price( $b['deposit'] ) ); ?></strong><small><?php echo esc_html( $sum['security_label'] ); ?></small></div>
				<div><span>Zahlungsart</span><strong><?php echo esc_html( MSB_Emails::method_label( $b['payment_method'] ) ); ?></strong></div>
			</div>

			<?php if ( $payments ) : ?>
			<table class="widefat striped msb-pay-table">
				<thead><tr><th>Art</th><th>Anbieter</th><th>Betrag</th><th>Status</th><th>Datum</th><th>Aktion</th></tr></thead>
				<tbody>
				<?php foreach ( array_reverse( $payments ) as $p ) : ?>
					<tr>
						<td><?php echo esc_html( $types[ $p['type'] ] ); ?></td>
						<td><?php echo esc_html( MSB_Payments::provider_label( $p['provider'] ) ); ?><?php echo $p['provider_ref'] ? '<br><small class="code">' . esc_html( $p['provider_ref'] ) . '</small>' : ''; ?></td>
						<td><?php echo esc_html( msb_price( $p['amount'] ) ); ?>
							<?php if ( ! empty( $p['data']['refunded'] ) ) : ?><br><small>erstattet: <?php echo esc_html( msb_price( $p['data']['refunded'] ) ); ?></small><?php endif; ?>
							<?php if ( ! empty( $p['data']['captured'] ) ) : ?><br><small>einbehalten: <?php echo esc_html( msb_price( $p['data']['captured'] ) ); ?><?php echo ! empty( $p['data']['reason'] ) ? ' – ' . esc_html( $p['data']['reason'] ) : ''; ?></small><?php endif; ?>
						</td>
						<td><span class="msb-pay msb-pay--<?php echo esc_attr( $p['status'] ); ?>"><?php echo esc_html( $stati[ $p['status'] ] ); ?></span>
							<?php if ( 'authorized' === $p['status'] && ! empty( $p['data']['expires'] ) ) : ?><br><small>gültig bis <?php echo esc_html( wp_date( 'd.m.Y', strtotime( $p['data']['expires'] ) ) ); ?></small><?php endif; ?>
							<?php if ( ! empty( $p['data']['error'] ) ) : ?><br><small class="msb-red"><?php echo esc_html( $p['data']['error'] ); ?></small><?php endif; ?>
							<?php if ( ! empty( $p['data']['note'] ) ) : ?><br><small><?php echo esc_html( $p['data']['note'] ); ?></small><?php endif; ?>
						</td>
						<td><?php echo esc_html( mysql2date( 'd.m.Y H:i', $p['updated_at'] ) ); ?></td>
						<td class="msb-pay-actions">
							<?php
							if ( 'security' === $p['type'] && 'authorized' === $p['status'] && 'manual' !== $p['provider'] ) {
								echo $form( 'release', '<input type="hidden" name="pid" value="' . (int) $p['id'] . '"><label><input type="checkbox" name="notify" value="1" checked> Kunde informieren</label> ', 'Freigeben', 'button button-primary' ); // phpcs:ignore
								echo $form( 'capture', '<input type="hidden" name="pid" value="' . (int) $p['id'] . '"><input type="number" step="0.01" min="0.01" max="' . esc_attr( $p['amount'] ) . '" name="amount" value="' . esc_attr( $p['amount'] ) . '" class="small-text"> <input type="text" name="reason" placeholder="Grund, z. B. Schaden" class="regular-text"> ', 'Einbehalten', 'button', 'Betrag wirklich einbehalten? Der Rest wird freigegeben.' ); // phpcs:ignore
							} elseif ( 'security' === $p['type'] && 'authorized' === $p['status'] ) {
								echo $form( 'manual_release', '<input type="hidden" name="pid" value="' . (int) $p['id'] . '">', 'Als zurückgezahlt vermerken' ); // phpcs:ignore
								echo $form( 'capture', '<input type="hidden" name="pid" value="' . (int) $p['id'] . '"><input type="number" step="0.01" min="0.01" max="' . esc_attr( $p['amount'] ) . '" name="amount" value="' . esc_attr( $p['amount'] ) . '" class="small-text"> <input type="text" name="reason" placeholder="Grund, z. B. Schaden" class="regular-text" required> ', 'Einbehalten', 'button', 'Betrag wirklich einbehalten? Es wird eine Kautionsabrechnung erstellt, den Rest bitte bar zurückgeben.' ); // phpcs:ignore
							} elseif ( 'paid' === $p['status'] && 'security' !== $p['type'] ) {
								$left = (float) $p['amount'] - ( isset( $p['data']['refunded'] ) ? (float) $p['data']['refunded'] : 0 );
								echo $form( 'refund', '<input type="hidden" name="pid" value="' . (int) $p['id'] . '"><input type="number" step="0.01" min="0.01" max="' . esc_attr( $left ) . '" name="amount" value="' . esc_attr( $left ) . '" class="small-text"> ', 'manual' === $p['provider'] ? 'Erstattung vermerken' : 'Erstatten', 'button', 'Betrag wirklich erstatten?' ); // phpcs:ignore
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php else : ?>
				<p class="description">Noch keine Zahlungen erfasst.</p>
			<?php endif; ?>

			<div class="msb-pay-tools">
				<?php if ( $online ) : ?>
					<div>
						<h4>Zahlungslink senden</h4>
						<?php
						$opts = '<select name="for">';
						foreach ( array( 'prepay' => 'Anzahlung', 'rest' => 'Restbetrag', 'security' => 'Kaution (reservieren)' ) as $k => $l ) {
							$opts .= '<option value="' . esc_attr( $k ) . '"' . selected( MSB_Payments::default_for( $b ), $k, false ) . '>' . esc_html( $l . ' – ' . msb_price( MSB_Payments::amount_for( $b, $k ) ) ) . '</option>';
						}
						$opts .= '</select> ';
						echo $form( 'link', $opts, 'Per E-Mail senden' ); // phpcs:ignore
						?>
						<p class="description">Link zum Kopieren: <input type="text" readonly class="msb-copy" value="<?php echo esc_attr( MSB_Payments::pay_url( $b ) ); ?>" onclick="this.select()" style="width:100%"></p>
					</div>
				<?php endif; ?>
				<?php if ( MSB_Payments::security_hold_active() && (float) $b['deposit'] > 0 ) : ?>
					<div>
						<h4>Kaution jetzt reservieren</h4>
						<?php echo $form( 'hold', '', 'Jetzt reservieren' ); // phpcs:ignore ?>
						<p class="description">Belastet eine hinterlegte Karte (Stripe) oder schickt dem Kunden einen Link. Passiert sonst automatisch <?php echo (int) msb_setting( 'security_hold_days' ); ?> Tag(e) vor Abholung.</p>
					</div>
				<?php endif; ?>
				<div>
					<h4>Zahlung vor Ort erfassen</h4>
					<?php echo $form( 'manual', '<select name="type"><option value="rest">Mietpreis / Rest</option><option value="prepay">Anzahlung</option><option value="security">Kaution (bar erhalten)</option></select> <input type="number" step="0.01" min="0.01" name="amount" value="' . esc_attr( $sum['open'] ) . '" class="small-text"> <input type="text" name="note" placeholder="Notiz, z. B. bar" class="regular-text"> ', 'Erfassen' ); // phpcs:ignore ?>
				</div>
			</div>
		</div>
		<?php
	}

	private static function page_new() {
		$prev = get_transient( 'msb_admin_form_' . get_current_user_id() );
		delete_transient( 'msb_admin_form_' . get_current_user_id() );
		$prev  = is_array( $prev ) ? $prev : array();
		$items = get_posts(
			array(
				'post_type'      => MSB_Items::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$lines = ! empty( $prev['lines'] ) ? array_values( $prev['lines'] ) : array( array() );
		$pre   = isset( $_GET['item'] ) ? absint( $_GET['item'] ) : 0; // phpcs:ignore
		$preday = isset( $_GET['date'] ) ? msb_normalize_date( sanitize_text_field( wp_unslash( $_GET['date'] ) ) ) : ''; // phpcs:ignore
		if ( empty( $prev['lines'] ) && ( $pre || $preday ) ) {
			$lines = array(
				array(
					'item_id' => $pre,
					'start'   => $preday,
					'end'     => $preday,
					'qty'     => 1,
				),
			);
		}
		?>
		<div class="wrap msb-admin">
			<h1>Buchung manuell anlegen</h1>
			<?php self::notices(); ?>
			<p>Für telefonische Anfragen oder Buchungen vor Ort. Buchungsregeln (Mindestdauer, Vorlaufzeit, Ruhetage) gelten hier nicht, die Verfügbarkeit wird aber geprüft.</p>
			<?php if ( ! $items ) : ?>
				<div class="notice notice-warning"><p>Bitte legen Sie zuerst <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . MSB_Items::POST_TYPE ) ); ?>">einen Mietartikel</a> an.</p></div>
			<?php else : ?>
			<form method="post" class="msb-new-booking">
				<?php wp_nonce_field( 'msb_create_booking' ); ?>
				<input type="hidden" name="msb_booking_action" value="create">

				<div class="msb-card-box">
					<h2>Artikel</h2>
					<table class="widefat msb-lines">
						<thead><tr><th>Artikel</th><th>Abholung</th><th>Rückgabe</th><th style="width:90px">Anzahl</th><th style="width:40px"></th></tr></thead>
						<tbody>
						<?php foreach ( $lines as $i => $l ) : ?>
							<tr class="msb-line">
								<td>
									<select name="lines[<?php echo (int) $i; ?>][item_id]" class="widefat" required>
										<option value="">— Artikel wählen —</option>
										<?php foreach ( $items as $p ) : $d = MSB_Items::data( $p->ID ); ?>
											<option value="<?php echo (int) $p->ID; ?>" <?php selected( isset( $l['item_id'] ) ? (int) $l['item_id'] : 0, $p->ID ); ?>><?php echo esc_html( $d['title'] . ' (' . $d['stock'] . ' Stk., ' . msb_price( $d['price_day'] ) . '/Tag)' ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
								<td><input type="date" name="lines[<?php echo (int) $i; ?>][start]" value="<?php echo esc_attr( isset( $l['start'] ) ? $l['start'] : '' ); ?>" required></td>
								<td><input type="date" name="lines[<?php echo (int) $i; ?>][end]" value="<?php echo esc_attr( isset( $l['end'] ) ? $l['end'] : '' ); ?>" required></td>
								<td><input type="number" min="1" name="lines[<?php echo (int) $i; ?>][qty]" value="<?php echo esc_attr( isset( $l['qty'] ) ? $l['qty'] : 1 ); ?>" class="small-text"></td>
								<td><button type="button" class="button-link msb-remove-line" title="Entfernen"><span class="dashicons dashicons-trash"></span></button></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<p><button type="button" class="button" id="msb-add-line">+ Weiterer Artikel</button></p>
				</div>

				<div class="msb-card-box">
					<h2>Kundendaten</h2>
					<?php
					$customers = MSB_Customers::all_for_select();
					$pre_c     = isset( $_GET['customer'] ) ? absint( $_GET['customer'] ) : ( isset( $prev['customer_id'] ) ? absint( $prev['customer_id'] ) : 0 ); // phpcs:ignore
					?>
					<p><label>Bestehender Kunde<br>
						<select name="customer_id" id="msb-customer-select" class="regular-text">
							<option value="">— Neuer Kunde —</option>
							<?php foreach ( $customers as $c ) : ?>
								<option value="<?php echo (int) $c['id']; ?>" <?php selected( $pre_c, (int) $c['id'] ); ?> data-values="<?php echo esc_attr( wp_json_encode( array_merge( array( 'email' => $c['email'] ), $c['data'] ) ) ); ?>"><?php echo esc_html( trim( $c['name'] . ' · ' . $c['email'] . ( $c['city'] ? ' · ' . $c['city'] : '' ), ' ·' ) ); ?></option>
							<?php endforeach; ?>
						</select></label></p>
					<p class="description">Die Auswahl füllt die Felder aus; Änderungen werden im Kundenstamm gespeichert. Pflichtfelder sind hier optional.</p>
					<div class="msb-admin-form">
						<?php
						$values = isset( $prev['msb_field'] ) && is_array( $prev['msb_field'] ) ? $prev['msb_field'] : array();
						if ( ! $values && $pre_c ) {
							$pc     = MSB_Customers::get( $pre_c );
							$values = $pc ? array_merge( array( 'email' => $pc['email'] ), $pc['data'] ) : array();
						}
						foreach ( MSB_Form_Fields::get() as $f ) {
							if ( in_array( $f['type'], array( 'heading', 'paragraph', 'checkbox' ), true ) ) {
								continue;
							}
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
								echo '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $n ) . '" value="' . esc_attr( $v ) . '" class="widefat" data-key="' . esc_attr( $f['key'] ) . '">';
							}
							echo '</label></p>';
						}
						?>
					</div>
				</div>

				<div class="msb-card-box">
					<h2>Optionen</h2>
					<p>
						<label>Status
							<select name="status">
								<?php foreach ( msb_statuses() as $k => $label ) : if ( 'awaiting_payment' === $k ) { continue; } ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( isset( $prev['status'] ) ? $prev['status'] : 'pending', $k ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					</p>
					<?php $pm = isset( $prev['payment_method'] ) ? $prev['payment_method'] : 'offline'; ?>
					<fieldset><legend><strong>Zahlung</strong></legend>
						<p><label><input type="radio" name="payment_method" value="offline" <?php checked( 'offline', $pm ); ?>> Bar bei Abholung (Miete und Kaution)</label>
						<?php foreach ( MSB_Payments::online_providers() as $k => $label ) : ?>
							<br><label><input type="radio" name="payment_method" value="<?php echo esc_attr( $k ); ?>" <?php checked( $k, $pm ); ?>> Online: <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?>
						<br><span class="description">Bei Online-Zahlung enthält die Bestätigungsmail einen Bezahlknopf für die Miete<?php echo MSB_Payments::security_hold_active() ? '; die Kaution wird kurz vor der Abholung auf der Karte reserviert' : ''; ?>. Der Link steht außerdem im Kasten „Zahlungen“ der Buchung.</span></p>
					</fieldset>
					<p><label><input type="checkbox" name="notify" value="1" <?php checked( $prev ? ! empty( $prev['notify'] ) : true ); ?>> Kunden per E-Mail informieren (Link zum Bestätigen von Buchung, Mietbedingungen und Datenschutz)</label><br><span class="description">Bestätigt der Kunde, wird die Buchung automatisch „Bestätigt“ und er erhält die Bestätigungsmail mit den Zahlungsangaben. Die Benachrichtigung an Ihre Buchungsadresse geht immer raus.</span></p>
					<p><label><input type="checkbox" name="force" value="1"> Verfügbarkeit nicht prüfen (Überbuchung zulassen)</label></p>
					<p><label>Interne Notiz<br><textarea name="admin_notes" rows="3" class="large-text"><?php echo esc_textarea( isset( $prev['admin_notes'] ) ? $prev['admin_notes'] : '' ); ?></textarea></label></p>
				</div>

				<p><button type="submit" class="button button-primary button-hero">Buchung anlegen</button></p>
			</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Belegungskalender (Monatsansicht).
	 */
	public static function page_calendar() {
		// phpcs:disable WordPress.Security.NonceVerification
		$month = isset( $_GET['month'] ) && preg_match( '/^\d{4}-\d{2}$/', sanitize_text_field( wp_unslash( $_GET['month'] ) ) ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : current_time( 'Y-m' );
		$item  = isset( $_GET['item'] ) ? absint( $_GET['item'] ) : 0;
		// phpcs:enable
		$first = DateTime::createFromFormat( '!Y-m-d', $month . '-01' );
		if ( ! $first ) {
			$first = DateTime::createFromFormat( '!Y-m-d', current_time( 'Y-m' ) . '-01' );
			$month = $first->format( 'Y-m' );
		}
		$from = $first->format( 'Y-m-d' );
		$to   = $first->format( 'Y-m-t' );
		$prev = ( clone $first )->modify( '-1 month' )->format( 'Y-m' );
		$next = ( clone $first )->modify( '+1 month' )->format( 'Y-m' );

		$rows   = MSB_Bookings::items_in_range( $from, $to, $item );
		$by_day = array();
		foreach ( $rows as $r ) {
			foreach ( msb_date_range( max( $r['start_date'], $from ), min( $r['end_date'], $to ) ) as $d ) {
				$by_day[ $d ][] = $r;
			}
		}

		$items  = get_posts(
			array(
				'post_type'      => MSB_Items::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$months = array( 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember' );
		$base   = admin_url( 'admin.php?page=msb-calendar' . ( $item ? '&item=' . $item : '' ) );
		$today  = msb_today();
		?>
		<div class="wrap msb-admin">
			<h1>Belegungskalender</h1>
			<div class="msb-cal-toolbar">
				<a class="button" href="<?php echo esc_url( add_query_arg( 'month', $prev, $base ) ); ?>">‹</a>
				<strong class="msb-cal-month"><?php echo esc_html( $months[ (int) $first->format( 'n' ) - 1 ] . ' ' . $first->format( 'Y' ) ); ?></strong>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'month', $next, $base ) ); ?>">›</a>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'month', current_time( 'Y-m' ), $base ) ); ?>">Heute</a>
				<form method="get" style="display:inline-block;margin-left:16px">
					<input type="hidden" name="page" value="msb-calendar">
					<input type="hidden" name="month" value="<?php echo esc_attr( $month ); ?>">
					<select name="item" onchange="this.form.submit()">
						<option value="">Alle Artikel</option>
						<?php foreach ( $items as $p ) : ?>
							<option value="<?php echo (int) $p->ID; ?>" <?php selected( $item, $p->ID ); ?>><?php echo esc_html( get_the_title( $p ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</form>
				<span class="msb-cal-legend">
					<?php foreach ( msb_statuses() as $k => $label ) : if ( 'cancelled' === $k ) { continue; } ?>
						<span class="msb-status msb-status--<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></span>
					<?php endforeach; ?>
				</span>
			</div>

			<div class="msb-admin-cal">
				<?php foreach ( array( 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So' ) as $wd ) : ?>
					<div class="msb-admin-cal__wd"><?php echo esc_html( $wd ); ?></div>
				<?php endforeach; ?>
				<?php
				$lead = ( (int) $first->format( 'w' ) + 6 ) % 7;
				for ( $i = 0; $i < $lead; $i++ ) {
					echo '<div class="msb-admin-cal__day is-empty"></div>';
				}
				$wds = msb_weekday_short();
				foreach ( msb_date_range( $from, $to ) as $d ) {
					$cls = 'msb-admin-cal__day' . ( $d === $today ? ' is-today' : '' ) . ( empty( $by_day[ $d ] ) ? '' : ' has-ev' );
					echo '<div class="' . esc_attr( $cls ) . '">';
					echo '<div class="msb-admin-cal__num"><span><span class="msb-admin-cal__wdname">' . esc_html( $wds[ msb_weekday( $d ) ] ) . '</span>' . (int) substr( $d, 8, 2 ) . '</span><a class="msb-admin-cal__add" title="Buchung an diesem Tag anlegen" href="' . esc_url( admin_url( 'admin.php?page=msb-booking&date=' . $d . ( $item ? '&item=' . $item : '' ) ) ) . '">+</a></div>';
					if ( ! empty( $by_day[ $d ] ) ) {
						foreach ( $by_day[ $d ] as $r ) {
							$mark = '';
							if ( $r['start_date'] === $d ) {
								$mark = '▶ ';
							} elseif ( $r['end_date'] === $d ) {
								$mark = '◀ ';
							}
							$title = $r['booking_number'] . ' · ' . $r['customer_name'] . ' · ' . msb_date( $r['start_date'] ) . '–' . msb_date( $r['end_date'] );
							echo '<a class="msb-admin-cal__ev msb-status--' . esc_attr( $r['status'] ) . '" title="' . esc_attr( $title ) . '" href="' . esc_url( admin_url( 'admin.php?page=msb-booking&id=' . (int) $r['booking_id'] ) ) . '">' . esc_html( $mark . $r['quantity'] . '× ' . $r['item_title'] ) . '<small>' . esc_html( $r['customer_name'] ) . '</small></a>';
						}
					}
					echo '</div>';
				}
				?>
			</div>
			<?php if ( ! $by_day ) : ?>
				<p class="msb-admin-cal__none">Keine Buchungen in diesem Monat.</p>
			<?php endif; ?>
			<p class="description">▶ = Abholung, ◀ = Rückgabe. Stornierte Buchungen werden nicht angezeigt. Auf dem Handy erscheinen nur Tage mit Buchungen.</p>
		</div>
		<?php
	}

	/**
	 * Inventarübersicht: freie Stückzahl je Artikel und Tag.
	 */
	public static function page_inventory() {
		$start = isset( $_GET['start'] ) ? msb_normalize_date( sanitize_text_field( wp_unslash( $_GET['start'] ) ) ) : ''; // phpcs:ignore
		$start = $start ? $start : msb_today();
		$days  = 21;
		$end   = msb_add_days( $start, $days - 1 );
		$range = msb_date_range( $start, $end );
		$items = get_posts(
			array(
				'post_type'      => MSB_Items::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
			)
		);
		$wd     = array( 'So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa' );
		$closed = array_map( 'intval', (array) msb_setting( 'closed_weekdays', array() ) );
		?>
		<div class="wrap msb-admin">
			<h1 class="wp-heading-inline">Inventar &amp; Verfügbarkeit</h1>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . MSB_Items::POST_TYPE ) ); ?>" class="page-title-action">Neuer Artikel</a>
			<hr class="wp-header-end">
			<div class="msb-cal-toolbar">
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=msb-inventory&start=' . msb_add_days( $start, -$days ) ) ); ?>">‹ früher</a>
				<form method="get" style="display:inline-block">
					<input type="hidden" name="page" value="msb-inventory">
					<input type="date" name="start" value="<?php echo esc_attr( $start ); ?>" onchange="this.form.submit()">
				</form>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=msb-inventory&start=' . msb_add_days( $start, $days ) ) ); ?>">später ›</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=msb-inventory' ) ); ?>">Heute</a>
			</div>
			<?php if ( ! $items ) : ?>
				<p>Noch keine Mietartikel vorhanden.</p>
			<?php else : ?>
			<div class="msb-inv-wrap">
				<table class="msb-inv">
					<thead>
						<tr>
							<th class="msb-inv__item">Artikel</th>
							<th>Bestand</th>
							<?php foreach ( $range as $d ) : $w = msb_weekday( $d ); ?>
								<th class="<?php echo in_array( $w, $closed, true ) ? 'is-closed' : ''; ?> <?php echo $d === msb_today() ? 'is-today' : ''; ?>"><?php echo esc_html( $wd[ $w ] ); ?><br><?php echo esc_html( substr( $d, 8, 2 ) . '.' . substr( $d, 5, 2 ) . '.' ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
					<?php
					foreach ( $items as $p ) :
						$data   = MSB_Items::data( $p->ID );
						$booked = MSB_Availability::booked_map( $p->ID, $start, $end );
						?>
						<tr>
							<td class="msb-inv__item"><a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>"><?php echo esc_html( $data['title'] ); ?></a><?php echo $data['bookable'] ? '' : ' <small>(nicht buchbar)</small>'; ?></td>
							<td><?php echo (int) $data['stock']; ?></td>
							<?php
							foreach ( $range as $d ) :
								$blocked = MSB_Items::is_blocked( $data, $d );
								$used    = isset( $booked[ $d ] ) ? $booked[ $d ] : 0;
								$free    = $blocked ? 0 : max( 0, $data['stock'] - $used );
								$cls     = $blocked ? 'is-blocked' : ( 0 === $free ? 'is-full' : ( $free < $data['stock'] ? 'is-partial' : 'is-free' ) );
								$title   = $blocked ? 'gesperrt' : sprintf( '%d frei, %d belegt', $free, $used );
								$link    = admin_url( 'admin.php?page=msb-bookings&item_id=' . $p->ID . '&from=' . $d . '&to=' . $d );
								?>
								<td class="msb-inv__cell <?php echo esc_attr( $cls ); ?>" title="<?php echo esc_attr( $title ); ?>"><a href="<?php echo esc_url( $link ); ?>"><?php echo $blocked ? '✕' : (int) $free; ?></a></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="description">Zahl = freie Stück an diesem Tag (inkl. Puffertage). Klick zeigt die Buchungen dieses Tages. ✕ = gesperrter Zeitraum. Grau hinterlegte Spalten = keine Abholung/Rückgabe.</p>
			<?php endif; ?>
		</div>
		<?php
	}
}
