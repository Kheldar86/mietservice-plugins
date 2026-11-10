<?php
/**
 * QR-Code als SVG (1.9.1), ohne externe Bibliothek.
 * Byte-Modus, Fehlerkorrektur Q (25 %), Versionen 1 bis 10 (bis 151 Zeichen). Reicht für die Werbe-Links.
 * Aufbau nach ISO/IEC 18004.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_QR {

	/** Fehlerkorrektur-Codewörter je Block und Anzahl Blöcke für Stufe Q, Index = Version. */
	private static $ecc_q    = array( 0, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24 );
	private static $blocks_q = array( 0, 1, 1, 2, 2, 4, 4, 6, 6, 8, 8 );
	private static $align    = array(
		2  => array( 6, 18 ),
		3  => array( 6, 22 ),
		4  => array( 6, 26 ),
		5  => array( 6, 30 ),
		6  => array( 6, 34 ),
		7  => array( 6, 22, 38 ),
		8  => array( 6, 24, 42 ),
		9  => array( 6, 26, 46 ),
		10 => array( 6, 28, 50 ),
	);

	private $size;
	private $mod  = array();
	private $func = array();

	/**
	 * Matrix [Zeile][Spalte] mit true = dunkel, oder null wenn der Text zu lang ist.
	 */
	public static function matrix( $text ) {
		$bytes = array_values( unpack( 'C*', (string) $text ) );
		$n     = count( $bytes );
		for ( $ver = 1; $ver <= 10; $ver++ ) {
			$cap = self::data_codewords( $ver ) * 8;
			if ( 4 + ( $ver < 10 ? 8 : 16 ) + 8 * $n <= $cap ) {
				$qr = new self( $ver );
				return $qr->build( $bytes );
			}
		}
		return null;
	}

	/**
	 * SVG mit 4 Modulen Ruhezone. $px = Kantenlänge in Pixel (0 = ohne feste Größe).
	 */
	public static function svg( $text, $px = 0 ) {
		$m = self::matrix( $text );
		if ( null === $m ) {
			return '';
		}
		$size = count( $m );
		$full = $size + 8;
		$path = '';
		foreach ( $m as $y => $row ) {
			foreach ( $row as $x => $dark ) {
				if ( $dark ) {
					$path .= 'M' . ( $x + 4 ) . ' ' . ( $y + 4 ) . 'h1v1h-1z';
				}
			}
		}
		$dim = $px ? ' width="' . (int) $px . '" height="' . (int) $px . '"' : '';
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $full . ' ' . $full . '"' . $dim . ' shape-rendering="crispEdges">'
			. '<rect width="' . $full . '" height="' . $full . '" fill="#fff"/><path fill="#000" d="' . $path . '"/></svg>';
	}

	private static function raw_codewords( $ver ) {
		$r = ( 16 * $ver + 128 ) * $ver + 64;
		if ( $ver >= 2 ) {
			$na = intdiv( $ver, 7 ) + 2;
			$r -= ( 25 * $na - 10 ) * $na - 55;
			if ( $ver >= 7 ) {
				$r -= 36;
			}
		}
		return intdiv( $r, 8 );
	}

	private static function data_codewords( $ver ) {
		return self::raw_codewords( $ver ) - self::$ecc_q[ $ver ] * self::$blocks_q[ $ver ];
	}

	private function __construct( $ver ) {
		$this->ver  = $ver;
		$this->size = $ver * 4 + 17;
		$row        = array_fill( 0, $this->size, false );
		$this->mod  = array_fill( 0, $this->size, $row );
		$this->func = array_fill( 0, $this->size, $row );
	}

	private $ver;

	private function build( array $bytes ) {
		$this->draw_functions();
		$this->draw_codewords( $this->codewords( $bytes ) );

		$best = 0;
		$min  = PHP_INT_MAX;
		for ( $mask = 0; $mask < 8; $mask++ ) {
			$this->apply_mask( $mask );
			$this->draw_format( $mask );
			$p = $this->penalty();
			if ( $p < $min ) {
				$min  = $p;
				$best = $mask;
			}
			$this->apply_mask( $mask ); // XOR macht die Maske rückgängig.
		}
		$this->apply_mask( $best );
		$this->draw_format( $best );
		return $this->mod;
	}

	private function set( $x, $y, $dark ) {
		$this->mod[ $y ][ $x ]  = (bool) $dark;
		$this->func[ $y ][ $x ] = true;
	}

	private function draw_functions() {
		$s = $this->size;
		for ( $i = 0; $i < $s; $i++ ) {
			$this->set( 6, $i, 0 === $i % 2 );
			$this->set( $i, 6, 0 === $i % 2 );
		}
		foreach ( array( array( 3, 3 ), array( $s - 4, 3 ), array( 3, $s - 4 ) ) as $c ) {
			for ( $dy = -4; $dy <= 4; $dy++ ) {
				for ( $dx = -4; $dx <= 4; $dx++ ) {
					$x = $c[0] + $dx;
					$y = $c[1] + $dy;
					if ( $x >= 0 && $x < $s && $y >= 0 && $y < $s ) {
						$d = max( abs( $dx ), abs( $dy ) );
						$this->set( $x, $y, 2 !== $d && 4 !== $d );
					}
				}
			}
		}
		if ( isset( self::$align[ $this->ver ] ) ) {
			$pos = self::$align[ $this->ver ];
			$na  = count( $pos );
			for ( $i = 0; $i < $na; $i++ ) {
				for ( $j = 0; $j < $na; $j++ ) {
					if ( ( 0 === $i && 0 === $j ) || ( 0 === $i && $na - 1 === $j ) || ( $na - 1 === $i && 0 === $j ) ) {
						continue;
					}
					for ( $dy = -2; $dy <= 2; $dy++ ) {
						for ( $dx = -2; $dx <= 2; $dx++ ) {
							$this->set( $pos[ $i ] + $dx, $pos[ $j ] + $dy, 1 !== max( abs( $dx ), abs( $dy ) ) );
						}
					}
				}
			}
		}
		$this->draw_format( 0 );
		if ( $this->ver >= 7 ) {
			$rem = $this->ver;
			for ( $i = 0; $i < 12; $i++ ) {
				$rem = ( $rem << 1 ) ^ ( ( $rem >> 11 ) * 0x1F25 );
			}
			$bits = ( $this->ver << 12 ) | $rem;
			for ( $i = 0; $i < 18; $i++ ) {
				$bit = ( $bits >> $i ) & 1;
				$a   = $s - 11 + $i % 3;
				$b   = intdiv( $i, 3 );
				$this->set( $a, $b, $bit );
				$this->set( $b, $a, $bit );
			}
		}
	}

	private function draw_format( $mask ) {
		$data = ( 3 << 3 ) | $mask; // Stufe Q = 11.
		$rem  = $data;
		for ( $i = 0; $i < 10; $i++ ) {
			$rem = ( $rem << 1 ) ^ ( ( $rem >> 9 ) * 0x537 );
		}
		$bits = ( ( $data << 10 ) | $rem ) ^ 0x5412;
		$s    = $this->size;
		for ( $i = 0; $i <= 5; $i++ ) {
			$this->set( 8, $i, ( $bits >> $i ) & 1 );
		}
		$this->set( 8, 7, ( $bits >> 6 ) & 1 );
		$this->set( 8, 8, ( $bits >> 7 ) & 1 );
		$this->set( 7, 8, ( $bits >> 8 ) & 1 );
		for ( $i = 9; $i < 15; $i++ ) {
			$this->set( 14 - $i, 8, ( $bits >> $i ) & 1 );
		}
		for ( $i = 0; $i < 8; $i++ ) {
			$this->set( $s - 1 - $i, 8, ( $bits >> $i ) & 1 );
		}
		for ( $i = 8; $i < 15; $i++ ) {
			$this->set( 8, $s - 15 + $i, ( $bits >> $i ) & 1 );
		}
		$this->set( 8, $s - 8, true );
	}

	private function codewords( array $bytes ) {
		$ver  = $this->ver;
		$cap  = self::data_codewords( $ver ) * 8;
		$bits = '0100' . str_pad( decbin( count( $bytes ) ), $ver < 10 ? 8 : 16, '0', STR_PAD_LEFT );
		foreach ( $bytes as $b ) {
			$bits .= str_pad( decbin( $b ), 8, '0', STR_PAD_LEFT );
		}
		$bits .= str_repeat( '0', min( 4, $cap - strlen( $bits ) ) );
		$bits .= str_repeat( '0', ( 8 - strlen( $bits ) % 8 ) % 8 );
		$data  = array_map( 'bindec', str_split( $bits, 8 ) );
		for ( $pad = 0xEC; count( $data ) < $cap / 8; $pad ^= 0xEC ^ 0x11 ) {
			$data[] = $pad;
		}

		$nb    = self::$blocks_q[ $ver ];
		$ecl   = self::$ecc_q[ $ver ];
		$raw   = self::raw_codewords( $ver );
		$short = $nb - $raw % $nb;
		$slen  = intdiv( $raw, $nb );
		$div   = self::rs_divisor( $ecl );
		$blks  = array();
		$k     = 0;
		for ( $i = 0; $i < $nb; $i++ ) {
			$len = $slen - $ecl + ( $i < $short ? 0 : 1 );
			$dat = array_slice( $data, $k, $len );
			$k  += $len;
			$ecc = self::rs_remainder( $dat, $div );
			if ( $i < $short ) {
				$dat[] = 0;
			}
			$blks[] = array_merge( $dat, $ecc );
		}
		$out = array();
		$bl  = count( $blks[0] );
		for ( $i = 0; $i < $bl; $i++ ) {
			foreach ( $blks as $j => $blk ) {
				if ( $i !== $slen - $ecl || $j >= $short ) {
					$out[] = $blk[ $i ];
				}
			}
		}
		return $out;
	}

	private static function gf_mul( $x, $y ) {
		$z = 0;
		for ( $i = 7; $i >= 0; $i-- ) {
			$z  = ( $z << 1 ) ^ ( ( $z >> 7 ) * 0x11D );
			$z ^= ( ( $y >> $i ) & 1 ) * $x;
		}
		return $z;
	}

	private static function rs_divisor( $deg ) {
		$r                = array_fill( 0, $deg, 0 );
		$r[ $deg - 1 ]    = 1;
		$root             = 1;
		for ( $i = 0; $i < $deg; $i++ ) {
			for ( $j = 0; $j < $deg; $j++ ) {
				$r[ $j ] = self::gf_mul( $r[ $j ], $root );
				if ( $j + 1 < $deg ) {
					$r[ $j ] ^= $r[ $j + 1 ];
				}
			}
			$root = self::gf_mul( $root, 0x02 );
		}
		return $r;
	}

	private static function rs_remainder( array $data, array $div ) {
		$r = array_fill( 0, count( $div ), 0 );
		foreach ( $data as $b ) {
			$f   = $b ^ array_shift( $r );
			$r[] = 0;
			foreach ( $div as $i => $d ) {
				$r[ $i ] ^= self::gf_mul( $d, $f );
			}
		}
		return $r;
	}

	private function draw_codewords( array $data ) {
		$s     = $this->size;
		$i     = 0;
		$total = count( $data ) * 8;
		for ( $right = $s - 1; $right >= 1; $right -= 2 ) {
			if ( 6 === $right ) {
				$right = 5;
			}
			for ( $v = 0; $v < $s; $v++ ) {
				for ( $j = 0; $j < 2; $j++ ) {
					$x  = $right - $j;
					$up = 0 === ( ( $right + 1 ) & 2 );
					$y  = $up ? $s - 1 - $v : $v;
					if ( ! $this->func[ $y ][ $x ] && $i < $total ) {
						$this->mod[ $y ][ $x ] = 1 === ( ( $data[ $i >> 3 ] >> ( 7 - ( $i & 7 ) ) ) & 1 );
						$i++;
					}
				}
			}
		}
	}

	private function apply_mask( $m ) {
		$s = $this->size;
		for ( $y = 0; $y < $s; $y++ ) {
			for ( $x = 0; $x < $s; $x++ ) {
				if ( $this->func[ $y ][ $x ] ) {
					continue;
				}
				switch ( $m ) {
					case 0: $inv = 0 === ( $x + $y ) % 2; break;
					case 1: $inv = 0 === $y % 2; break;
					case 2: $inv = 0 === $x % 3; break;
					case 3: $inv = 0 === ( $x + $y ) % 3; break;
					case 4: $inv = 0 === ( intdiv( $x, 3 ) + intdiv( $y, 2 ) ) % 2; break;
					case 5: $inv = 0 === $x * $y % 2 + $x * $y % 3; break;
					case 6: $inv = 0 === ( $x * $y % 2 + $x * $y % 3 ) % 2; break;
					default: $inv = 0 === ( ( $x + $y ) % 2 + $x * $y % 3 ) % 2;
				}
				if ( $inv ) {
					$this->mod[ $y ][ $x ] = ! $this->mod[ $y ][ $x ];
				}
			}
		}
	}

	/** Strafpunkte nach den vier Regeln der Norm (Reihen, 2×2-Blöcke, Finder-ähnliche Muster, Verhältnis). */
	private function penalty() {
		$s    = $this->size;
		$p    = 0;
		$dark = 0;
		$lines = array();
		for ( $y = 0; $y < $s; $y++ ) {
			$row = '';
			$col = '';
			for ( $x = 0; $x < $s; $x++ ) {
				$row  .= $this->mod[ $y ][ $x ] ? '1' : '0';
				$col  .= $this->mod[ $x ][ $y ] ? '1' : '0';
				$dark += $this->mod[ $y ][ $x ] ? 1 : 0;
				if ( $x < $s - 1 && $y < $s - 1 ) {
					$c = $this->mod[ $y ][ $x ];
					if ( $c === $this->mod[ $y ][ $x + 1 ] && $c === $this->mod[ $y + 1 ][ $x ] && $c === $this->mod[ $y + 1 ][ $x + 1 ] ) {
						$p += 3;
					}
				}
			}
			$lines[] = $row;
			$lines[] = $col;
		}
		foreach ( $lines as $l ) {
			if ( preg_match_all( '/0{5,}|1{5,}/', $l, $mm ) ) {
				foreach ( $mm[0] as $run ) {
					$p += 3 + strlen( $run ) - 5;
				}
			}
			$p += 40 * preg_match_all( '/(?=(10111010000|00001011101))/', $l );
		}
		$total = $s * $s;
		$p    += 10 * max( 0, (int) ceil( abs( $dark * 20 - $total * 10 ) / $total ) - 1 );
		return $p;
	}
}
