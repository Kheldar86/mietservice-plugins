<?php
/**
 * Kleinanzeigen-Vorlage (1.8.0): Anzeigentext, Fotos und Kurzlink je Mietartikel zum Einfügen bei Kleinanzeigen.
 * Kleinanzeigen hat keine offene Schnittstelle und verbietet automatisches Einstellen, deshalb wird nur kopiert.
 * Der Kurzlink /ka/<name> leitet mit UTM-Parametern auf den Artikel weiter, zählt die Aufrufe und merkt sich die
 * Herkunft im Warenkorb bis zur Buchung (kein eigenes Cookie).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Kleinanzeigen {

	const SOURCE     = 'kleinanzeigen';
	const QUERY_VAR  = 'msb_ka';
	const TITLE_MAX  = 65;
	const TEXT_MAX   = 4000;
	const PICKUP     = 'Abholung und Rückgabe in Bernau.'; // Adresse und Zeiten blendet Kleinanzeigen selbst ein.
	const BOT_AGENTS = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|curl|wget|python|headless/i';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'redirect' ), 1 );

		// Herkunft merken, bevor der Artikel in den Warenkorb kommt (MSB_Ajax::cart_add beendet die Anfrage selbst).
		add_action( 'wp_ajax_msb_cart_add', array( __CLASS__, 'remember_ref' ), 1 );
		add_action( 'wp_ajax_nopriv_msb_cart_add', array( __CLASS__, 'remember_ref' ), 1 );
		add_action( 'msb_booking_created', array( __CLASS__, 'on_booking_created' ), 10, 2 );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
			add_action( 'save_post_' . MSB_Items::POST_TYPE, array( __CLASS__, 'save' ), 20 );
			add_filter( 'manage_' . MSB_Items::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ), 20 );
			add_action( 'manage_' . MSB_Items::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
			add_action( 'admin_post_msb_ka_photos', array( __CLASS__, 'download_photos' ) );
		}
	}

	/* ------------------------------------------------------------------
	 * Kurzlink /ka/<name>
	 * ------------------------------------------------------------------ */

	public static function rewrite() {
		add_rewrite_rule( '^ka/([a-z0-9-]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
		// Neue Regel einmal nach dem Update eintragen (msb_maybe_flush_rewrite läuft später in init).
		if ( '1.8.0' !== get_option( 'msb_ka_rules' ) ) {
			update_option( 'msb_ka_rules', '1.8.0' );
			update_option( 'msb_flush_rewrite', 1 );
		}
	}

	public static function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public static function redirect() {
		$slug = sanitize_title( (string) get_query_var( self::QUERY_VAR ) );
		if ( '' === $slug ) {
			return;
		}
		$id     = self::item_by_slug( $slug );
		$target = $id ? get_permalink( $id ) : get_post_type_archive_link( MSB_Items::POST_TYPE );
		if ( $id ) {
			self::count_click( $id );
		}
		wp_redirect( self::utm_url( $target ? $target : home_url( '/' ), $slug ), 302 );
		exit;
	}

	public static function utm_url( $url, $slug ) {
		return add_query_arg(
			array(
				'utm_source'   => self::SOURCE,
				'utm_medium'   => 'anzeige',
				'utm_campaign' => 'mietartikel',
				'utm_content'  => $slug,
			),
			$url
		);
	}

	private static function item_by_slug( $slug ) {
		$ids = get_posts(
			array(
				'post_type'      => MSB_Items::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			if ( self::slug( $id ) === $slug ) {
				return (int) $id;
			}
		}
		return 0;
	}

	/**
	 * Name des Kurzlinks: gespeichert oder aus dem Fokus-Keyword („Kinderkraxe mieten“ → kinderkraxe).
	 */
	public static function slug( $id ) {
		$saved = (string) get_post_meta( $id, '_msb_ka_slug', true );
		return '' !== $saved ? $saved : self::default_slug( $id );
	}

	private static function default_slug( $id ) {
		$type = self::product_type( $id );
		if ( '' !== $type ) {
			return sanitize_title( $type );
		}
		$name = (string) get_post_field( 'post_name', $id );
		return sanitize_title( preg_replace( '/-mieten.*$/', '', $name ) );
	}

	public static function short_url( $id ) {
		return home_url( '/ka/' . self::slug( $id ) );
	}

	/**
	 * Aufrufe je Monat. Vorschauen von Messengern, Suchmaschinen und eigene Aufrufe im Backend zählen nicht.
	 */
	private static function count_click( $id ) {
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : ''; // phpcs:ignore
		$head  = isset( $_SERVER['REQUEST_METHOD'] ) && 'HEAD' === $_SERVER['REQUEST_METHOD'];
		if ( $head || '' === $agent || preg_match( self::BOT_AGENTS, $agent ) || current_user_can( 'edit_msb_items' ) ) {
			return;
		}
		$clicks = self::clicks( $id );
		$month  = current_time( 'Y-m' );
		$clicks[ $month ] = ( isset( $clicks[ $month ] ) ? (int) $clicks[ $month ] : 0 ) + 1;
		update_post_meta( $id, '_msb_ka_clicks', $clicks );
	}

	public static function clicks( $id ) {
		$c = get_post_meta( $id, '_msb_ka_clicks', true );
		return is_array( $c ) ? $c : array();
	}

	/* ------------------------------------------------------------------
	 * Herkunft bis zur Buchung
	 * ------------------------------------------------------------------ */

	public static function remember_ref() {
		$ref = wp_get_referer();
		if ( ! $ref ) {
			return;
		}
		parse_str( (string) wp_parse_url( $ref, PHP_URL_QUERY ), $args );
		if ( isset( $args['utm_source'] ) && self::SOURCE === sanitize_key( $args['utm_source'] ) && method_exists( 'MSB_Cart', 'set_ref' ) ) {
			MSB_Cart::set_ref( self::SOURCE );
		}
	}

	public static function on_booking_created( $booking_id, $args ) {
		global $wpdb;
		if ( isset( $args['source'] ) && 'admin' === $args['source'] ) {
			return;
		}
		if ( ! method_exists( 'MSB_Cart', 'ref' ) || self::SOURCE !== MSB_Cart::ref() ) {
			return;
		}
		$wpdb->update( MSB_Install::table( 'bookings' ), array( 'channel' => self::SOURCE ), array( 'id' => (int) $booking_id ) );
	}

	/**
	 * Buchungen über Kleinanzeigen für einen Artikel (ohne Tests und abgebrochene Online-Zahlungen).
	 */
	public static function booking_count( $id ) {
		global $wpdb;
		$b = MSB_Install::table( 'bookings' );
		$i = MSB_Install::table( 'booking_items' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT b.id) FROM {$b} b INNER JOIN {$i} i ON i.booking_id = b.id WHERE b.channel = %s AND b.is_test = 0 AND b.status NOT IN ('awaiting_payment','cancelled') AND i.item_id = %d", self::SOURCE, $id ) ); // phpcs:ignore
	}

	/* ------------------------------------------------------------------
	 * Vorlage
	 * ------------------------------------------------------------------ */

	/**
	 * Produktart aus dem Fokus-Keyword (Styleguide: „[Produktart] mieten“).
	 */
	private static function product_type( $id ) {
		$kw = trim( (string) get_post_meta( $id, 'rank_math_focus_keyword', true ) );
		$kw = trim( strtok( $kw, ',' ) );
		return trim( preg_replace( '/\s+mieten$/iu', '', $kw ) );
	}

	/**
	 * Marke und Modell: der Titel vor dem Gedankenstrich („Deuter Kid Comfort II – Kinderkraxe …“).
	 */
	private static function model( $id ) {
		$title = html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' );
		$parts = preg_split( '/\s+[–-]\s+/u', $title, 2 );
		return trim( $parts[0] );
	}

	public static function title( $id ) {
		$type  = self::product_type( $id );
		$model = self::model( $id );
		$cands = array();
		if ( '' !== $type ) {
			$cands[] = $type . ' mieten in Bernau – ' . $model;
			$cands[] = $type . ' mieten – ' . $model;
		}
		$cands[] = $model . ' mieten in Bernau';
		foreach ( $cands as $c ) {
			if ( mb_strlen( $c ) <= self::TITLE_MAX ) {
				return $c;
			}
		}
		return rtrim( mb_substr( end( $cands ), 0, self::TITLE_MAX - 1 ) ) . '…';
	}

	/**
	 * Anzeigentext: Kurzbeschreibung statt Einleitung (anders als die Website), Abschnitte aus dem Artikeltext,
	 * Technische Daten, Preis aus den Feldern, Abholung, Kurzlink und Hinweis auf die AGB.
	 */
	public static function text( $id ) {
		$item  = MSB_Items::data( $id );
		$parts = array();

		$excerpt = trim( html_entity_decode( wp_strip_all_tags( get_post_field( 'post_excerpt', $id ) ), ENT_QUOTES, 'UTF-8' ) );
		if ( '' !== $excerpt ) {
			$parts[] = $excerpt;
		}

		foreach ( self::sections( (string) get_post_field( 'post_content', $id ) ) as $s ) {
			$parts[] = $s[0] . ":\n" . implode( "\n", $s[1] );
		}

		$specs = array();
		foreach ( $item['specs'] as $sp ) {
			$specs[] = '' !== $sp[0] ? $sp[0] . ': ' . $sp[1] : $sp[1];
		}
		if ( $specs ) {
			$parts[] = "Technische Daten:\n" . implode( "\n", $specs );
		}

		$price = 'Miete: ' . msb_price( $item['price_day'] ) . ' pro Tag';
		if ( $item['tiers'] ) {
			$days  = array_keys( $item['tiers'] );
			$price .= ', ab ' . (int) end( $days ) . ' Tagen ' . msb_price( end( $item['tiers'] ) ) . ' pro Tag';
		}
		if ( $item['base_fee'] > 0 ) {
			$price .= "\nGrundgebühr: " . msb_price( $item['base_fee'] ) . ' je Buchung';
		}
		if ( $item['deposit'] > 0 ) {
			$price .= "\nKaution: " . msb_price( $item['deposit'] );
		}
		if ( $item['min_days'] > 1 ) {
			$price .= "\nMindestmietdauer: " . (int) $item['min_days'] . ' Tage';
		}
		$rules = self::rule_lines( $item );
		if ( $rules ) {
			$price .= "\n\nRabatte und Zuschläge (werden bei der Buchung automatisch berechnet):\n" . implode( "\n", $rules );
		}
		$parts[] = $price . "\n\n" . self::PICKUP;

		$parts[] = "Verfügbarkeit prüfen und online buchen:\n" . preg_replace( '#^https?://#', '', self::short_url( $id ) );
		$parts[] = 'Es gelten unsere AGB, siehe „Rechtliche Angaben“.';

		$text = implode( "\n\n", $parts );
		return mb_strlen( $text ) > self::TEXT_MAX ? mb_substr( $text, 0, self::TEXT_MAX ) : $text;
	}

	/**
	 * Aktive Preisregeln (eigene und globale) als Zeilen, z. B. „• Wochenendrabatt 15 % (Abholung Fr/Sa, …)“.
	 * Abgelaufene Saisonregeln fallen weg.
	 */
	private static function rule_lines( array $item ) {
		$lines     = array();
		$discounts = 0;
		foreach ( MSB_Pricing::rules_for( $item ) as $r ) {
			if ( 'period' === $r['type'] && $r['to'] && $r['to'] < msb_today() ) {
				continue;
			}
			list( $label, $cond, $val ) = MSB_Pricing::rule_summary( $r );
			$cond = preg_replace( '/\bab (\d+) Tage\b/u', 'ab $1 Tagen', $cond );
			$text = in_array( $r['mode'], array( 'percent_off', 'percent_on' ), true ) && empty( $r['label'] ) ? $label : $label . ' ' . $val;
			$lines[] = '• ' . $text . ( '' !== $cond ? ' (' . $cond . ')' : '' );
			if ( in_array( $r['mode'], array( 'percent_off', 'amount_off' ), true ) ) {
				$discounts++;
			}
		}
		if ( $discounts > 1 && ! msb_setting( 'stack_discounts' ) ) {
			$lines[] = 'Treffen mehrere Rabatte zu, gilt der höchste.';
		}
		return $lines;
	}

	/**
	 * Abschnitte (H2) des Artikeltexts als Klartext. Die Einleitung und der Abschluss „… mieten in Bernau“ fallen weg.
	 *
	 * @return array( array( Überschrift, Zeilen ) )
	 */
	private static function sections( $html ) {
		$html  = strip_shortcodes( $html );
		$split = preg_split( '#<h2[^>]*>(.*?)</h2>#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		$out   = array();
		for ( $n = 1; $n + 1 < count( $split ); $n += 2 ) {
			$head = trim( html_entity_decode( wp_strip_all_tags( $split[ $n ] ), ENT_QUOTES, 'UTF-8' ) );
			if ( '' === $head || preg_match( '/mieten in Bernau$/iu', $head ) ) {
				continue;
			}
			$lines = self::plain_lines( $split[ $n + 1 ] );
			if ( $lines ) {
				$out[] = array( $head, $lines );
			}
		}
		return $out;
	}

	private static function plain_lines( $html ) {
		// Verweise in Klammern ergeben ohne Link keinen Sinn („(Tipps von Deuter)“).
		$html  = preg_replace( '#\s*\(\s*<a\s[^>]*>.*?</a>\s*\)#is', '', $html );
		$html  = preg_replace( '#<li[^>]*>\s*#i', "\n\x01", $html );
		$html  = preg_replace( '#</(p|li|ul|ol|div)>|<br\s*/?>#i', "\n", $html );
		$text  = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
		$lines = array();
		foreach ( msb_lines( $text ) as $line ) {
			if ( 0 === strpos( $line, "\x01" ) ) {
				$line = trim( substr( $line, 1 ) );
				$line = 0 === strpos( $line, '✓' ) ? $line : '• ' . $line;
			}
			$lines[] = preg_replace( '/\s+/u', ' ', $line );
		}
		return array_values( array_filter( $lines, 'strlen' ) );
	}

	/**
	 * Stand der Vorlage: ändert sich Titel, Text (auch Preis) oder ein Foto, gilt die Anzeige als veraltet.
	 */
	public static function hash( $id ) {
		return md5( self::title( $id ) . "\n" . self::text( $id ) . "\n" . implode( ',', MSB_Items::gallery_ids( $id ) ) );
	}

	/**
	 * none = nie als eingestellt markiert, current = Anzeige entspricht der Vorlage, outdated = Vorlage hat sich geändert.
	 */
	public static function status( $id ) {
		$saved = (string) get_post_meta( $id, '_msb_ka_hash', true );
		if ( '' === $saved ) {
			return 'none';
		}
		return self::hash( $id ) === $saved ? 'current' : 'outdated';
	}

	/* ------------------------------------------------------------------
	 * Kasten im Artikel
	 * ------------------------------------------------------------------ */

	public static function add_meta_box() {
		add_meta_box( 'msb_item_kleinanzeigen', 'Kleinanzeigen-Vorlage', array( __CLASS__, 'render_box' ), MSB_Items::POST_TYPE, 'normal', 'low' );
	}

	public static function render_box( $post ) {
		wp_nonce_field( 'msb_ka_save', 'msb_ka_nonce' );
		$id = (int) $post->ID;
		if ( 'publish' !== $post->post_status ) {
			echo '<p>Die Vorlage erscheint, sobald der Artikel veröffentlicht ist.</p>';
			return;
		}
		$title  = self::title( $id );
		$text   = self::text( $id );
		$status = self::status( $id );
		$marked = (string) get_post_meta( $id, '_msb_ka_marked', true );
		$url    = (string) get_post_meta( $id, '_msb_ka_url', true );
		$clicks = self::clicks( $id );
		$month  = current_time( 'Y-m' );
		$last   = gmdate( 'Y-m', strtotime( $month . '-01 -1 month' ) );
		$photos = MSB_Items::gallery_ids( $id );
		$zip    = wp_nonce_url( admin_url( 'admin-post.php?action=msb_ka_photos&item=' . $id ), 'msb_ka_photos_' . $id );
		$labels = array(
			'none'     => '<span style="color:#50575e">Noch nicht als eingestellt markiert.</span>',
			'current'  => '<strong style="color:#2f6b3a">Aktuell</strong> – die Anzeige entspricht dieser Vorlage' . ( $marked ? ' (markiert am ' . esc_html( msb_date( $marked ) ) . ')' : '' ) . '.',
			'outdated' => '<strong style="color:#b32d2e">Veraltet</strong> – Text, Preis oder Fotos haben sich seit ' . ( $marked ? esc_html( msb_date( $marked ) ) : 'dem Markieren' ) . ' geändert. Bitte die Anzeige bei Kleinanzeigen anpassen.',
		);
		?>
		<div class="msb-ka">
			<p><?php echo $labels[ $status ]; // phpcs:ignore ?></p>

			<p>
				<label for="msb-ka-title"><strong>Titel</strong> <span class="description">(<?php echo (int) mb_strlen( $title ); ?> von <?php echo (int) self::TITLE_MAX; ?> Zeichen)</span></label><br>
				<input type="text" readonly id="msb-ka-title" class="widefat" value="<?php echo esc_attr( $title ); ?>">
				<button type="button" class="button msb-ka-copy" data-target="msb-ka-title" style="margin-top:4px">Kopieren</button>
			</p>

			<p>
				<strong>Preis</strong><br>
				<span class="description">Im Preisfeld <strong><?php echo esc_html( msb_price( MSB_Items::data( $id )['price_day'] ) ); ?></strong> als Festpreis eintragen. Im Text steht „pro Tag“.</span>
			</p>

			<p>
				<label for="msb-ka-text"><strong>Beschreibung</strong> <span class="description">(<?php echo (int) mb_strlen( $text ); ?> von <?php echo (int) self::TEXT_MAX; ?> Zeichen)</span></label><br>
				<textarea readonly id="msb-ka-text" class="widefat" rows="14"><?php echo esc_textarea( $text ); ?></textarea>
				<button type="button" class="button msb-ka-copy" data-target="msb-ka-text" style="margin-top:4px">Kopieren</button>
			</p>

			<p><strong>Fotos</strong> <span class="description">(Beitragsbild zuerst)</span></p>
			<?php if ( $photos ) : ?>
				<p style="display:flex;flex-wrap:wrap;gap:6px">
					<?php foreach ( $photos as $pid ) : ?>
						<a href="<?php echo esc_url( wp_get_attachment_url( $pid ) ); ?>" target="_blank" rel="noopener"><?php echo wp_get_attachment_image( $pid, array( 60, 60 ) ); ?></a>
					<?php endforeach; ?>
				</p>
				<p><a class="button" href="<?php echo esc_url( $zip ); ?>">Fotos als ZIP herunterladen</a></p>
			<?php else : ?>
				<p class="description">Noch keine Fotos. Beitragsbild und Galerie füllen.</p>
			<?php endif; ?>

			<p>
				<label for="msb_ka_slug"><strong>Kurzlink</strong></label><br>
				<code><?php echo esc_html( home_url( '/ka/' ) ); ?></code><input type="text" id="msb_ka_slug" name="msb_ka_slug" value="<?php echo esc_attr( self::slug( $id ) ); ?>" style="width:180px">
				<br><span class="description">Leitet mit UTM-Parametern auf diesen Artikel. Nur Kleinbuchstaben, Zahlen und Bindestriche.</span>
			</p>
			<p>
				<strong>Aufrufe über den Kurzlink:</strong>
				<?php echo (int) ( isset( $clicks[ $month ] ) ? $clicks[ $month ] : 0 ); ?> diesen Monat,
				<?php echo (int) ( isset( $clicks[ $last ] ) ? $clicks[ $last ] : 0 ); ?> im Vormonat,
				<?php echo (int) array_sum( $clicks ); ?> gesamt ·
				<strong>Buchungen über Kleinanzeigen:</strong> <?php echo (int) self::booking_count( $id ); ?>
			</p>

			<p>
				<label for="msb_ka_url"><strong>Link zur Anzeige bei Kleinanzeigen</strong> <span class="description">(optional, nur zum schnellen Wiederfinden)</span></label><br>
				<input type="url" id="msb_ka_url" name="msb_ka_url" class="widefat" value="<?php echo esc_attr( $url ); ?>" placeholder="https://www.kleinanzeigen.de/s-anzeige/…">
				<?php if ( $url ) : ?>
					<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">Anzeige öffnen</a>
				<?php endif; ?>
			</p>

			<p>
				<label><input type="checkbox" name="msb_ka_mark" value="1"> Anzeige bei Kleinanzeigen ist jetzt auf dem Stand dieser Vorlage</label>
				<br><span class="description">Nach dem Einstellen oder Anpassen anhaken und den Artikel aktualisieren. Ändert sich danach etwas, steht in der Artikelliste „veraltet“.</span>
			</p>
		</div>
		<script>
		document.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( '.msb-ka-copy' );
			if ( ! b ) { return; }
			var f = document.getElementById( b.getAttribute( 'data-target' ) );
			var done = function () { b.textContent = 'Kopiert ✓'; setTimeout( function () { b.textContent = 'Kopieren'; }, 1500 ); };
			f.select();
			if ( navigator.clipboard ) {
				navigator.clipboard.writeText( f.value ).then( done, function () { document.execCommand( 'copy' ); done(); } );
			} else {
				document.execCommand( 'copy' );
				done();
			}
		} );
		</script>
		<?php
	}

	public static function save( $post_id ) {
		if ( ! isset( $_POST['msb_ka_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['msb_ka_nonce'] ) ), 'msb_ka_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['msb_ka_slug'] ) ) {
			$slug = sanitize_title( wp_unslash( $_POST['msb_ka_slug'] ) );
			if ( '' === $slug || self::default_slug( $post_id ) === $slug ) {
				delete_post_meta( $post_id, '_msb_ka_slug' );
				$slug = self::default_slug( $post_id );
			}
			// Jeder Kurzlink nur einmal.
			$base = $slug;
			$n    = 2;
			while ( ( $other = self::item_by_slug( $slug ) ) && $other !== (int) $post_id ) {
				$slug = $base . '-' . $n++;
			}
			if ( self::default_slug( $post_id ) !== $slug ) {
				update_post_meta( $post_id, '_msb_ka_slug', $slug );
			}
		}

		if ( isset( $_POST['msb_ka_url'] ) ) {
			$url = esc_url_raw( wp_unslash( $_POST['msb_ka_url'] ), array( 'https' ) );
			if ( $url ) {
				update_post_meta( $post_id, '_msb_ka_url', $url );
			} else {
				delete_post_meta( $post_id, '_msb_ka_url' );
			}
		}

		if ( ! empty( $_POST['msb_ka_mark'] ) ) {
			update_post_meta( $post_id, '_msb_ka_hash', self::hash( $post_id ) );
			update_post_meta( $post_id, '_msb_ka_marked', msb_today() );
		}
	}

	/* ------------------------------------------------------------------
	 * Artikelliste
	 * ------------------------------------------------------------------ */

	public static function columns( $cols ) {
		$new = array();
		foreach ( $cols as $key => $label ) {
			if ( 'date' === $key ) {
				$new['msb_ka'] = 'Kleinanzeigen';
			}
			$new[ $key ] = $label;
		}
		if ( ! isset( $new['msb_ka'] ) ) {
			$new['msb_ka'] = 'Kleinanzeigen';
		}
		return $new;
	}

	public static function column_content( $col, $post_id ) {
		if ( 'msb_ka' !== $col ) {
			return;
		}
		$status = self::status( $post_id );
		if ( 'current' === $status ) {
			echo '<span style="color:#2f6b3a">aktuell</span>';
		} elseif ( 'outdated' === $status ) {
			echo '<strong style="color:#b32d2e">veraltet</strong>';
		} else {
			echo '–';
		}
		$clicks = self::clicks( $post_id );
		$month  = current_time( 'Y-m' );
		if ( $clicks ) {
			echo '<br><small>' . (int) ( isset( $clicks[ $month ] ) ? $clicks[ $month ] : 0 ) . ' Aufrufe diesen Monat</small>';
		}
	}

	/* ------------------------------------------------------------------
	 * Fotos als ZIP
	 * ------------------------------------------------------------------ */

	public static function download_photos() {
		$id = isset( $_GET['item'] ) ? absint( $_GET['item'] ) : 0;
		check_admin_referer( 'msb_ka_photos_' . $id );
		if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( 'Auf dem Server fehlt die ZIP-Funktion. Bitte die Fotos einzeln über die Vorschaubilder im Kasten „Kleinanzeigen-Vorlage“ speichern.' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$base = sanitize_title( self::model( $id ) );
		$tmp  = wp_tempnam( 'msb-ka-' . $base );
		$zip  = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			wp_die( 'Die ZIP-Datei konnte nicht erstellt werden.' );
		}
		$n = 0;
		foreach ( MSB_Items::gallery_ids( $id ) as $pid ) {
			$file = get_attached_file( $pid );
			if ( $file && is_readable( $file ) ) {
				$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
				$zip->addFile( $file, sprintf( '%s-%02d.%s', $base, ++$n, $ext ) );
			}
		}
		$zip->close();

		if ( ! $n ) {
			@unlink( $tmp ); // phpcs:ignore
			wp_die( 'Für diesen Artikel gibt es keine Fotos.' );
		}
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $base . '-kleinanzeigen.zip"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp ); // phpcs:ignore
		@unlink( $tmp ); // phpcs:ignore
		exit;
	}
}
