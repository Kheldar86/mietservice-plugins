<?php
/**
 * E-Mail zu einem Beleg (1.6.2): Übersicht mit Belegnummer, Buchung und Betrag, passender Satz je Belegart
 * und Knopf zum PDF. Die Texte stehen unter Einstellungen → Belege.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Document_Mails {

	const VERSION = 2;

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'migrate' ) );
	}

	/**
	 * Mailtexte aus 1.6.0/1.6.1, die nie geändert wurden, einmalig durch die neuen ersetzen.
	 */
	public static function migrate() {
		if ( (int) get_option( 'msb_doc_mail_version' ) >= self::VERSION ) {
			return;
		}
		$s       = get_option( 'msb_settings', array() );
		$s       = is_array( $s ) ? $s : array();
		$new     = msb_settings_defaults();
		$changed = false;
		foreach ( self::old_defaults() as $k => $old ) {
			if ( isset( $s[ $k ] ) && self::same( $s[ $k ], $old ) ) {
				$s[ $k ] = $new[ $k ];
				$changed = true;
			}
		}
		if ( $changed ) {
			update_option( 'msb_settings', $s );
		}
		update_option( 'msb_doc_mail_version', self::VERSION );
	}

	private static function same( $a, $b ) {
		return trim( str_replace( "\r\n", "\n", (string) $a ) ) === trim( $b );
	}

	private static function old_defaults() {
		return array(
			'doc_mail_subject_deposit' => 'Ihre Kautionsabrechnung {document_number} zu Buchung {booking_number}',
			'doc_mail_body_invoice'    => "Hallo {customer_name},\n\nim Anhang finden Sie Ihre {document_type} {document_number} zu Ihrer Buchung {booking_number} als PDF.\n\nVielen Dank, dass Sie bei uns gemietet haben.\n\nViele Grüße\nIhr Team von {site_name}",
			'doc_mail_body_deposit'    => "Hallo {customer_name},\n\nim Anhang finden Sie die Kautionsabrechnung {document_number} zu Ihrer Buchung {booking_number} als PDF. Darin steht, welcher Betrag aus der Kaution einbehalten wurde und warum.\n\nBei Fragen melden Sie sich gerne bei uns.\n\nViele Grüße\nIhr Team von {site_name}",
		);
	}

	/**
	 * Betreff und HTML-Text der Mail an den Kunden.
	 *
	 * @return array{0:string,1:string}
	 */
	public static function build( array $doc, array $b ) {
		$s    = msb_settings();
		$kind = 'deposit' === $doc['series'] ? 'deposit' : 'invoice';
		$map  = array(
			'{document_number}' => $doc['number'],
			'{document_type}'   => MSB_Documents::label( $doc ),
		);

		$subject = wp_strip_all_tags( MSB_Emails::replace( strtr( $s[ 'doc_mail_subject_' . $kind ], $map ), $b, false ) );

		$text = (string) $s[ 'doc_mail_body_' . $kind ];
		if ( false === strpos( $text, '{document_summary}' ) ) {
			// Eigene Vorlage ohne Platzhalter: Übersicht vor den Gruß setzen.
			$pos  = strpos( $text, 'Viele Grüße' );
			$text = false === $pos ? $text . "\n\n{document_summary}" : substr( $text, 0, $pos ) . "{document_summary}\n\n" . substr( $text, $pos );
		}
		$map['{document_note}'] = self::note( $doc );
		$body                   = wpautop( strtr( $text, array_map( 'esc_html', $map ) ) );
		$summary                = self::summary( $doc, $b );
		$body                   = str_replace( array( '<p>{document_summary}</p>', '{document_summary}' ), $summary, $body );
		$body                   = MSB_Emails::replace( $body, $b, true );
		if ( class_exists( 'MSB_Reviews' ) ) {
			$body = MSB_Reviews::add_to_body( $body, $doc, $b );
		}
		return array( $subject, $body );
	}

	/**
	 * Passender Satz je Belegart.
	 */
	public static function note( array $doc ) {
		$d = $doc['data'];
		if ( 'correction' === $doc['type'] ) {
			$ref = $doc['ref_id'] ? MSB_Documents::get( $doc['ref_id'] ) : null;
			$was = isset( $d['meta'][1][1] ) ? $d['meta'][1][1] : ( $ref ? $ref['number'] : '' );
			return 'Dieser Beleg hebt ' . ( 'deposit' === $doc['series'] ? 'die Kautionsabrechnung ' : 'die Rechnung ' ) . $was . ' vollständig auf. Sie müssen nichts weiter tun.';
		}
		if ( 'deposit' === $doc['type'] ) {
			return 'Bei der Rückgabe haben wir einen Schaden festgestellt. Was wir aus der Kaution einbehalten haben und warum, sehen Sie oben und im PDF.';
		}
		if ( 'cancel_invoice' === $doc['type'] ) {
			return 'Schade, dass es diesmal nicht geklappt hat. Wir freuen uns, wenn Sie bald wieder bei uns buchen.';
		}
		return 'Vielen Dank, dass Sie bei uns gemietet haben. Wir freuen uns auf Ihre nächste Buchung.';
	}

	/**
	 * Kasten mit den wichtigsten Angaben und Knopf zum PDF.
	 */
	public static function summary( array $doc, array $b ) {
		$d     = $doc['data'];
		$rows  = array(
			array( MSB_Documents::label( $doc ), $doc['number'] ),
			array( 'Datum', mysql2date( 'd.m.Y', $doc['issued_at'] ) ),
			array( 'Buchung', $b['booking_number'] ),
		);
		if ( isset( $d['meta'][1] ) ) {
			$rows[] = array( $d['meta'][1][0], $d['meta'][1][1] );
		}
		if ( 'deposit' === $doc['type'] ) {
			foreach ( (array) $d['lines'] as $l ) {
				$rows[] = array( $l[0], msb_price( abs( (float) $l[2] ) ) );
				if ( (float) $l[2] < 0 && '' !== (string) $l[1] ) {
					$rows[] = array( 'Grund', $l[1] );
				}
			}
		}
		$rows[] = array( isset( $d['total_label'] ) ? $d['total_label'] : 'Betrag', msb_price( isset( $d['total'] ) ? $d['total'] : $doc['amount'] ), true );
		foreach ( (array) ( isset( $d['notes'] ) ? $d['notes'] : array() ) as $n ) {
			if ( 0 === strpos( $n, 'Betrag' ) || 0 === strpos( $n, 'Bereits' ) || 0 === strpos( $n, 'Offener' ) ) {
				$rows[] = array( 'Zahlung', $n );
			}
		}

		$label = 'padding:7px 12px;color:#666;font-size:14px;vertical-align:top;white-space:nowrap';
		$value = 'padding:7px 12px;font-size:14px;vertical-align:top;text-align:right';
		$html  = '<table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;margin:18px 0;background:#f4f7f3;border-radius:6px">';
		foreach ( $rows as $i => $r ) {
			$line  = $i ? 'border-top:1px solid #e1e8df;' : '';
			$bold  = ! empty( $r[2] );
			$html .= '<tr><td style="' . $line . $label . '">' . esc_html( $r[0] ) . '</td><td style="' . $line . $value . ( $bold ? ';font-weight:bold;font-size:16px' : '' ) . '">' . esc_html( $r[1] ) . '</td></tr>';
		}
		$html .= '</table>';

		$color = sanitize_hex_color( msb_setting( 'primary_color' ) );
		$color = $color ? $color : '#2f6b3a';
		$html .= '<p style="text-align:center;margin:0 0 18px"><a href="' . esc_url( MSB_Documents::customer_url( $doc, $b ) ) . '" style="display:inline-block;background:' . esc_attr( $color ) . ';color:#fff;text-decoration:none;padding:10px 20px;border-radius:4px;font-weight:bold">PDF ansehen</a><br><small style="color:#777">Das PDF hängt außerdem an dieser E-Mail.</small></p>';
		return $html;
	}
}
