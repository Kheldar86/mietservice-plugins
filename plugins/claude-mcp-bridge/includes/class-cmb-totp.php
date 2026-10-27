<?php
defined( 'ABSPATH' ) || exit;

/**
 * Zweiter Faktor für Freigaben: TOTP nach RFC 6238 (SHA-1, 6 Stellen, 30 s).
 * Kompatibel mit Aegis Authenticator und allen gängigen Authenticator-Apps.
 *
 * - Secret wird mit libsodium (secretbox) verschlüsselt in den User-Meta gespeichert;
 *   der Schlüssel stammt aus den WordPress-Salts (nicht aus der Datenbank).
 * - Jeder Code ist nur einmal gültig (Replay-Schutz über den zuletzt genutzten Zeitschritt).
 * - 10 Backup-Codes, nur als HMAC gespeichert, jeweils einmal nutzbar.
 */
final class CMB_TOTP {

	const META_SECRET  = '_cmb_totp_secret';
	const META_PENDING = '_cmb_totp_pending';
	const META_LAST    = '_cmb_totp_last_step';
	const META_BACKUP  = '_cmb_totp_backup';
	const ISSUER       = 'Claude MCP Bridge';

	/* --------------------------------------------------------------- base32 */

	public static function base32_encode( $bin ) {
		$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$bits     = '';
		foreach ( str_split( $bin ) as $c ) {
			$bits .= str_pad( decbin( ord( $c ) ), 8, '0', STR_PAD_LEFT );
		}
		$out = '';
		foreach ( str_split( $bits, 5 ) as $chunk ) {
			$out .= $alphabet[ bindec( str_pad( $chunk, 5, '0' ) ) ];
		}
		return $out;
	}

