<?php
/**
 * Bitte um eine Google-Bewertung (1.8.4).
 *
 * Nur mit Einwilligung: freiwilliger Haken im Buchungsformular (eine Bitte um Bewertung in einer Rechnungsmail
 * gilt als Werbung, BGH VI ZR 225/17). Mit Haken steht der Link in der Mail zur Rechnung nach der Rückgabe.
 * Der Link steht unter Einstellungen → Belege.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Reviews {

	const VERSION   = 1;
	const FIELD_KEY = 'review_ok';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'migrate' ) );
	}

	/**
	 * Feld im Buchungsformular. Text und Position lassen sich danach im Formular-Builder ändern.
	 */
	public static function field() {
		return array(
			'key'         => self::FIELD_KEY,
			'label'       => 'Ja, Sie dürfen mich nach der Rückgabe einmal per E-Mail um eine Bewertung bei Google bitten (freiwillig, jederzeit per Antwort auf die E-Mail widerrufbar).',
			'type'        => 'checkbox',
			'required'    => 0,
			'width'       => 'full',
			'options'     => '',
			'placeholder' => '',
			'profile'     => 0,
		);
	}

	/**
	 * Haken einmalig vor die AGB-Checkbox ins gespeicherte Buchungsformular setzen.
	 */
	public static function migrate() {
		if ( (int) get_option( 'msb_reviews_version' ) >= self::VERSION ) {
			return;
		}
		$fields = get_option( 'msb_form_fields' );
		if ( is_array( $fields ) && $fields ) {
			$keys = wp_list_pluck( $fields, 'key' );
			if ( ! in_array( self::FIELD_KEY, $keys, true ) ) {
				$pos = array_search( 'terms', $keys, true );
				array_splice( $fields, false === $pos ? count( $fields ) : (int) $pos, 0, array( self::field() ) );
				update_option( 'msb_form_fields', $fields );
			}
		}
		update_option( 'msb_reviews_version', self::VERSION );
	}

	public static function url() {
		return esc_url_raw( trim( (string) msb_setting( 'review_url', '' ) ) );
	}

	/**
	 * Hat der Kunde bei der Buchung den Haken gesetzt?
	 */
	public static function wanted( array $b ) {
		$d = isset( $b['form_data'] ) && is_array( $b['form_data'] ) ? $b['form_data'] : array();
		return isset( $d[ self::FIELD_KEY ]['value'] ) && 'Ja' === $d[ self::FIELD_KEY ]['value'];
	}

	/**
	 * Absatz für die Mail zur Rechnung. Leer ohne Link, ohne Haken und bei allen anderen Belegarten.
	 */
	public static function mail_block( array $doc, array $b ) {
		$url = self::url();
		if ( '' === $url || 'invoice' !== $doc['type'] || ! self::wanted( $b ) ) {
			return '';
		}
		$color = sanitize_hex_color( msb_setting( 'primary_color' ) );
		$color = $color ? $color : '#2f6b3a';
		return '<p style="margin:18px 0 10px">Sie hatten bei der Buchung angekreuzt, dass wir Sie um eine Bewertung bitten dürfen. Wenn Ihnen die Miete gefallen hat, freuen wir uns sehr über ein paar Worte bei Google. Das dauert etwa eine Minute und hilft uns als kleinem Mietservice aus Bernau sehr.</p>'
			. '<p style="text-align:center;margin:0 0 6px"><a href="' . esc_url( $url ) . '" style="display:inline-block;border:2px solid ' . esc_attr( $color ) . ';color:' . esc_attr( $color ) . ';text-decoration:none;padding:8px 18px;border-radius:4px;font-weight:bold">Bei Google bewerten</a></p>'
			. '<p style="text-align:center;margin:0 0 18px"><small style="color:#777">Möchten Sie keine solche Bitte mehr erhalten, antworten Sie einfach kurz auf diese E-Mail.</small></p>';
	}

	/**
	 * Absatz vor den Gruß der fertigen Mail setzen.
	 */
	public static function add_to_body( $body, array $doc, array $b ) {
		$block = self::mail_block( $doc, $b );
		if ( '' === $block ) {
			return $body;
		}
		$pos = strpos( $body, '<p>Viele Grüße' );
		return false === $pos ? $body . $block : substr( $body, 0, $pos ) . $block . substr( $body, $pos );
	}
}
