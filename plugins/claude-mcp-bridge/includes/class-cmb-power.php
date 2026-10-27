<?php
defined( 'ABSPATH' ) || exit;

/**
 * Expertenmodus: Dateien, PHP, SQL, WP-CLI, Plugins.
 *
 * Grundsätze
 * - Jede Kategorie ist einzeln abschaltbar und standardmäßig AUS.
 * - Kill-Switch in wp-config.php: define( 'CMB_DISABLE_POWER_TOOLS', true );
 * - WordPress-Härtung wird respektiert (DISALLOW_FILE_MODS / DISALLOW_FILE_EDIT).
 * - Ohne Freigabe laufen nur lesende Operationen; alles Ändernde wird zum Vorschlag.
 * - Das Freigabesystem selbst (Plugin-Ordner, cmb_-Tabellen) ist über MCP nicht veränderbar,
 *   damit ein Vorschlag nie die Freigabepflicht aushebeln kann.
 */
final class CMB_Power {

	const CATEGORIES = array(
		'files'   => 'Dateien',
		'php'     => 'PHP ausführen',
		'sql'     => 'Datenbank (SQL)',
		'wpcli'   => 'WP-CLI',
		'plugins' => 'Plugins verwalten',
	);

	const MAX_READ   = 524288;   // 512 KB
	const MAX_WRITE  = 1048576;  // 1 MB
	const MAX_OUTPUT = 65536;    // 64 KB Ausgabe für Claude
	const SQL_ROWS   = 200;
	const CLI_TIMEOUT = 60;

	/* ------------------------------------------------------------ toggles */

	public static function kill_switch() {
		return defined( 'CMB_DISABLE_POWER_TOOLS' ) && CMB_DISABLE_POWER_TOOLS;
	}

