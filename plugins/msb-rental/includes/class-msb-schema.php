<?php
/**
 * Strukturierte Daten (schema.org) für Mietartikel – ab Version 1.2.3.
 *
 * Gibt auf jeder Artikelseite ein „Product“ mit Mietangebot (Tagespreis) aus.
 * Mit Rank Math wird das Produkt in dessen JSON-LD-Graph eingefügt, sonst als eigenes Skript.
 * Ab 1.2.7: Feld „Marke“ je Artikel (Meta-Box „Suchmaschinen“), ausgegeben als Product.brand.
 * Ab 1.8.5: Verfügbarkeit datumsbezogen – InStock nur mit mindestens einem freien Tag in den nächsten 30 Tagen (SEO-02).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Schema {

	const META_BRAND = '_msb_brand';

	// Ab 1.8.5: Prüfzeitraum für die Verfügbarkeit in Tagen, ab heute + Vorlauf.
	const AVAILABILITY_DAYS = 30;

	public static function init() {
		add_filter( 'rank_math/json_ld', array( __CLASS__, 'rank_math' ), 99, 2 );
		add_action( 'wp_head', array( __CLASS__, 'fallback' ), 30 );
		// Ab 1.2.4: Astra-Mikrodaten (CreativeWork/Person) sind neben Rank Math doppelt und lösen Validierungsfehler aus.
		add_filter( 'astra_creativework_schema_enabled', array( __CLASS__, 'theme_microdata' ) );
		add_filter( 'astra_person_schema_enabled', array( __CLASS__, 'theme_microdata' ) );
		// Ab 1.2.7: Marke pflegen.
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . MSB_Items::POST_TYPE, array( __CLASS__, 'save_meta' ), 10, 2 );
	}

	/**
	 * Theme-Mikrodaten nur verwenden, wenn kein SEO-Plugin strukturierte Daten liefert.
	 */
	public static function theme_microdata( $enabled ) {
		return defined( 'RANK_MATH_VERSION' ) ? false : $enabled;
	}

	/* ------------------------------------------------------------------
	 * Meta-Box „Suchmaschinen“ (Marke)
	 * ------------------------------------------------------------------ */

	public static function brand( $id ) {
		return trim( (string) get_post_meta( (int) $id, self::META_BRAND, true ) );
	}

	public static function add_meta_box() {
		add_meta_box( 'msb_item_seo', 'Suchmaschinen (Google)', array( __CLASS__, 'render_meta_box' ), MSB_Items::POST_TYPE, 'side', 'default' );
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'msb_save_brand', 'msb_brand_nonce' );
		echo '<p><label for="msb_brand"><strong>Marke / Hersteller</strong></label><br>';
		echo '<input type="text" id="msb_brand" name="msb_brand" class="widefat" maxlength="70" placeholder="z. B. Thule, Deuter" value="' . esc_attr( self::brand( $post->ID ) ) . '"></p>';
		echo '<p class="description">Nur die Marke, ohne Modell. Wird Google als „brand“ in den strukturierten Daten übergeben. Leer lassen, wenn unbekannt.</p>';
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['msb_brand_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['msb_brand_nonce'] ) ), 'msb_save_brand' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$brand = isset( $_POST['msb_brand'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['msb_brand'] ) ), 0, 70 ) : '';
		if ( '' === $brand ) {
			delete_post_meta( $post_id, self::META_BRAND );
		} else {
			update_post_meta( $post_id, self::META_BRAND, $brand );
		}
	}

	/* ------------------------------------------------------------------
	 * Product
	 * ------------------------------------------------------------------ */

	/**
	 * Ab 1.8.5: Ist der Artikel im Prüfzeitraum an mindestens einem Tag frei?
	 * Nutzt den Buchungskalender (buchbar, Bestand, Buchungen mit Puffer, Sperrzeiten, Vorausbuchung).
	 * Schliesstage (keine Abholung/Rückgabe) zählen als frei.
	 */
	public static function available( $id ) {
		$days = max( 1, (int) apply_filters( 'msb_schema_availability_days', self::AVAILABILITY_DAYS, $id ) );
		$from = msb_add_days( msb_today(), max( 0, (int) msb_setting( 'lead_days' ) ) );
		$to   = msb_add_days( $from, $days - 1 );
		foreach ( MSB_Availability::calendar( $id, $from, $to ) as $day ) {
			if ( ! $day['x'] && $day['a'] > 0 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Product-Daten eines Mietartikels.
	 */
	public static function product( $id ) {
		$id   = (int) $id;
		$post = get_post( $id );
		if ( ! $post || MSB_Items::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}
		$item = MSB_Items::data( $id );
		$url  = get_permalink( $id );

		$images = array();
		foreach ( array_slice( $item['gallery'], 0, 5 ) as $img ) {
			$src = wp_get_attachment_image_url( $img, 'full' );
			if ( $src ) {
				$images[] = $src;
			}
		}

		$description = trim( (string) get_post_meta( $id, 'rank_math_description', true ) );
		if ( '' === $description ) {
			$description = trim( wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : $post->post_content ) );
		}

		$product = array(
			'@type'       => 'Product',
			'@id'         => $url . '#product',
			'name'        => wp_strip_all_tags( get_the_title( $id ) ),
			'url'         => $url,
			'description' => wp_html_excerpt( $description, 300, '' ),
		);
		if ( $images ) {
			$product['image'] = $images;
		}
		$brand = self::brand( $id );
		if ( '' !== $brand ) {
			$product['brand'] = array(
				'@type' => 'Brand',
				'name'  => $brand,
			);
		}
		$terms = get_the_terms( $id, MSB_Items::TAXONOMY );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$product['category'] = $terms[0]->name;
		}

		$price = $item['price_day'] > 0 ? $item['price_day'] : ( $item['tiers'] ? min( $item['tiers'] ) : 0 );
		if ( $price > 0 ) {
			$currency          = (string) msb_setting( 'currency_code', 'EUR' );
			$formatted         = number_format( (float) $price, 2, '.', '' );
			$available         = self::available( $id );
			$product['offers'] = array(
				'@type'              => 'Offer',
				'url'                => $url,
				'price'              => $formatted,
				'priceCurrency'      => $currency,
				'availability'       => $available ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
				'businessFunction'   => 'http://purl.org/goodrelations/v1#LeaseOut',
				'itemCondition'      => 'https://schema.org/UsedCondition',
				'priceSpecification' => array(
					'@type'            => 'UnitPriceSpecification',
					'price'            => $formatted,
					'priceCurrency'    => $currency,
					'unitCode'         => 'DAY',
					'unitText'         => 'Tag',
					'referenceQuantity' => array(
						'@type'    => 'QuantitativeValue',
						'value'    => 1,
						'unitCode' => 'DAY',
					),
				),
				'seller'             => array( '@id' => home_url( '/#organization' ) ),
			);
		}
		return apply_filters( 'msb_product_schema', $product, $id );
	}

	/**
	 * Rank Math: Produkt in den vorhandenen Graph einfügen.
	 */
	public static function rank_math( $data, $jsonld = null ) {
		if ( is_singular( MSB_Items::POST_TYPE ) ) {
			$product = self::product( get_queried_object_id() );
			if ( $product ) {
				$data['msbProduct'] = $product;
			}
		}
		return $data;
	}

	/**
	 * Ohne Rank Math: eigenes JSON-LD-Skript.
	 */
	public static function fallback() {
		if ( defined( 'RANK_MATH_VERSION' ) || ! is_singular( MSB_Items::POST_TYPE ) ) {
			return;
		}
		$product = self::product( get_queried_object_id() );
		if ( ! $product ) {
			return;
		}
		$product = array_merge( array( '@context' => 'https://schema.org' ), $product );
		echo '<script type="application/ld+json">' . wp_json_encode( $product, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n"; // phpcs:ignore
	}
}
