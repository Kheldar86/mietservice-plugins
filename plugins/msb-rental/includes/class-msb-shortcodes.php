<?php
/**
 * Shortcodes und Frontend-Ausgabe.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Shortcodes {

	public static function init() {
		$codes = array(
			'msb_items'        => 'items',
			'msb_item'         => 'item',
			'msb_booking'      => 'booking',
			'msb_calendar'     => 'calendar',
			'msb_price'        => 'price',
			'msb_specs'        => 'specs',
			'msb_availability' => 'availability',
			'msb_categories'   => 'categories',
			'msb_search'       => 'search',
			'msb_cart'         => 'cart',
			'msb_checkout'     => 'checkout',
			'msb_cart_link'    => 'cart_link',
			'msb_gallery'      => 'gallery',
		);
		foreach ( $codes as $tag => $method ) {
			add_shortcode( $tag, array( __CLASS__, $method ) );
		}

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_filter( 'the_content', array( __CLASS__, 'single_content' ), 20 );
	}

	/* ------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------ */

	public static function register_assets() {
		// Block-Themes rendern Inhalte schon vor wp_enqueue_scripts – nur einmal registrieren.
		if ( wp_script_is( 'msb-frontend', 'registered' ) ) {
			return;
		}
		wp_register_style( 'msb-frontend', MSB_URL . 'assets/css/frontend.css', array(), MSB_VERSION );
		$color = sanitize_hex_color( msb_setting( 'primary_color' ) );
		if ( $color ) {
			wp_add_inline_style( 'msb-frontend', '.msb{--msb-primary:' . $color . ';}' );
		}

		wp_register_script( 'msb-frontend', MSB_URL . 'assets/js/frontend.js', array(), MSB_VERSION, true );
		wp_localize_script(
			'msb-frontend',
			'MSB',
			array(
				'ajaxurl'   => admin_url( 'admin-ajax.php' ),
				'countMode' => msb_setting( 'count_mode' ),
				'today'     => msb_today(),
				'months'    => array( 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember' ),
				'weekdays'  => array( 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So' ),
				'i18n'      => array(
					'selectStart' => 'Bitte wählen Sie das Abholdatum.',
					'selectEnd'   => 'Bitte wählen Sie das Rückgabedatum.',
					'available'   => 'frei',
					'booked'      => 'ausgebucht',
					'closed'      => 'keine Abholung/Rückgabe',
					'toCheckout'  => 'Zur Buchung',
					'error'       => 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.',
					'sending'     => 'Wird gesendet …',
					'prev'        => 'Vorheriger Monat',
					'next'        => 'Nächster Monat',
				),
			)
		);
	}

	public static function enqueue_assets() {
		self::enqueue();
	}

	private static function enqueue() {
		if ( ! wp_style_is( 'msb-frontend', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'msb-frontend' );
		wp_enqueue_script( 'msb-frontend' );
	}

	/* ------------------------------------------------------------------
	 * Hilfsfunktionen
	 * ------------------------------------------------------------------ */

	private static function resolve_id( $atts ) {
		$id = isset( $atts['id'] ) ? absint( $atts['id'] ) : 0;
		if ( ! $id && is_singular( MSB_Items::POST_TYPE ) ) {
			$id = get_the_ID();
		}
		return MSB_Items::exists( $id ) ? $id : 0;
	}

	private static function bool( $v ) {
		return in_array( strtolower( (string) $v ), array( '1', 'yes', 'ja', 'true', 'on' ), true );
	}

	private static function notice( $text ) {
		return current_user_can( 'edit_posts' ) ? '<div class="msb msb-notice">' . esc_html( $text ) . '</div>' : '';
	}

	/**
	 * Vorgewählte Daten aus der URL (z. B. von der Verfügbarkeitssuche).
	 */
	private static function url_dates() {
		$s = isset( $_GET['msb_start'] ) ? msb_normalize_date( sanitize_text_field( wp_unslash( $_GET['msb_start'] ) ) ) : ''; // phpcs:ignore
		$e = isset( $_GET['msb_end'] ) ? msb_normalize_date( sanitize_text_field( wp_unslash( $_GET['msb_end'] ) ) ) : ''; // phpcs:ignore
		return ( $s && $e && $e >= $s ) ? array( $s, $e ) : array( '', '' );
	}

	public static function price_html( $id, $mode = 'from' ) {
		$item = MSB_Items::data( $id );
		$note = msb_setting( 'price_note' ) ? ' <span class="msb-price__note">' . esc_html( msb_setting( 'price_note' ) ) . '</span>' : '';

		if ( 'table' === $mode ) {
			$rows   = array();
			$rows[] = array( 'Pro Tag', $item['price_day'] );
			foreach ( $item['tiers'] as $days => $price ) {
				$rows[] = array( 'ab ' . MSB_Pricing::days_label( $days ) . ', pro Tag', $price );
			}
			$html = '<table class="msb-price-table"><tbody>';
			foreach ( $rows as $r ) {
				$html .= '<tr><th>' . esc_html( $r[0] ) . '</th><td>' . esc_html( msb_price( $r[1] ) ) . '</td></tr>';
			}
			if ( $item['base_fee'] > 0 ) {
				$html .= '<tr><th>Grundgebühr je Buchung</th><td>' . esc_html( msb_price( $item['base_fee'] ) ) . '</td></tr>';
			}
			if ( $item['deposit'] > 0 ) {
				$html .= '<tr><th>Kaution</th><td>' . esc_html( msb_price( $item['deposit'] ) ) . '</td></tr>';
			}
			if ( $item['min_days'] > 1 ) {
				$html .= '<tr><th>Mindestmietdauer</th><td>' . esc_html( MSB_Pricing::days_label( $item['min_days'] ) ) . '</td></tr>';
			}
			foreach ( MSB_Pricing::rules_for( $item ) as $r ) {
				list( $label, $cond, $val ) = MSB_Pricing::rule_summary( $r );
				$html .= '<tr class="msb-price-table__rule"><th>' . esc_html( $label ) . '<small>' . esc_html( $cond ) . '</small></th><td>' . esc_html( $val ) . '</td></tr>';
			}
			$html .= '</tbody></table>';
			if ( $note ) {
				$html .= '<p class="msb-price-table__note">' . esc_html( msb_setting( 'price_note' ) ) . '</p>';
			}
			return $html;
		}

		if ( 'day' === $mode || empty( $item['tiers'] ) ) {
			return '<span class="msb-price"><span class="msb-price__amount">' . esc_html( msb_price( $item['price_day'] ) ) . '</span> / Tag' . $note . '</span>';
		}
		return '<span class="msb-price">ab <span class="msb-price__amount">' . esc_html( msb_price( MSB_Pricing::lowest_rate( $item ) ) ) . '</span> / Tag' . $note . '</span>';
	}

	public static function specs_html( $id ) {
		$item = MSB_Items::data( $id );
		if ( empty( $item['specs'] ) ) {
			return '';
		}
		$html = '<table class="msb-specs"><tbody>';
		foreach ( $item['specs'] as $s ) {
			$html .= '<tr><th>' . esc_html( $s[0] ) . '</th><td>' . esc_html( $s[1] ) . '</td></tr>';
		}
		return $html . '</tbody></table>';
	}

	/**
	 * Bildergalerie.
	 *
	 * @param string $mode card (kompakt mit Pfeilen) oder full (großes Bild + Vorschaubilder).
	 */
	public static function gallery_html( $id, $mode = 'full', $size = 'large', $link = '' ) {
		$ids = MSB_Items::gallery_ids( $id );
		if ( ! $ids ) {
			return '';
		}
		$title = get_the_title( $id );
		$html  = '<div class="msb-gallery msb-gallery--' . esc_attr( $mode ) . '" data-count="' . count( $ids ) . '">';
		$html .= '<div class="msb-gallery__stage">';
		foreach ( $ids as $i => $img ) {
			$full  = wp_get_attachment_image_url( $img, 'full' );
			$alt   = get_post_meta( $img, '_wp_attachment_image_alt', true );
			$tag   = wp_get_attachment_image(
				$img,
				$size,
				false,
				array(
					'loading' => 0 === $i ? 'eager' : 'lazy',
					'alt'     => $alt ? $alt : $title,
				)
			);
			$inner = 'card' === $mode && $link ? '<a href="' . esc_url( $link ) . '">' . $tag . '</a>' : '<button type="button" class="msb-gallery__zoom" data-full="' . esc_url( $full ) . '" aria-label="Bild vergrößern">' . $tag . '</button>';
			$html .= '<figure class="msb-gallery__slide' . ( 0 === $i ? ' is-active' : '' ) . '" data-index="' . (int) $i . '">' . $inner . '</figure>';
		}
		if ( count( $ids ) > 1 ) {
			$html .= '<button type="button" class="msb-gallery__arrow msb-gallery__arrow--prev" aria-label="Vorheriges Bild">‹</button>';
			$html .= '<button type="button" class="msb-gallery__arrow msb-gallery__arrow--next" aria-label="Nächstes Bild">›</button>';
			if ( 'card' === $mode ) {
				$html .= '<div class="msb-gallery__dots">';
				foreach ( $ids as $i => $img ) {
					$html .= '<button type="button" class="msb-gallery__dot' . ( 0 === $i ? ' is-active' : '' ) . '" data-index="' . (int) $i . '" aria-label="Bild ' . ( $i + 1 ) . '"></button>';
				}
				$html .= '</div>';
			}
		}
		$html .= '</div>';
		if ( 'full' === $mode && count( $ids ) > 1 ) {
			$html .= '<div class="msb-gallery__thumbs">';
			foreach ( $ids as $i => $img ) {
				$html .= '<button type="button" class="msb-gallery__thumb' . ( 0 === $i ? ' is-active' : '' ) . '" data-index="' . (int) $i . '">' . wp_get_attachment_image( $img, 'thumbnail', false, array( 'loading' => 'lazy' ) ) . '</button>';
			}
			$html .= '</div>';
		}
		return $html . '</div>';
	}

	/**
	 * [msb_gallery id="12" mode="full|card"]
	 */
	public static function gallery( $atts ) {
		$a  = shortcode_atts(
			array(
				'id'   => 0,
				'mode' => 'full',
				'size' => 'large',
			),
			$atts,
			'msb_gallery'
		);
		$id = self::resolve_id( $a );
		if ( ! $id ) {
			return self::notice( '[msb_gallery]: Bitte eine gültige Artikel-ID angeben.' );
		}
		self::enqueue();
		return '<div class="msb">' . self::gallery_html( $id, 'card' === $a['mode'] ? 'card' : 'full', $a['size'] ) . '</div>';
	}

	private static function card( $id, array $a, array $link_args = array() ) {
		$item = MSB_Items::data( $id );
		$url  = get_permalink( $id );
		if ( $link_args ) {
			$url = add_query_arg( $link_args, $url );
		}
		$html  = '<article class="msb-card' . ( $item['bookable'] ? '' : ' msb-card--disabled' ) . '">';
		if ( self::bool( $a['show_gallery'] ) && count( $item['gallery'] ) > 1 ) {
			$html .= self::gallery_html( $id, 'card', $a['image_size'], $url );
		} else {
			$thumb = get_the_post_thumbnail( $id, $a['image_size'], array( 'loading' => 'lazy' ) );
			if ( ! $thumb && $item['gallery'] ) {
				$thumb = wp_get_attachment_image( $item['gallery'][0], $a['image_size'], false, array( 'loading' => 'lazy' ) );
			}
			if ( $thumb ) {
				$html .= '<a class="msb-card__img" href="' . esc_url( $url ) . '">' . $thumb . '</a>';
			}
		}
		$html .= '<div class="msb-card__body">';
		$html .= '<h3 class="msb-card__title"><a href="' . esc_url( $url ) . '">' . esc_html( $item['title'] ) . '</a></h3>';

		if ( self::bool( $a['show_excerpt'] ) && has_excerpt( $id ) ) {
			$html .= '<div class="msb-card__excerpt">' . wp_kses_post( wpautop( get_the_excerpt( $id ) ) ) . '</div>';
		}
		if ( self::bool( $a['show_specs'] ) ) {
			$html .= self::specs_html( $id );
		}
		$html .= '<div class="msb-card__footer">';
		if ( ! empty( $link_args['msb_start'] ) && ! empty( $link_args['msb_end'] ) ) {
			// Preis für den gesuchten Zeitraum.
			$p     = MSB_Pricing::calculate( $item, $link_args['msb_start'], $link_args['msb_end'], 1 );
			$html .= '<div class="msb-card__period-price"><span class="msb-label">' . esc_html( MSB_Pricing::days_label( $p['days'] ) ) . ' Miete</span>';
			if ( $p['discount'] > 0.004 ) {
				$html .= '<s class="msb-muted">' . esc_html( msb_price( $p['base_total'] ) ) . '</s> ';
			}
			$html .= '<strong class="msb-price__amount">' . esc_html( msb_price( $p['line_total'] ) ) . '</strong>';
			foreach ( $p['adjustments'] as $adj ) {
				$html .= '<span class="msb-card__adj ' . ( $adj['amount'] < 0 ? 'is-discount' : 'is-surcharge' ) . '">' . esc_html( $adj['label'] ) . '</span>';
			}
			$html .= '</div>';
		}
		if ( self::bool( $a['show_price'] ) ) {
			$html .= '<div class="msb-card__price">' . self::price_html( $id, 'from' ) . '</div>';
			if ( self::bool( $a['show_deposit'] ) && $item['deposit'] > 0 ) {
				$html .= '<div class="msb-card__deposit">Kaution: ' . esc_html( msb_price( $item['deposit'] ) ) . '</div>';
			}
		}
		if ( self::bool( $a['show_stock'] ) ) {
			$free   = MSB_Availability::free_on( $id, msb_today() );
			$html  .= '<div class="msb-card__stock ' . ( $free ? 'is-free' : 'is-booked' ) . '">' . ( $free ? 'Heute verfügbar' : 'Heute ausgebucht' ) . '</div>';
		}
		if ( self::bool( $a['show_button'] ) ) {
			$label = $item['bookable'] ? $a['button_text'] : 'Details';
			$html .= '<a class="msb-btn" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		$html .= '</div></div></article>';
		return $html;
	}

	private static function card_defaults() {
		return array(
			'show_price'   => 'yes',
			'show_deposit' => 'no',
			'show_excerpt' => 'yes',
			'show_specs'   => 'no',
			'show_stock'   => 'no',
			'show_button'  => 'yes',
			'show_gallery' => 'yes',
			'button_text'  => 'Jetzt mieten',
			'image_size'   => 'medium_large',
		);
	}

	/* ------------------------------------------------------------------
	 * Shortcodes
	 * ------------------------------------------------------------------ */

	/**
	 * [msb_items category="kraxen" limit="6" columns="3" orderby="menu_order" layout="grid"]
	 */
	public static function items( $atts ) {
		self::enqueue();
		$a = shortcode_atts(
			array_merge(
				self::card_defaults(),
				array(
					'category' => '',
					'ids'      => '',
					'exclude'  => '',
					'limit'    => -1,
					'columns'  => 3,
					'orderby'  => 'menu_order',
					'order'    => 'ASC',
					'layout'   => 'grid',
					'bookable' => '',
				)
			),
			$atts,
			'msb_items'
		);

		$args = array(
			'post_type'      => MSB_Items::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => (int) $a['limit'],
			'fields'         => 'ids',
			'order'          => 'DESC' === strtoupper( $a['order'] ) ? 'DESC' : 'ASC',
		);

		switch ( $a['orderby'] ) {
			case 'title':
				$args['orderby'] = 'title';
				break;
			case 'date':
				$args['orderby'] = 'date';
				break;
			case 'rand':
				$args['orderby'] = 'rand';
				break;
			case 'price':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_msb_price_day'; // phpcs:ignore
				break;
			default:
				$args['orderby'] = array(
					'menu_order' => $args['order'],
					'title'      => 'ASC',
				);
		}

		if ( $a['ids'] ) {
			$args['post__in'] = array_map( 'absint', explode( ',', $a['ids'] ) );
			if ( 'menu_order' === $a['orderby'] ) {
				$args['orderby'] = 'post__in';
			}
		}
		if ( $a['exclude'] ) {
			$args['post__not_in'] = array_map( 'absint', explode( ',', $a['exclude'] ) );
		}
		if ( $a['category'] ) {
			$args['tax_query'] = array( // phpcs:ignore
				array(
					'taxonomy' => MSB_Items::TAXONOMY,
					'field'    => 'slug',
					'terms'    => array_map( 'trim', explode( ',', $a['category'] ) ),
				),
			);
		}
		if ( self::bool( $a['bookable'] ) ) {
			$args['meta_query'] = array( // phpcs:ignore
				'relation' => 'OR',
				array(
					'key'     => '_msb_bookable',
					'value'   => 'no',
					'compare' => '!=',
				),
				array(
					'key'     => '_msb_bookable',
					'compare' => 'NOT EXISTS',
				),
			);
		}

		$ids = get_posts( $args );
		if ( empty( $ids ) ) {
			return '<div class="msb msb-empty">Zurzeit sind keine Artikel verfügbar.</div>';
		}

		return self::grid( $ids, $a );
	}

	private static function grid( array $ids, array $a, array $link_args = array() ) {
		$cols   = max( 1, min( 6, (int) $a['columns'] ) );
		$layout = 'list' === $a['layout'] ? 'list' : 'grid';
		$html   = '<div class="msb msb-items msb-items--' . $layout . '" style="--msb-cols:' . $cols . '">';
		foreach ( $ids as $id ) {
			$html .= self::card( $id, $a, $link_args );
		}
		return $html . '</div>';
	}

	/**
	 * [msb_item id="12"] – komplette Artikelansicht.
	 */
	public static function item( $atts ) {
		$a  = shortcode_atts(
			array(
				'id'          => 0,
				'show_image'  => 'yes',
				'show_title'  => 'yes',
				'show_content'=> 'yes',
				'show_specs'  => 'yes',
				'show_prices' => 'yes',
				'months'      => msb_setting( 'calendar_months', 1 ),
			),
			$atts,
			'msb_item'
		);
		$id = self::resolve_id( $a );
		if ( ! $id ) {
			return self::notice( '[msb_item]: Bitte eine gültige Artikel-ID angeben.' );
		}
		self::enqueue();

		$post = get_post( $id );
		$html = '<div class="msb msb-item">';
		$html .= '<div class="msb-item__media">';
		if ( self::bool( $a['show_image'] ) ) {
			$html .= self::gallery_html( $id, 'full', 'large' );
		}
		if ( self::bool( $a['show_specs'] ) ) {
			$specs = self::specs_html( $id );
			if ( $specs ) {
				$html .= '<h3 class="msb-item__subtitle">Technische Daten</h3>' . $specs;
			}
		}
		$html .= '</div><div class="msb-item__main">';
		if ( self::bool( $a['show_title'] ) ) {
			$html .= '<h2 class="msb-item__title">' . esc_html( get_the_title( $id ) ) . '</h2>';
		}
		$html .= '<div class="msb-item__price">' . self::price_html( $id, 'from' ) . '</div>';
		if ( self::bool( $a['show_content'] ) && $post->post_content ) {
			// Inhalt ohne eigene Buchungsbox ausgeben (verhindert Doppelungen).
			remove_filter( 'the_content', array( __CLASS__, 'single_content' ), 20 );
			$html .= '<div class="msb-item__content">' . apply_filters( 'the_content', $post->post_content ) . '</div>';
			add_filter( 'the_content', array( __CLASS__, 'single_content' ), 20 );
		}
		if ( self::bool( $a['show_prices'] ) ) {
			$html .= '<details class="msb-item__prices"><summary>Preise &amp; Konditionen</summary>' . self::price_html( $id, 'table' ) . '</details>';
		}
		$html .= self::booking(
			array(
				'id'     => $id,
				'months' => $a['months'],
			)
		);
		$html .= '</div></div>';
		return $html;
	}

	/**
	 * [msb_booking id="12"] – Buchungsbox mit Kalender.
	 */
	public static function booking( $atts ) {
		$a  = shortcode_atts(
			array(
				'id'     => 0,
				'months' => msb_setting( 'calendar_months', 1 ),
				'title'  => 'Zeitraum wählen',
				'button' => 'Zur Buchung hinzufügen',
			),
			$atts,
			'msb_booking'
		);
		$id = self::resolve_id( $a );
		if ( ! $id ) {
			return self::notice( '[msb_booking]: Bitte eine gültige Artikel-ID angeben.' );
		}
		self::enqueue();
		$item = MSB_Items::data( $id );

		if ( ! $item['bookable'] || $item['stock'] < 1 ) {
			return '<div class="msb msb-booking msb-booking--disabled"><p>Dieser Artikel ist derzeit nicht online buchbar. Bitte kontaktieren Sie uns.</p></div>';
		}

		list( $start, $end ) = self::url_dates();
		$months = max( 1, min( 3, (int) $a['months'] ) );

		$html  = '<div class="msb msb-booking" data-item="' . esc_attr( $id ) . '" data-mode="book" data-months="' . esc_attr( $months ) . '" data-stock="' . esc_attr( $item['stock'] ) . '" data-start="' . esc_attr( $start ) . '" data-end="' . esc_attr( $end ) . '">';
		if ( $a['title'] ) {
			$html .= '<h3 class="msb-booking__title">' . esc_html( $a['title'] ) . '</h3>';
		}
		$html .= '<div class="msb-cal" aria-live="polite"></div>';
		$html .= self::legend();
		$html .= '<div class="msb-booking__panel">';
		$html .= '<div class="msb-booking__dates"><div><span class="msb-label">Abholung</span><strong class="msb-start">–</strong></div><div><span class="msb-label">Rückgabe</span><strong class="msb-end">–</strong></div></div>';
		if ( $item['stock'] > 1 ) {
			$html .= '<label class="msb-booking__qty"><span class="msb-label">Anzahl</span><span class="msb-stepper"><button type="button" class="msb-step" data-step="-1" aria-label="weniger">−</button><input type="number" class="msb-qty" min="1" max="' . esc_attr( $item['stock'] ) . '" value="1" inputmode="numeric"><button type="button" class="msb-step" data-step="1" aria-label="mehr">+</button></span></label>';
		} else {
			$html .= '<input type="hidden" class="msb-qty" value="1">';
		}
		$html .= '<div class="msb-quote"></div>';
		$html .= '<div class="msb-msg" role="status"></div>';
		$html .= '<button type="button" class="msb-btn msb-btn--primary msb-add" disabled>' . esc_html( $a['button'] ) . '</button>';
		$html .= '</div></div>';
		return $html;
	}

	private static function legend() {
		return '<ul class="msb-legend"><li><span class="msb-dot msb-dot--free"></span>frei</li><li><span class="msb-dot msb-dot--few"></span>wenige frei</li><li><span class="msb-dot msb-dot--booked"></span>belegt</li><li><span class="msb-dot msb-dot--closed"></span>keine Abholung/Rückgabe</li></ul>';
	}

	/**
	 * [msb_calendar id="12" months="2"] – reiner Verfügbarkeitskalender.
	 */
	public static function calendar( $atts ) {
		$a  = shortcode_atts(
			array(
				'id'     => 0,
				'months' => 1,
				'legend' => 'yes',
			),
			$atts,
			'msb_calendar'
		);
		$id = self::resolve_id( $a );
		if ( ! $id ) {
			return self::notice( '[msb_calendar]: Bitte eine gültige Artikel-ID angeben.' );
		}
		self::enqueue();
		$item   = MSB_Items::data( $id );
		$months = max( 1, min( 3, (int) $a['months'] ) );
		$html   = '<div class="msb msb-booking msb-booking--view" data-item="' . esc_attr( $id ) . '" data-mode="view" data-months="' . esc_attr( $months ) . '" data-stock="' . esc_attr( $item['stock'] ) . '"><div class="msb-cal"></div>';
		if ( self::bool( $a['legend'] ) ) {
			$html .= self::legend();
		}
		return $html . '</div>';
	}

	/**
	 * [msb_price id="12" show="from|day|table"]
	 */
	public static function price( $atts ) {
		$a  = shortcode_atts(
			array(
				'id'   => 0,
				'show' => 'from',
			),
			$atts,
			'msb_price'
		);
		$id = self::resolve_id( $a );
		if ( ! $id ) {
			return self::notice( '[msb_price]: Bitte eine gültige Artikel-ID angeben.' );
		}
		self::enqueue();
		return '<span class="msb msb-price-wrap">' . self::price_html( $id, $a['show'] ) . '</span>';
	}

	/**
	 * [msb_specs id="12"]
	 */
	public static function specs( $atts ) {
		$a  = shortcode_atts( array( 'id' => 0 ), $atts, 'msb_specs' );
		$id = self::resolve_id( $a );
		if ( ! $id ) {
			return self::notice( '[msb_specs]: Bitte eine gültige Artikel-ID angeben.' );
		}
		self::enqueue();
		return '<div class="msb">' . self::specs_html( $id ) . '</div>';
	}

	/**
	 * [msb_availability id="12"] – "Heute 2 von 3 verfügbar".
	 */
	public static function availability( $atts ) {
		$a  = shortcode_atts(
			array(
				'id'   => 0,
				'date' => '',
			),
			$atts,
			'msb_availability'
		);
		$id = self::resolve_id( $a );
		if ( ! $id ) {
			return self::notice( '[msb_availability]: Bitte eine gültige Artikel-ID angeben.' );
		}
		self::enqueue();
		$date  = msb_normalize_date( $a['date'] );
		$date  = $date ? $date : msb_today();
		$item  = MSB_Items::data( $id );
		$free  = MSB_Availability::free_on( $id, $date );
		$label = $date === msb_today() ? 'Heute' : 'Am ' . msb_date( $date );
		$text  = $free > 0 ? sprintf( '%s %d von %d verfügbar', $label, $free, $item['stock'] ) : sprintf( '%s ausgebucht', $label );
		return '<span class="msb msb-availability ' . ( $free ? 'is-free' : 'is-booked' ) . '">' . esc_html( $text ) . '</span>';
	}

	/**
	 * [msb_categories show_count="yes" layout="tiles"]
	 */
	public static function categories( $atts ) {
		$a     = shortcode_atts(
			array(
				'show_count'       => 'yes',
				'show_description' => 'yes',
				'layout'           => 'tiles',
				'hide_empty'       => 'yes',
				'parent'           => '',
				'columns'          => 3,
			),
			$atts,
			'msb_categories'
		);
		$args  = array(
			'taxonomy'   => MSB_Items::TAXONOMY,
			'hide_empty' => self::bool( $a['hide_empty'] ),
		);
		if ( '' !== $a['parent'] ) {
			$args['parent'] = absint( $a['parent'] );
		}
		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}
		self::enqueue();
		$layout = 'list' === $a['layout'] ? 'list' : 'tiles';
		$html   = '<ul class="msb msb-cats msb-cats--' . $layout . '" style="--msb-cols:' . max( 1, min( 6, (int) $a['columns'] ) ) . '">';
		foreach ( $terms as $t ) {
			$html .= '<li class="msb-cats__item"><a href="' . esc_url( get_term_link( $t ) ) . '"><span class="msb-cats__name">' . esc_html( $t->name ) . '</span>';
			if ( self::bool( $a['show_count'] ) ) {
				$html .= ' <span class="msb-cats__count">' . (int) $t->count . ' ' . ( 1 === (int) $t->count ? 'Artikel' : 'Artikel' ) . '</span>';
			}
			if ( 'tiles' === $layout && self::bool( $a['show_description'] ) && $t->description ) {
				$html .= '<span class="msb-cats__desc">' . esc_html( $t->description ) . '</span>';
			}
			$html .= '</a></li>';
		}
		return $html . '</ul>';
	}

	/**
	 * [msb_search] – Verfügbarkeitssuche nach Zeitraum.
	 */
	public static function search( $atts ) {
		self::enqueue();
		$a = shortcode_atts(
			array_merge(
				self::card_defaults(),
				array(
					'category'      => '',
					'columns'       => 3,
					'layout'        => 'grid',
					'show_category' => 'yes',
					'button'        => 'Verfügbarkeit prüfen',
				)
			),
			$atts,
			'msb_search'
		);

		list( $start, $end ) = self::url_dates();
		$cat = isset( $_GET['msb_cat'] ) ? sanitize_title( wp_unslash( $_GET['msb_cat'] ) ) : $a['category']; // phpcs:ignore
		$min = msb_add_days( msb_today(), max( 0, (int) msb_setting( 'lead_days' ) ) );

		$html  = '<div class="msb msb-search">';
		$html .= '<form class="msb-search__form" method="get" action="">';
		$html .= '<label><span class="msb-label">Abholung</span><input type="date" name="msb_start" value="' . esc_attr( $start ) . '" min="' . esc_attr( $min ) . '" required></label>';
		$html .= '<label><span class="msb-label">Rückgabe</span><input type="date" name="msb_end" value="' . esc_attr( $end ) . '" min="' . esc_attr( $min ) . '" required></label>';
		if ( self::bool( $a['show_category'] ) && ! $a['category'] ) {
			$terms = get_terms(
				array(
					'taxonomy'   => MSB_Items::TAXONOMY,
					'hide_empty' => true,
				)
			);
			if ( ! is_wp_error( $terms ) && $terms ) {
				$html .= '<label><span class="msb-label">Kategorie</span><select name="msb_cat"><option value="">Alle</option>';
				foreach ( $terms as $t ) {
					$html .= '<option value="' . esc_attr( $t->slug ) . '"' . selected( $cat, $t->slug, false ) . '>' . esc_html( $t->name ) . '</option>';
				}
				$html .= '</select></label>';
			}
		}
		// Seiten-ID beibehalten, falls keine "schönen" Permalinks aktiv sind.
		if ( isset( $_GET['page_id'] ) ) { // phpcs:ignore
			$html .= '<input type="hidden" name="page_id" value="' . absint( $_GET['page_id'] ) . '">'; // phpcs:ignore
		}
		$html .= '<button type="submit" class="msb-btn msb-btn--primary">' . esc_html( $a['button'] ) . '</button>';
		$html .= '</form>';

		if ( $start && $end ) {
			$query = array();
			if ( $cat ) {
				$query['tax_query'] = array( // phpcs:ignore
					array(
						'taxonomy' => MSB_Items::TAXONOMY,
						'field'    => 'slug',
						'terms'    => $cat,
					),
				);
			}
			$ids   = MSB_Availability::available_items( $start, $end, 1, $query );
			$html .= '<p class="msb-search__summary">' . sprintf( '%d Artikel verfügbar vom %s bis %s (%s)', count( $ids ), esc_html( msb_date( $start ) ), esc_html( msb_date( $end ) ), esc_html( MSB_Pricing::days_label( MSB_Pricing::rental_days( $start, $end ) ) ) ) . '</p>';
			if ( $ids ) {
				$html .= self::grid(
					$ids,
					$a,
					array(
						'msb_start' => $start,
						'msb_end'   => $end,
					)
				);
			} else {
				$html .= '<div class="msb-empty">Für diesen Zeitraum ist leider nichts verfügbar. Bitte versuchen Sie einen anderen Zeitraum.</div>';
			}
		}

		return $html . '</div>';
	}

	/**
	 * Warenkorb-Tabelle.
	 */
	private static function cart_table( $editable = true ) {
		$totals = MSB_Cart::totals();
		if ( empty( $totals['lines'] ) ) {
			return '';
		}
		$cols  = $editable ? 5 : 4;
		$html  = '<table class="msb-cart"><thead><tr><th>Artikel</th><th>Zeitraum</th><th>Anzahl</th><th class="msb-cart__num">Preis</th>' . ( $editable ? '<th></th>' : '' ) . '</tr></thead><tbody>';
		foreach ( $totals['lines'] as $i => $l ) {
			$adj = '';
			foreach ( $l['adjustments'] as $a ) {
				$adj .= '<br><small class="' . ( $a['amount'] < 0 ? 'is-discount' : 'is-surcharge' ) . '">' . esc_html( $a['label'] ) . ' ' . ( $a['amount'] < 0 ? '−' : '+' ) . esc_html( msb_price( abs( $a['amount'] ) ) ) . '</small>';
			}
			$html .= '<tr>';
			$html .= '<td data-label="Artikel"><a href="' . esc_url( get_permalink( $l['item_id'] ) ) . '">' . esc_html( $l['title'] ) . '</a>' . $adj . '</td>';
			$html .= '<td data-label="Zeitraum">' . esc_html( msb_date( $l['start'] ) . ' – ' . msb_date( $l['end'] ) ) . '<br><small>' . esc_html( MSB_Pricing::days_label( $l['days'] ) . ' à ' . msb_price( $l['day_rate'] ) ) . '</small></td>';
			$html .= '<td data-label="Anzahl">' . (int) $l['qty'] . '</td>';
			$html .= '<td data-label="Preis" class="msb-cart__num">' . ( $l['discount'] > 0.004 ? '<s class="msb-muted">' . esc_html( msb_price( $l['base_total'] ) ) . '</s><br>' : '' ) . esc_html( msb_price( $l['line_total'] ) ) . '</td>';
			if ( $editable ) {
				$html .= '<td class="msb-cart__remove"><button type="button" class="msb-link msb-cart-remove" data-index="' . (int) $i . '" aria-label="Entfernen">✕</button></td>';
			}
			$html .= '</tr>';
		}
		$html .= '</tbody><tfoot>';
		$pad   = $editable ? '<td></td>' : '';
		$html .= '<tr class="msb-cart__total"><th colspan="3">Mietpreis gesamt</th><td class="msb-cart__num">' . esc_html( msb_price( $totals['total'] ) ) . '</td>' . $pad . '</tr>';
		$prepay = MSB_Payments::prepay_amount( $totals['total'] );
		if ( $prepay > 0 && $prepay < $totals['total'] ) {
			$html .= '<tr class="msb-cart__deposit"><th colspan="3">davon Anzahlung bei Buchung</th><td class="msb-cart__num">' . esc_html( msb_price( $prepay ) ) . '</td>' . $pad . '</tr>';
		}
		if ( $totals['deposit'] > 0 ) {
			$label = MSB_Payments::security_hold_active() ? 'Kaution (wird nur reserviert, nach Rückgabe freigegeben)' : 'zzgl. Kaution (bei Abholung)';
			$html .= '<tr class="msb-cart__deposit"><th colspan="3">' . esc_html( $label ) . '</th><td class="msb-cart__num">' . esc_html( msb_price( $totals['deposit'] ) ) . '</td>' . $pad . '</tr>';
		}
		if ( msb_setting( 'price_note' ) ) {
			$html .= '<tr class="msb-cart__note"><td colspan="' . $cols . '">' . esc_html( msb_setting( 'price_note' ) ) . '</td></tr>';
		}
		$html .= '</tfoot></table>';
		return $html;
	}

	private static function empty_cart() {
		$archive = get_post_type_archive_link( MSB_Items::POST_TYPE );
		return '<div class="msb-empty">Sie haben noch keine Artikel ausgewählt.' . ( $archive ? ' <a href="' . esc_url( $archive ) . '">Zu den Mietartikeln</a>' : '' ) . '</div>';
	}

	/**
	 * [msb_cart] – Warenkorb mit Link zur Buchung.
	 */
	public static function cart( $atts ) {
		self::enqueue();
		$table = self::cart_table( true );
		$html  = '<div class="msb msb-cart-wrap">';
		if ( ! $table ) {
			return $html . self::empty_cart() . '</div>';
		}
		$html .= $table;
		$url   = msb_checkout_url();
		if ( $url ) {
			$html .= '<p class="msb-cart__actions"><a class="msb-btn msb-btn--primary" href="' . esc_url( $url ) . '">Weiter zur Buchung</a></p>';
		}
		return $html . '</div>';
	}

	/**
	 * Buchungsübersicht (Danke-Seite, Zahlungsseite).
	 */
	private static function booking_summary( array $booking ) {
		$sum   = MSB_Payments::summary( $booking );
		$html  = '<div class="msb-summary">' . MSB_Emails::items_table( $booking ) . '</div>';
		$facts = array( 'Zahlungsart' => MSB_Emails::method_label( $booking['payment_method'] ) );
		if ( (float) $booking['deposit'] > 0 && $sum['security_label'] ) {
			$facts['Kaution'] = msb_price( $booking['deposit'] ) . ' – ' . $sum['security_label'];
		}
		$html .= '<dl class="msb-account__facts">';
		foreach ( $facts as $k => $v ) {
			$html .= '<div><dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
		}
		return $html . '</dl>';
	}

	/**
	 * Danke-Seite nach der Buchung (auch nach Rückkehr vom Zahlungsanbieter).
	 */
	private static function thankyou( array $booking ) {
		$html = '<div class="msb msb-checkout"><div class="msb-thankyou">';
		$err  = isset( $_GET['msb_err'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['msb_err'] ) ) ) : ''; // phpcs:ignore
		if ( 'awaiting_payment' === $booking['status'] || $err ) {
			$html .= '<div class="msb-msg msb-msg--error">' . esc_html( $err ? $err : 'Die Zahlung wurde noch nicht bestätigt.' ) . ' <a href="' . esc_url( MSB_Payments::pay_url( $booking ) ) . '">Zahlung erneut versuchen</a></div>';
		}
		if ( 'cancelled' === $booking['status'] ) {
			$html .= '<p>Diese Buchung wurde storniert.</p>';
		} else {
			$html .= MSB_Emails::replace( wpautop( msb_setting( 'thankyou_message' ) ), $booking, true );
		}
		$html .= self::booking_summary( $booking );
		if ( is_user_logged_in() && msb_account_url() ) {
			$html .= '<p><a class="msb-btn" href="' . esc_url( msb_account_url() ) . '">Zu meinen Buchungen</a></p>';
		}
		return $html . '</div></div>';
	}

	/**
	 * Zahlungsseite für Zahlungslinks: ?msb_pay=TOKEN&for=prepay|rest|security
	 */
	private static function payment_page( array $booking ) {
		$for       = isset( $_GET['for'] ) ? sanitize_key( $_GET['for'] ) : MSB_Payments::default_for( $booking ); // phpcs:ignore
		$for       = in_array( $for, array( 'prepay', 'rest', 'security' ), true ) ? $for : MSB_Payments::default_for( $booking );
		$amount    = $for ? MSB_Payments::amount_for( $booking, $for ) : 0;
		$providers = MSB_Payments::online_providers();
		$labels    = MSB_Payments::type_labels();
		$err       = isset( $_GET['msb_err'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['msb_err'] ) ) ) : ''; // phpcs:ignore

		$html = '<div class="msb msb-checkout msb-paypage"><h2 class="msb-checkout__title">Buchung ' . esc_html( $booking['booking_number'] ) . '</h2>';
		if ( $err ) {
			$html .= '<div class="msb-msg msb-msg--error">' . esc_html( $err ) . '</div>';
		}
		if ( in_array( $booking['status'], array( 'cancelled', 'returned' ), true ) || $amount <= 0 || ! $providers ) {
			$html .= '<div class="msb-msg msb-msg--success">Für diese Buchung ist derzeit keine Online-Zahlung offen.</div>';
			return $html . self::booking_summary( $booking ) . '</div>';
		}
		$html .= '<div class="msb-paybox"><p class="msb-paybox__amount"><span class="msb-label">' . esc_html( $labels[ $for ] ) . '</span><strong>' . esc_html( msb_price( $amount ) ) . '</strong></p>';
		if ( 'security' === $for ) {
			$html .= '<p class="msb-muted">Die Kaution wird auf Ihrem Zahlungsmittel nur <strong>reserviert</strong> und nicht abgebucht. Nach der ordnungsgemäßen Rückgabe geben wir sie wieder frei.</p>';
		}
		$html .= '<form method="post" class="msb-paybox__form">' . wp_nonce_field( 'msb_pay_' . $booking['token'], '_wpnonce', true, false ) . '<input type="hidden" name="for" value="' . esc_attr( $for ) . '">';
		foreach ( $providers as $id => $label ) {
			$html .= '<button type="submit" name="msb_pay_provider" value="' . esc_attr( $id ) . '" class="msb-btn msb-btn--primary msb-btn--large msb-pay-' . esc_attr( $id ) . '">' . esc_html( 'Weiter mit ' . $label ) . '</button>';
		}
		$html .= '</form></div>';
		return $html . self::booking_summary( $booking ) . '</div>';
	}

	/**
	 * [msb_checkout] – Warenkorb, Buchungsformular, Zahlung, Abschluss.
	 */
	public static function checkout( $atts ) {
		self::enqueue();
		$a = shortcode_atts(
			array(
				'button'        => 'Zahlungspflichtig buchen',
				'button_online' => 'Zahlungspflichtig buchen',
				'title'         => 'Ihre Auswahl',
			),
			$atts,
			'msb_checkout'
		);

		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! empty( $_GET['msb_done'] ) ) {
			$booking = MSB_Bookings::get_by_token( sanitize_key( wp_unslash( $_GET['msb_done'] ) ) );
			if ( $booking ) {
				return self::thankyou( $booking );
			}
		}
		if ( ! empty( $_GET['msb_pay'] ) ) {
			$booking = MSB_Bookings::get_by_token( sanitize_key( wp_unslash( $_GET['msb_pay'] ) ) );
			if ( $booking ) {
				return self::payment_page( $booking );
			}
		}
		$cancelled = ! empty( $_GET['msb_cancelled'] );
		// phpcs:enable

		$table = self::cart_table( true );
		$html  = '<div class="msb msb-checkout">';
		if ( $cancelled ) {
			$html .= '<div class="msb-msg msb-msg--error">Die Zahlung wurde abgebrochen. Ihre Auswahl ist weiterhin hier – Sie können eine andere Zahlungsart wählen.</div>';
		}
		if ( ! $table ) {
			return $html . self::empty_cart() . '</div>';
		}
		if ( $a['title'] ) {
			$html .= '<h2 class="msb-checkout__title">' . esc_html( $a['title'] ) . '</h2>';
		}
		$html .= $table;

		$html .= '<form class="msb-form msb-checkout-form" novalidate data-button="' . esc_attr( $a['button'] ) . '" data-button-online="' . esc_attr( $a['button_online'] ) . '">';

		// Anmeldung / Konto.
		if ( MSB_Accounts::enabled() && ! is_user_logged_in() ) {
			$login = msb_account_url() ? msb_account_url() : wp_login_url( get_permalink() );
			$html .= '<div class="msb-checkout__login msb-muted">Bereits Kunde? <a href="' . esc_url( add_query_arg( 'redirect_to', rawurlencode( get_permalink() ), $login ) ) . '">Jetzt anmelden</a> – Ihre Daten werden dann automatisch eingetragen.</div>';
		}

		$html .= '<div class="msb-form__grid">' . MSB_Form_Fields::render( MSB_Accounts::prefill_values() ) . '</div>';

		if ( MSB_Accounts::enabled() && ! is_user_logged_in() ) {
			$required = (bool) msb_setting( 'account_required' );
			$html    .= '<fieldset class="msb-checkout__account"><legend>Kundenkonto</legend>';
			if ( $required ) {
				$html .= '<input type="hidden" name="msb_create_account" value="1"><p class="msb-muted">Für die Buchung wird ein Kundenkonto angelegt.</p>';
			} else {
				$html .= '<label class="msb-choice"><input type="checkbox" name="msb_create_account" value="1" class="msb-toggle-account"> <span>Kundenkonto anlegen <span class="msb-muted">(optional – Buchungen einsehen, Daten speichern)</span></span></label>';
			}
			$html .= '<div class="msb-field msb-account-pass" data-key="account"' . ( $required ? '' : ' hidden' ) . '><label for="msb-acc-pass">Passwort wählen (mind. 8 Zeichen)</label><input type="password" id="msb-acc-pass" name="msb_account_password" minlength="8" autocomplete="new-password"><span class="msb-field__error" role="alert"></span></div>';
			$html .= '</fieldset>';
		}

		// Zahlungsart.
		$methods = MSB_Payments::checkout_methods();
		$totals  = MSB_Cart::totals();
		$prepay  = MSB_Payments::prepay_amount( $totals['total'] );
		$hold    = MSB_Payments::security_hold_active() && $totals['deposit'] > 0;
		$html   .= '<fieldset class="msb-checkout__payment"' . ( count( $methods ) < 2 ? ' data-single="1"' : '' ) . '><legend>Zahlung</legend>';
		if ( $prepay > 0 ) {
			$html .= '<p>' . ( $prepay >= $totals['total'] ? 'Der Mietpreis von <strong>' . esc_html( msb_price( $prepay ) ) . '</strong> wird bei der Buchung fällig.' : 'Bei der Buchung ist eine Anzahlung von <strong>' . esc_html( msb_price( $prepay ) ) . '</strong> fällig, der Rest von ' . esc_html( msb_price( $totals['total'] - $prepay ) ) . ' bei Abholung.' ) . '</p>';
		}
		if ( $hold ) {
			$html .= '<p class="msb-muted">Die Kaution von ' . esc_html( msb_price( $totals['deposit'] ) ) . ' wird kurz vor der Abholung auf Ihrem Zahlungsmittel reserviert (nicht abgebucht) und nach der Rückgabe wieder freigegeben.</p>';
		}
		$first = true;
		foreach ( $methods as $id => $label ) {
			$desc = 'offline' === $id ? msb_setting( 'offline_text' ) : '';
			$html .= '<label class="msb-paymethod"><input type="radio" name="msb_payment_method" value="' . esc_attr( $id ) . '"' . ( $first ? ' checked' : '' ) . ' data-online="' . ( 'offline' === $id ? 0 : 1 ) . '"> <span><strong>' . esc_html( $label ) . '</strong>' . ( $desc ? '<small>' . esc_html( $desc ) . '</small>' : '' ) . '</span></label>';
			$first = false;
		}
		$html .= '</fieldset>';

		$html .= wp_nonce_field( 'msb_checkout', 'msb_checkout_nonce', false, false );
		$html .= '<div class="msb-hp" aria-hidden="true"><label>Website<input type="text" name="msb_website" tabindex="-1" autocomplete="off"></label></div>';
		$html .= '<div class="msb-msg" role="alert"></div>';
		$first_online = 'offline' !== key( $methods ) && ( $prepay > 0 || $hold );
		$html .= '<button type="submit" class="msb-btn msb-btn--primary msb-btn--large msb-checkout-submit">' . esc_html( $first_online ? $a['button_online'] : $a['button'] ) . '</button>';
		$html .= '</form></div>';
		return $html;
	}

	/**
	 * [msb_cart_link text="Meine Auswahl"]
	 */
	public static function cart_link( $atts ) {
		self::enqueue();
		$a   = shortcode_atts( array( 'text' => 'Meine Auswahl' ), $atts, 'msb_cart_link' );
		$url = msb_checkout_url();
		if ( ! $url ) {
			return '';
		}
		return '<a class="msb msb-cart-link" href="' . esc_url( $url ) . '">' . esc_html( $a['text'] ) . ' <span class="msb-cart-count">' . (int) MSB_Cart::count() . '</span></a>';
	}

	/* ------------------------------------------------------------------
	 * Automatische Ausgabe auf der Einzelseite eines Mietartikels
	 * ------------------------------------------------------------------ */

	public static function single_content( $content ) {
		if ( ! is_singular( MSB_Items::POST_TYPE ) || ! in_the_loop() || ! is_main_query() || ! msb_setting( 'auto_booking_box' ) ) {
			return $content;
		}
		// Wenn der Inhalt bereits eine Buchungsbox enthält, nichts anhängen.
		if ( has_shortcode( get_post()->post_content, 'msb_booking' ) || has_shortcode( get_post()->post_content, 'msb_item' ) ) {
			return $content;
		}
		$id    = get_the_ID();
		$extra = '<div class="msb msb-single">';
		if ( count( MSB_Items::gallery_ids( $id ) ) > 1 ) {
			$extra .= self::gallery_html( $id, 'full', 'large' );
		}
		$extra .= '<div class="msb-single__price">' . self::price_html( $id, 'from' ) . '</div>';
		$specs = self::specs_html( $id );
		if ( $specs ) {
			$extra .= '<h3>Technische Daten</h3>' . $specs;
		}
		$extra .= '<h3>Preise &amp; Konditionen</h3>' . self::price_html( $id, 'table' );
		$extra .= '</div>';
		$extra .= self::booking( array( 'id' => $id ) );
		self::enqueue();
		return $content . $extra;
	}
}
