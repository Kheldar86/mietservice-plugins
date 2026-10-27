<?php
/**
 * AGB und Widerrufsbelehrung als PDF für die Bestätigungsmail (1.8.3).
 *
 * Ein Link auf die AGB-Seite ist kein dauerhafter Datenträger (§ 312f BGB), weil sich die Seite ändern kann.
 * Deshalb hängt die Mail „Buchung bestätigt“ den Text an, der zum Zeitpunkt der Bestätigung gilt.
 * Das PDF wird aus den Seiten „agb“ und „widerrufsbelehrung“ erzeugt und neu gebaut, sobald sich eine Seite ändert.
 * Ältere Fassungen bleiben in uploads/msb-belege liegen, damit nachvollziehbar ist, welcher Text verschickt wurde.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Legal_PDF {

	const PAGES  = array( 'agb', 'widerrufsbelehrung' );
	const OPTION = 'msb_legal_pdf';

	/**
	 * Anhänge für die Bestätigungsmail (leer, wenn die Seiten fehlen oder das PDF nicht geschrieben werden kann).
	 */
	public static function attachments() {
		$file = self::file();
		return $file ? array( $file ) : array();
	}

	/**
	 * Pfad zum aktuellen PDF; wird bei Bedarf erzeugt.
	 */
	public static function file() {
		$pages = self::pages();
		if ( ! $pages ) {
			return '';
		}
		$hash = substr( md5( MSB_VERSION . '|' . wp_json_encode( $pages ) ), 0, 8 );
		$dir  = self::dir();
		$prev = get_option( self::OPTION, array() );
		if ( is_array( $prev ) && isset( $prev['hash'], $prev['file'] ) && $hash === $prev['hash'] && file_exists( $dir . '/' . $prev['file'] ) ) {
			return $dir . '/' . $prev['file'];
		}
		$stand = 0;
		foreach ( $pages as $p ) {
			$stand = max( $stand, $p['modified'] );
		}
		$name = 'AGB-und-Widerrufsbelehrung-' . wp_date( 'Y-m-d', $stand ) . '-' . $hash . '.pdf';
		if ( false === file_put_contents( $dir . '/' . $name, self::render( $pages, $stand ) ) ) { // phpcs:ignore
			return '';
		}
		update_option( self::OPTION, array( 'hash' => $hash, 'file' => $name ), false );
		return $dir . '/' . $name;
	}

	/**
	 * Titel, Inhalt und Änderungszeit der veröffentlichten Seiten.
	 */
	private static function pages() {
		$out = array();
		foreach ( self::PAGES as $slug ) {
			$page = get_page_by_path( $slug );
			if ( ! $page || 'publish' !== $page->post_status ) {
				continue;
			}
			$out[] = array(
				'title'    => get_the_title( $page ),
				'content'  => (string) $page->post_content,
				'modified' => (int) strtotime( $page->post_modified_gmt . ' UTC' ),
				'url'      => get_permalink( $page ),
			);
		}
		return $out;
	}

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
	 * Seiteninhalt in Blöcke zerlegen: Überschriften, Absätze und Listenpunkte in Reihenfolge.
	 * Kopfbereich (Brotkrumen, Seitentitel) und Inhaltsverzeichnis fallen weg.
	 *
	 * @return array Liste von array( type => h1|h2|h3|p|li, text => … ).
	 */
	public static function blocks( $html ) {
		$html = preg_replace( '#<!--.*?-->#s', '', (string) $html );
		$html = preg_replace( '#<section[^>]*msbx-phero.*?</section>#s', '', $html );
		$html = preg_replace( '#<ul[^>]*msbx-toc.*?</ul>#s', '', $html );
		$html = preg_replace( '#<p>\s*<strong>\s*Inhaltsverzeichnis\s*</strong>\s*</p>#u', '', $html );
		$out  = array();
		if ( preg_match_all( '#<(h1|h2|h3|p|li)\b[^>]*>(.*?)</\1>#s', $html, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $b ) {
				$text = preg_replace( '#<br\s*/?>#i', "\n", $b[2] );
				$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$text = trim( preg_replace( "/[ \t]+/u", ' ', $text ) );
				if ( '' !== $text ) {
					$out[] = array( 'type' => strtolower( $b[1] ), 'text' => $text );
				}
			}
		}
		return $out;
	}

	public static function render( array $pages, $stand ) {
		$name   = get_bloginfo( 'name' );
		$pdf    = new MSB_PDF( 'AGB und Widerrufsbelehrung – ' . $name );
		$left   = 20;
		$width  = 170;
		$bottom = 280;
		$y      = 0;
		$page_no = 0;

		$new_page = function () use ( $pdf, $left, $width, $name, $stand, &$y, &$page_no ) {
			$pdf->add_page();
			$page_no++;
			$pdf->text( $left, 12, $name . ' · AGB und Widerrufsbelehrung · Stand ' . wp_date( 'd.m.Y', $stand ), 8, false, 'L', 0.45 );
			$pdf->text( $left + $width, 12, 'Seite ' . $page_no, 8, false, 'R', 0.45 );
			$pdf->line( $left, 14, $left + $width, 14, 0.15, 0.75 );
			$y = 24;
		};

		// $need = Platz in mm, der vor dem Block mindestens frei sein muss (Überschrift nicht allein am Seitenende).
		$write = function ( $text, $size, $bold, $indent, $gap_before, $need, $bullet = false ) use ( $pdf, $left, $width, $bottom, &$y, $new_page ) {
			$lh    = $size * 0.45;
			$lines = $pdf->wrap( $text, $width - $indent, $size, $bold );
			$y    += $gap_before;
			if ( $y + max( $need, $lh ) > $bottom ) {
				$new_page();
			}
			foreach ( $lines as $n => $line ) {
				if ( $y + $lh > $bottom ) {
					$new_page();
				}
				$y += $lh;
				if ( $bullet && 0 === $n ) {
					$pdf->text( $left + 2, $y, '•', $size );
				}
				$pdf->text( $left + $indent, $y, $line, $size, $bold );
			}
		};

		foreach ( $pages as $i => $p ) {
			$new_page();
			$write( $p['title'], 16, true, 0, 0, 20 );
			$y += 1;
			$pdf->text( $left, $y + 4, $p['url'], 8, false, 'L', 0.45 );
			$y += 6;
			foreach ( self::blocks( $p['content'] ) as $b ) {
				if ( 'h1' === $b['type'] ) {
					continue;
				}
				if ( 'h2' === $b['type'] ) {
					$write( $b['text'], 11.5, true, 0, 5, 18 );
				} elseif ( 'h3' === $b['type'] ) {
					$write( $b['text'], 10, true, 0, 3.5, 14 );
				} elseif ( 'li' === $b['type'] ) {
					$write( $b['text'], 9, false, 6, 1.2, 0, true );
				} else {
					$write( $b['text'], 9, false, 0, 2.2, 0 );
				}
			}
		}
		return $pdf->output();
	}
}
