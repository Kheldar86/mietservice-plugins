<?php
/**
 * Auslagen der Gesellschafter (1.10.0): private Auslagen erfassen, monatlich als Beleg „Auslagenabrechnung“
 * (Nummernkreis AUS-JJJJ-0001) abrechnen, vom zweiten Gesellschafter freigeben lassen (1.10.1), an das
 * Finom-Beleg-Postfach senden, als ausgezahlt markieren und für die EÜR exportieren (PAY-37 bis PAY-44, ROL-04, DSG-12).
 *
 * Eine Abrechnung wird nie geändert oder gelöscht (außer nach Ablauf der Aufbewahrungsfrist). Fehler werden
 * storniert, die Positionen sind danach wieder offen. Die Überweisung selbst bestätigt ein Gesellschafter in Finom.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Expenses {

	const PAGE     = 'msb-expenses';
	const MAX_SIZE = 10485760; // 10 MB, Grenze des Finom-Beleg-Postfachs.
	const DB       = '1.10.1'; // Stand der Tabellen msb_expenses und msb_expense_claims (1.10.1: Freigabe).
	const SMALL    = 250; // Grenze für Kleinbetragsrechnungen (§ 33 UStDV), PAY-44.
	const RULE     = 'Auslagenabrechnungen (8 Jahre)'; // Eintrag im Löschprotokoll (DSG-12).

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 6 );
		add_action( 'msb_cron_daily', array( __CLASS__, 'retention' ) );
		if ( is_admin() ) {
			add_action( 'admin_init', array( __CLASS__, 'handle_euer_export' ), 8 );
		}
	}

	/**
	 * Recht für die Auslagen (ROL-04): wie Buchungen, also Gesellschafter und Administratoren.
	 */
	public static function cap() {
		return msb_capability( 'bookings' );
	}

	/**
	 * Legt die Tabellen beim ersten Seitenaufruf nach dem Update an bzw. ergänzt sie (Datenbankversion 1.10.1).
	 */
	public static function maybe_install() {
		if ( self::DB === get_option( 'msb_expenses_db' ) ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset  = $wpdb->get_charset_collate();
		$expenses = self::t( 'expenses' );
		$claims   = self::t( 'expense_claims' );
		dbDelta(
			"CREATE TABLE {$expenses} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			spent_on date NOT NULL,
			purpose varchar(190) NOT NULL DEFAULT '',
			amount decimal(12,2) NOT NULL DEFAULT 0,
			vat_info varchar(40) NOT NULL DEFAULT '',
			receipt_file varchar(255) NOT NULL DEFAULT '',
			receipt_type varchar(10) NOT NULL DEFAULT '',
			no_receipt_reason text NULL,
			claim_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_claim (user_id,claim_id),
			KEY claim_id (claim_id)
		) {$charset};
		CREATE TABLE {$claims} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			number varchar(40) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			period_to date NOT NULL,
			amount decimal(12,2) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'issued',
			data longtext NULL,
			file varchar(255) NOT NULL DEFAULT '',
			sent_at datetime NULL DEFAULT NULL,
			paid_at datetime NULL DEFAULT NULL,
			paid_by bigint(20) unsigned NOT NULL DEFAULT 0,
			approved_at datetime NULL DEFAULT NULL,
			approved_by bigint(20) unsigned NOT NULL DEFAULT 0,
			cancel_reason text NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY number (number),
			KEY user_id (user_id)
		) {$charset};"
		);
		update_option( 'msb_expenses_db', self::DB, false );
	}

	/* ------------------------------------------------------------------
	 * Reine Hilfsfunktionen (ohne Datenbank, getestet)
	 * ------------------------------------------------------------------ */

	public static function status_labels() {
		return array(
			'issued'    => 'nicht ausgezahlt',
			'paid'      => 'ausgezahlt',
			'cancelled' => 'storniert',
		);
	}

	/**
	 * Status einer Abrechnung als Text, mit Freigabe (PAY-41, PAY-43).
	 */
	public static function status_text( array $c ) {
		if ( 'issued' === $c['status'] ) {
			return empty( $c['approved_at'] ) ? 'wartet auf Freigabe' : 'freigegeben, nicht ausgezahlt';
		}
		$labels = self::status_labels();
		return isset( $labels[ $c['status'] ] ) ? $labels[ $c['status'] ] : (string) $c['status'];
	}

	/**
	 * Vier-Augen-Prinzip (PAY-43): Freigeben darf nur, wer die Abrechnung weder bekommt noch erstellt hat.
	 */
	public static function can_approve( array $c, $user_id ) {
		$user_id = (int) $user_id;
		return $user_id > 0 && 'issued' === $c['status'] && empty( $c['approved_at'] ) && (int) $c['user_id'] !== $user_id && (int) $c['created_by'] !== $user_id;
	}

	public static function is_approved( array $c ) {
		return ! empty( $c['approved_at'] );
	}

	public static function iban_normalize( $iban ) {
		return strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $iban ) );
	}

	/**
	 * IBAN-Prüfung nach ISO 13616 (Länderkennung, Prüfziffer Modulo 97).
	 */
	public static function iban_valid( $iban ) {
		$iban = self::iban_normalize( $iban );
		if ( ! preg_match( '/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban ) ) {
			return false;
		}
		if ( 'DE' === substr( $iban, 0, 2 ) && 22 !== strlen( $iban ) ) {
			return false;
		}
		$moved = substr( $iban, 4 ) . substr( $iban, 0, 4 );
		$rest  = 0;
		foreach ( str_split( $moved ) as $ch ) {
			$digits = ctype_digit( $ch ) ? $ch : (string) ( ord( $ch ) - 55 );
			foreach ( str_split( $digits ) as $d ) {
				$rest = ( $rest * 10 + (int) $d ) % 97;
			}
		}
		return 1 === $rest;
	}

	public static function iban_format( $iban ) {
		return trim( chunk_split( self::iban_normalize( $iban ), 4, ' ' ) );
	}

	public static function iban_mask( $iban ) {
		$iban = self::iban_normalize( $iban );
		return strlen( $iban ) > 8 ? substr( $iban, 0, 4 ) . ' •••• ' . substr( $iban, -4 ) : $iban;
	}

	public static function format_number( $year, $seq ) {
		return 'AUS-' . (int) $year . '-' . str_pad( (string) (int) $seq, 4, '0', STR_PAD_LEFT );
	}

	/**
	 * Zeitraum aus den Belegdaten, z. B. „11/2026“ oder „10/2026–11/2026“.
	 */
	public static function period_label( array $dates ) {
		$dates = array_filter( array_map( 'strval', $dates ) );
		if ( ! $dates ) {
			return '';
		}
		sort( $dates );
		$first = substr( $dates[0], 5, 2 ) . '/' . substr( $dates[0], 0, 4 );
		$last  = substr( end( $dates ), 5, 2 ) . '/' . substr( end( $dates ), 0, 4 );
		return $first === $last ? $first : $first . '–' . $last;
	}

	/**
	 * Verwendungszweck der Überweisung, höchstens 140 Zeichen (SEPA).
	 */
	public static function purpose( $number, $period ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $number . ' Auslagen ' . $period, 0, 140 ) : substr( $number . ' Auslagen ' . $period, 0, 140 );
	}

	/**
	 * Inhalt des GiroCodes (EPC-QR, Version 002, UTF-8, ohne BIC).
	 */
	public static function epc_payload( $name, $iban, $amount, $purpose ) {
		$cut  = function ( $s, $n ) {
			$s = trim( preg_replace( '/[\r\n]+/', ' ', (string) $s ) );
			return function_exists( 'mb_substr' ) ? mb_substr( $s, 0, $n ) : substr( $s, 0, $n );
		};
		$rows = array( 'BCD', '002', '1', 'SCT', '', $cut( $name, 70 ), self::iban_normalize( $iban ), 'EUR' . number_format( (float) $amount, 2, '.', '' ), '', '', $cut( $purpose, 140 ) );
		return implode( "\n", $rows );
	}

	public static function total( array $positions ) {
		$sum = 0.0;
		foreach ( $positions as $p ) {
			$sum += (float) $p['amount'];
		}
		return round( $sum, 2 );
	}

	/**
	 * Betrag aus einer Eingabe wie „45,90“, „1.234,56“ oder „45.90“.
	 */
	public static function parse_amount( $in ) {
		$s = trim( str_replace( array( '€', ' ' ), '', (string) $in ) );
		if ( preg_match( '/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $s ) ) {
			$s = str_replace( '.', '', $s );
		}
		$s = str_replace( ',', '.', $s );
		return is_numeric( $s ) ? round( (float) $s, 2 ) : 0.0;
	}

	/**
	 * Zeilen für die EÜR: jede Position einer nicht stornierten Abrechnung, deren Belegdatum im Jahr liegt.
	 *
	 * @param array $claims Abrechnungen mit data['positions'].
	 */
	public static function euer_rows( array $claims, $year ) {
		$rows = array();
		foreach ( $claims as $c ) {
			if ( 'cancelled' === $c['status'] ) {
				continue;
			}
			foreach ( (array) $c['data']['positions'] as $p ) {
				if ( (int) substr( (string) $p['spent_on'], 0, 4 ) !== (int) $year ) {
					continue;
				}
				$rows[] = array(
					'date'    => (string) $p['spent_on'],
					'purpose' => (string) $p['purpose'],
					'amount'  => (float) $p['amount'],
					'partner' => (string) $c['data']['name'],
					'number'  => (string) $c['number'],
					'status'  => self::status_text( $c ),
					'paid'    => 'paid' === $c['status'] ? substr( (string) $c['paid_at'], 0, 10 ) : '',
					'own'     => '' !== trim( (string) $p['no_receipt_reason'] ) ? 'ja' : '',
				);
			}
		}
		usort(
			$rows,
			function ( $a, $b ) {
				return strcmp( $a['date'] . $a['number'], $b['date'] . $b['number'] );
			}
		);
		return $rows;
	}

	/* ------------------------------------------------------------------
	 * Datenbank
	 * ------------------------------------------------------------------ */

	private static function t( $name ) {
		return MSB_Install::table( $name );
	}

	public static function position( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'expenses' ) . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore
		return $row ? $row : null;
	}

	public static function open_positions( $user_id, $until = '' ) {
		global $wpdb;
		$sql = $wpdb->prepare( 'SELECT * FROM ' . self::t( 'expenses' ) . ' WHERE user_id = %d AND claim_id = 0', (int) $user_id ); // phpcs:ignore
		if ( $until ) {
			$sql .= $wpdb->prepare( ' AND spent_on <= %s', $until );
		}
		return (array) $wpdb->get_results( $sql . ' ORDER BY spent_on, id', ARRAY_A ); // phpcs:ignore
	}

	public static function prepare_claim( array $row ) {
		$row['data'] = json_decode( (string) $row['data'], true );
		$row['data'] = is_array( $row['data'] ) ? $row['data'] : array();
		$row['data'] += array(
			'name'      => '',
			'iban'      => '',
			'positions' => array(),
			'period'    => '',
			'purpose'   => '',
		);
		return $row;
	}

	public static function claim( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'expense_claims' ) . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore
		return $row ? self::prepare_claim( $row ) : null;
	}

	public static function claims( $year = 0 ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::t( 'expense_claims' );
		if ( $year ) {
			$sql .= $wpdb->prepare( ' WHERE YEAR(created_at) = %d', (int) $year );
		}
		return array_map( array( __CLASS__, 'prepare_claim' ), (array) $wpdb->get_results( $sql . ' ORDER BY id DESC', ARRAY_A ) ); // phpcs:ignore
	}

	/**
	 * Abrechnungen, die Positionen aus dem Jahr enthalten können (Belegdatum bis zu einem Jahr vor der Abrechnung).
	 */
	private static function claims_for_year( $year ) {
		global $wpdb;
		$sql = $wpdb->prepare( 'SELECT * FROM ' . self::t( 'expense_claims' ) . ' WHERE YEAR(created_at) BETWEEN %d AND %d ORDER BY id', (int) $year, (int) $year + 1 ); // phpcs:ignore
		return array_map( array( __CLASS__, 'prepare_claim' ), (array) $wpdb->get_results( $sql, ARRAY_A ) ); // phpcs:ignore
	}

	/* ------------------------------------------------------------------
	 * Gesellschafter und Bankverbindung
	 * ------------------------------------------------------------------ */

	public static function partner_name( $user_id ) {
		$name = trim( (string) get_user_meta( (int) $user_id, 'msb_partner_name', true ) );
		if ( '' !== $name ) {
			return $name;
		}
		$u = get_userdata( (int) $user_id );
		if ( ! $u ) {
			return '';
		}
		$full = trim( $u->first_name . ' ' . $u->last_name );
		return '' !== $full ? $full : $u->display_name;
	}

	public static function partner_iban( $user_id ) {
		return self::iban_normalize( get_user_meta( (int) $user_id, 'msb_partner_iban', true ) );
	}

	public static function save_bank( $user_id, $name, $iban ) {
		$iban = self::iban_normalize( $iban );
		if ( '' !== $iban && ! self::iban_valid( $iban ) ) {
			return new WP_Error( 'msb_iban', 'Die IBAN ist ungültig. Bitte prüfen.' );
		}
		update_user_meta( (int) $user_id, 'msb_partner_name', sanitize_text_field( $name ) );
		update_user_meta( (int) $user_id, 'msb_partner_iban', $iban );
		return true;
	}

	/* ------------------------------------------------------------------
	 * Ablage (geschützt, DSG-12)
	 * ------------------------------------------------------------------ */

	public static function dir() {
		$up  = wp_upload_dir();
		$dir = trailingslashit( $up['basedir'] ) . 'msb-belege/auslagen';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		foreach ( array( dirname( $dir ), $dir ) as $d ) {
			if ( ! file_exists( $d . '/.htaccess' ) ) {
				file_put_contents( $d . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore
			}
			if ( ! file_exists( $d . '/index.php' ) ) {
				file_put_contents( $d . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore
			}
		}
		return $dir;
	}

	public static function path( $name ) {
		$name = basename( (string) $name );
		return '' !== $name ? self::dir() . '/' . $name : '';
	}

	private static function delete_file( $name ) {
		$path = self::path( $name );
		if ( $path && file_exists( $path ) ) {
			@unlink( $path ); // phpcs:ignore
		}
	}

	/**
	 * Beleg aus dem Upload übernehmen. Fotos werden für die PDF in JPEG umgewandelt (gedreht nach EXIF,
	 * höchstens 1600 px), PDFs bleiben unverändert. Was sich nicht umwandeln lässt, bleibt als Original.
	 *
	 * @return array|WP_Error file, type (jpeg|pdf|other)
	 */
	public static function store_upload( array $f ) {
		if ( empty( $f['tmp_name'] ) || ! empty( $f['error'] ) ) {
			return new WP_Error( 'msb_upload', 'Der Beleg konnte nicht hochgeladen werden.' );
		}
		if ( ! is_uploaded_file( $f['tmp_name'] ) ) {
			return new WP_Error( 'msb_upload', 'Ungültiger Upload.' );
		}
		if ( (int) $f['size'] > self::MAX_SIZE ) {
			return new WP_Error( 'msb_upload', 'Der Beleg ist größer als 10 MB.' );
		}
		$ext  = strtolower( pathinfo( (string) $f['name'], PATHINFO_EXTENSION ) );
		$head = (string) file_get_contents( $f['tmp_name'], false, null, 0, 8 ); // phpcs:ignore
		$base = 'beleg-' . current_time( 'Ymd' ) . '-' . strtolower( wp_generate_password( 12, false ) );
		$dir  = self::dir();

		if ( 0 === strpos( $head, '%PDF' ) ) {
			$name = $base . '.pdf';
			return move_uploaded_file( $f['tmp_name'], $dir . '/' . $name ) ? array( 'file' => $name, 'type' => 'pdf' ) : new WP_Error( 'msb_upload', 'Der Beleg konnte nicht gespeichert werden.' );
		}
		if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif' ), true ) ) {
			return new WP_Error( 'msb_upload', 'Bitte ein Foto (JPG, PNG, HEIC) oder eine PDF hochladen.' );
		}
		$jpeg = self::to_jpeg( $f['tmp_name'], $dir . '/' . $base . '.jpg' );
		if ( $jpeg ) {
			return array( 'file' => $base . '.jpg', 'type' => 'jpeg' );
		}
		$name = $base . '.' . $ext;
		return move_uploaded_file( $f['tmp_name'], $dir . '/' . $name ) ? array( 'file' => $name, 'type' => 'other' ) : new WP_Error( 'msb_upload', 'Der Beleg konnte nicht gespeichert werden.' );
	}

	private static function to_jpeg( $src_file, $out ) {
		if ( ! function_exists( 'imagecreatefromstring' ) ) {
			return false;
		}
		$src = @imagecreatefromstring( (string) file_get_contents( $src_file ) ); // phpcs:ignore
		if ( ! $src ) {
			return false;
		}
		$exif = function_exists( 'exif_read_data' ) ? @exif_read_data( $src_file ) : false; // phpcs:ignore
		$turn = array( 3 => 180, 6 => -90, 8 => 90 );
		if ( $exif && isset( $exif['Orientation'], $turn[ (int) $exif['Orientation'] ] ) ) {
			$rot = imagerotate( $src, $turn[ (int) $exif['Orientation'] ], 0 );
			if ( $rot ) {
				$src = $rot;
			}
		}
		$w   = imagesx( $src );
		$h   = imagesy( $src );
		$max = 1600;
		$f   = min( 1, $max / max( $w, $h ) );
		$img = imagecreatetruecolor( (int) round( $w * $f ), (int) round( $h * $f ) );
		imagefill( $img, 0, 0, imagecolorallocate( $img, 255, 255, 255 ) );
		imagecopyresampled( $img, $src, 0, 0, 0, 0, imagesx( $img ), imagesy( $img ), $w, $h );
		$ok = imagejpeg( $img, $out, 82 );
		return $ok;
	}

	/* ------------------------------------------------------------------
	 * Auslagen erfassen (PAY-37)
	 * ------------------------------------------------------------------ */

	/**
	 * Speichert eine offene Auslage des Nutzers. $file = Ergebnis von store_upload() oder null.
	 *
	 * @return int|WP_Error
	 */
	public static function save_position( $user_id, array $in, $file = null, $id = 0 ) {
		global $wpdb;
		$date    = isset( $in['spent_on'] ) ? sanitize_text_field( $in['spent_on'] ) : '';
		$purpose = isset( $in['purpose'] ) ? sanitize_text_field( $in['purpose'] ) : '';
		$amount  = self::parse_amount( isset( $in['amount'] ) ? $in['amount'] : '' );
		$vat     = isset( $in['vat_info'] ) ? substr( sanitize_text_field( $in['vat_info'] ), 0, 40 ) : '';
		$reason  = isset( $in['no_receipt_reason'] ) ? sanitize_textarea_field( $in['no_receipt_reason'] ) : '';
		$noreceipt = ! empty( $in['no_receipt'] );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || $date > current_time( 'Y-m-d' ) ) {
			return new WP_Error( 'msb_exp', 'Bitte ein gültiges Datum angeben (nicht in der Zukunft).' );
		}
		if ( '' === $purpose ) {
			return new WP_Error( 'msb_exp', 'Bitte Lieferant und Zweck angeben.' );
		}
		if ( $amount <= 0 ) {
			return new WP_Error( 'msb_exp', 'Bitte einen Betrag größer als 0 angeben.' );
		}

		$old = $id ? self::position( $id ) : null;
		if ( $id && ( ! $old || (int) $old['user_id'] !== (int) $user_id || (int) $old['claim_id'] ) ) {
			return new WP_Error( 'msb_exp', 'Diese Auslage ist schon abgerechnet oder gehört jemand anderem.' );
		}
		$has_file = $file || ( $old && '' !== $old['receipt_file'] );
		if ( $noreceipt ) {
			if ( '' === trim( $reason ) ) {
				return new WP_Error( 'msb_exp', 'Ohne Beleg ist eine Begründung nötig (Eigenbeleg).' );
			}
		} elseif ( ! $has_file ) {
			return new WP_Error( 'msb_exp', 'Bitte ein Foto oder eine PDF des Belegs hochladen oder „Kein Beleg vorhanden“ wählen.' );
		} else {
			$reason = '';
		}

		$now  = current_time( 'mysql' );
		$data = array(
			'spent_on'          => $date,
			'purpose'           => substr( $purpose, 0, 190 ),
			'amount'            => $amount,
			'vat_info'          => $vat,
			'no_receipt_reason' => $reason,
			'updated_at'        => $now,
		);
		if ( $file ) {
			$data['receipt_file'] = $file['file'];
			$data['receipt_type'] = $file['type'];
		}
		if ( $noreceipt && $old && '' !== $old['receipt_file'] && ! $file ) {
			self::delete_file( $old['receipt_file'] );
			$data['receipt_file'] = '';
			$data['receipt_type'] = '';
		}
		if ( $old ) {
			if ( $file && '' !== $old['receipt_file'] ) {
				self::delete_file( $old['receipt_file'] );
			}
			$wpdb->update( self::t( 'expenses' ), $data, array( 'id' => (int) $id ) );
			return (int) $id;
		}
		$data += array(
			'user_id'    => (int) $user_id,
			'claim_id'   => 0,
			'created_by' => get_current_user_id(),
			'created_at' => $now,
		);
		$data += array(
			'receipt_file' => '',
			'receipt_type' => '',
		);
		$wpdb->insert( self::t( 'expenses' ), $data );
		return $wpdb->insert_id ? (int) $wpdb->insert_id : new WP_Error( 'msb_exp', 'Die Auslage konnte nicht gespeichert werden.' );
	}

	public static function delete_position( $user_id, $id ) {
		global $wpdb;
		$p = self::position( $id );
		if ( ! $p || (int) $p['user_id'] !== (int) $user_id || (int) $p['claim_id'] ) {
			return false;
		}
		self::delete_file( $p['receipt_file'] );
		return (bool) $wpdb->delete( self::t( 'expenses' ), array( 'id' => (int) $id ) );
	}

	/* ------------------------------------------------------------------
	 * Abrechnung (PAY-38, PAY-41)
	 * ------------------------------------------------------------------ */

	/**
	 * Erstellt die Abrechnung aus allen offenen Auslagen bis zum Stichtag.
	 *
	 * @return int|WP_Error ID der Abrechnung
	 */
	public static function issue_claim( $user_id, $until ) {
		global $wpdb;
		$until = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $until ) ? $until : current_time( 'Y-m-d' );
		$iban  = self::partner_iban( $user_id );
		$name  = self::partner_name( $user_id );
		if ( ! self::iban_valid( $iban ) || '' === $name ) {
			return new WP_Error( 'msb_exp', 'Bitte zuerst unter „Meine Bankverbindung“ Name und IBAN eintragen.' );
		}
		$open = self::open_positions( $user_id, $until );
		if ( ! $open ) {
			return new WP_Error( 'msb_exp', 'Bis zu diesem Stichtag gibt es keine offenen Auslagen.' );
		}
		$total = self::total( $open );
		$year  = (int) current_time( 'Y' );
		$seq   = msb_next_sequence( 'doc_expense', $year );
		if ( ! $seq ) {
			return new WP_Error( 'msb_exp', 'Belegnummer konnte nicht vergeben werden.' );
		}
		$number    = self::format_number( $year, $seq );
		$period    = self::period_label( wp_list_pluck( $open, 'spent_on' ) );
		$positions = array();
		foreach ( $open as $p ) {
			$positions[] = array(
				'id'                => (int) $p['id'],
				'spent_on'          => $p['spent_on'],
				'purpose'           => $p['purpose'],
				'amount'            => round( (float) $p['amount'], 2 ),
				'vat_info'          => $p['vat_info'],
				'receipt_file'      => $p['receipt_file'],
				'receipt_type'      => $p['receipt_type'],
				'no_receipt_reason' => $p['no_receipt_reason'],
			);
		}
		$creator = wp_get_current_user();
		$now     = current_time( 'mysql' );
		$data    = array(
			'name'       => $name,
			'iban'       => $iban,
			'positions'  => $positions,
			'period'     => $period,
			'until'      => $until,
			'purpose'    => self::purpose( $number, $period ),
			'issuer'     => MSB_Documents::issuer(),
			'created_by' => $creator && $creator->ID ? $creator->display_name : '',
		);
		$wpdb->insert(
			self::t( 'expense_claims' ),
			array(
				'number'        => $number,
				'user_id'       => (int) $user_id,
				'period_to'     => $until,
				'amount'        => $total,
				'status'        => 'issued',
				'data'          => wp_json_encode( $data ),
				'file'          => '',
				'cancel_reason' => '',
				'created_by'    => get_current_user_id(),
				'created_at'    => $now,
			)
		);
		$id = (int) $wpdb->insert_id;
		if ( ! $id ) {
			return new WP_Error( 'msb_exp', 'Die Abrechnung konnte nicht gespeichert werden.' );
		}
		$ids = implode( ',', array_map( 'intval', wp_list_pluck( $open, 'id' ) ) );
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::t( 'expenses' ) . " SET claim_id = %d WHERE claim_id = 0 AND id IN ({$ids})", $id ) ); // phpcs:ignore
		self::file( $id );
		self::notify_approvers( $id );
		do_action( 'msb_expense_claim_issued', $id );
		return $id;
	}

	/**
	 * Freigabe durch den zweiten Gesellschafter (PAY-43). Die PDF wird dabei einmal mit Freigabevermerk und
	 * GiroCode neu erzeugt (Inhalt und Beträge bleiben gleich) und erst jetzt an Finom gesendet (PAY-40).
	 */
	public static function approve( $id ) {
		global $wpdb;
		$c   = self::claim( $id );
		$uid = get_current_user_id();
		if ( ! $c || ! self::can_approve( $c, $uid ) ) {
			return false;
		}
		$wpdb->update(
			self::t( 'expense_claims' ),
			array(
				'approved_at' => current_time( 'mysql' ),
				'approved_by' => $uid,
				'file'        => '',
			),
			array( 'id' => (int) $id )
		);
		self::delete_file( $c['file'] );
		self::file( $id );
		if ( is_email( msb_setting( 'expense_finom_email' ) ) ) {
			self::send( $id );
		}
		do_action( 'msb_expense_claim_approved', $id );
		return true;
	}

	/**
	 * Mail an die anderen Gesellschafter (Rolle Gesellschafter und Admin-Adresse), dass eine Abrechnung auf Freigabe wartet.
	 */
	public static function notify_approvers( $id ) {
		$c = self::claim( $id );
		if ( ! $c ) {
			return;
		}
		$skip = array();
		foreach ( array( $c['user_id'], $c['created_by'] ) as $uid ) {
			$u = get_userdata( (int) $uid );
			if ( $u ) {
				$skip[] = strtolower( $u->user_email );
			}
		}
		$to = array();
		foreach ( get_users( array( 'role' => 'msb_partner', 'fields' => array( 'user_email' ) ) ) as $u ) {
			$to[] = $u->user_email;
		}
		$to[] = (string) msb_setting( 'admin_email' );
		$to   = array_unique( array_filter( array_map( 'strtolower', $to ), 'is_email' ) );
		$to   = array_diff( $to, $skip );
		if ( ! $to ) {
			return;
		}
		$url  = admin_url( 'admin.php?page=' . self::PAGE . '&tab=claims&claim=' . (int) $id . '#msb-pay' );
		$body = '<p>' . esc_html( 'Die Auslagenabrechnung ' . $c['number'] . ' von ' . $c['data']['name'] . ' über ' . msb_price( $c['amount'] ) . ' (Zeitraum ' . $c['data']['period'] . ') wartet auf deine Freigabe.' ) . '</p>'
			. '<p>' . esc_html( 'Freigeben kann nur der andere Gesellschafter (Vier-Augen-Prinzip). Erst danach wird ausgezahlt und an Finom gesendet.' ) . '</p>'
			. '<p><a href="' . esc_url( $url ) . '">Abrechnung prüfen und freigeben</a></p>';
		foreach ( $to as $mail ) {
			MSB_Emails::send_raw( $mail, 'Freigabe nötig: Auslagenabrechnung ' . $c['number'] . ' – ' . msb_price( $c['amount'] ), $body );
		}
	}

	public static function mark_paid( $id, $date ) {
		global $wpdb;
		$c = self::claim( $id );
		if ( ! $c || 'issued' !== $c['status'] || ! self::is_approved( $c ) ) {
			return false;
		}
		$date = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ? $date : current_time( 'Y-m-d' );
		return (bool) $wpdb->update(
			self::t( 'expense_claims' ),
			array(
				'status'  => 'paid',
				'paid_at' => $date . ' 00:00:00',
				'paid_by' => get_current_user_id(),
			),
			array( 'id' => (int) $id )
		);
	}

	/**
	 * Storno mit Grund: Die Nummer bleibt belegt, die Positionen sind wieder offen.
	 */
	public static function cancel( $id, $reason ) {
		global $wpdb;
		$c      = self::claim( $id );
		$reason = trim( sanitize_textarea_field( (string) $reason ) );
		if ( ! $c || 'issued' !== $c['status'] || '' === $reason ) {
			return false;
		}
		$wpdb->update(
			self::t( 'expense_claims' ),
			array(
				'status'        => 'cancelled',
				'cancel_reason' => $reason . ' (' . wp_get_current_user()->display_name . ', ' . current_time( 'd.m.Y H:i' ) . ')',
			),
			array( 'id' => (int) $id )
		);
		$wpdb->update( self::t( 'expenses' ), array( 'claim_id' => 0 ), array( 'claim_id' => (int) $id ) );
		return true;
	}

	/* ------------------------------------------------------------------
	 * PDF (PAY-38, PAY-39)
	 * ------------------------------------------------------------------ */

	/**
	 * Pfad zur PDF der Abrechnung; wird beim ersten Mal erzeugt und danach nicht mehr verändert.
	 */
	public static function file( $id ) {
		global $wpdb;
		$c = self::claim( $id );
		if ( ! $c ) {
			return '';
		}
		if ( $c['file'] && file_exists( self::path( $c['file'] ) ) ) {
			return self::path( $c['file'] );
		}
		$name = $c['file'] ? $c['file'] : sanitize_file_name( $c['number'] ) . '-' . strtolower( wp_generate_password( 10, false ) ) . '.pdf';
		if ( false === file_put_contents( self::path( $name ), self::render( $c ) ) ) { // phpcs:ignore
			return '';
		}
		if ( $name !== $c['file'] ) {
			$wpdb->update( self::t( 'expense_claims' ), array( 'file' => $name ), array( 'id' => (int) $c['id'] ) );
		}
		return self::path( $name );
	}

	public static function render( array $c ) {
		$d     = $c['data'];
		$is    = isset( $d['issuer'] ) && is_array( $d['issuer'] ) ? $d['issuer'] : MSB_Documents::issuer();
		$pdf   = new MSB_PDF( 'Auslagenabrechnung ' . $c['number'] );
		$left  = 20;
		$right = 190;
		$pdf->add_page();

		// Kopf: Logo links, auszahlende GbR rechts.
		$logo = MSB_Documents::logo_jpeg();
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
		$pdf->text( $right, $y, 'Auszahlende Gesellschaft', 8, false, 'R', 0.45 );
		$y += 5;
		$pdf->text( $right, $y, $is['name'], 12, true, 'R' );
		foreach ( (array) $is['address'] as $line ) {
			$y += 5;
			$pdf->text( $right, $y, $line, 9, false, 'R', 0.3 );
		}

		// Titel.
		$y = 56;
		$pdf->text( $left, $y, 'Auslagenabrechnung / Eigenbeleg', 16, true );
		$y += 6;
		$pdf->text( $left, $y, 'Aufstellung privater Auslagen eines Gesellschafters für die GbR', 9, false, 'L', 0.35 );

		// Zahlungsempfänger links, Belegdaten rechts.
		$y += 12;
		$pdf->text( $left, $y, 'Zahlungsempfänger (Gesellschafter)', 8, false, 'L', 0.45 );
		$pdf->text( $left, $y + 6, $d['name'], 11, true );
		$pdf->text( $left, $y + 11.5, 'IBAN: ' . self::iban_format( $d['iban'] ), 10 );
		$meta = array(
			array( 'Beleg-Nr.', $c['number'] ),
			array( 'Datum', mysql2date( 'd.m.Y', $c['created_at'] ) ),
			array( 'Zeitraum', $d['period'] ),
		);
		$my   = $y;
		foreach ( $meta as $m ) {
			$pdf->text( 150, $my, $m[0] . ':', 9, false, 'R', 0.35 );
			$pdf->text( 152, $my, $m[1], 9, true );
			$my += 5;
		}

		// Positionen.
		$y += 24;
		$pdf->rect( $left, $y - 5, $right - $left, 7.5, 0.93 );
		$pdf->text( $left + 2, $y, 'Pos.', 9, true );
		$pdf->text( $left + 13, $y, 'Datum', 9, true );
		$pdf->text( $left + 35, $y, 'Lieferant / Verwendungszweck', 9, true );
		$pdf->text( $right - 2, $y, 'Betrag (brutto)', 9, true, 'R' );
		$y      += 8;
		$annexes = array();
		foreach ( (array) $d['positions'] as $i => $p ) {
			if ( $y > 245 ) {
				$pdf->add_page();
				$y = 25;
			}
			$pos   = $i + 1;
			$notes = array();
			if ( '' !== trim( (string) $p['vat_info'] ) ) {
				$notes[] = 'Darin MwSt. laut Beleg: ' . $p['vat_info'] . ' (nur zur Information)';
			}
			if ( '' !== trim( (string) $p['no_receipt_reason'] ) ) {
				$notes[] = 'Eigenbeleg, kein Originalbeleg: ' . $p['no_receipt_reason'];
			} elseif ( 'jpeg' === $p['receipt_type'] ) {
				$annexes[] = array( $pos, $p );
				$notes[]   = 'Beleg: Anlage ' . count( $annexes ) . ' (Folgeseite)';
			} elseif ( '' !== (string) $p['receipt_file'] ) {
				$notes[] = 'Beleg: ' . ( 'pdf' === $p['receipt_type'] ? 'PDF' : 'Datei' ) . ', separat beigefügt';
			}
			$pdf->text( $left + 2, $y, (string) $pos, 10 );
			$pdf->text( $left + 13, $y, mysql2date( 'd.m.Y', $p['spent_on'] ), 10 );
			$first = true;
			foreach ( $pdf->wrap( $p['purpose'], 105, 10 ) as $line ) {
				$pdf->text( $left + 35, $y, $line, 10 );
				if ( $first ) {
					$pdf->text( $right - 2, $y, msb_price( $p['amount'] ), 10, false, 'R' );
					$first = false;
				}
				$y += 5;
			}
			foreach ( $notes as $n ) {
				foreach ( $pdf->wrap( $n, 105, 8.5 ) as $line ) {
					$pdf->text( $left + 35, $y, $line, 8.5, false, 'L', 0.4 );
					$y += 4.3;
				}
			}
			$pdf->line( $left, $y - 2.5, $right, $y - 2.5 );
			$y += 4;
		}
		$y += 3;
		$pdf->text( $left + 35, $y, 'Gesamtbetrag (Erstattungsbetrag)', 11, true );
		$pdf->text( $right - 2, $y, msb_price( $c['amount'] ), 11, true, 'R' );
		$y += 3;
		$pdf->line( $left, $y, $right, $y, 0.4, 0.2 );
		$y += 10;

		// Hinweise.
		$texts = array(
			'Verrechnungs- und Zahlungshinweis: Der Gesamtbetrag wurde vom Gesellschafter privat für Zwecke der Gesellschaft ausgelegt. Die Auszahlung erfolgt per Überweisung vom Geschäftskonto der GbR auf das genannte Privatkonto des Gesellschafters.',
			'Verwendungszweck: ' . $d['purpose'],
			'Die Gesellschaft ist Kleinunternehmer nach § 19 UStG. Alle Beträge sind Bruttobeträge, ein Vorsteuerabzug findet nicht statt.',
			'Erstellt im Backend von ' . ( '' !== (string) ( isset( $d['created_by'] ) ? $d['created_by'] : '' ) ? $d['created_by'] : 'unbekannt' ) . ' am ' . mysql2date( 'd.m.Y \u\m H:i', $c['created_at'] ) . ' Uhr.',
			self::is_approved( $c )
				? 'Freigegeben (Vier-Augen-Prinzip) von ' . self::partner_name( isset( $c['approved_by'] ) ? $c['approved_by'] : 0 ) . ' am ' . mysql2date( 'd.m.Y \u\m H:i', $c['approved_at'] ) . ' Uhr.'
				: 'Freigabe durch den zweiten Gesellschafter: ausstehend. Erst nach der Freigabe wird ausgezahlt.',
		);
		if ( 'cancelled' === $c['status'] ) {
			array_unshift( $texts, 'STORNIERT: ' . $c['cancel_reason'] );
		}
		foreach ( $texts as $n ) {
			if ( $y > 262 ) {
				$pdf->add_page();
				$y = 25;
			}
			foreach ( $pdf->wrap( $n, 120, 9.5 ) as $line ) {
				$pdf->text( $left, $y, $line, 9.5 );
				$y += 4.8;
			}
			$y += 2;
		}

		// GiroCode rechts neben den Hinweisen (EPC-QR).
		if ( class_exists( 'MSB_QR' ) && 'cancelled' !== $c['status'] && self::is_approved( $c ) ) {
			$m = MSB_QR::matrix( self::epc_payload( $d['name'], $d['iban'], $c['amount'], $d['purpose'] ) );
			if ( $m ) {
				$box = 32;
				$qy  = max( 25, min( 240, $y - 40 ) );
				$cell = $box / ( count( $m ) + 8 );
				$qx   = $right - $box;
				foreach ( $m as $ry => $row ) {
					foreach ( $row as $rx => $dark ) {
						if ( $dark ) {
							$pdf->rect( $qx + ( $rx + 4 ) * $cell, $qy + ( $ry + 4 ) * $cell, $cell + 0.02, $cell + 0.02, 0 );
						}
					}
				}
				$pdf->text( $qx + $box / 2, $qy + $box + 4, 'GiroCode zur Überweisung', 7.5, false, 'C', 0.45 );
			}
		}

		// Fußzeile.
		$pdf->text( 105, 284, $is['name'] . ' · ' . implode( ' · ', (array) $is['address'] ) . ( ! empty( $is['tax'] ) ? ' · Steuernummer ' . $is['tax'] : '' ), 7.5, false, 'C', 0.45 );

		// Anlagen: je Fotobeleg eine Seite.
		foreach ( $annexes as $k => $a ) {
			list( $pos, $p ) = $a;
			$file            = self::path( $p['receipt_file'] );
			$pdf->add_page();
			$pdf->text( $left, 20, 'Anlage ' . ( $k + 1 ) . ' zu ' . $c['number'] . ', Pos. ' . $pos, 12, true );
			$sub = $pdf->wrap( mysql2date( 'd.m.Y', $p['spent_on'] ) . ' · ' . $p['purpose'] . ' · ' . msb_price( $p['amount'] ), $right - $left, 9 );
			$pdf->text( $left, 26, $sub[0], 9, false, 'L', 0.35 );
			$info = $file && file_exists( $file ) ? @getimagesize( $file ) : false; // phpcs:ignore
			if ( $info && $info[0] > 0 && $info[1] > 0 ) {
				$mw = $right - $left;
				$mh = 245;
				$f  = min( $mw / $info[0], $mh / $info[1] );
				$pdf->image( $file, $left, 32, $info[0] * $f, $info[1] * $f );
			} else {
				$pdf->text( $left, 40, 'Das Foto des Belegs ist nicht mehr vorhanden.', 10 );
			}
		}
		return $pdf->output();
	}

	/* ------------------------------------------------------------------
	 * Versand an Finom (PAY-40)
	 * ------------------------------------------------------------------ */

	/**
	 * Dateien, die zusammen mit der Abrechnung verschickt werden: PDF der Abrechnung, PDF-Belege und
	 * Fotos, die sich nicht in die PDF einfügen ließen.
	 */
	public static function attachments( array $c ) {
		$files = array( self::file( $c['id'] ) );
		foreach ( (array) $c['data']['positions'] as $p ) {
			if ( '' !== (string) $p['receipt_file'] && 'jpeg' !== $p['receipt_type'] ) {
				$path = self::path( $p['receipt_file'] );
				if ( $path && file_exists( $path ) ) {
					$files[] = $path;
				}
			}
		}
		return array_values( array_filter( $files ) );
	}

	public static function send( $id ) {
		global $wpdb;
		$c  = self::claim( $id );
		$to = (string) msb_setting( 'expense_finom_email' );
		if ( ! $c || ! is_email( $to ) ) {
			return false;
		}
		$files   = self::attachments( $c );
		$subject = 'Auslagenabrechnung ' . $c['number'] . ' – ' . $c['data']['name'] . ' – ' . msb_price( $c['amount'] );
		$body    = '<p>' . esc_html( 'Auslagenabrechnung ' . $c['number'] . ' von ' . $c['data']['name'] . ' über ' . msb_price( $c['amount'] ) . ' (Zeitraum ' . $c['data']['period'] . ').' ) . '</p>'
			. '<p>' . esc_html( 'Verwendungszweck der Erstattung: ' . $c['data']['purpose'] ) . '</p>'
			. '<p>' . esc_html( 'Anhänge: ' . count( $files ) . ' (Abrechnung und separate Belege).' ) . '</p>';
		$ok = MSB_Emails::send_raw( $to, $subject, $body, $files );
		$admin = (string) msb_setting( 'admin_email' );
		if ( is_email( $admin ) && strtolower( $admin ) !== strtolower( $to ) ) {
			MSB_Emails::send_raw( $admin, $subject . ( $ok ? ' (an Finom gesendet)' : ' (Versand an Finom fehlgeschlagen)' ), $body, $files );
		}
		if ( $ok ) {
			$wpdb->update( self::t( 'expense_claims' ), array( 'sent_at' => current_time( 'mysql' ) ), array( 'id' => (int) $id ) );
		}
		return $ok;
	}

	/* ------------------------------------------------------------------
	 * EÜR (PAY-42)
	 * ------------------------------------------------------------------ */

	public static function box( $year ) {
		if ( ! current_user_can( self::cap() ) ) {
			return '';
		}
		$rows = self::euer_rows( self::claims_for_year( $year ), $year );
		$paid = 0.0;
		$open = 0.0;
		foreach ( $rows as $r ) {
			if ( '' !== $r['paid'] ) {
				$paid += $r['amount'];
			} else {
				$open += $r['amount'];
			}
		}
		$url = wp_nonce_url( admin_url( 'admin.php?page=' . self::PAGE . '&euer=csv&year=' . (int) $year ), 'msb_expense_export' );
		ob_start();
		?>
		<div class="msb-card-box">
			<h2>Auslagen <?php echo (int) $year; ?> für die EÜR</h2>
			<p>Abgerechnete Auslagen mit Belegdatum <?php echo (int) $year; ?>: <strong><?php echo esc_html( msb_price( $paid + $open ) ); ?></strong> · davon ausgezahlt: <strong><?php echo esc_html( msb_price( $paid ) ); ?></strong> · noch nicht ausgezahlt: <strong><?php echo esc_html( msb_price( $open ) ); ?></strong></p>
			<p><a class="button" href="<?php echo esc_url( $url ); ?>">Auslagen <?php echo (int) $year; ?> als CSV (EÜR)</a></p>
			<p class="description">Jede Position einzeln mit Belegdatum, Zweck, Betrag (brutto), Gesellschafter, Abrechnungsnummer, Status und Erstattungsdatum. Stornierte Abrechnungen fehlen, ihre Positionen zählen mit der neuen Abrechnung. Ob das Belegdatum oder das Erstattungsdatum maßgeblich ist, klärt der Steuerberater (die CSV enthält beide).</p>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function handle_euer_export() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! isset( $_GET['page'], $_GET['euer'] ) || self::PAGE !== $_GET['page'] ) {
			return;
		}
		check_admin_referer( 'msb_expense_export' );
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		$year = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : (int) current_time( 'Y' );
		// phpcs:enable
		$rows = self::euer_rows( self::claims_for_year( $year ), $year );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="auslagen-euer-' . $year . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore
		fputcsv( $out, array( 'Belegdatum', 'Lieferant / Zweck', 'Betrag brutto', 'Gesellschafter', 'Abrechnung', 'Status', 'Erstattet am', 'Eigenbeleg' ), ';' );
		foreach ( $rows as $r ) {
			fputcsv(
				$out,
				array(
					mysql2date( 'd.m.Y', $r['date'] ),
					$r['purpose'],
					number_format( $r['amount'], 2, ',', '' ),
					$r['partner'],
					$r['number'],
					$r['status'],
					$r['paid'] ? mysql2date( 'd.m.Y', $r['paid'] ) : '',
					$r['own'],
				),
				';'
			);
		}
		fclose( $out ); // phpcs:ignore
		exit;
	}

	/* ------------------------------------------------------------------
	 * Aufbewahrung (DSG-12)
	 * ------------------------------------------------------------------ */

	/**
	 * Täglicher Lauf zusammen mit den Löschfristen: im Probelauf nur zählen, scharf löschen. Beides steht im Löschprotokoll.
	 */
	public static function retention() {
		global $wpdb;
		$ids = self::expired_claims();
		if ( ! $ids ) {
			return;
		}
		$live = class_exists( 'MSB_Retention' ) && MSB_Retention::live();
		if ( $live ) {
			foreach ( $ids as $id ) {
				self::purge_claim( $id );
			}
		}
		$wpdb->insert(
			self::t( 'deletion_log' ),
			array(
				'run_at' => current_time( 'mysql' ),
				'rule'   => self::RULE,
				'count'  => count( $ids ),
				'dry'    => $live ? 0 : 1,
			)
		);
	}

	/**
	 * Abrechnungen, deren Frist (8 Jahre ab Ende des Jahres der Abrechnung) abgelaufen ist.
	 */
	public static function expired_claims() {
		global $wpdb;
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . self::t( 'expense_claims' ) . ' WHERE YEAR(created_at) + 8 < %d', (int) current_time( 'Y' ) ) ) ); // phpcs:ignore
	}

	/**
	 * Löscht eine Abrechnung samt PDF. Positionen und Belegdateien nur, wenn sie zu dieser Abrechnung gehören.
	 */
	public static function purge_claim( $id ) {
		global $wpdb;
		$c = self::claim( $id );
		if ( ! $c ) {
			return;
		}
		self::delete_file( $c['file'] );
		foreach ( (array) $c['data']['positions'] as $p ) {
			$still = $wpdb->get_var( $wpdb->prepare( 'SELECT claim_id FROM ' . self::t( 'expenses' ) . ' WHERE id = %d', (int) $p['id'] ) ); // phpcs:ignore
			if ( null === $still || (int) $still === (int) $id ) {
				self::delete_file( $p['receipt_file'] );
				$wpdb->delete( self::t( 'expenses' ), array( 'id' => (int) $p['id'] ) );
			}
		}
		$wpdb->delete( self::t( 'expense_claims' ), array( 'id' => (int) $id ) );
	}
}
