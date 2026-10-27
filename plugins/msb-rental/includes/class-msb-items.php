<?php
/**
 * Mietartikel (Inventar): Beitragstyp, Kategorien, Artikeldaten.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Items {

	const POST_TYPE = 'msb_item';
	const TAXONOMY  = 'msb_category';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_meta' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		// Klassischer Editor: Preise, Regeln und Galerie stehen direkt sichtbar unter dem Text.
		add_filter(
			'use_block_editor_for_post_type',
			function ( $use, $type ) {
				return self::POST_TYPE === $type ? (bool) apply_filters( 'msb_use_block_editor', false ) : $use;
			},
			10,
			2
		);
	}

	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'               => 'Mietartikel',
					'singular_name'      => 'Mietartikel',
					'add_new'            => 'Neuer Artikel',
					'add_new_item'       => 'Neuen Mietartikel anlegen',
					'edit_item'          => 'Mietartikel bearbeiten',
					'new_item'           => 'Neuer Mietartikel',
					'view_item'          => 'Mietartikel ansehen',
					'search_items'       => 'Mietartikel suchen',
					'not_found'          => 'Keine Mietartikel gefunden',
					'not_found_in_trash' => 'Keine Mietartikel im Papierkorb',
					'all_items'          => 'Mietartikel',
					'menu_name'          => 'Mietartikel',
				),
				'public'       => true,
				'has_archive'  => 'mietartikel',
				'rewrite'      => array( 'slug' => 'mieten' ),
				'show_in_menu' => 'msb-bookings',
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-archive',
				'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
				// Eigene Rechte statt „Beiträge“, damit die Rolle „Gesellschafter“ Artikel ohne Blog-Rechte pflegen kann (ROL-03).
				'capability_type' => array( 'msb_item', 'msb_items' ),
				'map_meta_cap'    => true,
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => 'Mietkategorien',
					'singular_name' => 'Mietkategorie',
					'add_new_item'  => 'Neue Kategorie',
					'edit_item'     => 'Kategorie bearbeiten',
					'all_items'     => 'Alle Kategorien',
					'menu_name'     => 'Kategorien',
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'mietkategorie' ),
				'capabilities'      => array(
					'manage_terms' => msb_capability( 'items' ),
					'edit_terms'   => msb_capability( 'items' ),
					'delete_terms' => msb_capability( 'settings' ),
					'assign_terms' => 'edit_msb_items',
				),
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Artikeldaten
	 * ------------------------------------------------------------------ */

	public static function exists( $id ) {
		$post = get_post( $id );
		return $post && self::POST_TYPE === $post->post_type && 'publish' === $post->post_status;
	}

	/**
	 * Normalisierte Daten eines Mietartikels.
	 */
	public static function data( $id ) {
		$id    = (int) $id;
		$stock = get_post_meta( $id, '_msb_stock', true );

		return array(
			'id'        => $id,
			'title'     => get_the_title( $id ),
			'price_day' => (float) get_post_meta( $id, '_msb_price_day', true ),
			'tiers'     => self::parse_tiers( get_post_meta( $id, '_msb_price_tiers', true ) ),
			'base_fee'  => (float) get_post_meta( $id, '_msb_base_fee', true ),
			'deposit'   => (float) get_post_meta( $id, '_msb_deposit', true ),
			'stock'     => '' === $stock ? 1 : max( 0, (int) $stock ),
			'min_days'  => max( 1, (int) get_post_meta( $id, '_msb_min_days', true ) ),
			'max_days'  => max( 0, (int) get_post_meta( $id, '_msb_max_days', true ) ),
			'blocked'   => self::parse_blocked( get_post_meta( $id, '_msb_blocked', true ) ),
			'specs'     => self::parse_specs( get_post_meta( $id, '_msb_specs', true ) ),
			'bookable'  => 'no' !== get_post_meta( $id, '_msb_bookable', true ),
			'rules'     => self::rules( $id ),
			'ignore_global' => 'yes' === get_post_meta( $id, '_msb_ignore_global_rules', true ),
			'gallery'   => self::gallery_ids( $id ),
		);
	}

	public static function rules( $id ) {
		$r = get_post_meta( $id, '_msb_price_rules', true );
		return is_array( $r ) ? $r : array();
	}

	/**
	 * Bild-IDs: Beitragsbild zuerst, danach die Galerie.
	 */
	public static function gallery_ids( $id, $with_thumbnail = true ) {
		$ids = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $id, '_msb_gallery', true ) ) ) );
		if ( $with_thumbnail ) {
			$thumb = (int) get_post_thumbnail_id( $id );
			if ( $thumb ) {
				$ids = array_merge( array( $thumb ), array_diff( $ids, array( $thumb ) ) );
			}
		}
		return array_values(
			array_filter(
				$ids,
				function ( $i ) {
					return wp_attachment_is_image( $i );
				}
			)
		);
	}

	/**
	 * Staffelpreise: "3=8,50" → ab 3 Tagen 8,50 pro Tag.
	 */
	public static function parse_tiers( $text ) {
		$tiers = array();
		foreach ( msb_lines( $text ) as $line ) {
			if ( preg_match( '/^\D*?(\d+)\s*[=:]\s*([0-9]+(?:[.,][0-9]+)?)/u', $line, $m ) ) {
				$days = (int) $m[1];
				if ( $days > 0 ) {
					$tiers[ $days ] = (float) str_replace( ',', '.', $m[2] );
				}
			}
		}
		ksort( $tiers );
		return $tiers;
	}

	/**
	 * Gesperrte Zeiträume: "24.12.2026 - 26.12.2026 Betriebsferien" oder einzelne Tage.
	 */
	public static function parse_blocked( $text ) {
		$ranges = array();
		foreach ( msb_lines( $text ) as $line ) {
			preg_match_all( '/\d{4}-\d{2}-\d{2}|\d{1,2}\.\d{1,2}\.\d{4}/', $line, $m );
			$dates = array_values( array_filter( array_map( 'msb_normalize_date', $m[0] ) ) );
			if ( empty( $dates ) ) {
				continue;
			}
			$from = $dates[0];
			$to   = isset( $dates[1] ) ? $dates[1] : $dates[0];
			if ( $to < $from ) {
				list( $from, $to ) = array( $to, $from );
			}
			$ranges[] = array( $from, $to );
		}
		return $ranges;
	}

	/**
	 * Technische Daten: "Gewicht: 3,2 kg".
	 */
	public static function parse_specs( $text ) {
		$specs = array();
		foreach ( msb_lines( $text ) as $line ) {
			$parts = explode( ':', $line, 2 );
			if ( 2 === count( $parts ) ) {
				$specs[] = array( trim( $parts[0] ), trim( $parts[1] ) );
			} else {
				$specs[] = array( '', $line );
			}
		}
		return $specs;
	}

	public static function is_blocked( $item, $ymd ) {
		foreach ( $item['blocked'] as $r ) {
			if ( $ymd >= $r[0] && $ymd <= $r[1] ) {
				return true;
			}
		}
		return false;
	}

	/* ------------------------------------------------------------------
	 * Meta-Box
	 * ------------------------------------------------------------------ */

	public static function add_meta_boxes() {
		add_meta_box( 'msb_item_data', 'Vermietung: Preise & Inventar', array( __CLASS__, 'render_meta_box' ), self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'msb_item_rules', 'Dynamische Preise & Rabatte', array( __CLASS__, 'render_rules_box' ), self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'msb_item_gallery', 'Bildergalerie', array( __CLASS__, 'render_gallery_box' ), self::POST_TYPE, 'side', 'low' );
		add_meta_box( 'msb_item_shortcodes', 'Shortcodes', array( __CLASS__, 'render_shortcode_box' ), self::POST_TYPE, 'side', 'default' );
	}

	public static function render_gallery_box( $post ) {
		$ids = self::gallery_ids( $post->ID, false );
		echo '<div class="msb-gallery-edit" data-input="msb_gallery">';
		echo '<ul class="msb-gallery-edit__list">';
		foreach ( $ids as $id ) {
			echo '<li data-id="' . (int) $id . '">' . wp_get_attachment_image( $id, 'thumbnail' ) . '<button type="button" class="msb-gallery-edit__remove" aria-label="Bild entfernen">×</button></li>';
		}
		echo '</ul>';
		echo '<input type="hidden" name="msb_gallery" value="' . esc_attr( implode( ',', $ids ) ) . '">';
		echo '<p><button type="button" class="button msb-gallery-edit__add">Bilder hinzufügen</button></p>';
		echo '<p class="description">Zusätzlich zum Beitragsbild. Reihenfolge per Ziehen ändern. ' . msb_help_link( 'galerie', '' ) . '</p>'; // phpcs:ignore
		echo '</div>';
	}

	public static function render_rules_box( $post ) {
		echo '<p class="description">Regeln verändern den Mietpreis automatisch, z. B. einen Wochenendrabatt oder einen Saisonzuschlag. ' . msb_help_link( 'preisregeln', 'Erklärung & Beispiele' ) . '</p>'; // phpcs:ignore
		self::render_rules_editor( 'msb_rules', self::rules( $post->ID ) );
		echo '<p><label><input type="checkbox" name="msb_ignore_global_rules" value="yes" ' . checked( 'yes', get_post_meta( $post->ID, '_msb_ignore_global_rules', true ), false ) . '> Globale Preisregeln (Einstellungen → Preise) für diesen Artikel <strong>nicht</strong> anwenden</label></p>';
	}

	/**
	 * Editor für Preisregeln (Artikel und Einstellungen).
	 */
	public static function render_rules_editor( $name, array $rules ) {
		$types = MSB_Pricing::rule_types();
		$modes = MSB_Pricing::rule_modes();
		$wd    = msb_weekday_short();
		$row   = function ( $i, $r ) use ( $name, $types, $modes, $wd ) {
			$r = wp_parse_args(
				$r,
				array(
					'active' => 1,
					'type'   => 'weekend',
					'label'  => '',
					'mode'   => 'percent_off',
					'value'  => '',
					'days'   => array(),
					'from'   => '',
					'to'     => '',
					'min'    => '',
					'max'    => '',
				)
			);
			$n = $name . '[' . $i . ']';
			ob_start();
			?>
			<tr class="msb-rule" data-type="<?php echo esc_attr( $r['type'] ); ?>">
				<td class="msb-rule__active"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[active]" value="1" <?php checked( ! empty( $r['active'] ) ); ?> title="aktiv"></td>
				<td>
					<select name="<?php echo esc_attr( $n ); ?>[type]" class="msb-rule__type">
						<?php foreach ( $types as $k => $l ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $r['type'], $k ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
				<td class="msb-rule__cond">
					<span class="msb-c msb-c--weekend">max. <input type="number" min="2" max="7" name="<?php echo esc_attr( $n ); ?>[max]" value="<?php echo esc_attr( $r['max'] ? $r['max'] : ( 'weekend' === $r['type'] ? 4 : '' ) ); ?>" class="small-text msb-in-max"> Kalendertage</span>
					<span class="msb-c msb-c--weekdays"><?php foreach ( array( 1, 2, 3, 4, 5, 6, 0 ) as $d ) : ?><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[days][]" value="<?php echo (int) $d; ?>" <?php checked( in_array( $d, array_map( 'intval', (array) $r['days'] ), true ) ); ?>><?php echo esc_html( $wd[ $d ] ); ?></label><?php endforeach; ?></span>
					<span class="msb-c msb-c--period"><input type="date" name="<?php echo esc_attr( $n ); ?>[from]" value="<?php echo esc_attr( $r['from'] ); ?>"> – <input type="date" name="<?php echo esc_attr( $n ); ?>[to]" value="<?php echo esc_attr( $r['to'] ); ?>"></span>
					<span class="msb-c msb-c--duration">ab <input type="number" min="1" name="<?php echo esc_attr( $n ); ?>[min]" value="<?php echo esc_attr( $r['min'] ); ?>" class="small-text msb-in-min"> bis <input type="number" min="0" name="<?php echo esc_attr( $n ); ?>[max]" value="<?php echo esc_attr( $r['max'] ); ?>" class="small-text msb-in-max"> Tage</span>
					<span class="msb-c msb-c--early">mind. <input type="number" min="1" name="<?php echo esc_attr( $n ); ?>[min]" value="<?php echo esc_attr( $r['min'] ); ?>" class="small-text msb-in-min"> Tage vor Abholung gebucht</span>
					<span class="msb-c msb-c--lastminute">Abholung in max. <input type="number" min="0" name="<?php echo esc_attr( $n ); ?>[max]" value="<?php echo esc_attr( $r['max'] ); ?>" class="small-text msb-in-max"> Tagen</span>
				</td>
				<td>
					<select name="<?php echo esc_attr( $n ); ?>[mode]" class="msb-rule__mode">
						<?php foreach ( $modes as $k => $l ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $r['mode'], $k ); ?> data-day="<?php echo in_array( $k, MSB_Pricing::modes_for( 'period' ), true ) ? 1 : 0; ?>" data-booking="<?php echo in_array( $k, MSB_Pricing::modes_for( 'weekend' ), true ) ? 1 : 0; ?>"><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
					<input type="number" step="0.01" min="0" name="<?php echo esc_attr( $n ); ?>[value]" value="<?php echo esc_attr( $r['value'] ); ?>" class="small-text" placeholder="Wert">
				</td>
				<td><input type="text" name="<?php echo esc_attr( $n ); ?>[label]" value="<?php echo esc_attr( $r['label'] ); ?>" placeholder="automatisch" class="widefat"></td>
				<td><button type="button" class="button-link msb-rule__remove" title="Regel entfernen"><span class="dashicons dashicons-trash"></span></button></td>
			</tr>
			<?php
			return ob_get_clean();
		};
		?>
		<div class="msb-rules" data-name="<?php echo esc_attr( $name ); ?>">
			<table class="widefat msb-rules__table">
				<thead><tr><th title="aktiv">✓</th><th>Regel</th><th>Bedingung</th><th>Wirkung</th><th>Bezeichnung für Kunden</th><th></th></tr></thead>
				<tbody>
					<?php
					foreach ( array_values( $rules ) as $i => $r ) {
						echo $row( $i, $r ); // phpcs:ignore
					}
					?>
				</tbody>
			</table>
			<script type="text/html" class="msb-rules__tmpl"><?php echo $row( '__i__', array() ); // phpcs:ignore ?></script>
			<p><button type="button" class="button msb-rules__add">+ Preisregel hinzufügen</button></p>
		</div>
		<?php
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'msb_save_item', 'msb_item_nonce' );
		$m = function ( $key, $default = '' ) use ( $post ) {
			$v = get_post_meta( $post->ID, $key, true );
			return '' === $v ? $default : $v;
		};
		$cur = esc_html( msb_setting( 'currency' ) );
		?>
		<div class="msb-meta">
			<div class="msb-meta__grid">
				<p>
					<label for="msb_price_day"><strong>Preis pro Tag (<?php echo $cur; ?>)</strong></label>
					<input type="number" step="0.01" min="0" id="msb_price_day" name="msb_price_day" value="<?php echo esc_attr( $m( '_msb_price_day' ) ); ?>">
				</p>
				<p>
					<label for="msb_base_fee"><strong>Grundgebühr pro Buchung (<?php echo $cur; ?>)</strong></label>
					<input type="number" step="0.01" min="0" id="msb_base_fee" name="msb_base_fee" value="<?php echo esc_attr( $m( '_msb_base_fee' ) ); ?>">
					<span class="description">Einmalig je Stück, z. B. Reinigung. Optional.</span>
				</p>
				<p>
					<label for="msb_deposit"><strong>Kaution pro Stück (<?php echo $cur; ?>)</strong></label>
					<input type="number" step="0.01" min="0" id="msb_deposit" name="msb_deposit" value="<?php echo esc_attr( $m( '_msb_deposit' ) ); ?>">
				</p>
				<p>
					<label for="msb_stock"><strong>Bestand (Anzahl Stück)</strong></label>
					<input type="number" step="1" min="0" id="msb_stock" name="msb_stock" value="<?php echo esc_attr( $m( '_msb_stock', 1 ) ); ?>">
				</p>
				<p>
					<label for="msb_min_days"><strong>Mindestmietdauer (Tage)</strong></label>
					<input type="number" step="1" min="1" id="msb_min_days" name="msb_min_days" value="<?php echo esc_attr( $m( '_msb_min_days', 1 ) ); ?>">
				</p>
				<p>
					<label for="msb_max_days"><strong>Maximale Mietdauer (Tage)</strong></label>
					<input type="number" step="1" min="0" id="msb_max_days" name="msb_max_days" value="<?php echo esc_attr( $m( '_msb_max_days', 0 ) ); ?>">
					<span class="description">0 = unbegrenzt</span>
				</p>
			</div>

			<p>
				<label>
					<input type="checkbox" name="msb_bookable" value="yes" <?php checked( 'no' !== $m( '_msb_bookable', 'yes' ) ); ?>>
					Online buchbar
				</label>
				<span class="description">Deaktivieren, um den Artikel nur anzuzeigen (z. B. vorübergehend in Reparatur).</span>
			</p>

			<div class="msb-meta__cols">
				<p>
					<label for="msb_price_tiers"><strong>Staffelpreise (Preis pro Tag ab X Tagen)</strong></label>
					<textarea id="msb_price_tiers" name="msb_price_tiers" rows="5" placeholder="3=8,00&#10;7=6,50"><?php echo esc_textarea( $m( '_msb_price_tiers' ) ); ?></textarea>
					<span class="description">Eine Zeile je Staffel: <code>Tage=Preis</code>. Beispiel <code>7=6,50</code> = ab 7 Tagen 6,50 <?php echo $cur; ?> pro Tag.</span>
				</p>
				<p>
					<label for="msb_specs"><strong>Technische Daten / Eigenschaften</strong></label>
					<textarea id="msb_specs" name="msb_specs" rows="5" placeholder="Gewicht: 3,2 kg&#10;Max. Kindgewicht: 18 kg"><?php echo esc_textarea( $m( '_msb_specs' ) ); ?></textarea>
					<span class="description">Eine Zeile je Eigenschaft: <code>Bezeichnung: Wert</code></span>
				</p>
				<p>
					<label for="msb_blocked"><strong>Gesperrte Zeiträume</strong></label>
					<textarea id="msb_blocked" name="msb_blocked" rows="5" placeholder="24.12.2026 - 26.12.2026 Betriebsferien&#10;15.11.2026 Wartung"><?php echo esc_textarea( $m( '_msb_blocked' ) ); ?></textarea>
					<span class="description">Ein Zeitraum oder Tag je Zeile (TT.MM.JJJJ). Text danach dient als Notiz.</span>
				</p>
			</div>
		</div>
		<?php
	}

	public static function render_shortcode_box( $post ) {
		if ( 'auto-draft' === $post->post_status ) {
			echo '<p>Nach dem ersten Speichern erscheinen hier die Shortcodes.</p>';
			return;
		}
		$id    = (int) $post->ID;
		$codes = array(
			'Komplette Artikelansicht' => '[msb_item id="' . $id . '"]',
			'Buchungsbox mit Kalender' => '[msb_booking id="' . $id . '"]',
			'Verfügbarkeitskalender'   => '[msb_calendar id="' . $id . '"]',
			'Preis / Preistabelle'     => '[msb_price id="' . $id . '" show="table"]',
			'Technische Daten'         => '[msb_specs id="' . $id . '"]',
		);
		foreach ( $codes as $label => $code ) {
			echo '<p style="margin:0 0 8px"><span class="description">' . esc_html( $label ) . '</span><br><input type="text" readonly class="widefat msb-copy" value="' . esc_attr( $code ) . '" onclick="this.select()"></p>';
		}
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['msb_item_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['msb_item_nonce'] ) ), 'msb_save_item' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$num = function ( $key, $int = false ) {
			if ( ! isset( $_POST[ $key ] ) || '' === $_POST[ $key ] ) {
				return '';
			}
			$v = str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
			return $int ? max( 0, (int) $v ) : max( 0, round( (float) $v, 2 ) );
		};

		update_post_meta( $post_id, '_msb_price_day', $num( 'msb_price_day' ) );
		update_post_meta( $post_id, '_msb_base_fee', $num( 'msb_base_fee' ) );
		update_post_meta( $post_id, '_msb_deposit', $num( 'msb_deposit' ) );
		update_post_meta( $post_id, '_msb_stock', $num( 'msb_stock', true ) );
		update_post_meta( $post_id, '_msb_min_days', $num( 'msb_min_days', true ) );
		update_post_meta( $post_id, '_msb_max_days', $num( 'msb_max_days', true ) );
		update_post_meta( $post_id, '_msb_bookable', isset( $_POST['msb_bookable'] ) ? 'yes' : 'no' );

		$rules = isset( $_POST['msb_rules'] ) && is_array( $_POST['msb_rules'] ) ? $_POST['msb_rules'] : array(); // phpcs:ignore
		update_post_meta( $post_id, '_msb_price_rules', MSB_Pricing::sanitize_rules( wp_unslash( $rules ) ) );
		update_post_meta( $post_id, '_msb_ignore_global_rules', isset( $_POST['msb_ignore_global_rules'] ) ? 'yes' : 'no' );

		if ( isset( $_POST['msb_gallery'] ) ) {
			$ids = array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['msb_gallery'] ) ) ) ) );
			update_post_meta( $post_id, '_msb_gallery', implode( ',', $ids ) );
		}

		foreach ( array( 'msb_price_tiers', 'msb_specs', 'msb_blocked' ) as $key ) {
			$val = isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : '';
			update_post_meta( $post_id, '_' . $key, $val );
		}
	}

	/* ------------------------------------------------------------------
	 * Spalten in der Artikelliste (Inventarübersicht)
	 * ------------------------------------------------------------------ */

	public static function columns( $cols ) {
		$new = array();
		foreach ( $cols as $key => $label ) {
			if ( 'title' === $key ) {
				$new['msb_thumb'] = '';
			}
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['msb_price']     = 'Preis / Tag';
				$new['msb_deposit']   = 'Kaution';
				$new['msb_stock']     = 'Bestand';
				$new['msb_available'] = 'Heute frei';
				$new['msb_shortcode'] = 'Shortcode';
			}
		}
		return $new;
	}

	public static function column_content( $col, $post_id ) {
		$item = self::data( $post_id );
		switch ( $col ) {
			case 'msb_thumb':
				echo get_the_post_thumbnail( $post_id, array( 48, 48 ) );
				break;
			case 'msb_price':
				echo esc_html( msb_price( $item['price_day'] ) );
				if ( $item['tiers'] ) {
					echo '<br><small>ab ' . esc_html( msb_price( min( $item['tiers'] ) ) ) . '</small>';
				}
				break;
			case 'msb_deposit':
				echo esc_html( $item['deposit'] ? msb_price( $item['deposit'] ) : '–' );
				break;
			case 'msb_stock':
				echo (int) $item['stock'];
				if ( ! $item['bookable'] ) {
					echo '<br><small style="color:#b32d2e">nicht buchbar</small>';
				}
				break;
			case 'msb_available':
				$today = msb_today();
				$free  = MSB_Availability::free_on( $post_id, $today );
				$color = $free > 0 ? '#2f6b3a' : '#b32d2e';
				echo '<strong style="color:' . esc_attr( $color ) . '">' . (int) $free . '</strong> / ' . (int) $item['stock'];
				break;
			case 'msb_shortcode':
				echo '<input type="text" readonly class="msb-copy" value="' . esc_attr( '[msb_item id="' . $post_id . '"]' ) . '" onclick="this.select()" style="width:160px">';
				break;
		}
	}
}
