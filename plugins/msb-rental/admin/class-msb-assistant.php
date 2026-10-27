<?php
/**
 * Backend: KI-Artikelassistent (ab Version 1.2.0).
 *
 * Fotos + Eckdaten → KI-Vorschlag (Claude API) nach Styleguide → Prüfen → Mietartikel als Entwurf.
 * Außerdem: Vollständigkeitsprüfung auf der Bearbeitungsseite jedes Mietartikels.
 * Einstellungen (ab 1.2.1): Vermietung → Einstellungen → KI-Assistent.
 * SEO-Aufbau (ab 1.2.2): Produktart, SEO-Titel, Keyword-Zwischenüberschrift und interne Links.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Assistant {

	const OPTION      = 'msb_ai_settings';
	const PAGE        = 'msb-ai';
	const API_URL     = 'https://api.anthropic.com/v1/messages';
	const API_VERSION = '2023-06-01';
	const MAX_IMAGES  = 6;
	const MAX_BYTES   = 4718592; // 4,5 MB pro Bild.

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 10 );
		add_action( 'admin_init', array( __CLASS__, 'handle_settings_post' ), 5 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_msb_ai_generate', array( __CLASS__, 'ajax_generate' ) );
		add_action( 'wp_ajax_msb_ai_save', array( __CLASS__, 'ajax_save' ) );
		add_action( 'admin_notices', array( __CLASS__, 'completeness_notice' ) );
	}

	/* ------------------------------------------------------------------
	 * Einstellungen
	 * ------------------------------------------------------------------ */

	public static function models() {
		return array(
			'claude-sonnet-5-5' => 'Claude Sonnet 5.5 – empfohlen',
			'claude-haiku-5-5'  => 'Claude Haiku 5.5 – günstiger, etwas einfachere Texte',
			'claude-opus-5-5'   => 'Claude Opus 5.5 – stärkstes Modell, teurer',
		);
	}

	public static function default_closing() {
		return 'Abholung und Rückgabe in Bernau, Willi-Seng-Str. 4 – täglich zwischen 9 und 17 Uhr.';
	}

	public static function default_styleguide() {
		return implode(
			"\n",
			array(
				'Styleguide Mietartikel – Mietservice Barnim (Vermietung von Outdoor- und Transportartikeln in Bernau bei Berlin)',
				'',
				'Grundregeln:',
				'- Ansprache: du. Freundlich, sachlich, kurz. Keine Werbefloskeln, keine Superlative ohne Beleg („der beste“, „Premium“), kein „viele unserer Kunden“.',
				'- Nur Angaben, die auf den Fotos erkennbar sind, vom Vermieter genannt wurden oder sichere Herstellerangaben zum genannten Modell sind.',
				'- Keine Preise, keine Kaution, keine Mietbedingungen (Alter, Ausweis, Haftung, Stornierung) – das regeln die AGB und die Buchungsbox.',
				'',
				'Felder:',
				'- Produktart: Gattungsbegriff im Singular, nach dem Kunden suchen, z. B. „Kinderkraxe“, „Fahrradanhänger“, „Fahrradträger“.',
				'- Titel: „Marke Modell – Kurznutzen“, höchstens ca. 60 Zeichen. Beispiel: „Thule Chariot Cougar 1 – Multisport-Anhänger für 1 Kind“.',
				'- SEO-Titel: „[Produktart] mieten in Bernau: Marke Modell“ plus wenn möglich eine Zahl (z. B. Kapazität), höchstens ca. 60 Zeichen.',
				'- Kurzbeschreibung: genau 1 Satz, höchstens ca. 140 Zeichen, für die Artikelkarte.',
				'- Einleitung: 2–3 Sätze, beginnt mit „[Produktart] mieten statt kaufen: …“. Was ist es, wofür nutzt du es, was macht es praktisch.',
				'- Ausstattung: 4–6 kurze Punkte, nur was in der Miete enthalten ist. Ohne Häkchen-Zeichen.',
				'- Ideal für: 2–3 Einsatzzwecke, kurz.',
				'- Gut zu wissen: optional, höchstens 2 Sätze mit Sicherheits- oder Nutzungshinweisen. Leer lassen, wenn es nichts Wichtiges gibt.',
				'- Zustand: 1–2 Sätze, ehrlich (Gebrauchsspuren nennen, wenn sichtbar oder genannt), endet mit „Vor jeder Vermietung prüfen wir Funktion und Vollständigkeit.“',
				'- Technische Daten: Bezeichnung und Wert, z. B. Gewicht, Zuladung, Maße, Alter/Größe, Kompatibilität.',
				'- Meta-Beschreibung: 140–160 Zeichen, beginnt mit „[Produktart] mieten in Bernau bei Berlin: …“ + Marke Modell + Nutzen + „Jetzt Verfügbarkeit prüfen.“',
				'- Fokus-Keyword: „[Produktart] mieten“, z. B. „Kinderkraxe mieten“.',
				'- Alt-Texte: Bild 1 „[Produktart] mieten – Marke Modell“; weitere Bilder beschreiben, was zu sehen ist (Marke, Modell, Ansicht), höchstens ca. 120 Zeichen.',
				'- Abschluss: Das Plugin ergänzt automatisch die Zwischenüberschrift „[Produktart] mieten in Bernau“ mit Abholhinweis und Links zu AGB und Mietartikeln.',
			)
		);
	}

	/**
	 * Standard-Styleguide aus 1.2.0/1.2.1 – wird beim Laden automatisch durch den neuen ersetzt.
	 */
	private static function legacy_styleguide_120() {
		return implode(
			"\n",
			array(
				'Styleguide Mietartikel – Mietservice Barnim (Vermietung von Outdoor- und Transportartikeln in Bernau bei Berlin)',
				'',
				'Grundregeln:',
				'- Ansprache: du. Freundlich, sachlich, kurz. Keine Werbefloskeln, keine Superlative ohne Beleg („der beste“, „Premium“), kein „viele unserer Kunden“.',
				'- Nur Angaben, die auf den Fotos erkennbar sind, vom Vermieter genannt wurden oder sichere Herstellerangaben zum genannten Modell sind.',
				'- Keine Preise, keine Kaution, keine Mietbedingungen (Alter, Ausweis, Haftung, Stornierung) – das regeln die AGB und die Buchungsbox.',
				'',
				'Felder:',
				'- Titel: „Marke Modell – Kurznutzen“, höchstens ca. 60 Zeichen. Beispiel: „Thule Chariot Cougar 1 – Multisport-Kinderanhänger für 1 Kind“.',
				'- Kurzbeschreibung: genau 1 Satz, höchstens ca. 140 Zeichen, für die Artikelkarte.',
				'- Einleitung: 2–3 Sätze: Was ist es, wofür nutzt du es, was macht es praktisch.',
				'- Ausstattung: 4–6 kurze Punkte, nur was in der Miete enthalten ist. Ohne Häkchen-Zeichen.',
				'- Ideal für: 2–3 Einsatzzwecke, kurz.',
				'- Gut zu wissen: optional, höchstens 2 Sätze mit Sicherheits- oder Nutzungshinweisen. Leer lassen, wenn es nichts Wichtiges gibt.',
				'- Zustand: 1–2 Sätze, ehrlich (Gebrauchsspuren nennen, wenn sichtbar oder genannt), endet mit „Vor jeder Vermietung prüfen wir Funktion und Vollständigkeit.“',
				'- Technische Daten: Bezeichnung und Wert, z. B. Gewicht, Zuladung, Maße, Alter/Größe, Kompatibilität.',
				'- Meta-Beschreibung: 140–160 Zeichen: Produkt + „mieten in Bernau bei Berlin“ + Nutzen + „Jetzt Verfügbarkeit prüfen.“',
				'- Fokus-Keyword: „[Produktart] mieten Bernau“, z. B. „Kinderkraxe mieten Bernau“.',
				'- Alt-Texte: beschreiben, was auf dem jeweiligen Bild zu sehen ist (Marke, Modell, Ansicht), höchstens ca. 120 Zeichen.',
			)
		);
	}

	public static function settings() {
		$s = get_option( self::OPTION, array() );
		if ( ! is_array( $s ) ) {
			$s = array();
		}
		$s = wp_parse_args(
			$s,
			array(
				'api_key'    => '',
				'model'      => 'claude-sonnet-5-5',
				'styleguide' => '',
				'closing'    => '',
			)
		);
		$saved_sg = trim( str_replace( "\r\n", "\n", (string) $s['styleguide'] ) );
		if ( '' === $saved_sg || trim( self::legacy_styleguide_120() ) === $saved_sg ) {
			$s['styleguide'] = self::default_styleguide();
		}
		if ( '' === trim( $s['closing'] ) ) {
			$s['closing'] = self::default_closing();
		}
		if ( ! isset( self::models()[ $s['model'] ] ) ) {
			$s['model'] = 'claude-sonnet-5-5';
		}
		return $s;
	}

	/**
	 * Speichert die KI-Einstellungen aus Vermietung → Einstellungen → KI-Assistent.
	 * Berechtigung und Nonce prüft handle_settings_post().
	 */
	public static function save_from_settings( array $in ) {
		$s = self::settings();

		$key = isset( $in['ai_api_key'] ) ? trim( (string) $in['ai_api_key'] ) : '';
		if ( '__delete__' === $key ) {
			$s['api_key'] = '';
		} elseif ( '' !== $key ) {
			$s['api_key'] = sanitize_text_field( $key );
		}
		$model      = isset( $in['ai_model'] ) ? sanitize_text_field( (string) $in['ai_model'] ) : '';
		$s['model'] = isset( self::models()[ $model ] ) ? $model : 'claude-sonnet-5-5';

		if ( ! empty( $in['ai_reset_styleguide'] ) ) {
			$s['styleguide'] = '';
		} else {
			$s['styleguide'] = isset( $in['ai_styleguide'] ) ? sanitize_textarea_field( (string) $in['ai_styleguide'] ) : '';
		}
		$s['closing'] = isset( $in['ai_closing'] ) ? sanitize_text_field( (string) $in['ai_closing'] ) : '';

		update_option( self::OPTION, $s, false );
	}

	public static function settings_url() {
		return admin_url( 'admin.php?page=msb-settings&tab=ai' );
	}

	/**
	 * Speichern des Reiters „KI-Assistent“ (läuft vor MSB_Admin::handle_post).
	 */
	public static function handle_settings_post() {
		if ( empty( $_POST['msb_action'] ) || 'save_settings' !== $_POST['msb_action'] || empty( $_POST['tab'] ) || 'ai' !== $_POST['tab'] ) { // phpcs:ignore
			return;
		}
		if ( ! current_user_can( msb_capability( 'settings' ) ) ) {
			return;
		}
		check_admin_referer( 'msb_save_settings' );
		self::save_from_settings( wp_unslash( $_POST ) ); // phpcs:ignore
		wp_safe_redirect( add_query_arg( array( 'page' => 'msb-settings', 'tab' => 'ai', 'saved' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Reiter „KI-Assistent“ unter Vermietung → Einstellungen (gleicher Aufbau wie die übrigen Reiter).
	 */
	public static function settings_page( array $tabs, array $help ) {
		?>
		<div class="wrap msb-admin">
			<h1>Einstellungen <?php echo msb_help_link( isset( $help['ai'] ) ? $help['ai'] : 'ki-assistent', 'Erklärung zu diesem Bereich' ); // phpcs:ignore ?></h1>
			<?php
			if ( isset( $_GET['saved'] ) ) { // phpcs:ignore
				echo '<div class="notice notice-success is-dismissible"><p>Einstellungen gespeichert.</p></div>';
			}
			?>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $k => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=msb-settings&tab=' . $k ) ); ?>" class="nav-tab <?php echo 'ai' === $k ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<form method="post" action="">
				<?php wp_nonce_field( 'msb_save_settings' ); ?>
				<input type="hidden" name="msb_action" value="save_settings">
				<input type="hidden" name="tab" value="ai">
				<?php self::settings_fields(); ?>
				<p class="submit"><button type="submit" class="button button-primary">Einstellungen speichern</button></p>
			</form>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Menü & Assets
	 * ------------------------------------------------------------------ */

	public static function menu() {
		add_submenu_page( 'msb-bookings', 'Neuer Artikel mit KI', 'Neuer Artikel (KI)', msb_capability( 'items' ), self::PAGE, array( __CLASS__, 'page' ) );
	}

	private static function is_page() {
		return isset( $_GET['page'] ) && self::PAGE === $_GET['page']; // phpcs:ignore
	}

	public static function assets() {
		if ( ! self::is_page() ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'msb-assistant', MSB_URL . 'assets/css/assistant.css', array(), MSB_VERSION );
		wp_enqueue_script( 'msb-assistant', MSB_URL . 'assets/js/assistant.js', array( 'jquery', 'jquery-ui-sortable' ), MSB_VERSION, true );
		wp_localize_script(
			'msb-assistant',
			'msbAI',
			array(
				'ajax'      => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'msb_ai' ),
				'hasKey'    => '' !== self::settings()['api_key'],
				'maxImages' => self::MAX_IMAGES,
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Seite
	 * ------------------------------------------------------------------ */

	public static function page() {
		$s = self::settings();
		?>
		<div class="wrap msb-admin msb-ai">
			<h1>Neuer Artikel mit KI <?php echo msb_help_link( 'ki-assistent', 'Anleitung' ); // phpcs:ignore ?></h1>
			<?php self::render_wizard( $s ); ?>
		</div>
		<?php
	}

	/**
	 * Felder für Vermietung → Einstellungen → KI-Assistent (innerhalb des dortigen Formulars).
	 */
	public static function settings_fields() {
		$s   = self::settings();
		$set = '' !== $s['api_key'];
		?>
		<p>Einstellungen für den Assistenten unter <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ); ?>">Vermietung → Neuer Artikel (KI)</a>.</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="msb-ai-key">API-Schlüssel (Claude API)</label></th>
				<td>
					<input type="password" id="msb-ai-key" name="ai_api_key" value="" class="regular-text code" autocomplete="new-password" placeholder="<?php echo esc_attr( $set ? '•••••••• gespeichert (leer lassen = unverändert)' : 'sk-ant-…' ); ?>">
					<?php if ( $set ) : ?>
						<span class="msb-status msb-status--confirmed">bereit</span>
						<label><input type="checkbox" onchange="document.getElementById('msb-ai-key').value=this.checked?'__delete__':''"> löschen</label>
					<?php endif; ?>
					<p class="description">Schlüssel in der Claude Console (platform.claude.com) unter „API Keys“ erzeugen und hier eintragen. Der Schlüssel wird nur auf dem Server verwendet und nie im Browser angezeigt. Bitte nicht per E-Mail oder Chat weitergeben.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="msb-ai-model">KI-Modell</label></th>
				<td>
					<select id="msb-ai-model" name="ai_model">
						<?php foreach ( self::models() as $k => $label ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['model'], $k ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description">Abrechnung nach Verbrauch über Ihr API-Konto. Ein Artikel mit einigen Fotos kostet je nach Modell wenige Cent.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="msb-ai-closing">Abschlusssatz</label></th>
				<td>
					<input type="text" id="msb-ai-closing" name="ai_closing" value="<?php echo esc_attr( $s['closing'] ); ?>" class="large-text">
					<p class="description">Steht unter jeder Artikelbeschreibung, z. B. Abholort und Abholzeiten.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="msb-ai-style">Styleguide</label></th>
				<td>
					<textarea id="msb-ai-style" name="ai_styleguide" rows="22" class="large-text code"><?php echo esc_textarea( $s['styleguide'] ); ?></textarea>
					<label><input type="checkbox" name="ai_reset_styleguide" value="1"> Standard-Styleguide wiederherstellen</label>
					<p class="description">Nach diesen Regeln schreibt die KI. Der Aufbau der Beschreibung (Überschriften, Listen, Abschlusssatz) wird zusätzlich vom Plugin vorgegeben, damit alle Artikel gleich aussehen.</p>
				</td>
			</tr>
		</table>
		<?php
	}

	private static function render_wizard( array $s ) {
		$terms = get_terms(
			array(
				'taxonomy'   => MSB_Items::TAXONOMY,
				'hide_empty' => false,
			)
		);
		$cur   = esc_html( msb_setting( 'currency' ) );
		if ( '' === $s['api_key'] ) {
			echo '<div class="notice notice-warning"><p>Bitte zuerst unter <a href="' . esc_url( self::settings_url() ) . '">Einstellungen → KI-Assistent</a> den API-Schlüssel eintragen.</p></div>';
		}
		?>
		<p class="msb-ai__lead">Fotos auswählen, Eckdaten eintragen, Text erstellen lassen, prüfen – der Artikel wird als <strong>Entwurf</strong> gespeichert und erst nach Ihrer Freigabe veröffentlicht.</p>

		<div class="msb-card-box msb-ai__step" id="msb-ai-step-photos">
			<h2><span class="msb-ai__num">1</span> Fotos</h2>
			<p class="description">Bis zu <?php echo (int) self::MAX_IMAGES; ?> Fotos. Das erste Foto wird das Beitragsbild – Reihenfolge per Ziehen ändern. Möglichst echte Fotos, ohne Personen und Kennzeichen.</p>
			<ul class="msb-ai__images" id="msb-ai-images"></ul>
			<p><button type="button" class="button" id="msb-ai-pick">Fotos auswählen oder hochladen</button></p>
		</div>

		<div class="msb-card-box msb-ai__step" id="msb-ai-step-data">
			<h2><span class="msb-ai__num">2</span> Eckdaten</h2>
			<div class="msb-ai__grid">
				<p class="full"><label for="msb-ai-model-name"><strong>Marke und Modell</strong></label>
					<input type="text" id="msb-ai-model-name" data-field="brand_model" class="widefat" placeholder="z. B. Thule Chariot Cougar 1 – leer lassen, wenn unbekannt"></p>
				<p><label for="msb-ai-cat"><strong>Kategorie *</strong></label>
					<select id="msb-ai-cat" data-field="category" class="widefat">
						<option value="">– bitte wählen –</option>
						<?php if ( ! is_wp_error( $terms ) ) : ?>
							<?php foreach ( $terms as $t ) : ?>
								<option value="<?php echo (int) $t->term_id; ?>"><?php echo esc_html( $t->name ); ?></option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
					<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . MSB_Items::TAXONOMY . '&post_type=' . MSB_Items::POST_TYPE ) ); ?>" target="_blank">Kategorien verwalten</a></p>
				<p><label for="msb-ai-price"><strong>Preis pro Tag (<?php echo $cur; // phpcs:ignore ?>) *</strong></label>
					<input type="number" step="0.01" min="0" id="msb-ai-price" data-field="price_day" class="widefat"></p>
				<p><label for="msb-ai-deposit"><strong>Kaution pro Stück (<?php echo $cur; // phpcs:ignore ?>) *</strong></label>
					<input type="number" step="0.01" min="0" id="msb-ai-deposit" data-field="deposit" class="widefat">
					<span class="description">Wird als Kartenreservierung gehalten.</span></p>
				<p><label for="msb-ai-stock"><strong>Bestand (Stück)</strong></label>
					<input type="number" step="1" min="1" id="msb-ai-stock" data-field="stock" value="1" class="widefat"></p>
				<p><label for="msb-ai-min"><strong>Mindestmietdauer (Tage)</strong></label>
					<input type="number" step="1" min="1" id="msb-ai-min" data-field="min_days" value="1" class="widefat"></p>
				<p><label for="msb-ai-fee"><strong>Grundgebühr pro Buchung (<?php echo $cur; // phpcs:ignore ?>)</strong></label>
					<input type="number" step="0.01" min="0" id="msb-ai-fee" data-field="base_fee" class="widefat" placeholder="optional"></p>
				<p class="full"><strong>Rabatte</strong><br>
					<label><input type="radio" name="msb_ai_discount" data-field="discount" value="global" checked> Globale Rabatte anwenden (Einstellungen → Preise &amp; Rabatte)</label><br>
					<label><input type="radio" name="msb_ai_discount" data-field="discount" value="none"> Keine Rabatte für diesen Artikel</label><br>
					<label><input type="radio" name="msb_ai_discount" data-field="discount" value="custom"> Eigene Rabatte – lege ich nach dem Speichern im Artikel fest</label></p>
				<p class="full"><label for="msb-ai-tiers"><strong>Staffelpreise</strong> <span class="description">(optional, eine Zeile je Staffel: <code>Tage=Preis</code>, z. B. <code>7=6,50</code>)</span></label>
					<textarea id="msb-ai-tiers" data-field="tiers" rows="2" class="widefat code"></textarea></p>
				<p class="full"><label for="msb-ai-acc"><strong>Lieferumfang / Zubehör</strong> <span class="description">(was ist in der Miete enthalten?)</span></label>
					<textarea id="msb-ai-acc" data-field="accessories" rows="3" class="widefat" placeholder="z. B. Babyeinsatz, Joggingrad, 2 Kupplungen"></textarea></p>
				<p><label for="msb-ai-cond"><strong>Zustand</strong></label>
					<select id="msb-ai-cond" data-field="condition" class="widefat">
						<option value="">– nach Fotos beurteilen –</option>
						<option>neu / neuwertig</option>
						<option>sehr gut, kaum Gebrauchsspuren</option>
						<option>gut, normale Gebrauchsspuren</option>
						<option>gebraucht, deutliche Gebrauchsspuren, voll funktionsfähig</option>
					</select></p>
				<p class="full"><label for="msb-ai-notes"><strong>Weitere Hinweise für die KI</strong> <span class="description">(optional, Stichworte)</span></label>
					<textarea id="msb-ai-notes" data-field="notes" rows="3" class="widefat" placeholder="z. B. max. Kindgewicht 22 kg, passt in jeden Kombi, Adapter 13→7-polig liegt bei"></textarea></p>
			</div>
			<p><button type="button" class="button button-primary button-hero" id="msb-ai-generate">Text mit KI erstellen</button>
				<span class="msb-ai__status" id="msb-ai-status" aria-live="polite"></span></p>
		</div>

		<div class="msb-card-box msb-ai__step" id="msb-ai-step-result" hidden>
			<h2><span class="msb-ai__num">3</span> Prüfen &amp; anpassen</h2>
			<div class="msb-ai__check" id="msb-ai-check" hidden>
				<strong>Bitte prüfen:</strong>
				<ul id="msb-ai-check-list"></ul>
			</div>
			<div class="msb-ai__grid">
				<p><label for="msb-ai-r-type"><strong>Produktart</strong> <span class="description">(Singular, z. B. Kinderkraxe – ergibt Keyword, Zwischenüberschrift und URL)</span></label>
					<input type="text" id="msb-ai-r-type" data-result="product_type" class="widefat"></p>
				<p><label for="msb-ai-r-seo"><strong>SEO-Titel (Google)</strong> <span class="msb-ai__count" data-for="msb-ai-r-seo" data-max="60"></span></label>
					<input type="text" id="msb-ai-r-seo" data-result="seo_title" class="widefat"></p>
				<p class="full"><label for="msb-ai-r-title"><strong>Titel</strong> <span class="msb-ai__count" data-for="msb-ai-r-title" data-max="60"></span></label>
					<input type="text" id="msb-ai-r-title" data-result="title" class="widefat"></p>
				<p class="full"><label for="msb-ai-r-excerpt"><strong>Kurzbeschreibung</strong> <span class="msb-ai__count" data-for="msb-ai-r-excerpt" data-max="140"></span></label>
					<input type="text" id="msb-ai-r-excerpt" data-result="excerpt" class="widefat"></p>
				<p class="full"><label for="msb-ai-r-intro"><strong>Einleitung</strong></label>
					<textarea id="msb-ai-r-intro" data-result="intro" rows="4" class="widefat"></textarea></p>
				<p><label for="msb-ai-r-features"><strong>Ausstattung &amp; Zubehör</strong> <span class="description">(eine Zeile je Punkt)</span></label>
					<textarea id="msb-ai-r-features" data-result="features" rows="7" class="widefat"></textarea></p>
				<p><label for="msb-ai-r-ideal"><strong>Ideal für</strong> <span class="description">(eine Zeile je Punkt)</span></label>
					<textarea id="msb-ai-r-ideal" data-result="ideal" rows="7" class="widefat"></textarea></p>
				<p class="full"><label for="msb-ai-r-hints"><strong>Gut zu wissen</strong> <span class="description">(optional – leer = Abschnitt entfällt)</span></label>
					<textarea id="msb-ai-r-hints" data-result="hints" rows="2" class="widefat"></textarea></p>
				<p class="full"><label for="msb-ai-r-condition"><strong>Zustand</strong></label>
					<textarea id="msb-ai-r-condition" data-result="condition" rows="2" class="widefat"></textarea></p>
				<p class="full"><label for="msb-ai-r-specs"><strong>Technische Daten</strong> <span class="description">(eine Zeile je Wert: <code>Bezeichnung: Wert</code>)</span></label>
					<textarea id="msb-ai-r-specs" data-result="specs" rows="6" class="widefat code"></textarea></p>
				<p class="full"><label for="msb-ai-r-meta"><strong>Meta-Beschreibung (Google)</strong> <span class="msb-ai__count" data-for="msb-ai-r-meta" data-min="140" data-max="160"></span></label>
					<textarea id="msb-ai-r-meta" data-result="meta" rows="2" class="widefat"></textarea></p>
				<p><label for="msb-ai-r-keyword"><strong>Fokus-Keyword</strong> <span class="description">(leer = „Produktart mieten“)</span></label>
					<input type="text" id="msb-ai-r-keyword" data-result="keyword" class="widefat"></p>
			</div>
			<p class="description">Die Alt-Texte stehen unter den Fotos in Schritt 1 und können dort angepasst werden. Abschlusssatz: „<?php echo esc_html( $s['closing'] ); ?>“</p>
			<p>
				<button type="button" class="button button-primary button-hero" id="msb-ai-save">Als Entwurf speichern</button>
				<button type="button" class="button" id="msb-ai-regenerate">Neu erstellen</button>
				<span class="msb-ai__status" id="msb-ai-save-status" aria-live="polite"></span>
			</p>
		</div>

		<div class="msb-card-box msb-ai__step msb-ai__done" id="msb-ai-step-done" hidden>
			<h2><span class="msb-ai__num">✓</span> Entwurf gespeichert</h2>
			<p id="msb-ai-done-text"></p>
			<p><a class="button button-primary" id="msb-ai-edit" href="#">Artikel öffnen und veröffentlichen</a>
				<a class="button" id="msb-ai-preview" href="#" target="_blank">Vorschau</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ); ?>">Nächsten Artikel anlegen</a></p>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Eingaben
	 * ------------------------------------------------------------------ */

	private static function check_request() {
		check_ajax_referer( 'msb_ai', 'nonce' );
		if ( ! current_user_can( msb_capability() ) ) {
			wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ), 403 );
		}
	}

	private static function image_ids() {
		$raw = isset( $_POST['images'] ) ? (array) wp_unslash( $_POST['images'] ) : array(); // phpcs:ignore
		$ids = array();
		foreach ( $raw as $id ) {
			$id = absint( $id );
			if ( $id && wp_attachment_is_image( $id ) && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}
		return array_slice( $ids, 0, self::MAX_IMAGES );
	}

	private static function num( $v, $int = false ) {
		$v = str_replace( ',', '.', trim( (string) $v ) );
		if ( '' === $v ) {
			return '';
		}
		return $int ? max( 0, (int) $v ) : max( 0, round( (float) $v, 2 ) );
	}

	private static function input_data() {
		$d = isset( $_POST['data'] ) && is_array( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array(); // phpcs:ignore
		$g = function ( $k ) use ( $d ) {
			return isset( $d[ $k ] ) ? (string) $d[ $k ] : '';
		};
		$discount = $g( 'discount' );
		return array(
			'brand_model' => sanitize_text_field( $g( 'brand_model' ) ),
			'category'    => absint( $g( 'category' ) ),
			'price_day'   => self::num( $g( 'price_day' ) ),
			'deposit'     => self::num( $g( 'deposit' ) ),
			'stock'       => max( 1, (int) self::num( $g( 'stock' ), true ) ),
			'min_days'    => max( 1, (int) self::num( $g( 'min_days' ), true ) ),
			'base_fee'    => self::num( $g( 'base_fee' ) ),
			'discount'    => in_array( $discount, array( 'global', 'none', 'custom' ), true ) ? $discount : 'global',
			'tiers'       => sanitize_textarea_field( $g( 'tiers' ) ),
			'accessories' => sanitize_textarea_field( $g( 'accessories' ) ),
			'condition'   => sanitize_text_field( $g( 'condition' ) ),
			'notes'       => sanitize_textarea_field( $g( 'notes' ) ),
		);
	}

	private static function category_name( $term_id ) {
		$t = $term_id ? get_term( $term_id, MSB_Items::TAXONOMY ) : null;
		return $t && ! is_wp_error( $t ) ? $t->name : '';
	}

	/* ------------------------------------------------------------------
	 * KI-Aufruf
	 * ------------------------------------------------------------------ */

	public static function ajax_generate() {
		self::check_request();
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 180 ); // phpcs:ignore -- KI-Antwort kann bis zu einer Minute dauern.
		}
		$s = self::settings();
		if ( '' === $s['api_key'] ) {
			wp_send_json_error( array( 'message' => 'Bitte zuerst unter Einstellungen → KI-Assistent den API-Schlüssel eintragen.' ) );
		}
		$ids = self::image_ids();
		if ( ! $ids ) {
			wp_send_json_error( array( 'message' => 'Bitte mindestens ein Foto auswählen.' ) );
		}
		$d = self::input_data();
		if ( ! $d['category'] ) {
			wp_send_json_error( array( 'message' => 'Bitte eine Kategorie wählen.' ) );
		}

		$content = array();
		$sent    = 0;
		foreach ( $ids as $i => $id ) {
			$block = self::image_block( $id );
			if ( $block ) {
				$content[] = array( 'type' => 'text', 'text' => 'Bild ' . ( $i + 1 ) . ':' );
				$content[] = $block;
				$sent++;
			}
		}
		if ( ! $sent ) {
			wp_send_json_error( array( 'message' => 'Die Fotos konnten nicht gelesen werden (erlaubt: JPG, PNG, WebP, GIF bis 4,5 MB).' ) );
		}
		$content[] = array( 'type' => 'text', 'text' => self::user_prompt( $d, count( $ids ) ) );

		$body = array(
			'model'         => $s['model'],
			'max_tokens'    => 3000,
			'system'        => self::system_prompt( $s['styleguide'] ),
			'messages'      => array(
				array(
					'role'    => 'user',
					'content' => $content,
				),
			),
			'output_config' => array(
				'format' => array(
					'type'   => 'json_schema',
					'schema' => self::schema(),
				),
			),
		);

		$res = wp_remote_post(
			self::API_URL,
			array(
				'timeout' => 120,
				'headers' => array(
					'x-api-key'         => $s['api_key'],
					'anthropic-version' => self::API_VERSION,
					'content-type'      => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => 'Keine Verbindung zur KI: ' . $res->get_error_message() ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$json = json_decode( wp_remote_retrieve_body( $res ), true );

		if ( 200 !== $code ) {
			$detail = is_array( $json ) && isset( $json['error']['message'] ) ? (string) $json['error']['message'] : '';
			$map    = array(
				400 => 'Die Anfrage wurde abgelehnt.',
				401 => 'Der API-Schlüssel ist ungültig. Bitte unter Einstellungen → KI-Assistent prüfen.',
				403 => 'Der API-Schlüssel hat keine Berechtigung für dieses Modell.',
				404 => 'Das gewählte Modell wurde nicht gefunden. Bitte unter Einstellungen → KI-Assistent ein anderes wählen.',
				413 => 'Die Fotos sind zu groß. Bitte weniger oder kleinere Fotos verwenden.',
				429 => 'Zu viele Anfragen oder Guthaben/Limit erreicht. Bitte kurz warten bzw. das API-Konto prüfen.',
				500 => 'Fehler beim KI-Anbieter. Bitte in einigen Minuten erneut versuchen.',
				529 => 'Die KI ist gerade überlastet. Bitte in einigen Minuten erneut versuchen.',
			);
			$msg = isset( $map[ $code ] ) ? $map[ $code ] : 'Fehler ' . $code . ' bei der KI-Anfrage.';
			wp_send_json_error( array( 'message' => $msg . ( $detail ? ' (' . $detail . ')' : '' ) ) );
		}

		$text = '';
		if ( is_array( $json ) && ! empty( $json['content'] ) && is_array( $json['content'] ) ) {
			foreach ( $json['content'] as $block ) {
				if ( isset( $block['type'], $block['text'] ) && 'text' === $block['type'] ) {
					$text .= $block['text'];
				}
			}
		}
		$stop = is_array( $json ) && isset( $json['stop_reason'] ) ? $json['stop_reason'] : '';
		$out  = json_decode( $text, true );
		if ( 'refusal' === $stop || ! is_array( $out ) ) {
			wp_send_json_error( array( 'message' => 'Die Antwort der KI war unvollständig (' . ( $stop ? $stop : 'kein Text' ) . '). Bitte erneut versuchen.' ) );
		}

		$result          = self::clean_result( $out, $ids );
		$result['usage'] = array(
			'input'  => isset( $json['usage']['input_tokens'] ) ? (int) $json['usage']['input_tokens'] : 0,
			'output' => isset( $json['usage']['output_tokens'] ) ? (int) $json['usage']['output_tokens'] : 0,
		);
		wp_send_json_success( $result );
	}

	/**
	 * Foto als Base64-Block (mittlere Größe statt Original).
	 */
	private static function image_block( $id ) {
		$allowed = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
		$file    = get_attached_file( $id );
		$mime    = get_post_mime_type( $id );
		$meta    = wp_get_attachment_metadata( $id );
		if ( $file && is_array( $meta ) && ! empty( $meta['sizes'] ) ) {
			foreach ( array( 'large', 'medium_large' ) as $size ) {
				if ( ! empty( $meta['sizes'][ $size ]['file'] ) ) {
					$candidate = path_join( dirname( $file ), $meta['sizes'][ $size ]['file'] );
					if ( file_exists( $candidate ) ) {
						$file = $candidate;
						if ( ! empty( $meta['sizes'][ $size ]['mime-type'] ) ) {
							$mime = $meta['sizes'][ $size ]['mime-type'];
						}
						break;
					}
				}
			}
		}
		if ( ! $file || ! file_exists( $file ) || ! in_array( $mime, $allowed, true ) || filesize( $file ) > self::MAX_BYTES ) {
			return null;
		}
		$data = file_get_contents( $file ); // phpcs:ignore
		if ( false === $data ) {
			return null;
		}
		return array(
			'type'   => 'image',
			'source' => array(
				'type'       => 'base64',
				'media_type' => $mime,
				'data'       => base64_encode( $data ), // phpcs:ignore
			),
		);
	}

	private static function system_prompt( $styleguide ) {
		return "Du schreibst Artikelbeschreibungen für den Verleih „Mietservice Barnim“ in Bernau bei Berlin. Alle Texte auf Deutsch.\n\n"
			. trim( $styleguide ) . "\n\n"
			. "Arbeitsweise:\n"
			. "- Werte die Fotos und die Angaben des Vermieters aus. Angaben des Vermieters haben Vorrang vor deiner Einschätzung.\n"
			. "- Erfinde nichts. Technische Daten nur, wenn sie auf den Fotos erkennbar sind, vom Vermieter genannt wurden oder du sie für das genannte Modell sicher kennst.\n"
			. "- Jede Angabe, die nicht vom Vermieter stammt und nicht eindeutig auf den Fotos zu sehen ist (z. B. Herstellerdaten aus deinem Wissen, vermutetes Modell, geschätzter Zustand), nennst du zusätzlich im Feld „pruefen“ mit kurzer Begründung.\n"
			. "- Ist Marke oder Modell nicht sicher erkennbar, verwende eine neutrale Bezeichnung und vermerke das in „pruefen“.\n"
			. "- Orientiere dich in Ton und Länge an den Beispielen bestehender Artikel, falls vorhanden.\n"
			. "- Antworte ausschließlich im vorgegebenen JSON-Format.";
	}

	private static function user_prompt( array $d, $count ) {
		$lines   = array( 'Angaben des Vermieters:' );
		$lines[] = '- Kategorie: ' . self::category_name( $d['category'] );
		$lines[] = '- Marke/Modell: ' . ( '' !== $d['brand_model'] ? $d['brand_model'] : 'nicht angegeben – bitte anhand der Fotos bestimmen' );
		$lines[] = '- Lieferumfang/Zubehör in der Miete: ' . ( '' !== $d['accessories'] ? $d['accessories'] : 'nicht angegeben' );
		$lines[] = '- Zustand: ' . ( '' !== $d['condition'] ? $d['condition'] : 'nicht angegeben – nach Fotos beurteilen' );
		if ( '' !== $d['notes'] ) {
			$lines[] = '- Weitere Hinweise: ' . $d['notes'];
		}
		$lines[] = '';
		$lines[] = 'Es liegen ' . (int) $count . ' Fotos vor (Bild 1 wird das Beitragsbild). Gib im Feld „alt_texte“ genau ' . (int) $count . ' Alt-Texte in der Reihenfolge der Bilder zurück.';

		$examples = self::examples();
		if ( $examples ) {
			$lines[] = '';
			$lines[] = 'Beispiele bestehender Artikel (Stil und Aufbau übernehmen, Inhalte nicht übernehmen):';
			$lines[] = $examples;
		}
		return implode( "\n", $lines );
	}

	/**
	 * Bis zu 3 veröffentlichte Artikel als Stilbeispiele.
	 */
	private static function examples() {
		$posts = get_posts(
			array(
				'post_type'      => MSB_Items::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 3,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		$out = array();
		foreach ( $posts as $p ) {
			$text  = preg_replace( '#</(p|li|h2|h3|ul)>#i', "\n", $p->post_content );
			$text  = preg_replace( '#<h2[^>]*>#i', "\n## ", $text );
			$text  = preg_replace( '#<li[^>]*>#i', '- ', $text );
			$text  = trim( preg_replace( "/\n{3,}/", "\n\n", wp_strip_all_tags( $text ) ) );
			$out[] = "---\nTitel: " . get_the_title( $p ) . "\nKurzbeschreibung: " . $p->post_excerpt . "\n" . mb_substr( $text, 0, 1500 );
		}
		return implode( "\n", $out );
	}

	private static function schema() {
		$str  = array( 'type' => 'string' );
		$list = array(
			'type'  => 'array',
			'items' => array( 'type' => 'string' ),
		);
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'produktart'        => $str,
				'titel'             => $str,
				'seo_titel'         => $str,
				'kurzbeschreibung'  => $str,
				'einleitung'        => $str,
				'ausstattung'       => $list,
				'ideal_fuer'        => $list,
				'gut_zu_wissen'     => $str,
				'zustand'           => $str,
				'technische_daten'  => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'properties'           => array(
							'bezeichnung' => $str,
							'wert'        => $str,
						),
						'required'             => array( 'bezeichnung', 'wert' ),
						'additionalProperties' => false,
					),
				),
				'meta_beschreibung' => $str,
				'fokus_keyword'     => $str,
				'alt_texte'         => $list,
				'pruefen'           => $list,
			),
			'required'             => array( 'produktart', 'titel', 'seo_titel', 'kurzbeschreibung', 'einleitung', 'ausstattung', 'ideal_fuer', 'gut_zu_wissen', 'zustand', 'technische_daten', 'meta_beschreibung', 'fokus_keyword', 'alt_texte', 'pruefen' ),
			'additionalProperties' => false,
		);
	}

	private static function str_list( $v ) {
		$out = array();
		foreach ( (array) $v as $x ) {
			$x = trim( preg_replace( '/^[\s✓✔•\-\*]+/u', '', sanitize_text_field( (string) $x ) ) );
			if ( '' !== $x ) {
				$out[] = $x;
			}
		}
		return $out;
	}

	private static function clean_result( array $o, array $ids ) {
		$g     = function ( $k ) use ( $o ) {
			return isset( $o[ $k ] ) && is_scalar( $o[ $k ] ) ? (string) $o[ $k ] : '';
		};
		$specs = array();
		if ( isset( $o['technische_daten'] ) && is_array( $o['technische_daten'] ) ) {
			foreach ( $o['technische_daten'] as $row ) {
				if ( is_array( $row ) && isset( $row['bezeichnung'], $row['wert'] ) ) {
					$l = trim( str_replace( ':', '', sanitize_text_field( (string) $row['bezeichnung'] ) ) );
					$w = trim( sanitize_text_field( (string) $row['wert'] ) );
					if ( '' !== $l && '' !== $w ) {
						$specs[] = $l . ': ' . $w;
					}
				}
			}
		}
		$alts     = self::str_list( isset( $o['alt_texte'] ) ? $o['alt_texte'] : array() );
		$alt_by_id = array();
		foreach ( $ids as $i => $id ) {
			$alt_by_id[ $id ] = isset( $alts[ $i ] ) ? $alts[ $i ] : '';
		}
		return array(
			'product_type' => sanitize_text_field( $g( 'produktart' ) ),
			'seo_title'    => sanitize_text_field( $g( 'seo_titel' ) ),
			'title'     => sanitize_text_field( $g( 'titel' ) ),
			'excerpt'   => sanitize_text_field( $g( 'kurzbeschreibung' ) ),
			'intro'     => sanitize_textarea_field( $g( 'einleitung' ) ),
			'features'  => implode( "\n", self::str_list( isset( $o['ausstattung'] ) ? $o['ausstattung'] : array() ) ),
			'ideal'     => implode( "\n", self::str_list( isset( $o['ideal_fuer'] ) ? $o['ideal_fuer'] : array() ) ),
			'hints'     => sanitize_textarea_field( $g( 'gut_zu_wissen' ) ),
			'condition' => sanitize_textarea_field( $g( 'zustand' ) ),
			'specs'     => implode( "\n", $specs ),
			'meta'      => sanitize_text_field( $g( 'meta_beschreibung' ) ),
			'keyword'   => sanitize_text_field( $g( 'fokus_keyword' ) ),
			'alts'      => $alt_by_id,
			'check'     => self::str_list( isset( $o['pruefen'] ) ? $o['pruefen'] : array() ),
		);
	}

	/* ------------------------------------------------------------------
	 * Speichern als Entwurf
	 * ------------------------------------------------------------------ */

	private static function result_input() {
		$r = isset( $_POST['result'] ) && is_array( $_POST['result'] ) ? wp_unslash( $_POST['result'] ) : array(); // phpcs:ignore
		$g = function ( $k ) use ( $r ) {
			return isset( $r[ $k ] ) && is_scalar( $r[ $k ] ) ? (string) $r[ $k ] : '';
		};
		$alts = array();
		if ( isset( $_POST['alts'] ) && is_array( $_POST['alts'] ) ) { // phpcs:ignore
			foreach ( wp_unslash( $_POST['alts'] ) as $id => $alt ) { // phpcs:ignore
				$alts[ absint( $id ) ] = sanitize_text_field( (string) $alt );
			}
		}
		return array(
			'product_type' => sanitize_text_field( $g( 'product_type' ) ),
			'seo_title'    => sanitize_text_field( $g( 'seo_title' ) ),
			'title'     => sanitize_text_field( $g( 'title' ) ),
			'excerpt'   => sanitize_text_field( $g( 'excerpt' ) ),
			'intro'     => sanitize_textarea_field( $g( 'intro' ) ),
			'features'  => msb_lines( sanitize_textarea_field( $g( 'features' ) ) ),
			'ideal'     => msb_lines( sanitize_textarea_field( $g( 'ideal' ) ) ),
			'hints'     => sanitize_textarea_field( $g( 'hints' ) ),
			'condition' => sanitize_textarea_field( $g( 'condition' ) ),
			'specs'     => sanitize_textarea_field( $g( 'specs' ) ),
			'meta'      => sanitize_text_field( $g( 'meta' ) ),
			'keyword'   => sanitize_text_field( $g( 'keyword' ) ),
			'alts'      => $alts,
		);
	}

	private static function paragraphs( $text ) {
		$html = '';
		foreach ( preg_split( "/\n\s*\n/", trim( (string) $text ) ) as $p ) {
			$p = trim( $p );
			if ( '' !== $p ) {
				$html .= '<p>' . nl2br( esc_html( $p ), false ) . '</p>';
			}
		}
		return $html;
	}

	private static function bullet_list( array $items, $check ) {
		$html = '<ul>';
		foreach ( $items as $i ) {
			$i     = trim( preg_replace( '/^[\s✓✔•\-\*]+/u', '', $i ) );
			$html .= '<li>' . ( $check ? '✓ ' : '' ) . esc_html( $i ) . '</li>';
		}
		return $html . '</ul>';
	}

	/**
	 * Einheitlicher Aufbau der Beschreibung nach Styleguide.
	 */
	public static function build_content( array $r, $closing ) {
		$type    = isset( $r['product_type'] ) ? trim( $r['product_type'] ) : '';
		$keyword = $type ? $type . ' mieten' : '';
		$intro   = trim( $r['intro'] );
		if ( $keyword && '' !== $intro && 0 !== stripos( $intro, $keyword ) ) {
			$intro = $keyword . ' statt kaufen: ' . $intro;
		}
		$html = self::paragraphs( $intro );
		if ( $r['features'] ) {
			$html .= '<h2>Ausstattung &amp; Zubehör</h2>' . self::bullet_list( $r['features'], true );
		}
		if ( $r['ideal'] ) {
			$html .= '<h2>Ideal für</h2>' . self::bullet_list( $r['ideal'], false );
		}
		if ( '' !== trim( $r['hints'] ) ) {
			$html .= '<h2>Gut zu wissen</h2>' . self::paragraphs( $r['hints'] );
		}
		if ( '' !== trim( $r['condition'] ) ) {
			$html .= '<h2>Zustand</h2>' . self::paragraphs( $r['condition'] );
		}
		if ( $keyword ) {
			$html .= '<h2>' . esc_html( $keyword . ' in ' . self::location() ) . '</h2>';
		}
		$links = self::closing_links();
		if ( '' !== trim( $closing ) || $links ) {
			$html .= '<p>' . esc_html( trim( $closing ) ) . ( $links ? ' ' . $links : '' ) . '</p>';
		}
		return $html;
	}

	/**
	 * Ort für Zwischenüberschrift und SEO-Angaben.
	 */
	public static function location() {
		return (string) apply_filters( 'msb_ai_location', 'Bernau' );
	}

	/**
	 * Interne Links unter dem Abschlusssatz (AGB und Mietartikel).
	 */
	public static function closing_links() {
		$agb  = get_page_by_path( 'agb' );
		$html = '';
		if ( $agb && 'publish' === $agb->post_status ) {
			$html .= 'Alle Mietbedingungen findest du in unseren <a href="' . esc_url( wp_make_link_relative( get_permalink( $agb ) ) ) . '">AGB</a>, weitere';
		} else {
			$html .= 'Weitere';
		}
		$html .= ' Ausrüstung für deinen Ausflug unter <a href="' . esc_url( wp_make_link_relative( home_url( '/' ) ) . '#mietartikel' ) . '">unseren Mietartikeln</a>.';
		return (string) apply_filters( 'msb_ai_closing_links', $html );
	}

	public static function ajax_save() {
		self::check_request();
		$s   = self::settings();
		$ids = self::image_ids();
		$d   = self::input_data();
		$r   = self::result_input();

		$errors = array();
		if ( '' === $r['title'] ) {
			$errors[] = 'Titel';
		}
		if ( ! $d['category'] ) {
			$errors[] = 'Kategorie';
		}
		if ( '' === $d['price_day'] || $d['price_day'] <= 0 ) {
			$errors[] = 'Preis pro Tag';
		}
		if ( '' === $d['deposit'] ) {
			$errors[] = 'Kaution';
		}
		if ( $errors ) {
			wp_send_json_error( array( 'message' => 'Bitte noch ausfüllen: ' . implode( ', ', $errors ) . '.' ) );
		}

		if ( '' !== $r['product_type'] && '' === $r['keyword'] ) {
			$r['keyword'] = $r['product_type'] . ' mieten';
		}
		$model = '' !== $d['brand_model'] ? $d['brand_model'] : trim( preg_replace( '/\s+[–-]\s+.*$/u', '', $r['title'] ) );
		$slug  = sanitize_title( ( '' !== $r['product_type'] ? $r['product_type'] . ' mieten ' : '' ) . $model );
		if ( strlen( $slug ) > 45 ) {
			$slug = substr( $slug, 0, 45 );
			$slug = false !== strrpos( $slug, '-' ) ? substr( $slug, 0, strrpos( $slug, '-' ) ) : $slug;
		}
		if ( $ids && '' !== $r['keyword'] ) {
			$first = isset( $r['alts'][ $ids[0] ] ) ? $r['alts'][ $ids[0] ] : '';
			if ( false === stripos( $first, $r['keyword'] ) ) {
				$r['alts'][ $ids[0] ] = $r['keyword'] . ' – ' . ( '' !== $first ? $first : $model );
			}
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => MSB_Items::POST_TYPE,
				'post_status'  => 'draft',
				'post_name'    => $slug,
				'post_title'   => $r['title'],
				'post_excerpt' => $r['excerpt'],
				'post_content' => self::build_content( $r, $s['closing'] ),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Speichern fehlgeschlagen: ' . $post_id->get_error_message() ) );
		}

		update_post_meta( $post_id, '_msb_price_day', $d['price_day'] );
		update_post_meta( $post_id, '_msb_deposit', $d['deposit'] );
		update_post_meta( $post_id, '_msb_base_fee', $d['base_fee'] );
		update_post_meta( $post_id, '_msb_stock', $d['stock'] );
		update_post_meta( $post_id, '_msb_min_days', $d['min_days'] );
		update_post_meta( $post_id, '_msb_max_days', 0 );
		update_post_meta( $post_id, '_msb_bookable', 'yes' );
		// Ab 1.2.7: Marke = erstes Wort aus „Marke/Modell“ (im Artikel unter „Suchmaschinen“ korrigierbar).
		if ( '' !== $d['brand_model'] ) {
			$brand = strtok( $d['brand_model'], " \t" );
			if ( false !== $brand && '' !== $brand ) {
				update_post_meta( $post_id, '_msb_brand', mb_substr( $brand, 0, 70 ) );
			}
		}
		update_post_meta( $post_id, '_msb_price_tiers', $d['tiers'] );
		update_post_meta( $post_id, '_msb_specs', $r['specs'] );
		update_post_meta( $post_id, '_msb_blocked', '' );
		update_post_meta( $post_id, '_msb_price_rules', array() );
		update_post_meta( $post_id, '_msb_ignore_global_rules', 'global' === $d['discount'] ? 'no' : 'yes' );

		wp_set_object_terms( $post_id, array( $d['category'] ), MSB_Items::TAXONOMY );

		if ( $ids ) {
			set_post_thumbnail( $post_id, $ids[0] );
			update_post_meta( $post_id, '_msb_gallery', implode( ',', array_slice( $ids, 1 ) ) );
			foreach ( $ids as $id ) {
				if ( isset( $r['alts'][ $id ] ) && '' !== $r['alts'][ $id ] ) {
					update_post_meta( $id, '_wp_attachment_image_alt', $r['alts'][ $id ] );
				}
				if ( 0 === (int) get_post_field( 'post_parent', $id ) ) {
					wp_update_post(
						array(
							'ID'          => $id,
							'post_parent' => $post_id,
						)
					);
				}
			}
		}

		// SEO-Daten für Rank Math (werden ignoriert, falls Rank Math nicht aktiv ist).
		if ( '' !== $r['meta'] ) {
			update_post_meta( $post_id, 'rank_math_description', $r['meta'] );
		}
		if ( '' !== $r['keyword'] ) {
			update_post_meta( $post_id, 'rank_math_focus_keyword', $r['keyword'] );
		}
		if ( '' !== $r['seo_title'] ) {
			update_post_meta( $post_id, 'rank_math_title', $r['seo_title'] );
		}

		$text = 'Der Artikel „' . $r['title'] . '“ wurde als Entwurf gespeichert.';
		if ( 'custom' === $d['discount'] ) {
			$text .= ' Bitte jetzt im Artikel unter „Dynamische Preise & Rabatte“ die eigenen Rabatte anlegen.';
		}
		$text .= ' Prüfen Sie Text und Bilder und klicken Sie dann auf „Veröffentlichen“.';

		wp_send_json_success(
			array(
				'id'      => $post_id,
				'edit'    => get_edit_post_link( $post_id, 'raw' ),
				'preview' => get_preview_post_link( $post_id ),
				'message' => $text,
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Vollständigkeitsprüfung
	 * ------------------------------------------------------------------ */

	/**
	 * Fehlende Angaben eines Mietartikels laut Styleguide.
	 */
	public static function missing( $post_id ) {
		$item    = MSB_Items::data( $post_id );
		$missing = array();
		$terms   = wp_get_object_terms( $post_id, MSB_Items::TAXONOMY, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $terms ) || ! $terms ) {
			$missing[] = 'Kategorie';
		}
		if ( $item['price_day'] <= 0 && ! $item['tiers'] ) {
			$missing[] = 'Preis pro Tag';
		}
		if ( $item['deposit'] <= 0 ) {
			$missing[] = 'Kaution';
		}
		if ( '' === trim( (string) get_post_field( 'post_excerpt', $post_id ) ) ) {
			$missing[] = 'Kurzbeschreibung (Textauszug)';
		}
		if ( ! $item['specs'] ) {
			$missing[] = 'Technische Daten';
		}
		if ( ! has_post_thumbnail( $post_id ) ) {
			$missing[] = 'Beitragsbild';
		}
		$images = $item['gallery'];
		if ( count( $images ) < 3 ) {
			$missing[] = 'mindestens 3 Fotos (aktuell ' . count( $images ) . ')';
		}
		$no_alt = 0;
		foreach ( $images as $id ) {
			if ( '' === trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) ) {
				$no_alt++;
			}
		}
		if ( $no_alt ) {
			$missing[] = 'Alt-Text bei ' . $no_alt . ' Foto' . ( 1 === $no_alt ? '' : 's' );
		}
		if ( defined( 'RANK_MATH_VERSION' ) && '' === trim( (string) get_post_meta( $post_id, 'rank_math_description', true ) ) ) {
			$missing[] = 'Meta-Beschreibung (Rank Math)';
		}
		if ( defined( 'RANK_MATH_VERSION' ) && '' === trim( (string) get_post_meta( $post_id, 'rank_math_focus_keyword', true ) ) ) {
			$missing[] = 'Fokus-Keyword (Rank Math)';
		}
		return $missing;
	}

	public static function completeness_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->base || MSB_Items::POST_TYPE !== $screen->post_type ) {
			return;
		}
		global $post;
		if ( ! $post || 'auto-draft' === $post->post_status ) {
			return;
		}
		$missing = self::missing( $post->ID );
		if ( ! $missing ) {
			echo '<div class="notice notice-success"><p><strong>Vollständig:</strong> Der Artikel erfüllt alle Punkte des Styleguides.</p></div>';
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>Vollständigkeit nach Styleguide – es fehlt noch:</strong> ' . esc_html( implode( ', ', $missing ) ) . '. ' . msb_help_link( 'ki-assistent', 'Styleguide' ) . '</p></div>'; // phpcs:ignore
	}
}
