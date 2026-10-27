<?php
/**
 * Sehr kleiner PDF-Erzeuger für Belege (A4, Helvetica, Umlaute über WinAnsi), ohne fremde Bibliothek.
 *
 * Koordinaten in Millimetern ab der linken oberen Ecke.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_PDF {

	const K = 2.834645669; // pt pro mm.
	const W = 210;
	const H = 297;

	private $pages  = array();
	private $page   = -1;
	private $title  = '';
	private $images = array();

	/**
	 * Zeichenbreiten von Helvetica (1/1000 Schriftgröße) für WinAnsi-Zeichen. Nicht aufgeführte Zeichen: 556.
	 */
	private static $widths = array(
		32 => 278, 33 => 278, 34 => 355, 35 => 556, 36 => 556, 37 => 889, 38 => 667, 39 => 191, 40 => 333, 41 => 333,
		42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278, 58 => 278, 59 => 278, 60 => 584, 61 => 584,
		62 => 584, 63 => 556, 64 => 1015, 65 => 667, 66 => 667, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778,
		72 => 722, 73 => 278, 74 => 500, 75 => 667, 76 => 556, 77 => 833, 78 => 722, 79 => 778, 80 => 667, 81 => 778,
		82 => 722, 83 => 667, 84 => 611, 85 => 722, 86 => 667, 87 => 944, 88 => 667, 89 => 667, 90 => 611, 91 => 278,
		92 => 278, 93 => 278, 94 => 469, 95 => 556, 96 => 333, 99 => 500, 102 => 278, 105 => 222, 106 => 222, 107 => 500,
		108 => 222, 109 => 833, 114 => 333, 115 => 500, 116 => 278, 118 => 500, 119 => 722, 120 => 500, 121 => 500,
		122 => 500, 123 => 334, 124 => 260, 125 => 334, 126 => 584, 128 => 556, 130 => 222, 132 => 333, 133 => 1000,
		145 => 222, 146 => 222, 147 => 333, 148 => 333, 149 => 350, 150 => 556, 151 => 1000, 167 => 556, 176 => 400,
		183 => 278, 196 => 667, 214 => 778, 215 => 584, 220 => 722, 223 => 611,
	);

	public function __construct( $title = '' ) {
		$this->title = (string) $title;
	}

	public function add_page() {
		$this->pages[] = '';
		$this->page    = count( $this->pages ) - 1;
	}

	/**
	 * UTF-8 nach Windows-1252 (WinAnsi), unbekannte Zeichen werden zu „?“.
	 */
	public static function encode( $s ) {
		$s = html_entity_decode( (string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' ); // z. B. „&amp;“ aus Artikelnamen.
		if ( function_exists( 'mb_convert_encoding' ) ) {
			return mb_convert_encoding( $s, 'Windows-1252', 'UTF-8' );
		}
		return function_exists( 'iconv' ) ? (string) iconv( 'UTF-8', 'Windows-1252//TRANSLIT', $s ) : $s;
	}

	/**
	 * Textbreite in mm.
	 */
	public function width( $text, $size = 10, $bold = false ) {
		$enc = self::encode( $text );
		$w   = 0;
		$len = strlen( $enc );
		for ( $i = 0; $i < $len; $i++ ) {
			$o  = ord( $enc[ $i ] );
			$w += isset( self::$widths[ $o ] ) ? self::$widths[ $o ] : 556;
		}
		return $w * $size / 1000 / self::K * ( $bold ? 1.06 : 1 );
	}

	/**
	 * Text in Zeilen umbrechen, die höchstens $max mm breit sind.
	 */
	public function wrap( $text, $max, $size = 10, $bold = false ) {
		$lines = array();
		foreach ( preg_split( '/\R/u', (string) $text ) as $para ) {
			$line = '';
			foreach ( preg_split( '/\s+/u', trim( $para ) ) as $word ) {
				$try = '' === $line ? $word : $line . ' ' . $word;
				if ( '' !== $line && $this->width( $try, $size, $bold ) > $max ) {
					$lines[] = $line;
					$line    = $word;
				} else {
					$line = $try;
				}
			}
			$lines[] = $line;
		}
		return $lines;
	}

	private static function esc( $s ) {
		return str_replace( array( '\\', '(', ')', "\r", "\n" ), array( '\\\\', '\\(', '\\)', '', '' ), $s );
	}

	private static function num( $n ) {
		return rtrim( rtrim( sprintf( '%.3F', $n ), '0' ), '.' );
	}

	/**
	 * Text setzen. $y ist die Grundlinie, $align L, R oder C (bei R/C ist $x die rechte Kante bzw. Mitte).
	 */
	public function text( $x, $y, $text, $size = 10, $bold = false, $align = 'L', $gray = 0 ) {
		if ( 'R' === $align ) {
			$x -= $this->width( $text, $size, $bold );
		} elseif ( 'C' === $align ) {
			$x -= $this->width( $text, $size, $bold ) / 2;
		}
		$g = self::num( $gray );
		$this->pages[ $this->page ] .= sprintf( "BT %s g /%s %s Tf %s %s Td (%s) Tj ET\n", $g, $bold ? 'F2' : 'F1', self::num( $size ), self::num( $x * self::K ), self::num( ( self::H - $y ) * self::K ), self::esc( self::encode( $text ) ) );
	}

	public function line( $x1, $y1, $x2, $y2, $width = 0.2, $gray = 0.7 ) {
		$this->pages[ $this->page ] .= sprintf( "%s G %s w %s %s m %s %s l S\n", self::num( $gray ), self::num( $width * self::K ), self::num( $x1 * self::K ), self::num( ( self::H - $y1 ) * self::K ), self::num( $x2 * self::K ), self::num( ( self::H - $y2 ) * self::K ) );
	}

	public function rect( $x, $y, $w, $h, $gray = 0.95 ) {
		$this->pages[ $this->page ] .= sprintf( "%s g %s %s %s %s re f\n", self::num( $gray ), self::num( $x * self::K ), self::num( ( self::H - $y - $h ) * self::K ), self::num( $w * self::K ), self::num( $h * self::K ) );
	}

	/**
	 * JPEG-Bild setzen ($y = Oberkante). Andere Formate vorher umwandeln.
	 */
	public function image( $file, $x, $y, $w, $h ) {
		$info = @getimagesize( $file ); // phpcs:ignore
		if ( ! $info || IMAGETYPE_JPEG !== $info[2] ) {
			return false;
		}
		$key = md5( $file );
		if ( ! isset( $this->images[ $key ] ) ) {
			$ch                   = isset( $info['channels'] ) ? (int) $info['channels'] : 3;
			$this->images[ $key ] = array(
				'name'  => 'Im' . ( count( $this->images ) + 1 ),
				'w'     => (int) $info[0],
				'h'     => (int) $info[1],
				'cs'    => 1 === $ch ? 'DeviceGray' : ( 4 === $ch ? 'DeviceCMYK' : 'DeviceRGB' ),
				'data'  => (string) file_get_contents( $file ), // phpcs:ignore
				'cmyk'  => 4 === $ch,
			);
		}
		$this->pages[ $this->page ] .= sprintf( "q %s 0 0 %s %s %s cm /%s Do Q\n", self::num( $w * self::K ), self::num( $h * self::K ), self::num( $x * self::K ), self::num( ( self::H - $y - $h ) * self::K ), $this->images[ $key ]['name'] );
		return true;
	}

	/**
	 * Fertiges PDF als String.
	 */
	public function output() {
		$objs   = array();
		$n      = count( $this->pages );
		$objs[] = '<< /Type /Catalog /Pages 2 0 R >>';
		$kids   = array();
		for ( $i = 0; $i < $n; $i++ ) {
			$kids[] = ( 5 + 2 * $i ) . ' 0 R';
		}
		$objs[] = '<< /Type /Pages /Kids [' . implode( ' ', $kids ) . '] /Count ' . $n . ' >>';
		$objs[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
		$objs[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
		$xobj = '';
		$k    = 0;
		foreach ( $this->images as $img ) {
			$xobj .= ' /' . $img['name'] . ' ' . ( 5 + 2 * $n + $k ) . ' 0 R';
			$k++;
		}
		$xobj = $xobj ? ' /XObject <<' . $xobj . ' >>' : '';
		foreach ( $this->pages as $i => $content ) {
			$objs[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources << /Font << /F1 3 0 R /F2 4 0 R >>' . $xobj . ' >> /Contents ' . ( 6 + 2 * $i ) . ' 0 R >>';
			if ( function_exists( 'gzcompress' ) ) {
				$data   = gzcompress( $content );
				$objs[] = "<< /Filter /FlateDecode /Length " . strlen( $data ) . " >>\nstream\n" . $data . "\nendstream";
			} else {
				$objs[] = '<< /Length ' . strlen( $content ) . " >>\nstream\n" . $content . "\nendstream";
			}
		}
		foreach ( $this->images as $img ) {
			$objs[] = '<< /Type /XObject /Subtype /Image /Width ' . $img['w'] . ' /Height ' . $img['h'] . ' /ColorSpace /' . $img['cs'] . ( $img['cmyk'] ? ' /Decode [1 0 1 0 1 0 1 0]' : '' ) . ' /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen( $img['data'] ) . " >>\nstream\n" . $img['data'] . "\nendstream";
		}
		$objs[] = '<< /Title (' . self::esc( self::encode( $this->title ) ) . ') /Producer (Mietservice Barnim Vermietung) /CreationDate (D:' . gmdate( 'YmdHis' ) . 'Z) >>';
		$info   = count( $objs );

		$pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array();
		foreach ( $objs as $i => $o ) {
			$offsets[] = strlen( $pdf );
			$pdf      .= ( $i + 1 ) . " 0 obj\n" . $o . "\nendobj\n";
		}
		$xref = strlen( $pdf );
		$pdf .= "xref\n0 " . ( count( $objs ) + 1 ) . "\n0000000000 65535 f \n";
		foreach ( $offsets as $off ) {
			$pdf .= sprintf( "%010d 00000 n \n", $off );
		}
		$pdf .= 'trailer << /Size ' . ( count( $objs ) + 1 ) . ' /Root 1 0 R /Info ' . $info . " 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
		return $pdf;
	}
}