	/** Grund, warum eine Kategorie technisch nicht verfügbar ist (null = verfügbar). */
	public static function blocked_reason( $cat ) {
		if ( self::kill_switch() ) {
			return 'CMB_DISABLE_POWER_TOOLS ist in wp-config.php gesetzt.';
		}
		switch ( $cat ) {
			case 'php':
				if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) {
					return 'DISALLOW_FILE_EDIT ist gesetzt.';
				}
				if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
					return 'DISALLOW_FILE_MODS ist gesetzt.';
				}
				return null;
			case 'plugins':
				return ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) ? 'DISALLOW_FILE_MODS ist gesetzt.' : null;
			case 'wpcli':
				if ( ! function_exists( 'proc_open' ) || in_array( 'proc_open', array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) ), true ) ) {
					return 'proc_open ist auf diesem Server deaktiviert.';
				}
				$wp = (string) CMB_Settings::get( 'wp_cli_path' );
				if ( '' === $wp || ! is_file( $wp ) ) {
					return 'Pfad zu WP-CLI ist nicht eingerichtet.';
				}
				return null;
		}
		return null;
	}

	public static function enabled( $cat ) {
		return isset( self::CATEGORIES[ $cat ] ) && (bool) CMB_Settings::get( 'power_' . $cat ) && null === self::blocked_reason( $cat );
	}

	public static function any_enabled() {
		foreach ( array_keys( self::CATEGORIES ) as $c ) {
			if ( self::enabled( $c ) ) {
				return true;
			}
		}
		return false;
	}

	public static function file_writes_allowed() {
		return ! ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS );
	}

	/* -------------------------------------------------------------- paths */

	public static function root() {
		return rtrim( wp_normalize_path( (string) realpath( ABSPATH ) ), '/' );
	}

	public static function rel( $abs ) {
		$root = self::root();
		return ltrim( substr( wp_normalize_path( $abs ), strlen( $root ) ), '/' );
	}

	private static function inside_root( $real ) {
		$root = self::root();
		$real = wp_normalize_path( $real );
		return $real === $root || 0 === strpos( $real, $root . '/' );
	}

	/**
	 * Löst einen Pfad relativ zum WordPress-Verzeichnis sicher auf.
	 * - keine Nullbytes, keine "..", keine Pfade außerhalb von ABSPATH (auch nicht per Symlink)
	 *
	 * @return string|WP_Error absoluter, normalisierter Pfad
	 */
	public static function resolve( $path, $must_exist = true ) {
		if ( ! is_string( $path ) || '' === $path || strlen( $path ) > 500 || false !== strpos( $path, "\0" ) ) {
			return new WP_Error( 'path', 'Ungültiger Pfad.' );
		}
		$path = wp_normalize_path( $path );
		$root = self::root();
		if ( 0 === strpos( $path, $root . '/' ) ) {
			$path = substr( $path, strlen( $root ) + 1 );
		}
		$path = ltrim( $path, '/' );
		foreach ( explode( '/', $path ) as $seg ) {
			if ( '..' === $seg ) {
				return new WP_Error( 'path', '".." ist in Pfaden nicht erlaubt.' );
			}
		}
		$abs = '' === $path ? $root : $root . '/' . $path;

		if ( file_exists( $abs ) || is_link( $abs ) ) {
			$real = realpath( $abs );
			if ( false === $real || ! self::inside_root( $real ) ) {
				return new WP_Error( 'path', 'Pfad liegt außerhalb der WordPress-Installation.' );
			}
			return wp_normalize_path( $real );
		}
		if ( $must_exist ) {
			return new WP_Error( 'missing', 'Datei oder Ordner existiert nicht.' );
		}
		// Neues Ziel: nächster existierender Vorfahre muss innerhalb liegen.
		$parent = dirname( $abs );
		while ( ! file_exists( $parent ) && strlen( $parent ) > strlen( $root ) ) {
			$parent = dirname( $parent );
		}
		$real_parent = realpath( $parent );
		if ( false === $real_parent || ! self::inside_root( $real_parent ) || ! is_dir( $real_parent ) ) {
			return new WP_Error( 'path', 'Pfad liegt außerhalb der WordPress-Installation.' );
		}
		return wp_normalize_path( $abs );
	}

	private static function is_php_like( $abs ) {
		return (bool) preg_match( '/\.(php\d?|phtml|phar|pht|phps|inc)$/i', $abs );
	}

	/** Dateien, die Geheimnisse enthalten: nie an Claude ausliefern. */
	public static function read_denied( $abs ) {
		$rel  = strtolower( self::rel( $abs ) );
		$base = basename( $rel );
		if ( preg_match( '/^wp-config.*\.php$/', $base ) || in_array( $base, array( '.htpasswd', 'id_rsa', 'id_ed25519', 'id_ecdsa', 'auth.json' ), true ) ) {
			return 'Datei enthält Zugangsdaten.';
		}
		if ( preg_match( '/^\.env(\..*)?$/', $base ) ) {
			return 'Datei enthält Zugangsdaten.';
		}
		if ( preg_match( '/\.(sql|sql\.gz|sqlite|sqlite3|db|key|pem|p12|pfx|crt|bak|backup|old|orig|swp)$/', $base ) ) {
			return 'Backups, Datenbanken und Schlüssel werden nicht ausgeliefert.';
		}
		if ( preg_match( '#(^|/)(wp-content/database|wp-content/updraft|wp-content/ai1wm-backups|wp-content/backups?|wp-content/backup-db)(/|$)#', $rel ) ) {
			return 'Backup-/Datenbank-Ordner werden nicht ausgeliefert.';
		}
		// Ab 0.4.2: Rechnungen und andere Belege mit Kundendaten (PDF, CSV) nicht ausliefern.
		if ( CMB_Security::pii_enabled() && preg_match( '#(^|/)wp-content/uploads/msb-belege/.+\.(pdf|csv)$#', $rel ) ) {
			return 'Belege enthalten Kundendaten und werden nicht ausgeliefert (Einstellung „Kundendaten schwärzen“).';
		}
		return null;
	}

	/** Ziele, die über MCP nie geschrieben/gelöscht werden dürfen. */
	public static function write_denied( $abs ) {
		$reason = self::read_denied( $abs );
		if ( $reason ) {
			return $reason;
		}
		$rel    = self::rel( $abs );
		$lower  = strtolower( $rel );
		$normal = wp_normalize_path( $abs );
		// Plugin-Ordner auf allen Wegen erkennen (echter Pfad, Pfad unter WP_PLUGIN_DIR, Symlinks).
		$plugin_dir = wp_normalize_path( WP_PLUGIN_DIR . '/' . dirname( plugin_basename( CMB_FILE ) ) );
		$protected  = array_filter( array_unique( array(
			rtrim( wp_normalize_path( (string) realpath( CMB_DIR ) ), '/' ),
			rtrim( wp_normalize_path( CMB_DIR ), '/' ),
			$plugin_dir,
			rtrim( wp_normalize_path( (string) realpath( $plugin_dir ) ), '/' ),
		) ) );
		foreach ( $protected as $self ) {
			if ( $normal === $self || 0 === strpos( $normal, $self . '/' ) ) {
				return 'Die Claude MCP Bridge kann sich nicht selbst verändern (Schutz der Freigabepflicht).';
			}
		}
		if ( '' === $rel || preg_match( '#^(wp-admin|wp-includes)(/|$)#', $lower ) ) {
			return 'WordPress-Core-Dateien sind geschützt.';
		}
		if ( preg_match( '#^(index\.php|wp-[^/]+\.php|xmlrpc\.php|license\.txt|readme\.html)$#', $lower ) ) {
			return 'WordPress-Core-Dateien sind geschützt.';
		}
		$uploads = wp_upload_dir( null, false );
		$updir   = rtrim( wp_normalize_path( (string) $uploads['basedir'] ), '/' );
		if ( $updir && 0 === strpos( $normal, $updir . '/' ) && self::is_php_like( $abs ) ) {
			return 'PHP-Dateien im Upload-Ordner sind gesperrt (typischer Ort für Hintertüren).';
		}
		return null;
	}

	public static function file_risk( $abs ) {
		$base = strtolower( basename( $abs ) );
		if ( self::is_php_like( $abs ) || in_array( $base, array( '.htaccess', '.user.ini', 'php.ini', 'wp-cli.yml', 'wp-cli.local.yml' ), true ) || false !== strpos( wp_normalize_path( $abs ), '/mu-plugins/' ) ) {
			return 'critical';
		}
		return 'high';
	}

	public static function is_binary( $data ) {
		return false !== strpos( substr( $data, 0, 8192 ), "\0" );
	}

	public static function php_syntax_error( $code ) {
		try {
			token_get_all( $code, TOKEN_PARSE );
			return null;
		} catch ( ParseError $e ) {
			return 'Syntaxfehler in Zeile ' . $e->getLine() . ': ' . $e->getMessage();
		}
	}

	/* -------------------------------------------------------- file reads */

	public static function read_file( $path, $offset_line = 1, $max_lines = 2000 ) {
		$abs = self::resolve( $path );
		if ( is_wp_error( $abs ) ) {
			return $abs;
		}
		if ( ! is_file( $abs ) ) {
			return new WP_Error( 'type', 'Kein Datei-Pfad.' );
		}
		if ( $why = self::read_denied( $abs ) ) {
			return new WP_Error( 'denied', $why );
		}
		$size = filesize( $abs );
		if ( $size > self::MAX_READ ) {
			return new WP_Error( 'size', 'Datei ist größer als 512 KB.' );
		}
		$data = (string) file_get_contents( $abs );
		if ( self::is_binary( $data ) ) {
			return array( 'path' => self::rel( $abs ), 'binary' => true, 'size' => $size, 'sha256' => hash( 'sha256', $data ) );
		}
		$lines = explode( "\n", $data );
		$slice = array_slice( $lines, max( 0, $offset_line - 1 ), $max_lines );
		return array(
			'path'        => self::rel( $abs ),
			'size'        => $size,
			'sha256'      => hash( 'sha256', $data ),
			'total_lines' => count( $lines ),
			'from_line'   => $offset_line,
			'content'     => implode( "\n", $slice ),
		);
	}

	public static function list_dir( $path, $recursive = false ) {
		$abs = self::resolve( '' === $path ? '.' : $path );
		if ( '.' === $path || '' === $path ) {
			$abs = self::root();
		}
		if ( is_wp_error( $abs ) ) {
			return $abs;
		}
		if ( ! is_dir( $abs ) ) {
			return new WP_Error( 'type', 'Kein Ordner.' );
		}
		$out = array();
		$max = 500;
		if ( $recursive ) {
			$it = new RecursiveIteratorIterator(
				new RecursiveCallbackFilterIterator(
					new RecursiveDirectoryIterator( $abs, FilesystemIterator::SKIP_DOTS ),
					function ( $f ) {
						return ! in_array( $f->getFilename(), array( '.git', 'node_modules' ), true ) && ! $f->isLink();
					}
				),
				RecursiveIteratorIterator::SELF_FIRST
			);
			$it->setMaxDepth( 3 );
		} else {
			$it = new FilesystemIterator( $abs, FilesystemIterator::SKIP_DOTS );
		}
		foreach ( $it as $f ) {
			if ( count( $out ) >= $max ) {
				break;
			}
			$p     = wp_normalize_path( $f->getPathname() );
			$out[] = array(
				'path'     => self::rel( $p ),
				'type'     => $f->isLink() ? 'link' : ( $f->isDir() ? 'dir' : 'file' ),
				'size'     => $f->isFile() ? $f->getSize() : null,
				'modified' => gmdate( 'c', $f->getMTime() ),
				'readable' => $f->isFile() ? null === self::read_denied( $p ) : null,
			);
		}
		usort( $out, function ( $a, $b ) {
			return strcmp( $a['path'], $b['path'] );
		} );
		return array( 'dir' => self::rel( $abs ), 'truncated' => count( $out ) >= $max, 'entries' => $out );
	}

	public static function search( $needle, $path, $ext = '' ) {
		if ( ! is_string( $needle ) || '' === $needle ) {
			return new WP_Error( 'needle', 'Suchtext fehlt.' );
		}
		$abs = '' === $path ? self::root() : self::resolve( $path );
		if ( is_wp_error( $abs ) ) {
			return $abs;
		}
		if ( ! is_dir( $abs ) ) {
			return new WP_Error( 'type', 'Kein Ordner.' );
		}
		$exts    = array_filter( array_map( 'strtolower', array_map( 'trim', explode( ',', (string) $ext ) ) ) );
		$it      = new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator( $abs, FilesystemIterator::SKIP_DOTS ),
				function ( $f ) {
					return ! in_array( $f->getFilename(), array( '.git', 'node_modules' ), true ) && ! $f->isLink();
				}
			)
		);
		$matches = array();
		$scanned = 0;
		foreach ( $it as $f ) {
			if ( ! $f->isFile() || $f->getSize() > self::MAX_READ ) {
				continue;
			}
			if ( $exts && ! in_array( strtolower( $f->getExtension() ), $exts, true ) ) {
				continue;
			}
			$p = wp_normalize_path( $f->getPathname() );
			if ( self::read_denied( $p ) ) {
				continue;
			}
			if ( ++$scanned > 5000 ) {
				break;
			}
			$data = (string) @file_get_contents( $p );
			if ( self::is_binary( $data ) || false === stripos( $data, $needle ) ) {
				continue;
			}
			foreach ( explode( "\n", $data ) as $i => $line ) {
				if ( false !== stripos( $line, $needle ) ) {
					$matches[] = array( 'path' => self::rel( $p ), 'line' => $i + 1, 'text' => mb_substr( trim( $line ), 0, 300 ) );
					if ( count( $matches ) >= 100 ) {
						break 2;
					}
				}
			}
		}
		return array( 'matches' => $matches, 'files_scanned' => $scanned, 'truncated' => count( $matches ) >= 100 );
	}

	/* ------------------------------------------------------- file writes */

	/**
	 * Schreibt eine Datei, aber nur, wenn ihr aktueller Zustand dem bei Vorschlag
	 * gemessenen entspricht ($expected_sha = null heißt: Datei darf noch nicht existieren).
	 */
	public static function write( $path, $content, $expected_sha ) {
		if ( ! self::file_writes_allowed() ) {
			return new WP_Error( 'mods', 'DISALLOW_FILE_MODS ist gesetzt.' );
		}
		$abs = self::resolve( $path, false );
		if ( is_wp_error( $abs ) ) {
			return $abs;
		}
		if ( $why = self::write_denied( $abs ) ) {
			return new WP_Error( 'denied', $why );
		}
		if ( is_dir( $abs ) ) {
			return new WP_Error( 'type', 'Ziel ist ein Ordner.' );
		}
		$exists = is_file( $abs );
		$before = $exists ? (string) file_get_contents( $abs ) : null;
		if ( null === $expected_sha ? $exists : ( ! $exists || hash( 'sha256', $before ) !== $expected_sha ) ) {
			return new WP_Error( 'stale', 'Die Datei wurde seit dem Vorschlag verändert (oder angelegt/gelöscht). Bitte neuen Vorschlag erstellen.' );
		}
		if ( self::is_php_like( $abs ) && ( $err = self::php_syntax_error( $content ) ) ) {
			return new WP_Error( 'syntax', $err . ' – nicht gespeichert, damit die Website nicht ausfällt.' );
		}
		if ( ! wp_mkdir_p( dirname( $abs ) ) ) {
			return new WP_Error( 'mkdir', 'Ordner konnte nicht angelegt werden.' );
		}
		if ( false === file_put_contents( $abs, $content, LOCK_EX ) ) {
			return new WP_Error( 'write', 'Datei konnte nicht geschrieben werden (Rechte?).' );
		}
		if ( function_exists( 'opcache_invalidate' ) && self::is_php_like( $abs ) ) {
			@opcache_invalidate( $abs, true );
		}
		return array( 'before' => $before, 'after_sha' => hash( 'sha256', $content ) );
	}

	public static function delete( $path, $expected_sha ) {
		if ( ! self::file_writes_allowed() ) {
			return new WP_Error( 'mods', 'DISALLOW_FILE_MODS ist gesetzt.' );
		}
		$abs = self::resolve( $path );
		if ( is_wp_error( $abs ) ) {
			return $abs;
		}
		if ( ! is_file( $abs ) || is_link( $abs ) ) {
			return new WP_Error( 'type', 'Nur einzelne Dateien können gelöscht werden.' );
		}
		if ( $why = self::write_denied( $abs ) ) {
			return new WP_Error( 'denied', $why );
		}
		$before = (string) file_get_contents( $abs );
		if ( hash( 'sha256', $before ) !== $expected_sha ) {
			return new WP_Error( 'stale', 'Die Datei wurde seit dem Vorschlag verändert.' );
		}
		if ( ! @unlink( $abs ) ) {
			return new WP_Error( 'unlink', 'Datei konnte nicht gelöscht werden.' );
		}
		return array( 'before' => $before );
	}

	/* --------------------------------------------------------------- PHP */

	public static function normalize_php( $code ) {
		$code = preg_replace( '/^\s*<\?php\b/i', '', (string) $code );
		return preg_replace( '/\?>\s*$/', '', $code );
	}

	public static function run_php( $code ) {
		$code = self::normalize_php( $code );
		if ( $err = self::php_syntax_error( "<?php\n" . $code ) ) {
			return new WP_Error( 'syntax', $err );
		}
		@set_time_limit( 30 );
		$error = null;
		$ret   = null;
		ob_start();
		try {
			$ret = ( static function () use ( $code ) {
				return eval( $code ); // phpcs:ignore Squiz.PHP.Eval -- nur nach ausdrücklicher Freigabe mit Passwortbestätigung.
			} )();
		} catch ( Throwable $e ) {
			$error = get_class( $e ) . ': ' . $e->getMessage() . ' (Zeile ' . $e->getLine() . ')';
		}
		$output = (string) ob_get_clean();
		return array(
			'output' => self::clip( $output ),
			'return' => null === $ret ? null : self::clip( var_export( $ret, true ) ),
			'error'  => $error,
		);
	}

	public static function clip( $s, $max = self::MAX_OUTPUT ) {
		$s = (string) $s;
		return strlen( $s ) > $max ? substr( $s, 0, $max ) . "\n…[gekürzt]" : $s;
	}

	/* --------------------------------------------------------------- SQL */

	private static function sql_skeleton( $sql, $backslash_escapes ) {
		$len  = strlen( $sql );
		$skel = '';
		for ( $i = 0; $i < $len; $i++ ) {
			$ch = $sql[ $i ];
			$nx = $i + 1 < $len ? $sql[ $i + 1 ] : '';
			if ( "'" === $ch || '"' === $ch || '`' === $ch ) {
				$q = $ch;
				$i++;
				while ( $i < $len ) {
					if ( $backslash_escapes && '\\' === $sql[ $i ] && '`' !== $q ) {
						$i += 2;
						continue;
					}
					if ( $sql[ $i ] === $q ) {
						if ( $i + 1 < $len && $sql[ $i + 1 ] === $q ) {
							$i += 2;
							continue;
						}
						break;
					}
					$i++;
				}
				if ( $i >= $len ) {
					return new WP_Error( 'sql', 'Nicht geschlossenes Anführungszeichen.' );
				}
				$skel .= '`' === $q ? ' IDENT ' : ' STR ';
				continue;
			}
			// MySQL: "--" ist nur mit folgendem Leerzeichen/Zeilenende ein Kommentar ("1--1" ist Arithmetik).
			$after = $i + 2 < $len ? $sql[ $i + 2 ] : '';
			if ( ( '-' === $ch && '-' === $nx && ( '' === $after || ctype_space( $after ) || ctype_cntrl( $after ) ) ) || '#' === $ch ) {
				while ( $i < $len && "\n" !== $sql[ $i ] ) {
					$i++;
				}
				$skel .= ' ';
				continue;
			}
			if ( '/' === $ch && '*' === $nx ) {
				$end = strpos( $sql, '*/', $i + 2 );
				if ( false === $end ) {
					return new WP_Error( 'sql', 'Nicht geschlossener Kommentar.' );
				}
				$i     = $end + 1;
				$skel .= ' ';
				continue;
			}
			$skel .= $ch;
		}
		return rtrim( trim( $skel ), "; \t\n\r" );
	}

	/**
	 * Entfernt Strings und Kommentare (für die Analyse) und prüft auf genau ein Statement.
	 *
	 * @return array|WP_Error [keyword, sql, skeleton]
	 */
	public static function sql_analyze( $sql ) {
		global $wpdb;
		if ( ! is_string( $sql ) || '' === trim( $sql ) || strlen( $sql ) > 102400 || false !== strpos( $sql, "\0" ) ) {
			return new WP_Error( 'sql', 'Ungültige Abfrage.' );
		}
		if ( false !== strpos( $sql, '/*!' ) || false !== strpos( $sql, '/*+' ) ) {
			return new WP_Error( 'sql', 'Ausführbare MySQL-Kommentare (/*! … */) sind nicht erlaubt.' );
		}
		// Zweimal analysieren: mit und ohne Backslash-Escapes (sql_mode NO_BACKSLASH_ESCAPES),
		// damit sich nichts in einem vermeintlichen String verstecken kann.
		$skels = array();
		foreach ( array( true, false ) as $bs ) {
			$sk = self::sql_skeleton( $sql, $bs );
			if ( is_wp_error( $sk ) ) {
				return $sk;
			}
			if ( false !== strpos( $sk, ';' ) ) {
				return new WP_Error( 'sql', 'Nur genau eine Anweisung pro Vorschlag.' );
			}
			$skels[] = strtoupper( preg_replace( '/\s+/', ' ', $sk ) );
		}
		$upper = $skels[0];
		if ( ! preg_match( '/^\(?\s*([A-Z]+)/', $upper, $m ) ) {
			return new WP_Error( 'sql', 'Unbekannte Anweisung.' );
		}
		$kw = $m[1];

		$forbidden = array( 'GRANT', 'REVOKE', 'CREATE USER', 'DROP USER', 'ALTER USER', 'RENAME USER', 'SET PASSWORD', 'SHUTDOWN', 'KILL', 'LOAD DATA', 'LOAD XML', 'INTO OUTFILE', 'INTO DUMPFILE', 'LOAD_FILE', 'INSTALL PLUGIN', 'UNINSTALL PLUGIN', 'INSTALL COMPONENT', 'PREPARE', 'EXECUTE', 'DEALLOCATE', 'HANDLER', 'CREATE TRIGGER', 'CREATE EVENT', 'CREATE PROCEDURE', 'CREATE FUNCTION', 'CREATE DEFINER', 'ALTER EVENT', 'SYSTEM', 'SET GLOBAL', 'SET PERSIST', 'DROP DATABASE', 'CREATE DATABASE', 'USE' );
		foreach ( $forbidden as $f ) {
			foreach ( $skels as $sk ) {
				if ( preg_match( '/(^|[^A-Z_])' . str_replace( ' ', '\s+', $f ) . '([^A-Z_]|$)/', $sk ) ) {
					return new WP_Error( 'sql', "\"$f\" ist nicht erlaubt." );
				}
			}
		}
		// Das Freigabesystem (cmb_-Tabellen) ist über SQL tabu – auch über Bezeichner in Backticks.
		if ( false !== stripos( $sql, 'cmb_' ) ) {
			return new WP_Error( 'sql', 'Die Tabellen der Claude MCP Bridge sind gesperrt.' );
		}
		return array( $kw, rtrim( trim( $sql ), "; \t\n\r" ), implode( ' ', $skels ) );
	}

	public static function sql_is_read_only( $kw, $upper ) {
		if ( ! in_array( $kw, array( 'SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN' ), true ) ) {
			return false;
		}
		foreach ( array( 'SLEEP', 'BENCHMARK', 'GET_LOCK', 'FOR UPDATE', 'LOCK IN SHARE MODE', 'FOR SHARE', 'INTO @', 'ANALYZE' ) as $bad ) {
			if ( false !== strpos( $upper, $bad ) ) {
				return false;
			}
		}
		return true;
	}

	public static function sql_risk( $kw, $upper ) {
		if ( in_array( $kw, array( 'DROP', 'TRUNCATE', 'ALTER', 'RENAME' ), true ) ) {
			return 'critical';
		}
		if ( in_array( $kw, array( 'DELETE', 'UPDATE' ), true ) && false === strpos( $upper, ' WHERE ' ) ) {
			return 'critical';
		}
		return 'high';
	}

	private static function is_sqlite() {
		return defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE;
	}

	/**
	 * Sensible Werte (Passwort-Hashes, Sitzungen, App-Passwörter, API-Schlüssel) aus Ergebnissen entfernen.
	 * Ab 0.4.1 werden zusätzlich alle Spalten entpackt (serialisiert/JSON) und nach Schlüsselnamen
	 * und bekannten Schlüsselformaten geschwärzt – auch ohne option_name/meta_key in der Abfrage.
	 */
	private static function redact_rows( array $rows ) {
		$pii = CMB_Security::pii_enabled();
		foreach ( $rows as &$row ) {
			$sensitive_row = false;
			$pii_row       = false;
			foreach ( array( 'meta_key', 'option_name' ) as $k ) {
				if ( isset( $row[ $k ] ) && preg_match( '/(session_tokens|application_passwords|_password|secret|_key$|api_key|token)/i', (string) $row[ $k ] ) ) {
					$sensitive_row = true;
				}
			}
			// Ab 0.4.2: Benutzer-Metadaten wie first_name, last_name, billing_* (Kundendaten).
			if ( $pii && isset( $row['meta_key'] ) && CMB_Security::pii_key( (string) $row['meta_key'] ) ) {
				$pii_row = true;
			}
			foreach ( $row as $col => &$val ) {
				$lc = strtolower( $col );
				if ( in_array( $lc, array( 'user_pass', 'user_activation_key' ), true )
					|| ( $sensitive_row && in_array( $lc, array( 'meta_value', 'option_value' ), true ) )
					|| ( is_string( $val ) && preg_match( '/^\$(P|H|wp|2y|2a|2b|argon2id?|argon2i)\$/', $val ) ) ) {
					$val = CMB_Security::REDACTED;
				} elseif ( $pii && ( CMB_Security::pii_key( $lc ) || ( $pii_row && 'meta_value' === $lc ) ) && ( ( is_string( $val ) && '' !== $val ) || is_int( $val ) || is_float( $val ) ) ) {
					$val = CMB_Security::REDACTED;
				} elseif ( is_string( $val ) ) {
					$val = CMB_Security::redact_value( $val );
					if ( $pii ) {
						$val = CMB_Security::redact_pii_value( $val );
					}
				}
			}
		}
		return $rows;
	}

	public static function sql_select( $sql ) {
		global $wpdb;
		$a = self::sql_analyze( $sql );
		if ( is_wp_error( $a ) ) {
			return $a;
		}
		list( $kw, $clean, $upper ) = $a;
		if ( ! self::sql_is_read_only( $kw, $upper ) ) {
			return new WP_Error( 'sql', 'Nur lesende Abfragen (SELECT/SHOW/DESCRIBE/EXPLAIN) laufen ohne Freigabe. Für Änderungen propose_db_query verwenden.' );
		}
		if ( 'SELECT' === $kw && ! preg_match( '/\bLIMIT\s+\d+/', $upper ) ) {
			$clean .= ' LIMIT ' . ( self::SQL_ROWS + 1 );
		}
		$suppress = $wpdb->suppress_errors( true );
		$tx       = false;
		if ( ! self::is_sqlite() ) {
			// Zusätzliche Absicherung: Abfrage in einer READ-ONLY-Transaktion.
			$tx = false !== $wpdb->query( 'START TRANSACTION READ ONLY' );
		}
		$rows = $wpdb->get_results( $clean, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL -- geprüfte Lese-Abfrage des Admins.
		$err  = $wpdb->last_error;
		if ( $tx ) {
			$wpdb->query( 'ROLLBACK' );
		}
		$wpdb->suppress_errors( $suppress );
		if ( $err ) {
			return new WP_Error( 'sql', 'Datenbankfehler: ' . $err );
		}
		$rows      = (array) $rows;
		$truncated = count( $rows ) > self::SQL_ROWS;
		return array( 'rows' => self::redact_rows( array_slice( $rows, 0, self::SQL_ROWS ) ), 'row_count' => min( count( $rows ), self::SQL_ROWS ), 'truncated' => $truncated );
	}

	public static function sql_execute( $sql ) {
		global $wpdb;
		$a = self::sql_analyze( $sql );
		if ( is_wp_error( $a ) ) {
			return $a;
		}
		$suppress = $wpdb->suppress_errors( true );
		$r        = $wpdb->query( $a[1] ); // phpcs:ignore WordPress.DB.PreparedSQL -- vom Admin freigegebene Anweisung.
		$err      = $wpdb->last_error;
		$wpdb->suppress_errors( $suppress );
		if ( false === $r || $err ) {
			return new WP_Error( 'sql', 'Datenbankfehler: ' . ( $err ?: 'unbekannt' ) );
		}
		wp_cache_flush();
		return array( 'rows_affected' => (int) $wpdb->rows_affected, 'insert_id' => (int) $wpdb->insert_id );
	}

	/* ------------------------------------------------------------- WP-CLI */

	/** Shell-ähnliche Zerlegung OHNE Shell: keine Variablen, Pipes, Umleitungen oder Subshells. */
	public static function cli_tokenize( $cmd ) {
		if ( ! is_string( $cmd ) || strlen( $cmd ) > 4000 || preg_match( '/[\x00\r\n]/', $cmd ) ) {
			return new WP_Error( 'cli', 'Ungültiger Befehl.' );
		}
		$args = array();
		$cur  = '';
		$has  = false;
		$q    = null;
		$len  = strlen( $cmd );
		for ( $i = 0; $i < $len; $i++ ) {
			$c = $cmd[ $i ];
			if ( null === $q ) {
				if ( ctype_space( $c ) ) {
					if ( $has ) {
						$args[] = $cur;
					}
					$cur = '';
					$has = false;
					continue;
				}
				if ( "'" === $c || '"' === $c ) {
					$q   = $c;
					$has = true;
					continue;
				}
				if ( '\\' === $c && $i + 1 < $len ) {
					$cur .= $cmd[ ++$i ];
					$has  = true;
					continue;
				}
				$cur .= $c;
				$has  = true;
			} elseif ( $c === $q ) {
				$q = null;
			} elseif ( '\\' === $c && '"' === $q && $i + 1 < $len && in_array( $cmd[ $i + 1 ], array( '"', '\\' ), true ) ) {
				$cur .= $cmd[ ++$i ];
			} else {
				$cur .= $c;
			}
		}
		if ( null !== $q ) {
			return new WP_Error( 'cli', 'Nicht geschlossenes Anführungszeichen.' );
		}
		if ( $has ) {
			$args[] = $cur;
		}
		if ( $args && 'wp' === $args[0] ) {
			array_shift( $args );
		}
		if ( ! $args ) {
			return new WP_Error( 'cli', 'Leerer Befehl.' );
		}
		return $args;
	}

	const CLI_READ_ONLY = array(
		'core version', 'core check-update', 'core verify-checksums', 'plugin list', 'plugin status', 'plugin get', 'plugin verify-checksums',
		'theme list', 'theme status', 'theme get', 'post list', 'post get', 'post-type list', 'taxonomy list', 'term list', 'comment list',
		'cron event list', 'cron schedule list', 'cache type', 'rewrite list', 'db size', 'db tables', 'site list', 'language core list',
		'language plugin list', 'menu list', 'sidebar list', 'widget list', 'role list', 'cap list', 'user list', 'cli version', 'cli info',
	);

	/** @return array|WP_Error [args, read_only] */
	public static function cli_check( array $args ) {
		foreach ( $args as $a ) {
			if ( preg_match( '/^--(ssh|http|path|require|exec|allow-root|prompt|debug)(=|$)/i', $a ) ) {
				return new WP_Error( 'cli', "Option \"$a\" ist nicht erlaubt." );
			}
		}
		$cmd2 = strtolower( implode( ' ', array_slice( $args, 0, 2 ) ) );
		$cmd1 = strtolower( $args[0] );
		$deny = array( 'shell', 'server', 'cli update', 'package', 'db cli', 'db shell', 'db export', 'db import', 'db query', 'db reset', 'db drop', 'config get', 'config list', 'config path', 'config create', 'config shuffle-salts', 'user application-password', 'user session' );
		foreach ( $deny as $d ) {
			if ( $cmd1 === $d || $cmd2 === $d || 0 === strpos( $cmd2 . ' ', $d . ' ' ) ) {
				return new WP_Error( 'cli', "\"wp $d\" ist gesperrt (Zugangsdaten/Backups im Webroot bzw. interaktiv). Für SQL das SQL-Tool verwenden." );
			}
		}
		foreach ( $args as $a ) {
			if ( false !== stripos( $a, 'claude-mcp-bridge' ) || false !== stripos( $a, 'cmb_' ) ) {
				return new WP_Error( 'cli', 'Befehle, die die Claude MCP Bridge betreffen, sind gesperrt.' );
			}
		}
		$read_only = in_array( $cmd2, self::CLI_READ_ONLY, true ) || 'help' === $cmd1;
		foreach ( $args as $a ) {
			// Felder mit Geheimnissen nur mit Freigabe.
			if ( preg_match( '/user_pass|user_activation_key|password|secret|token/i', $a ) ) {
				$read_only = false;
			}
		}
		return array( $args, $read_only );
	}

	public static function cli_run( array $args ) {
		if ( null !== ( $why = self::blocked_reason( 'wpcli' ) ) ) {
			return new WP_Error( 'cli', $why );
		}
		$c = self::cli_check( $args );
		if ( is_wp_error( $c ) ) {
			return $c;
		}
		$wp  = (string) CMB_Settings::get( 'wp_cli_path' );
		$php = (string) CMB_Settings::get( 'php_cli_path' );
		$cmd = array();
		if ( '' !== $php ) {
			$cmd[] = $php;
		}
		$cmd[] = $wp;
		foreach ( $args as $a ) {
			$cmd[] = $a;
		}
		$cmd[] = '--path=' . ABSPATH;
		$cmd[] = '--no-color';
		if ( function_exists( 'posix_geteuid' ) && 0 === posix_geteuid() ) {
			$cmd[] = '--allow-root';
		}
		$env  = array(
			'PATH'      => '/usr/local/bin:/usr/bin:/bin',
			'HOME'      => sys_get_temp_dir(),
			'LANG'      => 'C.UTF-8',
			'WP_CLI_CACHE_DIR' => sys_get_temp_dir() . '/cmb-wp-cli-cache',
		);
		$proc = proc_open( $cmd, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, ABSPATH, $env );
		if ( ! is_resource( $proc ) ) {
			return new WP_Error( 'cli', 'WP-CLI konnte nicht gestartet werden.' );
		}
		fclose( $pipes[0] );
		stream_set_blocking( $pipes[1], false );
		stream_set_blocking( $pipes[2], false );
		$out   = '';
		$err   = '';
		$start = microtime( true );
		$timed = false;
		while ( true ) {
			$out .= (string) stream_get_contents( $pipes[1] );
			$err .= (string) stream_get_contents( $pipes[2] );
			$st   = proc_get_status( $proc );
			if ( ! $st['running'] ) {
				$code = $st['exitcode'];
				break;
			}
			if ( microtime( true ) - $start > self::CLI_TIMEOUT ) {
				proc_terminate( $proc, 9 );
				$timed = true;
				$code  = -1;
				break;
			}
			if ( strlen( $out ) > 4 * self::MAX_OUTPUT ) {
				proc_terminate( $proc, 9 );
				$code = -1;
				break;
			}
			usleep( 50000 );
		}
		$out .= (string) stream_get_contents( $pipes[1] );
		$err .= (string) stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		proc_close( $proc );
		return array(
			'exit_code' => $code,
			'timed_out' => $timed,
			'stdout'    => self::clip( $out ),
			'stderr'    => self::clip( $err, 16384 ),
		);
	}

	/* ------------------------------------------------------------ plugins */

	public static function load_admin_includes() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
	}

	/** Plugin-Datei (z. B. akismet/akismet.php) zu einem Slug oder Dateinamen. */
	public static function plugin_file( $id ) {
		self::load_admin_includes();
		$id  = (string) $id;
		$all = get_plugins();
		if ( isset( $all[ $id ] ) ) {
			return $id;
		}
		foreach ( array_keys( $all ) as $file ) {
			if ( dirname( $file ) === $id || basename( $file, '.php' ) === $id && '.' === dirname( $file ) ) {
				return $file;
			}
		}
		return null;
	}

	public static function is_self( $file ) {
		return plugin_basename( CMB_FILE ) === $file || basename( (string) $file ) === basename( CMB_FILE );
	}

	public static function plugin_info( $slug ) {
		self::load_admin_includes();
		if ( ! preg_match( '/^[a-z0-9][a-z0-9-]{0,99}$/', (string) $slug ) ) {
			return new WP_Error( 'slug', 'Ungültiger Plugin-Slug.' );
		}
		$info = plugins_api( 'plugin_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false, 'short_description' => true, 'icons' => false, 'banners' => false, 'reviews' => false ) ) );
		if ( is_wp_error( $info ) ) {
			return new WP_Error( 'api', 'Plugin auf wordpress.org nicht gefunden oder nicht erreichbar.' );
		}
		$link = (string) ( $info->download_link ?? '' );
		if ( ! preg_match( '#^https://downloads\.wordpress\.org/plugin/[A-Za-z0-9._-]+\.zip$#', $link ) ) {
			return new WP_Error( 'source', 'Nur Downloads direkt von wordpress.org sind erlaubt.' );
		}
		return array(
			'slug'           => $info->slug,
			'name'           => wp_strip_all_tags( $info->name ),
			'version'        => $info->version,
			'author'         => wp_strip_all_tags( (string) $info->author ),
			'active_installs' => (int) ( $info->active_installs ?? 0 ),
			'last_updated'   => (string) ( $info->last_updated ?? '' ),
			'requires'       => (string) ( $info->requires ?? '' ),
			'tested'         => (string) ( $info->tested ?? '' ),
			'description'    => wp_strip_all_tags( (string) ( $info->short_description ?? '' ) ),
			'download_link'  => $link,
		);
	}

	private static function skin_error( $skin, $result ) {
		if ( is_wp_error( $result ) ) {
			return $result->get_error_message();
		}
		if ( $skin && method_exists( $skin, 'get_errors' ) && $skin->get_errors()->has_errors() ) {
			return implode( ' ', (array) $skin->get_error_messages() );
		}
		if ( ! $result ) {
			return 'Fehlgeschlagen (Dateirechte? FS_METHOD?).';
		}
		return null;
	}

	public static function plugin_action( $action, $id, $activate = false ) {
		self::load_admin_includes();
		if ( ! self::file_writes_allowed() && in_array( $action, array( 'install', 'update', 'delete' ), true ) ) {
			return new WP_Error( 'mods', 'DISALLOW_FILE_MODS ist gesetzt.' );
		}
		if ( 'install' === $action ) {
			$info = self::plugin_info( $id );
			if ( is_wp_error( $info ) ) {
				return $info;
			}
			if ( self::plugin_file( $info['slug'] ) ) {
				return new WP_Error( 'exists', 'Plugin ist bereits installiert.' );
			}
			$skin     = new WP_Ajax_Upgrader_Skin();
			$upgrader = new Plugin_Upgrader( $skin );
			$r        = $upgrader->install( $info['download_link'] );
			if ( $e = self::skin_error( $skin, $r ) ) {
				return new WP_Error( 'install', 'Installation fehlgeschlagen: ' . $e );
			}
			$file = $upgrader->plugin_info();
			$msg  = 'Installiert: ' . $info['name'] . ' ' . $info['version'];
			if ( $activate && $file ) {
				$a = activate_plugin( $file );
				$msg .= is_wp_error( $a ) ? ' (Aktivierung fehlgeschlagen: ' . $a->get_error_message() . ')' : ' und aktiviert';
			}
			return $msg;
		}

		$file = self::plugin_file( $id );
		if ( ! $file ) {
			return new WP_Error( 'missing', 'Plugin ist nicht installiert.' );
		}
		if ( self::is_self( $file ) ) {
			return new WP_Error( 'self', 'Die Bridge kann sich nicht selbst verändern.' );
		}
		switch ( $action ) {
			case 'activate':
				$r = activate_plugin( $file );
				return is_wp_error( $r ) ? $r : 'Aktiviert: ' . $file;
			case 'deactivate':
				deactivate_plugins( $file );
				return 'Deaktiviert: ' . $file;
			case 'update':
				$skin     = new WP_Ajax_Upgrader_Skin();
				$upgrader = new Plugin_Upgrader( $skin );
				wp_update_plugins();
				$r = $upgrader->upgrade( $file );
				if ( $e = self::skin_error( $skin, $r ) ) {
					return new WP_Error( 'update', 'Update fehlgeschlagen: ' . $e );
				}
				return 'Aktualisiert: ' . $file;
			case 'delete':
				deactivate_plugins( $file );
				$r = delete_plugins( array( $file ) );
				return is_wp_error( $r ) || ! $r ? new WP_Error( 'delete', 'Löschen fehlgeschlagen.' ) : 'Gelöscht: ' . $file;
		}
		return new WP_Error( 'action', 'Unbekannte Aktion.' );
	}
}
