<?php
/**
 * Backend: Kundenangaben einer Buchung bearbeiten (Administratoren und Gesellschafter).
 *
 * Eine Buchung speichert beim Anlegen eine Kopie der Formularangaben. Hier lässt sich diese Kopie
 * korrigieren, auf Wunsch auch im Kundenstamm.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Admin_Booking_Customer {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'handle' ), 9 );
	}

	/**
	 * Bearbeitbare Felder: alle Eingabefelder außer Checkboxen (AGB, Datenschutz bleiben, wie der Kunde sie bestätigt hat).
	 * Pflichtfelder werden im Backend nicht erzwungen.
	 */
	private static function fields() {
		$out = array();
		foreach ( MSB_Form_Fields::get() as $f ) {
			if ( in_array( $f['type'], array( 'heading', 'paragraph', 'checkbox' ), true ) ) {
				continue;
			}
			$f['required'] = 0;
			$out[]         = $f;
		}
		return $out;
	}

	/**
	 * Formular unter den Kundenangaben der Buchung.
	 */
	public static function box( array $b ) {
		$values = array();
		foreach ( (array) $b['form_data'] as $key => $row ) {
			$values[ $key ] = isset( $row['value'] ) ? $row['value'] : '';
		}
		if ( class_exists( 'MSB_Terms_Confirm' ) ) {
			MSB_Terms_Confirm::admin_status( $b );
		}
		echo '<details class="msb-edit-customer"><summary class="button">Kundenangaben bearbeiten</summary>';
		echo '<form method="post" class="msb-form">';
		wp_nonce_field( 'msb_booking_' . (int) $b['id'] );
		echo '<input type="hidden" name="id" value="' . (int) $b['id'] . '"><input type="hidden" name="msb_booking_action" value="customer">';
		echo '<div class="msb-form__grid">' . MSB_Form_Fields::render( $values, self::fields() ) . '</div>'; // phpcs:ignore
		if ( $b['customer_id'] ) {
			echo '<p><label><input type="checkbox" name="to_customer" value="1" checked> Auch im Kundenstamm übernehmen</label></p>';
		}
		echo '<p><button type="submit" class="button button-primary">Kundenangaben speichern</button></p>';
		echo '</form></details>';
	}

	public static function handle() {
		// phpcs:disable WordPress.Security.NonceVerification -- Nonce wird unten geprüft.
		if ( empty( $_POST['msb_booking_action'] ) || 'customer' !== $_POST['msb_booking_action'] || ! current_user_can( msb_capability() ) ) {
			return;
		}
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		check_admin_referer( 'msb_booking_' . $id );
		$url     = admin_url( 'admin.php?page=msb-booking&id=' . $id );
		$booking = MSB_Bookings::get( $id );
		if ( ! $booking ) {
			self::notice( 'Buchung nicht gefunden.', 'error' );
			wp_safe_redirect( admin_url( 'admin.php?page=msb-bookings' ) );
			exit;
		}
		$input                         = isset( $_POST['msb_field'] ) && is_array( $_POST['msb_field'] ) ? wp_unslash( $_POST['msb_field'] ) : array(); // phpcs:ignore
		list( $values, $errors, $data ) = MSB_Form_Fields::validate( $input, self::fields() );
		$to_customer                   = ! empty( $_POST['to_customer'] );
		// phpcs:enable
		if ( $errors ) {
			self::notice( implode( ' ', array_unique( $errors ) ), 'error' );
			wp_safe_redirect( $url );
			exit;
		}

		// Nicht bearbeitbare Angaben (z. B. AGB bestätigt) bleiben erhalten.
		$form_data                    = array_merge( (array) $booking['form_data'], $data );
		list( $name, $email, $phone ) = MSB_Form_Fields::customer_from_data( $form_data );

		global $wpdb;
		$wpdb->update(
			MSB_Install::table( 'bookings' ),
			array(
				'form_data'      => wp_json_encode( $form_data ),
				'customer_name'  => $name,
				'customer_email' => $email,
				'customer_phone' => $phone,
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		$msg = 'Kundenangaben der Buchung gespeichert.';
		if ( $to_customer && $booking['customer_id'] ) {
			$customer = MSB_Customers::get( $booking['customer_id'] );
			if ( $customer ) {
				MSB_Customers::save( $values, (int) $customer['id'], (int) $customer['user_id'] );
				$msg = 'Kundenangaben in der Buchung und im Kundenstamm gespeichert.';
			}
		}
		do_action( 'msb_booking_customer_updated', $id );
		self::notice( $msg, 'success' );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Gleicher Hinweis-Mechanismus wie MSB_Admin_Bookings::redirect().
	 */
	private static function notice( $message, $type ) {
		set_transient( 'msb_notice_' . get_current_user_id(), array( $message, $type ), 60 );
	}
}