	public static function base32_decode( $str ) {
		$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$str      = strtoupper( preg_replace( '/[\s=]/', '', (string) $str ) );
		$bits     = '';
		foreach ( str_split( $str ) as $c ) {
			$i = strpos( $alphabet, $c );
			if ( false === $i ) {
				return false;
			}
			$bits .= str_pad( decbin( $i ), 5, '0', STR_PAD_LEFT );
		}
		$out = '';
		foreach ( str_split( $bits, 8 ) as $byte ) {
			if ( 8 === strlen( $byte ) ) {
				$out .= chr( bindec( $byte ) );
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------ crypto */

	private static function key() {
		return hash( 'sha256', wp_salt( 'secure_auth' ) . '|cmb-totp', true );
	}

	private static function encrypt( $plain ) {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, self::key() ) );
	}

	private static function decrypt( $stored ) {
		$raw = base64_decode( (string) $stored, true );
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return false;
		}
		$nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $nonce, self::key() );
	}

	/* -------------------------------------------------------------- TOTP */

	public static function code_at( $secret_bin, $step ) {
		$msg  = pack( 'N*', 0, $step ); // 64-bit big-endian Zähler
		$hash = hash_hmac( 'sha1', $msg, $secret_bin, true );
		$off  = ord( $hash[19] ) & 0x0f;
		$num  = ( ( ord( $hash[ $off ] ) & 0x7f ) << 24 ) | ( ord( $hash[ $off + 1 ] ) << 16 ) | ( ord( $hash[ $off + 2 ] ) << 8 ) | ord( $hash[ $off + 3 ] );
		return str_pad( (string) ( $num % 1000000 ), 6, '0', STR_PAD_LEFT );
	}

	private static function now_step() {
		return (int) floor( time() / 30 );
	}

	public static function is_enabled( $user_id ) {
		return '' !== (string) get_user_meta( $user_id, self::META_SECRET, true );
	}

	/* ------------------------------------------------------------- setup */

	/** Startet die Einrichtung und gibt das Base32-Secret zurück (nur zur Anzeige). */
	public static function start_setup( $user_id ) {
		$secret = random_bytes( 20 );
		update_user_meta( $user_id, self::META_PENDING, self::encrypt( $secret ) . '|' . ( time() + 900 ) );
		return self::base32_encode( $secret );
	}

	public static function pending_secret( $user_id ) {
		$raw = (string) get_user_meta( $user_id, self::META_PENDING, true );
		if ( '' === $raw || false === strpos( $raw, '|' ) ) {
			return null;
		}
		list( $enc, $exp ) = explode( '|', $raw, 2 );
		if ( (int) $exp < time() ) {
			delete_user_meta( $user_id, self::META_PENDING );
			return null;
		}
		$bin = self::decrypt( $enc );
		return false === $bin ? null : $bin;
	}

	public static function otpauth_uri( WP_User $user, $base32 ) {
		$label = rawurlencode( self::ISSUER . ' (' . wp_parse_url( home_url(), PHP_URL_HOST ) . '):' . $user->user_login );
		return 'otpauth://totp/' . $label . '?secret=' . $base32 . '&issuer=' . rawurlencode( self::ISSUER ) . '&algorithm=SHA1&digits=6&period=30';
	}

	/** Bestätigt die Einrichtung mit einem gültigen Code. Gibt Backup-Codes (Klartext, einmalig) zurück. */
	public static function confirm_setup( $user_id, $code ) {
		$secret = self::pending_secret( $user_id );
		if ( null === $secret ) {
			return new WP_Error( 'expired', 'Die Einrichtung ist abgelaufen. Bitte neu starten.' );
		}
		$step = self::match_code( $secret, $code, 0 );
		if ( false === $step ) {
			return new WP_Error( 'code', 'Der Code stimmt nicht. Uhrzeit des Handys prüfen und erneut versuchen.' );
		}
		update_user_meta( $user_id, self::META_SECRET, self::encrypt( $secret ) );
		update_user_meta( $user_id, self::META_LAST, $step );
		delete_user_meta( $user_id, self::META_PENDING );
		return self::new_backup_codes( $user_id );
	}

	public static function new_backup_codes( $user_id ) {
		$plain  = array();
		$hashes = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$c        = strtolower( bin2hex( random_bytes( 2 ) ) . '-' . bin2hex( random_bytes( 2 ) ) );
			$plain[]  = $c;
			$hashes[] = CMB_Security::hash( 'backup|' . $user_id . '|' . $c );
		}
		update_user_meta( $user_id, self::META_BACKUP, $hashes );
		return $plain;
	}

	public static function backup_count( $user_id ) {
		return count( (array) get_user_meta( $user_id, self::META_BACKUP, true ) );
	}

	public static function disable( $user_id ) {
		foreach ( array( self::META_SECRET, self::META_PENDING, self::META_LAST, self::META_BACKUP ) as $k ) {
			delete_user_meta( $user_id, $k );
		}
	}

	/* ------------------------------------------------------------ verify */

	/** @return int|false gefundener Zeitschritt */
	private static function match_code( $secret_bin, $code, $min_step_exclusive ) {
		$code = preg_replace( '/\s+/', '', (string) $code );
		if ( ! preg_match( '/^\d{6}$/', $code ) ) {
			return false;
		}
		$now   = self::now_step();
		$found = false;
		foreach ( array( -1, 0, 1 ) as $d ) {
			$step = $now + $d;
			// Alle Kandidaten prüfen (keine frühe Rückkehr), konstante Vergleiche.
			if ( hash_equals( self::code_at( $secret_bin, $step ), $code ) && $step > $min_step_exclusive ) {
				$found = $step;
			}
		}
		return $found;
	}

	/**
	 * Prüft einen TOTP- oder Backup-Code. Verbraucht ihn bei Erfolg.
	 */
	public static function verify( $user_id, $code ) {
		$enc = (string) get_user_meta( $user_id, self::META_SECRET, true );
		if ( '' === $enc ) {
			return false;
		}
		$code = trim( strtolower( (string) $code ) );
		if ( preg_match( '/^[0-9a-f]{4}-[0-9a-f]{4}$/', $code ) ) {
			$hashes = (array) get_user_meta( $user_id, self::META_BACKUP, true );
			$h      = CMB_Security::hash( 'backup|' . $user_id . '|' . $code );
			foreach ( $hashes as $i => $stored ) {
				if ( hash_equals( (string) $stored, $h ) ) {
					unset( $hashes[ $i ] );
					update_user_meta( $user_id, self::META_BACKUP, array_values( $hashes ) );
					CMB_Security::audit( 'totp_backup_used', array( 'left' => count( $hashes ) ), $user_id );
					return true;
				}
			}
			return false;
		}
		$secret = self::decrypt( $enc );
		if ( false === $secret ) {
			return false;
		}
		$last = (int) get_user_meta( $user_id, self::META_LAST, true );
		$step = self::match_code( $secret, $code, $last );
		if ( false === $step ) {
			return false;
		}
		update_user_meta( $user_id, self::META_LAST, $step );
		return true;
	}
}
