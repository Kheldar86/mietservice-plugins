<?php
defined( 'ABSPATH' ) || exit;

final class CMB_Security {

	/** URL-sicheres Zufallstoken aus random_bytes (CSPRNG). */
	public static function random_token( $bytes = 32 ) {
		return rtrim( strtr( base64_encode( random_bytes( $bytes ) ), '+/', '-_' ), '=' );
	}

	/** HMAC mit dem WordPress-Salt: ein DB-Leak allein liefert keine verwertbaren Tokens. */
	public static function hash( $value ) {
		return hash_hmac( 'sha256', (string) $value, wp_salt( 'auth' ) . '|cmb' );
	}

	public static function b64url( $bin ) {
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
	}

	/** PKCE S256 nach RFC 7636. */
	public static function pkce_valid( $verifier, $challenge ) {
		if ( ! is_string( $verifier ) || ! preg_match( '/^[A-Za-z0-9\-._~]{43,128}$/', $verifier ) ) {
			return false;
		}
		return hash_equals( (string) $challenge, self::b64url( hash( 'sha256', $verifier, true ) ) );
	}

	/**
	 * Client-IP. Absichtlich nur REMOTE_ADDR – X-Forwarded-For ist vom Client fälschbar.
	 */
	public static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/** Für das Protokoll: letzte Stelle der IP kürzen (Datensparsamkeit). */
	public static function anon_ip( $ip ) {
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return preg_replace( '/\.\d+$/', '.0', $ip );
		}
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$bin = inet_pton( $ip );
			return inet_ntop( substr( $bin, 0, 6 ) . str_repeat( "\0", 10 ) );
		}
		return '';
	}

	public static function ip_in_cidr( $ip, $cidr ) {
		$cidr = trim( $cidr );
		if ( false === strpos( $cidr, '/' ) ) {
			$cidr .= filter_var( $cidr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ? '/128' : '/32';
		}
		list( $net, $bits ) = explode( '/', $cidr, 2 );
		$ip_bin  = @inet_pton( $ip );
		$net_bin = @inet_pton( $net );
		if ( false === $ip_bin || false === $net_bin || strlen( $ip_bin ) !== strlen( $net_bin ) || ! ctype_digit( $bits ) ) {
			return false;
		}
		$bits = (int) $bits;
		if ( $bits > strlen( $ip_bin ) * 8 ) {
			return false;
		}
		$bytes = intdiv( $bits, 8 );
		if ( substr( $ip_bin, 0, $bytes ) !== substr( $net_bin, 0, $bytes ) ) {
			return false;
		}
		$rem = $bits % 8;
		if ( 0 === $rem ) {
			return true;
		}
		$mask = chr( ( 0xff << ( 8 - $rem ) ) & 0xff );
		return ( $ip_bin[ $bytes ] & $mask ) === ( $net_bin[ $bytes ] & $mask );
	}

	public static function valid_cidr_list( $list ) {
		foreach ( self::split_list( $list ) as $cidr ) {
			$parts = explode( '/', $cidr, 2 );
			if ( ! filter_var( $parts[0], FILTER_VALIDATE_IP ) ) {
				return false;
			}
			if ( isset( $parts[1] ) && ( ! ctype_digit( $parts[1] ) || (int) $parts[1] > 128 ) ) {
				return false;
			}
		}
		return true;
	}

	public static function split_list( $list ) {
		return array_values( array_filter( array_map( 'trim', preg_split( '/[\s,]+/', (string) $list ) ) ) );
	}

	/** Optionale IP-Allowlist für alle maschinellen Endpunkte (MCP, Token, Registrierung). */
	public static function ip_allowed() {
		if ( ! CMB_Settings::get( 'ip_allowlist_on' ) ) {
			return true;
		}
		$ip = self::client_ip();
		foreach ( self::split_list( CMB_Settings::get( 'ip_allowlist' ) ) as $cidr ) {
			if ( self::ip_in_cidr( $ip, $cidr ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Einfaches Fixed-Window-Rate-Limit über Transients.
	 * Gibt false zurück, wenn das Limit überschritten ist.
	 */
	public static function rate_limit( $bucket, $limit, $window ) {
		$key   = 'cmb_rl_' . md5( $bucket . '|' . floor( time() / $window ) );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $key, $count + 1, $window + 5 );
		return true;
	}

	public static function audit( $event, array $detail = array(), $user_id = null, $client_id = '' ) {
		global $wpdb;
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}
		$json = $detail ? wp_json_encode( $detail ) : null;
		if ( $json && strlen( $json ) > 2000 ) {
			$json = substr( $json, 0, 2000 );
		}
		$wpdb->insert(
			CMB_DB::t( 'audit' ),
			array(
				'created_at' => time(),
				'user_id'    => (int) $user_id,
				'client_id'  => substr( (string) $client_id, 0, 64 ),
				'event'      => substr( $event, 0, 40 ),
				'ip'         => self::anon_ip( self::client_ip() ),
				'detail'     => $json,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/** Header für alle Seiten/Antworten des Plugins außerhalb von wp-admin. */
	public static function no_store_headers( $html = false ) {
		if ( headers_sent() ) {
			return;
		}
		header( 'Cache-Control: no-store' );
		header( 'Pragma: no-cache' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Referrer-Policy: no-referrer' );
		if ( $html ) {
			header( 'X-Frame-Options: DENY' );
			header( "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self' https://claude.ai http://localhost:* http://127.0.0.1:*; frame-ancestors 'none'; base-uri 'none'" );
		}
	}

	/**
	 * Erlaubte Redirect-URIs: nur die Claude-Callback-URL, optional Loopback (Claude Code).
	 */
	public static function redirect_uri_allowed( $uri ) {
		if ( ! is_string( $uri ) || strlen( $uri ) > 300 ) {
			return false;
		}
		if ( CMB_Settings::CLAUDE_CALLBACK === $uri ) {
			return true;
		}
		if ( CMB_Settings::get( 'allow_loopback' ) ) {
			return (bool) preg_match( '#^http://(localhost|127\.0\.0\.1)(:\d{1,5})?/callback$#', $uri );
		}
		return false;
	}

	/* ---------------------------------------------------------- Schwärzung */

	const REDACTED = '[geschwärzt]';

	/** Schlüsselnamen, deren Werte nie an Claude gehen (auch in serialisierten Optionen). */
	const SECRET_KEY_RE = '/(secret|passw|^pass$|_pass$|(api|private|access|auth|license|encryption|signing|activation)_?key|^key$|token|webhook|credential)/i';

	/**
	 * Bekannte Schlüsselformate, egal wo sie stehen. Gruppe 1 = Präfix, das stehen bleiben darf
	 * (zeigt die Art des Schlüssels, z. B. rk_live_, ohne ihn preiszugeben).
	 */
	const SECRET_VALUE_RES = array(
		'/\b((?:sk|rk)_(?:live|test)_)[A-Za-z0-9]{8,}/',
		'/\b(whsec_)[A-Za-z0-9+\/=]{8,}/',
		'/\b(sk-ant-)[A-Za-z0-9_\-]{8,}/',
		'/\b(sk-(?:proj-)?)[A-Za-z0-9_\-]{20,}/',
		'/\b(gh[pousr]_)[A-Za-z0-9]{20,}/',
		'/\b(github_pat_)[A-Za-z0-9_]{20,}/',
		'/\b(xox[abposr]-)[A-Za-z0-9\-]{10,}/',
		'/\b(AKIA)[0-9A-Z]{16}\b/',
		'/(-----BEGIN [A-Z ]*PRIVATE KEY-----)[\s\S]*?-----END [A-Z ]*PRIVATE KEY-----/',
		'/(\$(?:P|H|wp|2y|2a|2b|argon2id|argon2i)\$)[^\s"\'<>]{8,}/',
	);

	public static function secret_key( $key ) {
		return is_string( $key ) && '' !== $key && (bool) preg_match( self::SECRET_KEY_RE, $key );
	}

	/** Schwärzt bekannte Schlüsselformate in freiem Text (Dateien, Ausgaben, JSON). */
	public static function redact_text( $text ) {
		if ( ! is_string( $text ) || '' === $text ) {
			return $text;
		}
		foreach ( self::SECRET_VALUE_RES as $re ) {
			$text = preg_replace( $re, '$1' . self::REDACTED, $text );
		}
		return $text;
	}

	/**
	 * Schwärzt einen einzelnen Wert. Serialisierte PHP-Werte und JSON werden entpackt,
	 * rekursiv nach Schlüsselnamen und Wertmustern geschwärzt und wieder verpackt.
	 */
	public static function redact_value( $value, $depth = 0 ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return $value;
		}
		if ( $depth < 5 && is_serialized( $value ) ) {
			$data = @unserialize( trim( $value ), array( 'allowed_classes' => false ) ); // phpcs:ignore
			if ( false !== $data || 'b:0;' === trim( $value ) ) {
				if ( is_array( $data ) ) {
					return self::redact_text( serialize( self::redact_data( $data, false, $depth + 1 ) ) ); // phpcs:ignore
				}
				if ( is_string( $data ) ) {
					return serialize( self::redact_value( $data, $depth + 1 ) ); // phpcs:ignore
				}
			}
			return self::redact_text( $value );
		}
		$first = substr( ltrim( $value ), 0, 1 );
		if ( $depth < 5 && ( '{' === $first || '[' === $first ) ) {
			$data = json_decode( $value, true );
			if ( is_array( $data ) ) {
				return wp_json_encode( self::redact_data( $data, false, $depth + 1 ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			}
		}
		return self::redact_text( $value );
	}

	/** Rekursiv über Arrays/Objekte. $force = alles darunter schwärzen (Elternschlüssel war geheim). */
	public static function redact_data( $data, $force = false, $depth = 0 ) {
		if ( is_array( $data ) ) {
			foreach ( $data as $k => $v ) {
				$data[ $k ] = self::redact_data( $v, $force || self::secret_key( $k ), $depth );
			}
			return $data;
		}
		if ( is_object( $data ) ) {
			if ( $data instanceof __PHP_Incomplete_Class ) {
				return $data;
			}
			foreach ( get_object_vars( $data ) as $k => $v ) {
				$data->$k = self::redact_data( $v, $force || self::secret_key( $k ), $depth );
			}
			return $data;
		}
		if ( $force && ( ( is_string( $data ) && '' !== $data ) || is_int( $data ) || is_float( $data ) ) ) {
			return self::REDACTED;
		}
		return is_string( $data ) ? self::redact_value( $data, $depth ) : $data;
	}

	/* ---------------------------------------------------------- Kundendaten (ab 0.4.2) */

	/**
	 * Spalten- und Schlüsselnamen mit personenbezogenen Daten von Kunden (Name, Kontakt, Anschrift, Freitext).
	 * Gilt für SQL-Ergebnisse, auch in entpackten JSON-/serialisierten Werten.
	 */
	const PII_KEY_RE = '/^(customer_(name|email|phone)|user_email|user_login|user_nicename|display_name|user_url|e_?mail|phone|telefon|tel|mobile|first_name|last_name|nickname|street|address|address_?\d|line\d|zip|postcode|postal_code|city|ort|message|notes|admin_notes|comment_author(_email|_ip|_url)?|author_email|ip|ip_address|user_ip|billing_\w+|shipping_\w+|birth\w*|iban|bic|card_holder|holder_name)$/i';

	/** In verschachtelten Werten (z. B. Stripe-Daten, Belegdaten) zusätzlich: name. */
	const PII_NESTED_KEY_RE = '/^name$/i';

	public static function pii_enabled() {
		return (bool) CMB_Settings::get( 'redact_pii' );
	}

	public static function pii_key( $key, $nested = false ) {
		if ( ! is_string( $key ) || '' === $key ) {
			return false;
		}
		return (bool) preg_match( self::PII_KEY_RE, $key ) || ( $nested && (bool) preg_match( self::PII_NESTED_KEY_RE, $key ) );
	}

	/** E-Mail-Adressen in freiem Text, außer Adressen der eigenen Domain. */
	public static function redact_emails( $text ) {
		if ( ! is_string( $text ) || false === strpos( $text, '@' ) ) {
			return $text;
		}
		$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$host = preg_replace( '/^www\./', '', $host );
		return preg_replace_callback(
			'/[A-Za-z0-9._%+\-]+@([A-Za-z0-9\-]+(?:\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,})/',
			function ( $m ) use ( $host ) {
				$domain = strtolower( $m[1] );
				return ( '' !== $host && ( $domain === $host || substr( $domain, -strlen( '.' . $host ) ) === '.' . $host ) ) ? $m[0] : self::REDACTED;
			},
			$text
		);
	}

	/**
	 * Kundendaten in einem Wert schwärzen: JSON und serialisierte Werte werden entpackt und nach
	 * Schlüsselnamen geschwärzt, E-Mail-Adressen überall.
	 */
	public static function redact_pii_value( $value, $depth = 0 ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return $value;
		}
		if ( $depth < 5 && is_serialized( $value ) ) {
			$data = @unserialize( trim( $value ), array( 'allowed_classes' => false ) ); // phpcs:ignore
			if ( is_array( $data ) ) {
				return serialize( self::redact_pii_data( $data, false, $depth + 1 ) ); // phpcs:ignore
			}
			return self::redact_emails( $value );
		}
		$first = substr( ltrim( $value ), 0, 1 );
		if ( $depth < 5 && ( '{' === $first || '[' === $first ) ) {
			$data = json_decode( $value, true );
			if ( is_array( $data ) ) {
				return wp_json_encode( self::redact_pii_data( $data, false, $depth + 1 ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			}
		}
		return self::redact_emails( $value );
	}

	/** Rekursiv; $force = Elternschlüssel war ein Kundendatenfeld (z. B. form_data.first_name.value). */
	public static function redact_pii_data( $data, $force = false, $depth = 0 ) {
		if ( is_array( $data ) ) {
			foreach ( $data as $k => $v ) {
				$data[ $k ] = self::redact_pii_data( $v, $force || self::pii_key( $k, true ), $depth );
			}
			return $data;
		}
		if ( $force && ( ( is_string( $data ) && '' !== $data ) || is_int( $data ) || is_float( $data ) ) ) {
			return self::REDACTED;
		}
		return is_string( $data ) ? self::redact_pii_value( $data, $depth ) : $data;
	}

	/** Letzte Stufe vor jeder Antwort an Claude: strukturierte Daten und Text schwärzen. */
	public static function redact_result( array $res ) {
		if ( isset( $res['data'] ) ) {
			// Tiefe 5: hier nichts entpacken, damit Dateiinhalte (z. B. JSON) unverändert bleiben.
			$res['data'] = self::redact_data( $res['data'], false, 5 );
		}
		if ( isset( $res['text'] ) ) {
			$res['text'] = self::redact_text( (string) $res['text'] );
		}
		return $res;
	}

	/** Vergleicht eine angefragte Redirect-URI mit einer registrierten (Loopback: Port egal). */
	public static function redirect_uri_matches( $requested, $registered ) {
		if ( $requested === $registered ) {
			return true;
		}
		$loop = '#^http://(localhost|127\.0\.0\.1)(?::\d{1,5})?/callback$#';
		if ( CMB_Settings::get( 'allow_loopback' ) && preg_match( $loop, $requested, $a ) && preg_match( $loop, $registered, $b ) ) {
			return $a[1] === $b[1];
		}
		return false;
	}
}
