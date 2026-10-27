<?php
/**
 * Belege (1.6.0): Mietrechnung, Stornorechnung und Rechnungskorrektur (Nummernkreis RE) sowie
 * Kautionsabrechnung (Nummernkreis KAU, echter Schadenersatz, keine Rechnung).
 *
 * Ein ausgestellter Beleg wird nie geändert oder gelöscht. Fehler werden über eine Korrektur aufgehoben.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Documents {

	const PAGE = 'msb-documents';

	public static function init() {
		add_action( 'msb_booking_status_changed', array( __CLASS__, 'on_status_change' ), 20, 3 );
		add_action( 'msb_booking_cancelled', array( __CLASS__, 'on_cancelled' ), 10, 3 );
		add_action( 'msb_security_captured', array( __CLASS__, 'on_security_captured' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'customer_download' ), 4 );
		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'menu' ), 10 );
			add_action( 'admin_init', array( __CLASS__, 'handle_admin' ), 8 );
		}
	}

	/* ------------------------------------------------------------------
	 * Grundlagen
	 * ------------------------------------------------------------------ */

	public static function type_labels() {
		return array(
			'invoice'        => 'Rechnung',
			'cancel_invoice' => 'Stornorechnung',
			'correction'     => 'Rechnungskorrektur',
			'deposit'        => 'Kautionsabrechnung',
		);
	}

	public static function label( array $doc ) {
		if ( 'correction' === $doc['type'] && 'deposit' === $doc['series'] ) {
			return 'Korrektur Kautionsabrechnung';
		}
		$l = self::type_labels();
		return isset( $l[ $doc['type'] ] ) ? $l[ $doc['type'] ] : $doc['type'];
	}

	private static function table() {
		return MSB_Install::table( 'documents' );
	}

	public static function prepare( array $row ) {
		$row['data'] = json_decode( (string) $row['data'], true );
		if ( ! is_array( $row['data'] ) ) {
			$row['data'] = array();
		}
		return $row;
	}

	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
		return $row ? self::prepare( $row ) : null;
	}

	public static function for_booking( $booking_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE booking_id = %d ORDER BY id', $booking_id ), ARRAY_A ); // phpcs:ignore
		return array_map( array( __CLASS__, 'prepare' ), (array) $rows );
	}

	/**
	 * Gültige (nicht korrigierte) Mietrechnung der Buchung.
	 */
	public static function active_invoice( $booking_id ) {
		foreach ( self::for_booking( $booking_id ) as $d ) {
			if ( 'invoice' === $d['type'] && 'issued' === $d['status'] ) {
				return $d;
			}
		}
		return null;
	}

	public static function is_test( array $doc ) {
		return ! empty( $doc['data']['test'] ) || 0 === strpos( (string) $doc['number'], 'TEST-' );
	}

	private static function prefix( $series ) {
		$p = trim( (string) msb_setting( 'deposit' === $series ? 'doc_prefix_deposit' : 'doc_prefix_invoice' ) );
		return '' !== $p ? $p : ( 'deposit' === $series ? 'KAU' : 'RE' );
	}

	/* ------------------------------------------------------------------
	 * Angaben für die Belege
	 * ------------------------------------------------------------------ */

	private static function issuer() {
		$s       = msb_settings();
		$contact = trim( (string) $s['doc_issuer_contact'] );
		if ( '' === $contact ) {
			$host    = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
			$contact = trim( $s['from_email'] . ( $host ? ' · ' . $host : '' ), ' ·' );
		}
		return array(
			'name'    => $s['doc_issuer_name'],
			'address' => msb_lines( $s['doc_issuer_address'] ),
			'contact' => $contact,
			'tax'     => $s['doc_show_tax_number'] && '' !== trim( (string) $s['doc_tax_number'] ) ? trim( (string) $s['doc_tax_number'] ) : '',
			'footer'  => trim( (string) $s['doc_footer'] ),
		);
	}

	/**
	 * Logo aus der Mediathek: Auswahl unter Einstellungen → Belege, sonst das Logo des Themes oder das Website-Icon.
	 */
	public static function logo_id() {
		$id = (int) msb_setting( 'doc_logo_id' );
		if ( ! $id ) {
			$id = (int) get_theme_mod( 'custom_logo' );
		}
		if ( ! $id ) {
			$id = (int) get_option( 'site_icon' );
		}
		return $id && wp_attachment_is_image( $id ) ? $id : 0;
	}

	public static function logo_url( $size = 'medium' ) {
		$id = self::logo_id();
		return $id ? (string) wp_get_attachment_image_url( $id, $size ) : '';
	}

	/**
	 * Logo als JPEG für das PDF: auf weißen Hintergrund gesetzt, weißer Rand abgeschnitten, höchstens 800 px breit.
	 */
	private static function logo_jpeg() {
		$id   = self::logo_id();
		$file = $id ? get_attached_file( $id ) : '';
		if ( ! $file || ! file_exists( $file ) ) {
			return '';
		}
		$out = self::dir() . '/logo-' . $id . '-' . filemtime( $file ) . '.jpg';
		if ( file_exists( $out ) ) {
			return $out;
		}
		$info = @getimagesize( $file ); // phpcs:ignore
		if ( ! $info ) {
			return '';
		}
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return IMAGETYPE_JPEG === $info[2] ? $file : '';
		}
		$src = @imagecreatefromstring( (string) file_get_contents( $file ) ); // phpcs:ignore
		if ( ! $src ) {
			return IMAGETYPE_JPEG === $info[2] ? $file : '';
		}
		$w   = imagesx( $src );
		$h   = imagesy( $src );
		$img = imagecreatetruecolor( $w, $h );
		imagefill( $img, 0, 0, imagecolorallocate( $img, 255, 255, 255 ) );
		imagealphablending( $img, true );
		imagecopy( $img, $src, 0, 0, 0, 0, $w, $h );
		imagedestroy( $src );
		if ( function_exists( 'imagecropauto' ) ) {
			$crop = imagecropauto( $img, IMG_CROP_THRESHOLD, 0.15, 0xFFFFFF );
			if ( $crop ) {
				imagedestroy( $img );
				$img = $crop;
			}
		}
		$w = imagesx( $img );
		if ( $w > 800 ) {
			$small = imagescale( $img, 800 );
			if ( $small ) {
				imagedestroy( $img );
				$img = $small;
			}
		}
		// Fast weiße Pixel (JPEG-Rauschen, Kanten) rein weiß setzen, damit kein grauer Kasten entsteht.
		$white = imagecolorallocate( $img, 255, 255, 255 );
		for ( $x = imagesx( $img ) - 1; $x >= 0; $x-- ) {
			for ( $y = imagesy( $img ) - 1; $y >= 0; $y-- ) {
				$c = imagecolorat( $img, $x, $y );
				if ( ( $c >> 16 & 255 ) > 235 && ( $c >> 8 & 255 ) > 235 && ( $c & 255 ) > 235 ) {
					imagesetpixel( $img, $x, $y, $white );
				}
			}
		}
		foreach ( glob( self::dir() . '/logo-*.jpg' ) as $old ) {
			@unlink( $old ); // phpcs:ignore
		}
		$ok = imagejpeg( $img, $out, 90 );
		imagedestroy( $img );
		return $ok ? $out : '';
	}

	private static function field( array $b, $key, $label_pattern ) {
		$fd = (array) $b['form_data'];
		if ( isset( $fd[ $key ]['value'] ) && '' !== trim( (string) $fd[ $key ]['value'] ) ) {
			return trim( (string) $fd[ $key ]['value'] );
		}
		foreach ( $fd as $row ) {
			if ( isset( $row['label'], $row['value'] ) && preg_match( $label_pattern, wp_strip_all_tags( $row['label'] ) ) ) {
				return trim( (string) $row['value'] );
			}
		}
		return '';
	}

	private static function recipient( array $b ) {
		$lines  = array( $b['customer_name'] );
		$street = self::field( $b, 'street', '/^stra(ß|ss)e/iu' );
		$zip    = self::field( $b, 'zip', '/^plz/iu' );
		$city   = self::field( $b, 'city', '/^(ort|stadt)$/iu' );
		if ( $street ) {
			$lines[] = $street;
		}
		if ( $zip || $city ) {
			$lines[] = trim( $zip . ' ' . $city );
		}
		return $lines;
	}

	private static function period( array $b ) {
		$start = '';
		$end   = '';
		foreach ( $b['items'] as $l ) {
			$start = $start ? min( $start, $l['start_date'] ) : $l['start_date'];
			$end   = max( $end, $l['end_date'] );
		}
		return $start === $end ? msb_date( $start ) : msb_date( $start ) . ' – ' . msb_date( $end );
	}

	private static function provider_text( $provider ) {
		if ( 'stripe' === $provider ) {
			return 'via Stripe (Karte, Apple Pay oder Google Pay)';
		}
		if ( 'paypal' === $provider ) {
			return 'via PayPal';
		}
		return 'bar';
	}

	/**
	 * Zahlungsvermerk: „Betrag dankend bar erhalten am …“ bzw. „bereits bezahlt via Stripe am …“ oder offener Betrag.
	 */
	public static function payment_note( array $b, $amount ) {
		$groups = array();
		$paid   = 0.0;
		foreach ( MSB_Payments::for_booking( $b['id'] ) as $p ) {
			if ( 'security' === $p['type'] || ! in_array( $p['status'], array( 'paid', 'refunded' ), true ) ) {
				continue;
			}
			$net = (float) $p['amount'] - ( isset( $p['data']['refunded'] ) ? (float) $p['data']['refunded'] : ( 'refunded' === $p['status'] ? (float) $p['amount'] : 0 ) );
			if ( $net <= 0 ) {
				continue;
			}
			$prov = in_array( $p['provider'], array( 'stripe', 'paypal' ), true ) ? $p['provider'] : 'cash';
			$date = 'manual' === $p['provider'] ? $p['created_at'] : $p['updated_at'];
			if ( ! isset( $groups[ $prov ] ) ) {
				$groups[ $prov ] = array( 'amount' => 0.0, 'date' => $date );
			}
			$groups[ $prov ]['amount'] += $net;
			$groups[ $prov ]['date']    = max( $groups[ $prov ]['date'], $date );
			$paid                      += $net;
		}
		$amount = round( (float) $amount, 2 );
		$paid   = round( $paid, 2 );
		if ( $amount <= 0 ) {
			return '';
		}
		if ( $paid + 0.005 >= $amount && $groups ) {
			if ( 1 === count( $groups ) ) {
				$prov = key( $groups );
				$date = mysql2date( 'd.m.Y', $groups[ $prov ]['date'] );
				return 'cash' === $prov ? 'Betrag dankend bar erhalten am ' . $date . '.' : 'Betrag bereits bezahlt ' . self::provider_text( $prov ) . ' am ' . $date . '.';
			}
			$parts = array();
			foreach ( $groups as $prov => $g ) {
				$parts[] = msb_price( $g['amount'] ) . ' ' . self::provider_text( 'cash' === $prov ? '' : $prov ) . ' am ' . mysql2date( 'd.m.Y', $g['date'] );
			}
			return 'Betrag bereits bezahlt: ' . implode( ', ', $parts ) . '.';
		}
		if ( $paid > 0 ) {
			return 'Bereits bezahlt: ' . msb_price( $paid ) . '. Offener Betrag: ' . msb_price( $amount - $paid ) . '.';
		}
		return 'Offener Betrag: ' . msb_price( $amount ) . '.';
	}

	/* ------------------------------------------------------------------
	 * Belege ausstellen
	 * ------------------------------------------------------------------ */

	/**
	 * Nummer vergeben, Beleg speichern und PDF erzeugen.
	 *
	 * @return int|WP_Error Beleg-ID
	 */
	private static function issue( array $b, $type, $series, $amount, array $data, $ref_id = 0 ) {
		global $wpdb;
		$year = (int) current_time( 'Y' );
		$test = ! empty( $b['is_test'] ); // Testbuchung (1.7.2): eigener Nummernkreis TEST, die Reihen RE und KAU bleiben lückenlos.
		$seq  = msb_next_sequence( $test ? 'doc_test' : ( 'deposit' === $series ? 'doc_deposit' : 'doc_invoice' ), $year );
		if ( ! $seq ) {
			return new WP_Error( 'msb_doc', 'Belegnummer konnte nicht vergeben werden.' );
		}
		$number = ( $test ? 'TEST' : self::prefix( $series ) ) . '-' . $year . '-' . str_pad( (string) $seq, 4, '0', STR_PAD_LEFT );
		if ( $test ) {
			$data['test']    = true;
			$data['notes']   = isset( $data['notes'] ) ? (array) $data['notes'] : array();
			$data['notes'][] = 'Testbeleg: Dieser Beleg gehört zu einer Testbuchung und ist keine Rechnung im steuerlichen Sinn.';
		}
		$now    = current_time( 'mysql' );

		$data['issuer']         = self::issuer();
		$data['recipient']      = self::recipient( $b );
		$data['booking_number'] = $b['booking_number'];
		$data['vat_note']       = 'deposit' === $series ? '' : (string) msb_setting( 'doc_vat_note' );

		$wpdb->insert(
			self::table(),
			array(
				'booking_id' => (int) $b['id'],
				'type'       => $type,
				'series'     => $series,
				'number'     => $number,
				'issued_at'  => $now,
				'amount'     => round( (float) $amount, 2 ),
				'status'     => 'issued',
				'ref_id'     => (int) $ref_id,
				'data'       => wp_json_encode( $data ),
				'file'       => '',
				'created_by' => get_current_user_id(),
				'created_at' => $now,
			)
		);
		$id = (int) $wpdb->insert_id;
		if ( ! $id ) {
			return new WP_Error( 'msb_doc', 'Beleg konnte nicht gespeichert werden.' );
		}
		self::file( $id );
		do_action( 'msb_document_issued', $id, $b['id'] );
		return $id;
	}

	/**
	 * Mietrechnung nach der Rückgabe (eine pro Buchung).
	 */
	public static function create_invoice( $booking_id ) {
		$b = MSB_Bookings::get( $booking_id );
		if ( ! $b || 'cancelled' === $b['status'] || ! $b['items'] ) {
			return new WP_Error( 'msb_doc', 'Für diese Buchung kann keine Rechnung erstellt werden.' );
		}
		if ( self::active_invoice( $booking_id ) ) {
			return new WP_Error( 'msb_doc', 'Für diese Buchung gibt es bereits eine Rechnung. Bitte zuerst korrigieren.' );
		}
		$lines = array();
		$sum   = 0.0;
		foreach ( $b['items'] as $l ) {
			$sub = ( $l['start_date'] === $l['end_date'] ? msb_date( $l['start_date'] ) : msb_date( $l['start_date'] ) . ' – ' . msb_date( $l['end_date'] ) ) . ' · ' . MSB_Pricing::days_label( $l['days'] );
			foreach ( (array) $l['adjustments'] as $a ) {
				$sub .= ' · ' . $a['label'] . ': ' . ( $a['amount'] < 0 ? '-' : '+' ) . msb_price( abs( $a['amount'] ) );
			}
			$lines[] = array( (int) $l['quantity'] . '× ' . $l['item_title'], $sub, round( (float) $l['line_total'], 2 ) );
			$sum    += (float) $l['line_total'];
		}
		$total = round( (float) $b['total'], 2 );
		if ( abs( $total - $sum ) >= 0.01 ) {
			$lines[] = array( 'Rabatt / Anpassung', '', round( $total - $sum, 2 ) );
		}
		$data = array(
			'title'       => 'Rechnung',
			'meta'        => array(
				array( 'Buchungsnummer', $b['booking_number'] ),
				array( 'Leistungszeitraum', self::period( $b ) ),
			),
			'intro'       => 'Vielen Dank für Ihre Miete. Wir berechnen Ihnen folgende Leistungen:',
			'lines'       => $lines,
			'total_label' => 'Rechnungsbetrag',
			'total'       => $total,
			'notes'       => array_filter( array( self::payment_note( $b, $total ) ) ),
		);
		return self::issue( $b, 'invoice', 'invoice', $total, $data );
	}

	/**
	 * Stornorechnung über die Stornogebühr.
	 */
	public static function create_cancel_invoice( $booking_id, $fee, $percent = null ) {
		$b   = MSB_Bookings::get( $booking_id );
		$fee = round( (float) $fee, 2 );
		if ( ! $b || $fee <= 0 ) {
			return new WP_Error( 'msb_doc', 'Keine Stornogebühr.' );
		}
		foreach ( self::for_booking( $booking_id ) as $d ) {
			if ( 'cancel_invoice' === $d['type'] && 'issued' === $d['status'] ) {
				return new WP_Error( 'msb_doc', 'Für diese Stornierung gibt es bereits eine Rechnung.' );
			}
		}
		$sub = 'Mietzeitraum ' . self::period( $b );
		if ( null !== $percent ) {
			$sub .= ' · ' . rtrim( rtrim( number_format( (float) $percent, 2, ',', '' ), '0' ), ',' ) . ' % der Tagesmiete';
		}
		$data = array(
			'title'       => 'Stornorechnung',
			'meta'        => array(
				array( 'Buchungsnummer', $b['booking_number'] ),
				array( 'Storniert am', $b['cancelled_at'] ? mysql2date( 'd.m.Y', $b['cancelled_at'] ) : wp_date( 'd.m.Y' ) ),
			),
			'intro'       => 'Für die Stornierung Ihrer Buchung berechnen wir laut unseren Mietbedingungen:',
			'lines'       => array( array( 'Stornogebühr zu Buchung ' . $b['booking_number'], $sub, $fee ) ),
			'total_label' => 'Rechnungsbetrag',
			'total'       => $fee,
			'notes'       => array_filter( array( self::payment_note( $b, $fee ) ) ),
		);
		return self::issue( $b, 'cancel_invoice', 'invoice', $fee, $data );
	}

	/**
	 * Kautionsabrechnung bei (teilweisem) Einbehalt. Echter Schadenersatz, keine Rechnung.
	 */
	public static function create_deposit_statement( $payment_id ) {
		$p = MSB_Payments::get( $payment_id );
		if ( ! $p || 'security' !== $p['type'] || 'captured' !== $p['status'] ) {
			return new WP_Error( 'msb_doc', 'Keine einbehaltene Kaution.' );
		}
		$b        = MSB_Bookings::get( $p['booking_id'] );
		$deposit  = round( (float) $p['amount'], 2 );
		$captured = round( isset( $p['data']['captured'] ) ? (float) $p['data']['captured'] : 0, 2 );
		if ( ! $b || $captured <= 0 ) {
			return new WP_Error( 'msb_doc', 'Keine einbehaltene Kaution.' );
		}
		foreach ( self::for_booking( $b['id'] ) as $d ) {
			if ( 'deposit' === $d['type'] && 'issued' === $d['status'] && (int) $d['data']['payment_id'] === (int) $payment_id ) {
				return new WP_Error( 'msb_doc', 'Für diesen Einbehalt gibt es bereits eine Kautionsabrechnung.' );
			}
		}
		$cash   = 'manual' === $p['provider'];
		$reason = isset( $p['data']['reason'] ) && '' !== $p['data']['reason'] ? $p['data']['reason'] : 'Schaden';
		$rest   = round( $deposit - $captured, 2 );
		$notes  = array( 'Der einbehaltene Betrag von ' . msb_price( $captured ) . ' ist Schadenersatz und nicht umsatzsteuerbar.' );
		if ( $rest > 0 ) {
			$notes[] = $cash ? 'Den Restbetrag von ' . msb_price( $rest ) . ' haben wir Ihnen bar zurückgezahlt.' : 'Den Restbetrag von ' . msb_price( $rest ) . ' haben wir auf Ihrer Karte freigegeben.';
		}
		$data = array(
			'title'       => 'Kautionsabrechnung',
			'meta'        => array(
				array( 'Buchungsnummer', $b['booking_number'] ),
				array( 'Mietzeitraum', self::period( $b ) ),
			),
			'intro'       => 'Bei der Rückgabe haben wir einen Schaden festgestellt. Wir rechnen Ihre Kaution wie folgt ab:',
			'lines'       => array(
				array( 'Hinterlegte Kaution', $cash ? 'bar' : 'Reservierung ' . self::provider_text( $p['provider'] ), $deposit ),
				array( 'Einbehalt (Schadenersatz)', $reason, -$captured ),
			),
			'total_label' => $cash ? 'An Sie zurückgezahlt' : 'Auf Ihrer Karte freigegeben',
			'total'       => $rest,
			'notes'       => $notes,
			'payment_id'  => (int) $payment_id,
		);
		return self::issue( $b, 'deposit', 'deposit', $captured, $data );
	}

	/**
	 * Beleg vollständig aufheben (Korrekturbeleg mit umgekehrten Beträgen).
	 */
	public static function correct( $doc_id ) {
		global $wpdb;
		$orig = self::get( $doc_id );
		if ( ! $orig || 'issued' !== $orig['status'] || 'correction' === $orig['type'] ) {
			return new WP_Error( 'msb_doc', 'Dieser Beleg kann nicht korrigiert werden.' );
		}
		$b = MSB_Bookings::get( $orig['booking_id'] );
		if ( ! $b ) {
			return new WP_Error( 'msb_doc', 'Buchung nicht gefunden.' );
		}
		$lines = array();
		foreach ( (array) $orig['data']['lines'] as $l ) {
			$lines[] = array( $l[0], $l[1], -1 * (float) $l[2] );
		}
		$data = array(
			'title'       => 'deposit' === $orig['series'] ? 'Korrektur der Kautionsabrechnung' : 'Rechnungskorrektur',
			'meta'        => array(
				array( 'Buchungsnummer', $b['booking_number'] ),
				array( 'Korrigiert', $orig['number'] . ' vom ' . mysql2date( 'd.m.Y', $orig['issued_at'] ) ),
			),
			'intro'       => 'Dieser Beleg hebt den Beleg ' . $orig['number'] . ' vom ' . mysql2date( 'd.m.Y', $orig['issued_at'] ) . ' vollständig auf.',
			'lines'       => $lines,
			'total_label' => 'Korrekturbetrag',
			'total'       => -1 * (float) $orig['data']['total'],
			'notes'       => array(),
		);
		$id = self::issue( $b, 'correction', $orig['series'], -1 * (float) $orig['amount'], $data, $orig['id'] );
		if ( ! is_wp_error( $id ) ) {
			$wpdb->update( self::table(), array( 'status' => 'corrected', 'ref_id' => $id ), array( 'id' => (int) $orig['id'] ) );
		}
		return $id;
	}

	/* ------------------------------------------------------------------
	 * PDF
	 * ------------------------------------------------------------------ */

	private static function dir() {
		$up  = wp_upload_dir();
		$dir = trailingslashit( $up['basedir'] ) . 'msb-belege';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore
		}
		return $dir;
	}

	/**
	 * Pfad zur PDF-Datei; wird bei Bedarf (neu) erzeugt. Die Daten des Belegs ändern sich dabei nicht.
	 */
	public static function file( $doc_id ) {
		global $wpdb;
		$doc = self::get( $doc_id );
		if ( ! $doc ) {
			return '';
		}
		$dir = self::dir();
		if ( $doc['file'] && file_exists( $dir . '/' . $doc['file'] ) ) {
			return $dir . '/' . $doc['file'];
		}
		$name = $doc['file'] ? $doc['file'] : sanitize_file_name( $doc['number'] ) . '-' . strtolower( wp_generate_password( 10, false ) ) . '.pdf';
		if ( false === file_put_contents( $dir . '/' . $name, self::render( $doc ) ) ) { // phpcs:ignore
			return '';
		}
		if ( $name !== $doc['file'] ) {
			$wpdb->update( self::table(), array( 'file' => $name ), array( 'id' => (int) $doc['id'] ) );
		}
		return $dir . '/' . $name;
	}

	public static function render( array $doc ) {
		$d   = $doc['data'];
		$is  = isset( $d['issuer'] ) ? $d['issuer'] : self::issuer();
		$pdf = new MSB_PDF( $d['title'] . ' ' . $doc['number'] );
		$pdf->add_page();
		$left  = 20;
		$right = 190;

		// Kopf: Logo links (höchstens 60 × 26 mm), Absender rechts.
		$logo = self::logo_jpeg();
		$size = $logo ? @getimagesize( $logo ) : false; // phpcs:ignore
		if ( $size && $size[0] > 0 && $size[1] > 0 ) {
			$lw = 60;
			$lh = $lw * $size[1] / $size[0];
			if ( $lh > 26 ) {
				$lh = 26;
				$lw = $lh * $size[0] / $size[1];
			}
			$pdf->image( $logo, $left, 14, $lw, $lh );
		}
		$y = 20;
		$pdf->text( $right, $y, $is['name'], 13, true, 'R' );
		foreach ( array_merge( (array) $is['address'], array( $is['contact'] ) ) as $line ) {
			$y += 5;
			$pdf->text( $right, $y, $line, 9, false, 'R', 0.3 );
		}
		if ( $is['tax'] ) {
			$y += 5;
			$pdf->text( $right, $y, 'Steuernummer: ' . $is['tax'], 9, false, 'R', 0.3 );
		}

		// Empfänger.
		$pdf->text( $left, 52, $is['name'] . ' · ' . implode( ' · ', (array) $is['address'] ), 7, false, 'L', 0.45 );
		$pdf->line( $left, 53.5, $left + 85, 53.5, 0.15, 0.6 );
		$y = 60;
		foreach ( (array) $d['recipient'] as $line ) {
			$pdf->text( $left, $y, $line, 10 );
			$y += 5;
		}

		// Belegdaten rechts.
		$meta = array_merge(
			array(
				array( 'deposit' === $doc['series'] ? 'Belegnummer' : 'Rechnungsnummer', $doc['number'] ),
				array( 'Datum', mysql2date( 'd.m.Y', $doc['issued_at'] ) ),
			),
			(array) $d['meta']
		);
		$y = 60;
		foreach ( $meta as $m ) {
			$pdf->text( 140, $y, $m[0] . ':', 9, false, 'R', 0.35 );
			$pdf->text( 142, $y, $m[1], 9, true );
			$y += 5;
		}

		// Titel und Einleitung.
		$y = max( $y, 82 ) + 14;
		$pdf->text( $left, $y, $d['title'] . ' ' . $doc['number'], 15, true );
		$y += 9;
		foreach ( $pdf->wrap( $d['intro'], $right - $left, 10 ) as $line ) {
			$pdf->text( $left, $y, $line, 10 );
			$y += 5;
		}

		// Positionen.
		$y += 4;
		$pdf->rect( $left, $y - 5, $right - $left, 7.5, 0.93 );
		$pdf->text( $left + 2, $y, 'Pos.', 9, true );
		$pdf->text( $left + 14, $y, 'Beschreibung', 9, true );
		$pdf->text( $right - 2, $y, 'Betrag', 9, true, 'R' );
		$y += 8;
		$pos = 0;
		foreach ( (array) $d['lines'] as $l ) {
			if ( $y > 250 ) {
				$pdf->add_page();
				$y = 25;
			}
			$pos++;
			$pdf->text( $left + 2, $y, (string) $pos, 10 );
			$first = true;
			foreach ( $pdf->wrap( $l[0], 125, 10 ) as $line ) {
				$pdf->text( $left + 14, $y, $line, 10 );
				if ( $first ) {
					$pdf->text( $right - 2, $y, msb_price( $l[2] ), 10, false, 'R' );
					$first = false;
				}
				$y += 5;
			}
			if ( '' !== (string) $l[1] ) {
				foreach ( $pdf->wrap( $l[1], 125, 8.5 ) as $line ) {
					$pdf->text( $left + 14, $y, $line, 8.5, false, 'L', 0.4 );
					$y += 4.3;
				}
			}
			$pdf->line( $left, $y - 2.5, $right, $y - 2.5 );
			$y += 4;
		}
		$y += 3;
		$pdf->text( $left + 14, $y, $d['total_label'], 11, true );
		$pdf->text( $right - 2, $y, msb_price( $d['total'] ), 11, true, 'R' );
		$y += 3;
		$pdf->line( $left, $y, $right, $y, 0.4, 0.2 );
		$y += 10;

		// Hinweise: § 19 UStG, Zahlungsvermerk.
		$notes = array();
		if ( ! empty( $d['vat_note'] ) ) {
			$notes[] = $d['vat_note'];
		}
		foreach ( (array) $d['notes'] as $n ) {
			$notes[] = $n;
		}
		foreach ( $notes as $n ) {
			if ( $y > 265 ) {
				$pdf->add_page();
				$y = 25;
			}
			foreach ( $pdf->wrap( $n, $right - $left, 10 ) as $line ) {
				$pdf->text( $left, $y, $line, 10 );
				$y += 5;
			}
			$y += 2;
		}

		// Fußzeile.
		$foot = array_filter( array( $is['footer'], $is['name'] . ' · ' . implode( ' · ', (array) $is['address'] ) . ( $is['tax'] ? ' · Steuernummer ' . $is['tax'] : '' ) ) );
		$fy   = 282;
		foreach ( $foot as $f ) {
			foreach ( $pdf->wrap( $f, $right - $left, 7.5 ) as $line ) {
				$pdf->text( 105, $fy, $line, 7.5, false, 'C', 0.45 );
				$fy += 3.8;
			}
		}
		return $pdf->output();
	}

	/* ------------------------------------------------------------------
	 * Versand
	 * ------------------------------------------------------------------ */

	public static function send( $doc_id ) {
		global $wpdb;
		$doc = self::get( $doc_id );
		$b   = $doc ? MSB_Bookings::get( $doc['booking_id'] ) : null;
		if ( ! $doc || ! $b ) {
			return false;
		}
		$file = self::file( $doc_id );
		if ( ! $file ) {
			return false;
		}
		$s                      = msb_settings();
		list( $subject, $body ) = MSB_Document_Mails::build( $doc, $b );
		$ok                     = false;
		if ( $s['doc_mail_enabled'] && is_email( $b['customer_email'] ) ) {
			$ok = MSB_Emails::send_raw( $b['customer_email'], $subject, $body, array( $file ) );
		}
		if ( is_email( $s['admin_email'] ) ) {
			MSB_Emails::send_raw( $s['admin_email'], self::label( $doc ) . ' ' . $doc['number'] . ' zu ' . $b['booking_number'] . ( $ok ? ' (an den Kunden gesendet)' : ' (nicht an den Kunden gesendet)' ), '<p>' . esc_html( self::label( $doc ) . ' ' . $doc['number'] . ' für ' . $b['customer_name'] . ', Betrag ' . msb_price( $doc['amount'] ) . '.' ) . '</p><p><a href="' . esc_url( admin_url( 'admin.php?page=msb-booking&id=' . (int) $b['id'] . '#documents' ) ) . '">Buchung im Backend öffnen</a></p>', array( $file ) );
		}
		if ( $ok ) {
			$wpdb->update( self::table(), array( 'sent_at' => current_time( 'mysql' ) ), array( 'id' => (int) $doc['id'] ) );
		}
		return $ok;
	}

	/* ------------------------------------------------------------------
	 * Automatik
	 * ------------------------------------------------------------------ */

	public static function on_status_change( $booking_id, $status, $old ) {
		// Barzahlung bei Abholung im Backend vermerken (Häkchen im Statuskasten).
		// phpcs:disable WordPress.Security.NonceVerification -- Nonce wurde in MSB_Admin_Bookings::handle_actions geprüft.
		if ( 'picked_up' === $status && is_admin() && isset( $_POST['msb_booking_action'] ) && 'update' === $_POST['msb_booking_action'] && current_user_can( msb_capability() ) ) {
			$b = MSB_Bookings::get( $booking_id );
			if ( $b && MSB_Payments::is_cash( $b ) ) {
				MSB_Payments::record_cash_pickup( $booking_id, ! empty( $_POST['msb_cash_rent'] ), ! empty( $_POST['msb_cash_deposit'] ) );
			}
		}
		// phpcs:enable
		if ( 'returned' === $status && msb_setting( 'doc_auto_invoice' ) && ! self::active_invoice( $booking_id ) ) {
			$id = self::create_invoice( $booking_id );
			if ( ! is_wp_error( $id ) ) {
				self::send( $id );
			}
		}
	}

	public static function on_cancelled( $booking_id, $by, $result ) {
		if ( ! empty( $result['fee'] ) && (float) $result['fee'] > 0 ) {
			$id = self::create_cancel_invoice( $booking_id, $result['fee'], isset( $result['percent'] ) ? $result['percent'] : null );
			if ( ! is_wp_error( $id ) ) {
				self::send( $id );
			}
		}
	}

	public static function on_security_captured( $booking_id, $payment_id ) {
		$id = self::create_deposit_statement( $payment_id );
		if ( ! is_wp_error( $id ) ) {
			self::send( $id );
		}
	}

	/* ------------------------------------------------------------------
	 * Download
	 * ------------------------------------------------------------------ */

	private static function customer_key( array $doc, array $b ) {
		return substr( hash_hmac( 'sha256', $doc['id'] . '|' . $b['token'], wp_salt( 'auth' ) ), 0, 24 );
	}

	public static function customer_url( array $doc, array $b ) {
		return add_query_arg(
			array(
				'msb_doc' => (int) $doc['id'],
				'k'       => self::customer_key( $doc, $b ),
			),
			home_url( '/' )
		);
	}

	public static function admin_url( array $doc ) {
		return wp_nonce_url( admin_url( 'admin.php?page=' . self::PAGE . '&download=' . (int) $doc['id'] ), 'msb_doc_download_' . (int) $doc['id'] );
	}

	private static function stream( array $doc ) {
		$file = self::file( $doc['id'] );
		if ( ! $file ) {
			wp_die( 'Der Beleg konnte nicht erzeugt werden.' );
		}
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="' . sanitize_file_name( $doc['number'] ) . '.pdf"' );
		header( 'Content-Length: ' . filesize( $file ) );
		readfile( $file ); // phpcs:ignore
		exit;
	}

	public static function customer_download() {
		// phpcs:disable WordPress.Security.NonceVerification -- Zugriff über geheimen Schlüssel je Beleg.
		if ( empty( $_GET['msb_doc'] ) ) {
			return;
		}
		$doc = self::get( absint( $_GET['msb_doc'] ) );
		$b   = $doc ? MSB_Bookings::get( $doc['booking_id'] ) : null;
		$key = isset( $_GET['k'] ) ? sanitize_key( wp_unslash( $_GET['k'] ) ) : '';
		// phpcs:enable
		if ( ! $doc || ! $b || ! hash_equals( self::customer_key( $doc, $b ), $key ) ) {
			wp_die( 'Dieser Link ist ungültig.', '', array( 'response' => 404 ) );
		}
		self::stream( $doc );
	}

	/* ------------------------------------------------------------------
	 * Backend
	 * ------------------------------------------------------------------ */

	public static function menu() {
		add_submenu_page( 'msb-bookings', 'Belege', 'Belege', msb_capability(), self::PAGE, array( __CLASS__, 'page' ) );
	}

	private static function notice( $url, $message, $type = 'success' ) {
		set_transient( 'msb_notice_' . get_current_user_id(), array( $message, $type ), 60 );
		wp_safe_redirect( $url );
		exit;
	}

	public static function handle_admin() {
		// phpcs:disable WordPress.Security.NonceVerification -- Nonces werden je Aktion geprüft.
		if ( isset( $_GET['page'], $_GET['download'] ) && self::PAGE === $_GET['page'] ) {
			$id = absint( $_GET['download'] );
			check_admin_referer( 'msb_doc_download_' . $id );
			$doc = current_user_can( msb_capability() ) ? self::get( $id ) : null;
			if ( ! $doc ) {
				wp_die( 'Beleg nicht gefunden.' );
			}
			self::stream( $doc );
		}
		if ( isset( $_GET['page'], $_GET['export'] ) && self::PAGE === $_GET['page'] ) {
			check_admin_referer( 'msb_doc_export' );
			if ( current_user_can( msb_capability() ) ) {
				self::export_csv( isset( $_GET['year'] ) ? absint( $_GET['year'] ) : 0 );
			}
		}
		if ( empty( $_POST['msb_doc_action'] ) ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_POST['msb_doc_action'] ) );
		// phpcs:enable

		if ( 'save_settings' === $action ) {
			check_admin_referer( 'msb_save_documents' );
			if ( current_user_can( msb_capability( 'settings' ) ) ) {
				self::save_settings();
			}
			return;
		}

		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
		check_admin_referer( 'msb_documents_' . $booking_id );
		if ( ! current_user_can( msb_capability() ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		$url = admin_url( 'admin.php?page=msb-booking&id=' . $booking_id ) . '#documents';
		$doc = isset( $_POST['doc_id'] ) ? self::get( absint( $_POST['doc_id'] ) ) : null;
		if ( $doc && (int) $doc['booking_id'] !== $booking_id ) {
			self::notice( $url, 'Beleg nicht gefunden.', 'error' );
		}
		switch ( $action ) {
			case 'create_invoice':
				$r = self::create_invoice( $booking_id );
				if ( is_wp_error( $r ) ) {
					self::notice( $url, $r->get_error_message(), 'error' );
				}
				$sent = ! empty( $_POST['send'] ) && self::send( $r ); // phpcs:ignore WordPress.Security.NonceVerification
				$new  = self::get( $r );
				self::notice( $url, 'Rechnung ' . $new['number'] . ' erstellt' . ( $sent ? ' und an den Kunden gesendet.' : '.' ) );
				break;
			case 'resend':
				if ( ! $doc ) {
					self::notice( $url, 'Beleg nicht gefunden.', 'error' );
				}
				$ok = self::send( $doc['id'] );
				self::notice( $url, $ok ? 'Beleg ' . $doc['number'] . ' wurde gesendet.' : 'Beleg konnte nicht an den Kunden gesendet werden (Versand aus oder keine gültige Adresse).', $ok ? 'success' : 'error' );
				break;
			case 'correct':
				if ( ! $doc ) {
					self::notice( $url, 'Beleg nicht gefunden.', 'error' );
				}
				$r = self::correct( $doc['id'] );
				if ( is_wp_error( $r ) ) {
					self::notice( $url, $r->get_error_message(), 'error' );
				}
				$sent = ! empty( $_POST['send'] ) && self::send( $r ); // phpcs:ignore WordPress.Security.NonceVerification
				$new  = self::get( $r );
				self::notice( $url, 'Beleg ' . $doc['number'] . ' mit ' . $new['number'] . ' aufgehoben' . ( $sent ? ' (an den Kunden gesendet).' : '.' ) . ( 'invoice' === $doc['type'] ? ' Sie können jetzt eine neue Rechnung erstellen.' : '' ) );
				break;
			case 'set_test':
				$on = ! empty( $_POST['is_test'] ); // phpcs:ignore WordPress.Security.NonceVerification
				$r  = MSB_Bookings::set_test( $booking_id, $on );
				if ( is_wp_error( $r ) ) {
					self::notice( $url, $r->get_error_message(), 'error' );
				}
				self::notice( $url, $on ? 'Als Testbuchung markiert. Neue Belege bekommen TEST-Nummern.' : 'Testbuchung aufgehoben.' );
				break;
		}
	}

	/**
	 * Kasten „Belege“ in der Buchung.
	 */
	public static function admin_box( array $b ) {
		$id   = (int) $b['id'];
		$docs = self::for_booking( $id );
		$form = function ( $action, $fields, $button, $class = 'button', $confirm = '' ) use ( $id ) {
			$h  = '<form method="post" class="msb-inline-form"' . ( $confirm ? ' onsubmit="return confirm(\'' . esc_js( $confirm ) . '\')"' : '' ) . '>';
			$h .= wp_nonce_field( 'msb_documents_' . $id, '_wpnonce', true, false );
			$h .= '<input type="hidden" name="booking_id" value="' . $id . '"><input type="hidden" name="msb_doc_action" value="' . esc_attr( $action ) . '">';
			$h .= $fields . '<button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $button ) . '</button></form>';
			return $h;
		};
		echo '<div class="msb-card-box" id="documents"><h2>Belege</h2>';
		if ( $docs ) {
			echo '<table class="widefat striped"><thead><tr><th>Nummer</th><th>Art</th><th>Datum</th><th style="text-align:right">Betrag</th><th>Versand</th><th>Aktion</th></tr></thead><tbody>';
			foreach ( array_reverse( $docs ) as $d ) {
				echo '<tr><td><a href="' . esc_url( self::admin_url( $d ) ) . '" target="_blank"><strong>' . esc_html( $d['number'] ) . '</strong></a>' . ( 'corrected' === $d['status'] ? '<br><small class="msb-red">aufgehoben</small>' : '' ) . '</td>';
				echo '<td>' . esc_html( self::label( $d ) ) . '</td>';
				echo '<td>' . esc_html( mysql2date( 'd.m.Y', $d['issued_at'] ) ) . '</td>';
				echo '<td style="text-align:right">' . esc_html( msb_price( $d['amount'] ) ) . '</td>';
				echo '<td>' . ( $d['sent_at'] ? esc_html( mysql2date( 'd.m.Y H:i', $d['sent_at'] ) ) : '–' ) . '</td><td class="msb-pay-actions">';
				echo '<a class="button" href="' . esc_url( self::admin_url( $d ) ) . '" target="_blank">PDF</a> ';
				echo $form( 'resend', '<input type="hidden" name="doc_id" value="' . (int) $d['id'] . '">', 'Senden' ); // phpcs:ignore
				if ( 'issued' === $d['status'] && 'correction' !== $d['type'] ) {
					echo $form( 'correct', '<input type="hidden" name="doc_id" value="' . (int) $d['id'] . '"><label><input type="checkbox" name="send" value="1"> Kunde informieren</label> ', 'Korrigieren', 'button', 'Beleg ' . $d['number'] . ' wirklich aufheben? Dafür wird ein Korrekturbeleg mit eigener Nummer erstellt.' ); // phpcs:ignore
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p class="description">Noch keine Belege. Die Rechnung entsteht automatisch beim Status „Zurückgegeben“.</p>';
		}
		// Testbuchung (1.7.2): nur änderbar, solange keine gültigen Belege existieren.
		if ( MSB_Bookings::test_locked( $id ) ) {
			echo '<p class="description">' . ( empty( $b['is_test'] ) ? 'Echte Buchung.' : MSB_Bookings::test_badge() . ' Testbuchung.' ) . ' „Testbuchung“ lässt sich erst ändern, wenn die gültigen Belege korrigiert sind.</p>'; // phpcs:ignore
		} elseif ( empty( $b['is_test'] ) ) {
			echo '<p>' . $form( 'set_test', '<input type="hidden" name="is_test" value="1">', 'Als Testbuchung markieren', 'button', 'Als Testbuchung markieren? Belege bekommen dann TEST-Nummern, Bargeld zählt nicht im EÜR-Export.' ) . '<br><span class="description">Ändert nur die Buchhaltung: Belege bekommen TEST-Nummern statt RE/KAU, Bargeld fehlt im EÜR-Export, Stripe-Zahlungen stehen dort mit Vermerk „Test“. Ablauf und Mails bleiben wie im echten Betrieb. Test-Stripe-Zahlungen danach voll erstatten.</span></p>'; // phpcs:ignore
		} else {
			echo '<p>' . MSB_Bookings::test_badge() . ' Testbuchung: neue Belege bekommen TEST-Nummern. ' . $form( 'set_test', '', 'Doch echte Buchung' ) . '</p>'; // phpcs:ignore
		}
		if ( 'cancelled' !== $b['status'] && ! self::active_invoice( $id ) ) {
			echo '<p>' . $form( 'create_invoice', '<label><input type="checkbox" name="send" value="1" checked> an den Kunden senden</label> ', 'Rechnung jetzt erstellen', 'button', 'Rechnung jetzt mit fortlaufender Nummer erstellen?' ) . '</p>'; // phpcs:ignore
		}
		echo '</div>';
	}

	/**
	 * Seite „Belege“: alle Belege mit Filter und CSV-Export für den Steuerberater.
	 */
	public static function page() {
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification
		$year = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : (int) current_time( 'Y' );
		$type = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : '';
		// phpcs:enable
		$where = $wpdb->prepare( 'WHERE YEAR(issued_at) = %d', $year );
		if ( $type && isset( self::type_labels()[ $type ] ) ) {
			$where .= $wpdb->prepare( ' AND type = %s', $type );
		}
		$rows  = array_map( array( __CLASS__, 'prepare' ), (array) $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ' . $where . ' ORDER BY id DESC', ARRAY_A ) ); // phpcs:ignore
		$years = $wpdb->get_col( 'SELECT DISTINCT YEAR(issued_at) FROM ' . self::table() . ' ORDER BY 1 DESC' ); // phpcs:ignore
		if ( ! in_array( (string) $year, $years, true ) ) {
			$years[] = (string) $year;
		}
		$sums = array( 'invoice' => 0.0, 'deposit' => 0.0 );
		foreach ( $rows as $r ) {
			if ( self::is_test( $r ) ) {
				continue;
			}
			$sums[ 'deposit' === $r['series'] ? 'deposit' : 'invoice' ] += (float) $r['amount'];
		}
		?>
		<div class="wrap msb-admin">
			<h1 class="wp-heading-inline">Belege</h1>
			<a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=' . self::PAGE . '&export=csv&year=' . $year ), 'msb_doc_export' ) ); ?>">CSV <?php echo (int) $year; ?> herunterladen</a>
			<hr class="wp-header-end">
			<form method="get" style="margin:12px 0">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
				<select name="year"><?php foreach ( $years as $y ) : ?><option value="<?php echo (int) $y; ?>" <?php selected( (int) $y, $year ); ?>><?php echo (int) $y; ?></option><?php endforeach; ?></select>
				<select name="type"><option value="">Alle Arten</option><?php foreach ( self::type_labels() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $type, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
				<button class="button">Filtern</button>
			</form>
			<p>Summe Rechnungen (RE, inkl. Korrekturen, ohne Testbelege): <strong><?php echo esc_html( msb_price( $sums['invoice'] ) ); ?></strong> · Summe Kautionseinbehalte (KAU, Schadenersatz): <strong><?php echo esc_html( msb_price( $sums['deposit'] ) ); ?></strong></p>
			<?php echo MSB_Euer::box( $year ); // phpcs:ignore ?>
			<table class="widefat striped msb-doc-list">
				<thead><tr><th>Nummer</th><th>Art</th><th>Datum</th><th>Buchung</th><th>Kunde</th><th style="text-align:right">Betrag</th><th>Status</th></tr></thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="7">Keine Belege in diesem Zeitraum.</td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $r ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( self::admin_url( $r ) ); ?>" target="_blank"><strong><?php echo esc_html( $r['number'] ); ?></strong></a></td>
						<td><?php echo esc_html( self::label( $r ) ); ?><?php echo self::is_test( $r ) ? ' ' . MSB_Bookings::test_badge() : ''; // phpcs:ignore ?></td>
						<td><?php echo esc_html( mysql2date( 'd.m.Y', $r['issued_at'] ) ); ?></td>
						<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=msb-booking&id=' . (int) $r['booking_id'] ) ); ?>"><?php echo esc_html( isset( $r['data']['booking_number'] ) ? $r['data']['booking_number'] : '#' . $r['booking_id'] ); ?></a></td>
						<td><?php echo esc_html( isset( $r['data']['recipient'][0] ) ? $r['data']['recipient'][0] : '' ); ?></td>
						<td style="text-align:right"><?php echo esc_html( msb_price( $r['amount'] ) ); ?></td>
						<td><?php echo 'corrected' === $r['status'] ? 'aufgehoben' : 'gültig'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private static function export_csv( $year ) {
		global $wpdb;
		$year = $year ? $year : (int) current_time( 'Y' );
		$rows = array_map( array( __CLASS__, 'prepare' ), (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE YEAR(issued_at) = %d ORDER BY series, id', $year ), ARRAY_A ) ); // phpcs:ignore
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="belege-' . $year . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore
		fputcsv( $out, array( 'Nummer', 'Art', 'Datum', 'Buchung', 'Kunde', 'Betrag', 'Steuerliche Einordnung', 'Zahlungsvermerk', 'Status', 'Bezieht sich auf' ), ';' );
		foreach ( $rows as $r ) {
			if ( self::is_test( $r ) ) {
				continue; // Testbelege gehören nicht in die Buchhaltung (1.7.2).
			}
			$ref = $r['ref_id'] ? self::get( $r['ref_id'] ) : null;
			fputcsv(
				$out,
				array(
					$r['number'],
					self::label( $r ),
					mysql2date( 'd.m.Y', $r['issued_at'] ),
					isset( $r['data']['booking_number'] ) ? $r['data']['booking_number'] : '',
					isset( $r['data']['recipient'][0] ) ? $r['data']['recipient'][0] : '',
					number_format( (float) $r['amount'], 2, ',', '' ),
					'deposit' === $r['series'] ? 'Schadenersatz, nicht umsatzsteuerbar' : 'Umsatz, § 19 UStG',
					isset( $r['data']['notes'] ) ? implode( ' ', (array) $r['data']['notes'] ) : '',
					'corrected' === $r['status'] ? 'aufgehoben' : 'gültig',
					$ref ? $ref['number'] : '',
				),
				';'
			);
		}
		fclose( $out ); // phpcs:ignore
		exit;
	}

	/* ------------------------------------------------------------------
	 * Einstellungen (Reiter „Belege“ unter Vermietung → Einstellungen)
	 * ------------------------------------------------------------------ */

	public static function settings_page( array $tabs, array $help ) {
		$s = msb_settings();
		wp_enqueue_media();
		$logo = self::logo_url();
		?>
		<div class="wrap msb-admin">
			<h1>Einstellungen <?php echo msb_help_link( isset( $help['documents'] ) ? $help['documents'] : 'belege', 'Erklärung zu diesem Bereich' ); // phpcs:ignore ?></h1>
			<?php
			if ( isset( $_GET['saved'] ) ) { // phpcs:ignore
				echo '<div class="notice notice-success is-dismissible"><p>Einstellungen gespeichert.</p></div>';
			}
			?>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $k => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=msb-settings&tab=' . $k ) ); ?>" class="nav-tab <?php echo 'documents' === $k ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<form method="post" action="">
				<?php wp_nonce_field( 'msb_save_documents' ); ?>
				<input type="hidden" name="msb_doc_action" value="save_settings">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Logo</th>
						<td><input type="hidden" id="doc_logo_id" name="doc_logo_id" value="<?php echo (int) $s['doc_logo_id']; ?>">
							<img id="msb-doc-logo" src="<?php echo esc_url( $logo ); ?>" alt="" style="max-width:220px;max-height:90px;display:<?php echo $logo ? 'block' : 'none'; ?>;margin-bottom:8px;background:#fff;padding:6px;border:1px solid #ddd">
							<button type="button" class="button" id="msb-doc-logo-pick">Logo aus der Mediathek wählen</button>
							<button type="button" class="button-link" id="msb-doc-logo-reset" style="margin-left:8px">Standard verwenden</button>
							<p class="description">Steht oben links auf Rechnungen und Kautionsabrechnungen und oben in den E-Mails an Kunden. Ohne Auswahl wird das Website-Icon verwendet. Am besten ein breites Logo mit hellem oder transparentem Hintergrund.</p>
							<script>
							( function () {
								var pick = document.getElementById( 'msb-doc-logo-pick' ), reset = document.getElementById( 'msb-doc-logo-reset' ), input = document.getElementById( 'doc_logo_id' ), img = document.getElementById( 'msb-doc-logo' ), frame;
								pick.addEventListener( 'click', function () {
									if ( ! window.wp || ! wp.media ) { return; }
									frame = frame || wp.media( { title: 'Logo wählen', button: { text: 'Als Logo verwenden' }, library: { type: 'image' }, multiple: false } );
									frame.off( 'select' ).on( 'select', function () {
										var a = frame.state().get( 'selection' ).first().toJSON();
										input.value = a.id;
										img.src = a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url;
										img.style.display = 'block';
									} );
									frame.open();
								} );
								reset.addEventListener( 'click', function () { input.value = 0; img.style.display = 'none'; } );
							} )();
							</script></td>
					</tr>
					<tr>
						<th scope="row"><label for="doc_issuer_name">Absender</label></th>
						<td><input type="text" id="doc_issuer_name" name="doc_issuer_name" value="<?php echo esc_attr( $s['doc_issuer_name'] ); ?>" class="regular-text"><br>
							<textarea name="doc_issuer_address" rows="3" class="regular-text" style="margin-top:6px"><?php echo esc_textarea( $s['doc_issuer_address'] ); ?></textarea>
							<p class="description">Name und Anschrift der GbR, eine Zeile pro Angabe.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="doc_issuer_contact">Kontaktzeile</label></th>
						<td><input type="text" id="doc_issuer_contact" name="doc_issuer_contact" value="<?php echo esc_attr( $s['doc_issuer_contact'] ); ?>" class="large-text" placeholder="<?php echo esc_attr( $s['from_email'] ); ?> · <?php echo esc_attr( wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ); ?>">
							<p class="description">Leer lassen für Absender-E-Mail und Webadresse.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="doc_tax_number">Steuernummer</label></th>
						<td><input type="text" id="doc_tax_number" name="doc_tax_number" value="<?php echo esc_attr( $s['doc_tax_number'] ); ?>" class="regular-text">
							<label style="margin-left:10px"><input type="checkbox" name="doc_show_tax_number" value="1" <?php checked( $s['doc_show_tax_number'] ); ?>> auf Belegen zeigen</label></td>
					</tr>
					<tr>
						<th scope="row"><label for="doc_vat_note">Hinweis Umsatzsteuer</label></th>
						<td><input type="text" id="doc_vat_note" name="doc_vat_note" value="<?php echo esc_attr( $s['doc_vat_note'] ); ?>" class="large-text">
							<p class="description">Steht auf jeder Rechnung (nicht auf der Kautionsabrechnung).</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="doc_footer">Fußzeile</label></th>
						<td><input type="text" id="doc_footer" name="doc_footer" value="<?php echo esc_attr( $s['doc_footer'] ); ?>" class="large-text" placeholder="z. B. Gesellschafter: …"></td>
					</tr>
					<tr>
						<th scope="row">Nummernkreise</th>
						<td><label>Rechnungen <input type="text" name="doc_prefix_invoice" value="<?php echo esc_attr( $s['doc_prefix_invoice'] ); ?>" class="small-text"></label>
							<label style="margin-left:10px">Kautionsabrechnungen <input type="text" name="doc_prefix_deposit" value="<?php echo esc_attr( $s['doc_prefix_deposit'] ); ?>" class="small-text"></label>
							<p class="description">Ergibt z. B. <?php echo esc_html( self::prefix( 'invoice' ) . '-' . current_time( 'Y' ) . '-0001' ); ?>. Die Nummer zählt jedes Jahr ab 0001.</p></td>
					</tr>
					<tr>
						<th scope="row">Automatik</th>
						<td><label><input type="checkbox" name="doc_auto_invoice" value="1" <?php checked( $s['doc_auto_invoice'] ); ?>> Rechnung automatisch beim Status „Zurückgegeben“ erstellen</label><br>
							<label><input type="checkbox" name="doc_mail_enabled" value="1" <?php checked( $s['doc_mail_enabled'] ); ?>> Belege als PDF an den Kunden senden</label>
							<p class="description">Eine Kopie geht immer an <?php echo esc_html( $s['admin_email'] ); ?>. Stornorechnung und Kautionsabrechnung entstehen automatisch bei Stornogebühr bzw. Einbehalt.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="review_url">Google-Bewertung</label></th>
						<td><input type="url" id="review_url" name="review_url" value="<?php echo esc_attr( $s['review_url'] ); ?>" class="large-text" placeholder="https://g.page/r/…/review">
							<p class="description">Link aus dem Google Unternehmensprofil („Nach Rezensionen fragen“). Nur Kunden, die bei der Buchung den freiwilligen Haken zur Bewertung gesetzt haben, bekommen ihn in der Mail zur Rechnung. Leer lassen, um die Bitte abzuschalten.</p></td>
					</tr>
				</table>
				<h2>E-Mail zum Beleg</h2>
				<p class="description">Platzhalter wie bei den anderen Vorlagen, zusätzlich <code>{document_number}</code> und <code>{document_type}</code>.</p>
				<?php foreach ( array( 'invoice' => 'Rechnung, Stornorechnung, Korrektur', 'deposit' => 'Kautionsabrechnung' ) as $k => $l ) : ?>
					<div class="msb-card-box">
						<h3><?php echo esc_html( $l ); ?></h3>
						<p><label>Betreff<br><input type="text" name="doc_mail_subject_<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $s[ 'doc_mail_subject_' . $k ] ); ?>" class="large-text"></label></p>
						<p><label>Nachricht<br><textarea name="doc_mail_body_<?php echo esc_attr( $k ); ?>" rows="7" class="large-text code"><?php echo esc_textarea( $s[ 'doc_mail_body_' . $k ] ); ?></textarea></label></p>
					</div>
				<?php endforeach; ?>
				<p class="submit"><button type="submit" class="button button-primary">Einstellungen speichern</button></p>
			</form>
		</div>
		<?php
	}

	private static function save_settings() {
		$in = wp_unslash( $_POST ); // phpcs:ignore
		$s  = msb_settings();
		$s['review_url'] = isset( $in['review_url'] ) ? esc_url_raw( trim( (string) $in['review_url'] ) ) : '';
		foreach ( array( 'doc_issuer_name', 'doc_issuer_contact', 'doc_tax_number', 'doc_vat_note', 'doc_footer', 'doc_mail_subject_invoice', 'doc_mail_subject_deposit' ) as $k ) {
			$s[ $k ] = isset( $in[ $k ] ) ? sanitize_text_field( (string) $in[ $k ] ) : '';
		}
		foreach ( array( 'doc_prefix_invoice', 'doc_prefix_deposit' ) as $k ) {
			$v       = isset( $in[ $k ] ) ? strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $in[ $k ] ) ) : '';
			$s[ $k ] = '' !== $v ? substr( $v, 0, 6 ) : msb_settings_defaults()[ $k ];
		}
		$s['doc_issuer_address'] = isset( $in['doc_issuer_address'] ) ? sanitize_textarea_field( (string) $in['doc_issuer_address'] ) : '';
		foreach ( array( 'doc_mail_body_invoice', 'doc_mail_body_deposit' ) as $k ) {
			$s[ $k ] = isset( $in[ $k ] ) ? wp_kses_post( (string) $in[ $k ] ) : '';
		}
		$s['doc_logo_id'] = isset( $in['doc_logo_id'] ) ? absint( $in['doc_logo_id'] ) : 0;
		foreach ( array( 'doc_show_tax_number', 'doc_auto_invoice', 'doc_mail_enabled' ) as $k ) {
			$s[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}
		update_option( 'msb_settings', $s );
		wp_safe_redirect( add_query_arg( array( 'page' => 'msb-settings', 'tab' => 'documents', 'saved' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Mein Konto
	 * ------------------------------------------------------------------ */

	/**
	 * Download-Links der Belege einer Buchung für „Mein Konto“.
	 */
	public static function account_links( array $b ) {
		$links = array();
		foreach ( self::for_booking( $b['id'] ) as $d ) {
			$links[] = '<a href="' . esc_url( self::customer_url( $d, $b ) ) . '" target="_blank">' . esc_html( self::label( $d ) . ' ' . $d['number'] ) . '</a>' . ( 'corrected' === $d['status'] ? ' <span class="msb-muted">(aufgehoben)</span>' : '' );
		}
		return $links ? '<p class="msb-account__docs"><span class="msb-muted">Belege (PDF):</span> ' . implode( ' · ', $links ) . '</p>' : '';
	}
}
