<?php
/**
 * Frei konfigurierbares Buchungsformular.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Form_Fields {

	public static function types() {
		return array(
			'text'      => 'Textzeile',
			'email'     => 'E-Mail',
			'tel'       => 'Telefon',
			'number'    => 'Zahl',
			'date'      => 'Datum',
			'textarea'  => 'Mehrzeiliger Text',
			'select'    => 'Auswahlliste',
			'radio'     => 'Optionsfelder (eine Auswahl)',
			'checkbox'  => 'Checkbox (z. B. AGB)',
			'heading'   => 'Zwischenüberschrift',
			'paragraph' => 'Hinweistext',
		);
	}

	public static function defaults() {
		$f = function ( $key, $label, $type, $required = 0, $width = 'full', $options = '', $placeholder = '', $profile = null ) {
			$row            = compact( 'key', 'label', 'type', 'required', 'width', 'options', 'placeholder' );
			$row['profile'] = null === $profile ? MSB_Form_Fields::default_profile_flag( $row ) : (int) $profile;
			return $row;
		};
		return array(
			$f( 'heading_contact', 'Ihre Kontaktdaten', 'heading' ),
			$f( 'first_name', 'Vorname', 'text', 1, 'half' ),
			$f( 'last_name', 'Nachname', 'text', 1, 'half' ),
			$f( 'email', 'E-Mail-Adresse', 'email', 1, 'half' ),
			$f( 'phone', 'Telefonnummer', 'tel', 1, 'half' ),
			$f( 'street', 'Straße und Hausnummer', 'text', 1, 'full' ),
			$f( 'zip', 'PLZ', 'text', 1, 'half' ),
			$f( 'city', 'Ort', 'text', 1, 'half' ),
			$f( 'heading_pickup', 'Abholung', 'heading' ),
			$f( 'pickup_time', 'Gewünschte Abholzeit', 'select', 0, 'half', "Vormittag (9–12 Uhr)\nNachmittag (12–17 Uhr)\nAbend (17–19 Uhr)", '', 0 ),
			$f( 'message', 'Anmerkungen', 'textarea', 0, 'full', '', 'Fragen oder Wünsche zu Ihrer Buchung' ),
			$f( 'terms', 'Ich habe die <a href="/agb" target="_blank">AGB / Mietbedingungen</a> gelesen und akzeptiere sie.', 'checkbox', 1 ),
			$f( 'privacy', 'Ich habe die <a href="/datenschutz" target="_blank">Datenschutzerklärung</a> zur Kenntnis genommen.', 'checkbox', 1 ),
		);
	}

	/**
	 * Standard für "Kundenstammdaten": Kontaktfelder ja, buchungsbezogene Felder nein.
	 */
	public static function default_profile_flag( array $f ) {
		if ( in_array( $f['key'], array( 'message', 'pickup_time', 'terms', 'privacy' ), true ) ) {
			return 0;
		}
		return in_array( $f['type'], array( 'text', 'email', 'tel', 'number', 'date' ), true ) ? 1 : 0;
	}

	public static function get() {
		$fields = get_option( 'msb_form_fields' );
		if ( ! is_array( $fields ) || empty( $fields ) ) {
			$fields = self::defaults();
		}
		foreach ( $fields as &$f ) {
			if ( ! isset( $f['profile'] ) ) {
				$f['profile'] = self::default_profile_flag( $f );
			}
		}
		unset( $f );
		return apply_filters( 'msb_form_fields', $fields );
	}

	/**
	 * Nur die Felder, die zu den Kundenstammdaten gehören (für Konto & Registrierung).
	 */
	public static function profile_fields() {
		return array_values(
			array_filter(
				self::get(),
				function ( $f ) {
					return ! empty( $f['profile'] ) && ! in_array( $f['type'], array( 'heading', 'paragraph', 'checkbox' ), true );
				}
			)
		);
	}

	/**
	 * Bereinigt die im Formular-Builder gespeicherten Felddefinitionen.
	 */
	public static function sanitize_definitions( $raw ) {
		$types = self::types();
		$out   = array();
		$used  = array();

		foreach ( (array) $raw as $row ) {
			$label = isset( $row['label'] ) ? wp_kses_post( wp_unslash( $row['label'] ) ) : '';
			$type  = isset( $row['type'] ) && isset( $types[ $row['type'] ] ) ? $row['type'] : 'text';
			if ( '' === trim( $label ) ) {
				continue;
			}
			$key = isset( $row['key'] ) ? sanitize_key( $row['key'] ) : '';
			if ( '' === $key ) {
				$key = sanitize_key( str_replace( '-', '_', sanitize_title( wp_strip_all_tags( $label ) ) ) );
			}
			if ( '' === $key ) {
				$key = 'field';
			}
			$base = $key;
			$n    = 2;
			while ( isset( $used[ $key ] ) ) {
				$key = $base . '_' . $n++;
			}
			$used[ $key ] = true;

			$out[] = array(
				'key'         => $key,
				'label'       => $label,
				'type'        => $type,
				'required'    => ! empty( $row['required'] ) ? 1 : 0,
				'width'       => isset( $row['width'] ) && 'half' === $row['width'] ? 'half' : 'full',
				'options'     => isset( $row['options'] ) ? self::sanitize_options( $row['options'] ) : '',
				'placeholder' => isset( $row['placeholder'] ) ? sanitize_text_field( wp_unslash( $row['placeholder'] ) ) : '',
				'profile'     => ! empty( $row['profile'] ) && ! in_array( $type, array( 'heading', 'paragraph', 'checkbox' ), true ) ? 1 : 0,
			);
		}
		return $out;
	}

	/**
	 * Auswahlmöglichkeiten: eine pro Zeile, ein Semikolon trennt ebenfalls.
	 */
	public static function sanitize_options( $raw ) {
		$text = sanitize_textarea_field( wp_unslash( (string) $raw ) );
		return implode( "\n", msb_lines( str_replace( ';', "\n", $text ) ) );
	}

	private static function is_input( $field ) {
		return ! in_array( $field['type'], array( 'heading', 'paragraph' ), true );
	}

	/**
	 * Gibt die Formularfelder als HTML aus.
	 */
	public static function render( array $values = array(), $fields = null ) {
		$html = '';
		foreach ( null === $fields ? self::get() : $fields as $f ) {
			$key   = $f['key'];
			$name  = 'msb_field[' . $key . ']';
			$id    = 'msb-f-' . $key;
			$value = isset( $values[ $key ] ) ? $values[ $key ] : '';
			$req   = $f['required'] ? ' required' : '';
			$star  = $f['required'] ? ' <span class="msb-req" aria-hidden="true">*</span>' : '';
			$ph    = $f['placeholder'] ? ' placeholder="' . esc_attr( $f['placeholder'] ) . '"' : '';
			$width = 'half' === $f['width'] ? 'msb-field--half' : 'msb-field--full';
			$label = wp_kses_post( $f['label'] );

			switch ( $f['type'] ) {
				case 'heading':
					$html .= '<h3 class="msb-form__heading msb-field--full">' . $label . '</h3>';
					continue 2;
				case 'paragraph':
					$html .= '<div class="msb-form__text msb-field--full">' . wpautop( $label ) . '</div>';
					continue 2;
			}

			$html .= '<div class="msb-field ' . esc_attr( $width ) . ' msb-field--' . esc_attr( $f['type'] ) . '" data-key="' . esc_attr( $key ) . '">';

			switch ( $f['type'] ) {
				case 'textarea':
					$html .= '<label for="' . esc_attr( $id ) . '">' . $label . $star . '</label>';
					$html .= '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="4"' . $req . $ph . '>' . esc_textarea( $value ) . '</textarea>';
					break;

				case 'select':
					$html .= '<label for="' . esc_attr( $id ) . '">' . $label . $star . '</label>';
					$html .= '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $req . '>';
					$html .= '<option value="">' . esc_html( $f['placeholder'] ? $f['placeholder'] : 'Bitte wählen …' ) . '</option>';
					foreach ( msb_lines( $f['options'] ) as $opt ) {
						$html .= '<option value="' . esc_attr( $opt ) . '"' . selected( $value, $opt, false ) . '>' . esc_html( $opt ) . '</option>';
					}
					$html .= '</select>';
					break;

				case 'radio':
					$html .= '<fieldset><legend>' . $label . $star . '</legend>';
					foreach ( msb_lines( $f['options'] ) as $i => $opt ) {
						$html .= '<label class="msb-choice"><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $opt ) . '"' . checked( $value, $opt, false ) . ( $f['required'] && 0 === $i ? ' required' : '' ) . '> <span>' . esc_html( $opt ) . '</span></label>';
					}
					$html .= '</fieldset>';
					break;

				case 'checkbox':
					$html .= '<label class="msb-choice"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( ! empty( $value ), true, false ) . $req . '> <span>' . $label . $star . '</span></label>';
					break;

				default:
					$type  = in_array( $f['type'], array( 'email', 'tel', 'number', 'date' ), true ) ? $f['type'] : 'text';
					$auto  = array(
						'first_name' => 'given-name',
						'last_name'  => 'family-name',
						'email'      => 'email',
						'phone'      => 'tel',
						'street'     => 'street-address',
						'zip'        => 'postal-code',
						'city'       => 'address-level2',
					);
					$ac    = isset( $auto[ $key ] ) ? ' autocomplete="' . esc_attr( $auto[ $key ] ) . '"' : '';
					$html .= '<label for="' . esc_attr( $id ) . '">' . $label . $star . '</label>';
					$html .= '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $req . $ph . $ac . '>';
			}

			$html .= '<span class="msb-field__error" role="alert"></span></div>';
		}
		return $html;
	}

	/**
	 * Validiert die übermittelten Werte.
	 *
	 * @return array( values, errors, data ) – data enthält Label/Wert-Paare zur Speicherung.
	 */
	public static function validate( $input, $fields = null ) {
		$input  = is_array( $input ) ? $input : array();
		$values = array();
		$errors = array();
		$data   = array();

		foreach ( null === $fields ? self::get() : $fields as $f ) {
			if ( ! self::is_input( $f ) ) {
				continue;
			}
			$key = $f['key'];
			$raw = isset( $input[ $key ] ) ? wp_unslash( $input[ $key ] ) : '';
			$raw = is_array( $raw ) ? '' : (string) $raw;

			switch ( $f['type'] ) {
				case 'email':
					$val = sanitize_email( $raw );
					if ( '' !== trim( $raw ) && ! is_email( $val ) ) {
						$errors[ $key ] = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
					}
					break;
				case 'textarea':
					$val = sanitize_textarea_field( $raw );
					break;
				case 'checkbox':
					$val = '' !== $raw ? '1' : '';
					break;
				case 'number':
					$val = '' === trim( $raw ) ? '' : (string) ( 0 + str_replace( ',', '.', preg_replace( '/[^0-9,.\-]/', '', $raw ) ) );
					break;
				case 'select':
				case 'radio':
					$val = sanitize_text_field( $raw );
					if ( '' !== $val && ! in_array( $val, msb_lines( $f['options'] ), true ) ) {
						$val            = '';
						$errors[ $key ] = 'Bitte wählen Sie eine gültige Option.';
					}
					break;
				default:
					$val = sanitize_text_field( $raw );
			}

			if ( $f['required'] && '' === $val && ! isset( $errors[ $key ] ) ) {
				$errors[ $key ] = 'checkbox' === $f['type'] ? 'Bitte bestätigen Sie dieses Feld.' : 'Dieses Feld ist erforderlich.';
			}

			$values[ $key ] = $val;
			$data[ $key ]   = array(
				'label' => wp_strip_all_tags( $f['label'] ),
				'type'  => $f['type'],
				'value' => 'checkbox' === $f['type'] ? ( $val ? 'Ja' : 'Nein' ) : $val,
			);
		}

		return array( $values, $errors, $data );
	}

	/**
	 * Ermittelt Name, E-Mail und Telefon aus den Formulardaten.
	 */
	public static function customer_from_data( array $data ) {
		$name  = '';
		$email = '';
		$phone = '';

		$first = isset( $data['first_name']['value'] ) ? $data['first_name']['value'] : '';
		$last  = isset( $data['last_name']['value'] ) ? $data['last_name']['value'] : '';
		$name  = trim( $first . ' ' . $last );
		if ( '' === $name && isset( $data['name']['value'] ) ) {
			$name = $data['name']['value'];
		}

		foreach ( $data as $row ) {
			if ( ! $email && 'email' === $row['type'] && is_email( $row['value'] ) ) {
				$email = $row['value'];
			}
			if ( ! $phone && 'tel' === $row['type'] ) {
				$phone = $row['value'];
			}
		}
		if ( '' === $name ) {
			$name = $email;
		}
		return array( $name, $email, $phone );
	}

	/**
	 * HTML-Tabelle der Kundenangaben (für E-Mails und Backend).
	 */
	public static function data_table( $data ) {
		if ( empty( $data ) || ! is_array( $data ) ) {
			return '';
		}
		$html = '<table class="msb-data" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;table-layout:fixed">';
		foreach ( $data as $row ) {
			if ( '' === (string) $row['value'] ) {
				continue;
			}
			$html .= '<tr><th align="left" valign="top" style="width:42%;text-align:left;padding:4px 12px 4px 0;vertical-align:top">' . esc_html( $row['label'] ) . '</th><td style="padding:4px 0;vertical-align:top;overflow-wrap:anywhere;word-wrap:break-word;word-break:break-word">' . nl2br( esc_html( $row['value'] ) ) . '</td></tr>';
		}
		return $html . '</table>';
	}
}
