<?php
/**
 * Backend: Hilfe & Dokumentation – zentrale Anlaufstelle für alle Beschreibungen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Docs {

	public static function init() {}

	/**
	 * Inhaltsverzeichnis: Anker => Titel (Gruppen mit Unterpunkten).
	 */
	private static function toc() {
		return array(
			'Erste Schritte'       => array(
				'schnellstart' => 'Schnellstart in 10 Schritten',
				'ablauf'       => 'So läuft eine Buchung ab',
				'status'       => 'Buchungsstatus',
			),
			'Artikel & Preise'     => array(
				'artikel'      => 'Mietartikel anlegen',
				'ki-assistent' => 'Artikel mit KI anlegen',
				'galerie'      => 'Bildergalerie',
				'preise'       => 'Preise, Staffeln, Kaution',
				'preisregeln'  => 'Dynamische Preise & Rabatte',
				'verfuegbarkeit' => 'Bestand & Verfügbarkeit',
			),
			'Zahlungen'            => array(
				'zahlungen'    => 'Überblick Zahlungen',
				'kaution'      => 'Kaution reservieren & freigeben',
				'stripe'       => 'Stripe einrichten',
				'paypal'       => 'PayPal einrichten',
				'zahlungen-backend' => 'Zahlungen im Backend',
			),
			'Kunden'               => array(
				'kunden'       => 'Kundenverwaltung',
				'kundenkonten' => 'Kundenkonten & Gastbuchung',
				'formular'     => 'Buchungsformular',
			),
			'Einstellungen'        => array(
				'einstellungen-allgemein' => 'Allgemein',
				'einstellungen-regeln'    => 'Buchungsregeln',
				'emails'                  => 'E-Mails & Platzhalter',
			),
			'Darstellung'          => array(
				'shortcodes'   => 'Alle Shortcodes',
				'seiten'       => 'Seitenvorschläge',
			),
			'Backend & Technik'    => array(
				'backend'      => 'Backend-Seiten',
				'technik'      => 'Automatik, Cron, Datenschutz',
				'probleme'     => 'Häufige Fragen & Probleme',
				'changelog'    => 'Versionshinweise',
			),
		);
	}

	private static function section( $id, $title, $html ) {
		echo '<section class="msb-doc" id="' . esc_attr( $id ) . '"><h2>' . esc_html( $title ) . ' <a class="msb-doc__anchor" href="#' . esc_attr( $id ) . '" aria-label="Link zu diesem Abschnitt">#</a></h2>' . $html . '<p class="msb-doc__top"><a href="#msb-docs-top">↑ nach oben</a></p></section>'; // phpcs:ignore
	}

	private static function link( $page, $label ) {
		return '<a href="' . esc_url( admin_url( $page ) ) . '">' . esc_html( $label ) . '</a>';
	}

	private static function table( array $rows, array $head = array( 'Feld', 'Bedeutung' ) ) {
		$h = '<table class="widefat striped msb-doc__table"><thead><tr>';
		foreach ( $head as $c ) {
			$h .= '<th>' . esc_html( $c ) . '</th>';
		}
		$h .= '</tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$h .= '<tr>';
			foreach ( $r as $i => $c ) {
				$h .= '<td>' . ( 0 === $i ? '<strong>' . $c . '</strong>' : $c ) . '</td>';
			}
			$h .= '</tr>';
		}
		return $h . '</tbody></table>';
	}

	public static function page() {
		$toc = self::toc();
		?>
		<div class="wrap msb-admin msb-docs" id="msb-docs-top">
			<h1>Hilfe &amp; Dokumentation <span class="msb-docs__version">Version <?php echo esc_html( MSB_VERSION ); ?></span></h1>
			<p class="msb-docs__lead">Hier finden Sie alle Funktionen, Einstellungen und Shortcodes des Vermietungs-Plugins an einem Ort. Überall im Plugin führen <span class="dashicons dashicons-editor-help"></span>-Links direkt zum passenden Abschnitt.</p>
			<input type="search" id="msb-doc-search" class="regular-text" placeholder="Dokumentation durchsuchen …">

			<div class="msb-docs__layout">
				<nav class="msb-docs__toc" aria-label="Inhalt">
					<?php foreach ( $toc as $group => $items ) : ?>
						<h3><?php echo esc_html( $group ); ?></h3>
						<ul>
							<?php foreach ( $items as $id => $title ) : ?>
								<li><a href="#<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $title ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endforeach; ?>
				</nav>
				<div class="msb-docs__content">
					<?php self::content(); ?>
				</div>
			</div>
		</div>
		<?php
	}

	private static function content() {
		$cur = esc_html( msb_setting( 'currency' ) );

		/* ---------------- Erste Schritte ---------------- */
		self::section(
			'schnellstart',
			'Schnellstart in 10 Schritten',
			'<ol class="msb-doc__steps">
				<li><strong>Einstellungen → Allgemein:</strong> Preishinweis (z. B. „inkl. MwSt.“ oder Kleinunternehmer-Hinweis), Akzentfarbe und Danke-Text festlegen. ' . self::link( 'admin.php?page=msb-settings', 'Öffnen' ) . '</li>
				<li><strong>Einstellungen → Buchungsregeln:</strong> Berechnung (Kalendertage oder Nächte), Puffertage, Vorlaufzeit und Ruhetage. ' . self::link( 'admin.php?page=msb-settings&tab=rules', 'Öffnen' ) . '</li>
				<li><strong>Kategorien</strong> anlegen, z. B. „Kraxen“ und „Fahrradanhänger“. ' . self::link( 'edit-tags.php?taxonomy=msb_category&post_type=msb_item', 'Öffnen' ) . '</li>
				<li><strong>Mietartikel</strong> anlegen: Titel, Beschreibung, Beitragsbild, Galerie, Preise, Kaution, Bestand. ' . self::link( 'post-new.php?post_type=msb_item', 'Neuer Artikel' ) . '</li>
				<li>Optional <strong>Preisregeln</strong> wie einen Wochenendrabatt hinzufügen – im Artikel oder global unter Einstellungen → Preise &amp; Rabatte.</li>
				<li><strong>Buchungsformular</strong> anpassen; die Links zu AGB und Datenschutz auf Ihre Seiten ändern. ' . self::link( 'admin.php?page=msb-form', 'Öffnen' ) . '</li>
				<li><strong>Zahlungen</strong> einrichten: Anzahlung, Kaution, Stripe und/oder PayPal – zuerst im Test-/Sandbox-Modus. ' . self::link( 'admin.php?page=msb-settings&tab=payments', 'Öffnen' ) . '</li>
				<li><strong>E-Mails</strong> prüfen und eine Test-Mail senden. ' . self::link( 'admin.php?page=msb-settings&tab=emails', 'Öffnen' ) . '</li>
				<li><strong>Seiten</strong> mit Shortcodes befüllen (siehe <a href="#seiten">Seitenvorschläge</a>). Die Seiten „Buchung abschließen“ und „Mein Konto“ wurden automatisch angelegt.</li>
				<li>Eine <strong>Testbuchung</strong> durchspielen – im Testmodus mit der Stripe-Testkarte 4242 4242 4242 4242 bzw. einem PayPal-Sandbox-Konto.</li>
			</ol>'
		);

		self::section(
			'ablauf',
			'So läuft eine Buchung ab',
			'<ol>
				<li>Der Kunde öffnet einen Artikel oder sucht über <code>[msb_search]</code> nach einem Zeitraum.</li>
				<li>Im Kalender klickt er auf Abhol- und Rückgabetag, wählt die Anzahl und sieht sofort den Preis inklusive aller Rabatte.</li>
				<li>„Zur Buchung hinzufügen“ legt die Auswahl in den Warenkorb – weitere Artikel sind möglich.</li>
				<li>Auf der Buchungsseite füllt er das Formular aus (eingeloggte Kunden: vorausgefüllt), legt optional ein Kundenkonto an und wählt die Zahlungsart.</li>
				<li><strong>Bezahlung bei Abholung:</strong> Die Buchung wird sofort gespeichert, Kunde und Sie erhalten eine E-Mail.<br>
					<strong>Online-Zahlung:</strong> Die Artikel werden reserviert (Status „Zahlung ausstehend“) und der Kunde zu Stripe bzw. PayPal geleitet. Nach erfolgreicher Zahlung kommt er auf die Danke-Seite zurück und die E-Mails werden verschickt. Bricht er ab, wird die Buchung storniert und seine Auswahl liegt wieder im Warenkorb.</li>
				<li>Kurz vor der Abholung wird – falls eingestellt – die Kaution reserviert.</li>
				<li>Bei Abholung setzen Sie den Status auf „Abgeholt“, bei Rückgabe auf „Zurückgegeben“ – die Kaution wird dann automatisch freigegeben.</li>
			</ol>'
		);

		self::section(
			'status',
			'Buchungsstatus',
			self::table(
				array(
					array( 'Zahlung ausstehend', 'Kunde ist gerade bei Stripe/PayPal. Artikel sind reserviert. Ohne Zahlung wird die Buchung nach der Zahlungsfrist automatisch storniert.' ),
					array( 'Angefragt', 'Buchung ist eingegangen, Sie haben sie noch nicht bestätigt. Inventar ist bereits blockiert.' ),
					array( 'Bestätigt', 'Verbindlich. Der Kunde erhält beim Wechsel auf diesen Status eine Bestätigungs-E-Mail.' ),
					array( 'Abgeholt', 'Artikel ist beim Kunden.' ),
					array( 'Zurückgegeben', 'Artikel ist zurück. Inventar ist wieder frei, eine reservierte Kaution wird automatisch freigegeben (abschaltbar).' ),
					array( 'Storniert', 'Inventar ist frei. Reservierte Kautionen werden freigegeben. Kunden stornieren bis Mietbeginn 0:00 Uhr selbst in „Mein Konto“: bis 7 Tage vorher kostenlos, ab 48 Stunden vorher 25 %, danach 50 % der Tagesmiete (ohne Grundgebühr); der Rest wird automatisch über Stripe bzw. PayPal erstattet und Sie erhalten eine Info-Mail. Im Backend stornieren Sie über den Kasten „Stornieren“ in der Buchung: Er schlägt dieselbe Gebühr vor, sie ist änderbar. Schnell- und Sammelaktionen stornieren ohne Gebühr und ohne Erstattung; erstatten Sie dann über „Erstatten“.' ),
				),
				array( 'Status', 'Bedeutung' )
			) .
			'<p><strong>Erlaubte Wechsel:</strong> Zahlung ausstehend → Angefragt, Bestätigt oder Storniert · Angefragt → Bestätigt oder Storniert · Bestätigt → Abgeholt oder Storniert · Abgeholt → Zurückgegeben. Die Statusauswahl zeigt nur diese Ziele.</p>' .
			'<p>Zurück aus „Zurückgegeben“ (nach „Abgeholt“) oder „Storniert“ (nach „Angefragt“ oder „Bestätigt“) dürfen nur Administratoren. Dabei wird geprüft, ob die Artikel noch frei sind.</p>' .
			'<p><strong>Buchungsnummer:</strong> Präfix (Einstellungen) + Jahr + laufende Nummer, z. B. <code>MB-2027-0001</code>. Die Nummer beginnt jedes Jahr wieder bei 0001.</p>'
		);

		/* ---------------- Artikel ---------------- */
		self::section(
			'artikel',
			'Mietartikel anlegen',
			'<p>Unter <em>Vermietung → Mietartikel → Neuer Artikel</em>. Neben Titel, Beschreibung, Kurzbeschreibung (Textauszug) und Beitragsbild gibt es folgende Felder:</p>' .
			self::table(
				array(
					array( 'Preis pro Tag', 'Grundpreis je Miettag in ' . $cur . '.' ),
					array( 'Grundgebühr pro Buchung', 'Einmalig je Stück und Buchung, z. B. Reinigung. Wird nicht rabattiert.' ),
					array( 'Kaution pro Stück', 'Sicherheit, getrennt vom Mietpreis. Je nach Einstellung bar bei Abholung oder online reserviert.' ),
					array( 'Bestand', 'Wie viele gleiche Exemplare Sie haben. Bei 3 Kraxen können 3 Kunden gleichzeitig buchen.' ),
					array( 'Mindest- / Maximale Mietdauer', 'In Tagen. 0 bei Maximum = unbegrenzt.' ),
					array( 'Online buchbar', 'Abwählen, um den Artikel nur zu zeigen (z. B. in Reparatur).' ),
					array( 'Staffelpreise', 'Günstigerer Tagespreis ab einer Mietdauer, eine Zeile je Staffel: <code>3=7,00</code>, <code>7=5,50</code>.' ),
					array( 'Technische Daten', 'Eine Zeile je Eigenschaft: <code>Gewicht: 3,2 kg</code>. Erscheint als Tabelle.' ),
					array( 'Gesperrte Zeiträume', 'Eine Zeile je Zeitraum: <code>24.12.2026 - 26.12.2026 Urlaub</code> oder ein einzelner Tag.' ),
					array( 'Dynamische Preise &amp; Rabatte', 'Preisregeln nur für diesen Artikel, siehe <a href="#preisregeln">Dynamische Preise</a>.' ),
					array( 'Bildergalerie', 'Weitere Bilder, siehe <a href="#galerie">Bildergalerie</a>.' ),
					array( 'Reihenfolge', 'Feld „Reihenfolge“ (Seitenattribute) steuert die Sortierung in <code>[msb_items]</code>.' ),
				)
			) .
			'<p>Die Artikelseite (<code>/mieten/artikelname/</code>) zeigt automatisch Galerie, Preise, technische Daten und den Buchungskalender (abschaltbar unter Einstellungen → Allgemein).</p>'
		);

		self::section(
			'galerie',
			'Bildergalerie',
			'<p>Im Artikel rechts im Kasten <strong>„Bildergalerie“</strong> auf „Bilder hinzufügen“ klicken und mehrere Bilder aus der Mediathek wählen. Die Reihenfolge ändern Sie per Ziehen, mit × entfernen Sie ein Bild. Das <strong>Beitragsbild</strong> ist immer das erste Bild.</p>
			<p>Die Galerie erscheint:</p>
			<ul>
				<li>auf der Artikelseite und in <code>[msb_item]</code> als großes Bild mit Vorschaubildern und Vergrößerung per Klick,</li>
				<li>in Artikellisten <code>[msb_items]</code> und in den Suchergebnissen von <code>[msb_search]</code> als kompaktes Bild mit Pfeilen – zusammen mit Beschreibung und Preis (in der Suche: Preis für den gesuchten Zeitraum),</li>
				<li>überall per <code>[msb_gallery id="…"]</code>.</li>
			</ul>
			<p>In Listen abschalten mit <code>show_gallery="no"</code>. Tipp: Bilder im Querformat 4:3 sehen in den Kacheln am besten aus.</p>'
		);

		self::section(
			'preise',
			'Preise, Staffeln, Kaution',
			'<p>Berechnung je Artikel und Buchung:</p>
			<ol>
				<li><strong>Tagespreis</strong> = Preis pro Tag bzw. passender Staffelpreis.</li>
				<li><strong>Tagesregeln</strong> (Wochentage, Saison) verändern einzelne Miettage.</li>
				<li><strong>Buchungsregeln</strong> (Wochenende, Mietdauer, Frühbucher, Last Minute) verändern den Mietpreis der Position.</li>
				<li><strong>+ Grundgebühr</strong>, alles <strong>× Anzahl</strong>.</li>
			</ol>
			<p>Die Kaution wird nie mit dem Mietpreis verrechnet, sondern immer separat ausgewiesen. Der Kunde sieht im Kalender, im Warenkorb und in allen E-Mails eine Aufstellung inklusive Rabatten.</p>'
		);

		self::section(
			'preisregeln',
			'Dynamische Preise & Rabatte',
			'<p>Preisregeln legen Sie <strong>pro Artikel</strong> (Kasten „Dynamische Preise &amp; Rabatte“) oder <strong>für alle Artikel</strong> (' . self::link( 'admin.php?page=msb-settings&tab=prices', 'Einstellungen → Preise & Rabatte' ) . ') an. Jede Regel hat einen Haken „aktiv“, einen Typ, eine Bedingung, eine Wirkung und optional eine Bezeichnung für Kunden (sonst automatisch, z. B. „Wochenendrabatt 20 %“).</p>' .
			self::table(
				array(
					array( 'Wochenende', 'Abholung Freitag oder Samstag, Rückgabe Samstag, Sonntag oder Montag, insgesamt höchstens X Kalendertage (Standard 4 = Fr–Mo).', 'Rabatt %, Aufschlag %, Rabatt in ' . $cur . ', Pauschalpreis' ),
					array( 'Bestimmte Wochentage', 'Gilt für jeden Miettag, der auf einen der gewählten Wochentage fällt.', 'Rabatt %, Aufschlag %, fester Tagespreis' ),
					array( 'Zeitraum / Saison', 'Gilt für jeden Miettag im Zeitraum, z. B. Sommerferien.', 'Rabatt %, Aufschlag %, fester Tagespreis' ),
					array( 'Mietdauer', 'Mietdauer von–bis Tagen (bis leer = unbegrenzt). Ergänzt die Staffelpreise.', 'Rabatt %, Aufschlag %, Rabatt in ' . $cur . ', Pauschalpreis' ),
					array( 'Frühbucher', 'Buchung mindestens X Tage vor der Abholung.', 'wie oben' ),
					array( 'Kurzfristig (Last Minute)', 'Abholung innerhalb von X Tagen nach der Buchung.', 'wie oben' ),
				),
				array( 'Regel', 'Bedingung', 'mögliche Wirkung' )
			) .
			'<h3>Beispiele</h3>' .
			self::table(
				array(
					array( 'Wochenendrabatt 20 %', 'Typ Wochenende · max. 4 Kalendertage · Rabatt in % · 20' ),
					array( 'Wochenendpauschale 25 ' . $cur, 'Typ Wochenende · Pauschalpreis · 25 (Preis je Stück für das ganze Wochenende)' ),
					array( 'Samstag teurer', 'Typ Bestimmte Wochentage · Sa · Aufschlag in % · 10' ),
					array( 'Sommersaison', 'Typ Zeitraum · 01.07.–31.08. · Aufschlag in % · 15' ),
					array( 'Wochenmiete günstiger', 'Typ Mietdauer · ab 7 Tage · Rabatt in % · 15' ),
					array( 'Frühbucher', 'Typ Frühbucher · mind. 30 Tage · Rabatt in % · 5' ),
				),
				array( 'Ziel', 'Einstellung' )
			) .
			'<h3>Wenn mehrere Regeln passen</h3>
			<ul>
				<li>Tagesregeln (Wochentage, Saison) werden alle angewendet.</li>
				<li>Von den Buchungsrabatten zählt standardmäßig nur der <strong>höchste</strong>. Mit „Rabatte addieren“ (Einstellungen → Preise &amp; Rabatte) werden alle abgezogen.</li>
				<li>Ein <strong>Pauschalpreis</strong> ersetzt alle anderen Buchungsrabatte; passen mehrere Pauschalen, gilt die günstigste.</li>
				<li>Aufschläge gelten immer. Der Preis wird nie negativ.</li>
				<li>Artikelregeln und globale Regeln werden gemeinsam angewendet. Im Artikel können Sie die globalen Regeln mit „Globale Preisregeln nicht anwenden“ abschalten.</li>
			</ul>
			<p>Aktive Regeln erscheinen automatisch in der Preistabelle (<code>[msb_price show="table"]</code>) – Kunden sehen also vorab, welche Rabatte es gibt.</p>'
		);

		self::section(
			'verfuegbarkeit',
			'Bestand & Verfügbarkeit',
			'<p>Ein Tag ist für eine Anzahl buchbar, wenn <em>Bestand − belegte Stück</em> ≥ gewünschte Anzahl. Belegt sind Buchungen mit Status „Zahlung ausstehend“, „Angefragt“, „Bestätigt“ und „Abgeholt“ – inklusive Puffertagen vor und nach jeder Buchung. Gesperrte Zeiträume, Vorlaufzeit und „buchbar im Voraus“ werden berücksichtigt. Ruhetage verhindern nur Abholung und Rückgabe, nicht das Mieten über diesen Tag.</p>
			<p>Die Übersicht ' . self::link( 'admin.php?page=msb-inventory', 'Inventar' ) . ' zeigt die freien Stück je Artikel und Tag. Doppelbuchungen bei gleichzeitigen Bestellungen werden durch eine Datenbank-Sperre verhindert.</p>'
		);

		self::section(
			'ki-assistent',
			'Artikel mit KI anlegen',
			'<p>Unter ' . self::link( 'admin.php?page=msb-ai', 'Vermietung → Neuer Artikel (KI)' ) . ' entsteht ein neuer Mietartikel in wenigen Minuten. Die KI schreibt Titel, Beschreibung, technische Daten, Alt-Texte und SEO-Angaben nach dem Styleguide – Sie prüfen alles, bevor der Artikel veröffentlicht wird.</p>
			<ol class="msb-doc__steps">
				<li><strong>Einmalig einrichten:</strong> Unter ' . self::link( 'admin.php?page=msb-settings&tab=ai', 'Einstellungen → KI-Assistent' ) . ' den API-Schlüssel der Claude API eintragen (Claude Console → API Keys), Modell wählen, Abschlusssatz und Styleguide prüfen.</li>
				<li><strong>Fotos:</strong> bis zu 6 Fotos auswählen oder hochladen. Das erste Foto wird das Beitragsbild, die übrigen bilden die Galerie.</li>
				<li><strong>Eckdaten:</strong> Kategorie, Preis pro Tag und Kaution (Pflicht), Bestand, Mindestmietdauer, Grundgebühr, Staffelpreise, Rabatte (global, keine oder eigene), Lieferumfang, Zustand und Hinweise.</li>
				<li><strong>Text mit KI erstellen:</strong> dauert meist 20–60 Sekunden. Angaben, die die KI nicht sicher aus Fotos oder Ihren Angaben ableiten konnte, stehen gelb unter „Bitte prüfen“.</li>
				<li><strong>Prüfen &amp; anpassen:</strong> alle Felder sind editierbar; Alt-Texte stehen unter den Fotos.</li>
				<li><strong>Als Entwurf speichern</strong> und den Artikel nach einer letzten Kontrolle veröffentlichen.</li>
			</ol>
			<h3>Einheitlicher Aufbau</h3>
			<p>Die Beschreibung setzt das Plugin immer gleich zusammen: Einleitung (beginnt mit „[Produktart] mieten statt kaufen: …“) · „Ausstattung &amp; Zubehör“ (✓-Liste) · „Ideal für“ · optional „Gut zu wissen“ · „Zustand“ · Zwischenüberschrift „[Produktart] mieten in Bernau“ mit Abschlusssatz und Links zu AGB und Mietartikeln. Preise, Kaution und Mietbedingungen stehen bewusst nicht im Text – sie kommen aus den Feldern bzw. den AGB.</p>
			<h3>SEO (Rank Math)</h3>
			<p>Aus der Produktart (Singular, z. B. „Kinderkraxe“) entstehen automatisch: Fokus-Keyword „Kinderkraxe mieten“, kurze URL wie <code>/mieten/kinderkraxe-mieten-deuter-kid-comfort/</code> und der Alt-Text des Beitragsbilds. Den SEO-Titel („Kinderkraxe mieten in Bernau: Deuter Kid Comfort II bis 22 kg“) und die Meta-Beschreibung schlägt die KI vor. Hinweise von Rank Math zu 600 Wörtern, externen Links, Inhaltsverzeichnis und „Power Words“ sind für kurze Mietartikelseiten nicht sinnvoll und werden bewusst nicht umgesetzt.</p>
			<h3>Vollständigkeitsprüfung</h3>
			<p>Auf der Bearbeitungsseite jedes Mietartikels zeigt ein Hinweis, was laut Styleguide noch fehlt: Kategorie, Preis, Kaution, Kurzbeschreibung, technische Daten, Beitragsbild, mindestens 3 Fotos, Alt-Texte und Meta-Beschreibung (Rank Math).</p>
			<h3>Datenschutz &amp; Kosten</h3>
			<p>An die KI (Anthropic, Claude API) werden nur die ausgewählten Fotos in mittlerer Größe, Ihre Eckdaten und Beispieltexte bestehender Artikel übertragen – keine Kunden- oder Buchungsdaten. Bitte Fotos ohne Personen und Kfz-Kennzeichen verwenden. Die Kosten werden nach Verbrauch über Ihr API-Konto abgerechnet (je Artikel wenige Cent).</p>'
		);

		/* ---------------- Zahlungen ---------------- */
		self::section(
			'zahlungen',
			'Überblick Zahlungen',
			'<p>Einstellungen unter ' . self::link( 'admin.php?page=msb-settings&tab=payments', 'Einstellungen → Zahlungen & Kaution' ) . '.</p>' .
			self::table(
				array(
					array( 'Bei der Buchung zahlen', '<em>nichts</em> (alles bei Abholung), <em>Anzahlung in %</em>, <em>feste Anzahlung</em> oder <em>kompletten Mietpreis</em>. Der Rest wird bei Abholung fällig oder per Zahlungslink.' ),
					array( 'Kaution', '<em>bei Abholung vor Ort</em> oder <em>über Stripe/PayPal reservieren</em>. Siehe <a href="#kaution">Kaution</a>.' ),
					array( 'Kaution reservieren … Tage vor Abholung', 'Wann die Reservierung ausgelöst wird (Standard 2).' ),
					array( 'Freigabe', 'Kaution automatisch freigeben, wenn die Buchung auf „Zurückgegeben“ gesetzt wird.' ),
					array( 'Zahlungsfrist', 'Minuten, die Artikel für eine begonnene Online-Zahlung reserviert bleiben (mind. 15, Standard 60).' ),
					array( 'Währungscode', 'ISO-Code für die Zahlungsanbieter (EUR).' ),
					array( 'Bezahlung bei Abholung', 'Erlaubt Buchungen ohne Online-Zahlung. Bezeichnung und Hinweistext (z. B. Bankverbindung) frei wählbar. Sind weder Stripe noch PayPal aktiv, ist diese Option immer verfügbar.' ),
				)
			) .
			'<p><strong>Sicherheit:</strong> Kunden geben Kartendaten nur auf den Seiten von Stripe bzw. PayPal ein. Ihre Webseite speichert keine Kartendaten, nur Referenznummern. Die geheimen Schlüssel liegen in der WordPress-Datenbank – geben Sie Administrator-Zugänge nur an vertrauenswürdige Personen.</p>'
		);

		self::section(
			'kaution',
			'Kaution reservieren & freigeben',
			'<p>Bei „über Stripe/PayPal reservieren“ wird die Kaution auf dem Zahlungsmittel des Kunden <strong>nur reserviert (autorisiert)</strong>. Der Betrag ist für den Kunden gesperrt, wird aber nicht abgebucht. Nach der Rückgabe geben Sie ihn frei – oder behalten bei einem Schaden einen Teil oder alles ein. Die Kaution läuft immer getrennt von der Mietgebühr.</p>
			<h3>Ablauf mit Stripe</h3>
			<ol>
				<li>Bei der Buchung wird die Karte für spätere Belastungen gespeichert (bei Anzahlung zusammen mit der Anzahlung, sonst als reine Kartenhinterlegung).</li>
				<li><em>X Tage vor Abholung</em> reserviert das Plugin die Kaution automatisch auf dieser Karte.</li>
				<li>Verlangt die Bank eine erneute Bestätigung (3-D Secure) oder ist keine Karte gespeichert, erhält der Kunde automatisch eine E-Mail mit einem Link, um die Kaution selbst zu hinterlegen.</li>
			</ol>
			<h3>Ablauf mit PayPal</h3>
			<ol>
				<li>Liegt die Rückgabe innerhalb von 29 Tagen, wird die Kaution gleich bei der Buchung zusammen mit der Anzahlung autorisiert – der Kunde bestätigt nur einmal.</li>
				<li>Liegt die Buchung weiter in der Zukunft, wird nur die Anzahlung gezahlt. X Tage vor Abholung erhält der Kunde einen Link, um die Kaution zu hinterlegen.</li>
			</ol>
			<h3>Wichtige Grenzen</h3>
			<ul>
				<li><strong>Stripe:</strong> Kartenreservierungen verfallen in der Regel nach <strong>7 Tagen</strong>. Bei längeren Mieten ist die Kaution ggf. vor der Rückgabe nicht mehr gesperrt. Das Backend zeigt „gültig bis“. Für lange Mieten: Kaution bar nehmen oder kurz vor Ablauf freigeben und per Link neu anfordern.</li>
				<li><strong>PayPal:</strong> Autorisierungen gelten 29 Tage; garantiert ist der Einzug nur in den ersten 3 Tagen, danach in aller Regel weiterhin möglich.</li>
				<li>Nicht jede Zahlungsart unterstützt Reservierungen (z. B. Sofortüberweisung). Stripe zeigt im Checkout nur passende Zahlungsarten an.</li>
			</ul>
			<h3>Freigeben oder einbehalten</h3>
			<p>In der Buchung im Kasten <a href="#zahlungen-backend">Zahlungen &amp; Kaution</a>: „Freigeben“ (Kunde erhält auf Wunsch eine E-Mail) oder Betrag eingeben und „Einbehalten“ – der Rest wird automatisch freigegeben. Mit aktivierter Freigabe-Automatik genügt es, die Buchung auf „Zurückgegeben“ zu setzen. Bei Storno wird eine reservierte Kaution immer freigegeben.</p>'
		);

		self::section(
			'stripe',
			'Stripe einrichten',
			'<ol>
				<li>Konto bei <a href="https://dashboard.stripe.com/register" target="_blank" rel="noopener">stripe.com</a> anlegen.</li>
				<li>Im Stripe-Dashboard unter <em>Entwickler → API-Schlüssel</em> den <strong>geheimen Schlüssel</strong> kopieren (Test: <code>sk_test_…</code>, später Live: <code>sk_live_…</code>). Tipp: Einen „eingeschränkten Schlüssel“ mit Schreibrechten für Checkout Sessions, Customers, PaymentIntents und Refunds anlegen.</li>
				<li>In ' . self::link( 'admin.php?page=msb-settings&tab=payments', 'Zahlungen & Kaution' ) . ' Stripe aktivieren, Modus „Test“ wählen und den Schlüssel eintragen.</li>
				<li><strong>Webhook</strong> (empfohlen): <em>Entwickler → Webhooks → Endpunkt hinzufügen</em>, die im Plugin angezeigte Adresse eintragen (<code>' . esc_html( MSB_Payments::webhook_url() ) . '</code>), Ereignis <code>checkout.session.completed</code> wählen und das Signatur-Geheimnis (<code>whsec_…</code>) im Plugin speichern.</li>
				<li>Unter <em>Einstellungen → Zahlungsmethoden</em> die gewünschten Methoden (Karte, Apple Pay, Google Pay, SEPA-Lastschrift …) aktivieren.</li>
				<li>Testbuchung mit Karte <code>4242 4242 4242 4242</code>, beliebigem Ablaufdatum in der Zukunft und beliebiger Prüfziffer. Testkarte mit 3-D Secure: <code>4000 0027 6000 3184</code>.</li>
				<li>Wenn alles klappt: Live-Schlüssel eintragen, Modus „Live“, Webhook auch im Live-Modus anlegen.</li>
			</ol>'
		);

		self::section(
			'paypal',
			'PayPal einrichten',
			'<ol>
				<li>Ein PayPal-<strong>Geschäftskonto</strong> wird benötigt.</li>
				<li>Auf <a href="https://developer.paypal.com/dashboard/applications" target="_blank" rel="noopener">developer.paypal.com</a> unter <em>Apps &amp; Credentials</em> eine App anlegen (zuerst unter „Sandbox“, später „Live“).</li>
				<li><strong>Client-ID</strong> und <strong>Secret</strong> in ' . self::link( 'admin.php?page=msb-settings&tab=payments', 'Zahlungen & Kaution' ) . ' eintragen, PayPal aktivieren, Modus „Sandbox“.</li>
				<li>Testbuchung mit einem Sandbox-Käuferkonto (wird im Developer-Dashboard unter „Sandbox accounts“ angezeigt).</li>
				<li>Danach Live-Zugangsdaten eintragen und Modus „Live“ wählen.</li>
			</ol>
			<p>Ein Webhook ist für PayPal nicht nötig: Schließt ein Kunde das Fenster nach der Zahlung, prüft das Plugin stündlich offene PayPal-Bestellungen und schließt sie ab.</p>'
		);

		self::section(
			'zahlungen-backend',
			'Zahlungen im Backend',
			'<p>In jeder Buchung zeigt der Kasten <strong>„Zahlungen &amp; Kaution“</strong> Mietpreis, bezahlten und offenen Betrag, den Kautionsstatus und alle Einzelzahlungen. Mögliche Aktionen:</p>' .
			self::table(
				array(
					array( 'Freigeben', 'Reservierte Kaution freigeben (optional mit E-Mail an den Kunden).' ),
					array( 'Einbehalten', 'Betrag der Kaution einziehen, z. B. bei Schaden; mit Grund. Der Rest wird freigegeben.' ),
					array( 'Erstatten', 'Bezahlte Anzahlung/Miete ganz oder teilweise zurückzahlen (bei manuell erfassten Zahlungen nur vermerken).' ),
					array( 'Zahlungslink senden', 'E-Mail mit Link für Anzahlung, Restbetrag oder Kaution. Den Link können Sie auch kopieren und z. B. per Messenger senden.' ),
					array( 'Kaution jetzt reservieren', 'Reservierung sofort auslösen statt X Tage vor Abholung.' ),
					array( 'Zahlung vor Ort erfassen', 'Barzahlung, EC-Zahlung oder bar erhaltene Kaution vermerken.' ),
				),
				array( 'Aktion', 'Wirkung' )
			) .
			'<p>In der Buchungsliste zeigt die Spalte „Zahlung / Kaution“ den Stand auf einen Blick.</p>'
		);

		/* ---------------- Kunden ---------------- */
		self::section(
			'kunden',
			'Kundenverwaltung',
			'<p>' . self::link( 'admin.php?page=msb-customers', 'Vermietung → Kunden' ) . ' listet alle Kunden. Bei jeder Buchung wird der Kunde automatisch angelegt bzw. aktualisiert (Gäste anhand der E-Mail-Adresse, Kontoinhaber anhand ihres Kontos).</p>
			<ul>
				<li><strong>Stammdaten:</strong> alle Formularfelder mit Haken „Kundenstamm“ im ' . self::link( 'admin.php?page=msb-form', 'Buchungsformular' ) . ', plus eine interne Notiz.</li>
				<li><strong>Buchungen:</strong> alle Buchungen des Kunden mit Status.</li>
				<li><strong>Neue Buchung für diesen Kunden:</strong> öffnet die manuelle Buchung mit vorausgefüllten Daten. Auch in „Neue Buchung“ kann ein bestehender Kunde gewählt werden.</li>
				<li><strong>Kundenkonto anlegen &amp; Einladung senden:</strong> macht aus einem Gast einen Kontoinhaber; er erhält einen Link zum Festlegen seines Passworts.</li>
				<li><strong>Filter</strong> „Mit Kundenkonto“ / „Gäste“, Suche und <strong>CSV-Export</strong>.</li>
				<li><strong>Löschen</strong> (z. B. bei DSGVO-Anfragen): Buchungen bleiben für Ihre Buchhaltung erhalten, verlieren aber die Verknüpfung.</li>
			</ul>'
		);

		self::section(
			'kundenkonten',
			'Kundenkonten & Gastbuchung',
			'<p>Kundenkonten sind <strong>optional</strong>. Standardmäßig können Kunden als Gast buchen und alle Daten im Formular eingeben.</p>' .
			self::table(
				array(
					array( 'Kunden dürfen ein Kundenkonto anlegen', 'Zeigt im Checkout „Kundenkonto anlegen“ (mit Passwort) und auf „Mein Konto“ eine Registrierung.' ),
					array( 'Kundenkonto ist Pflicht', 'Nur aktivieren, wenn Sie keine Gastbuchungen wollen.' ),
					array( 'Seite „Mein Konto“', 'Seite mit <code>[msb_account]</code> (automatisch angelegt).' ),
					array( 'Admin-Leiste ausblenden', 'Mietkunden sehen die WordPress-Leiste nicht und werden vom Backend ferngehalten.' ),
				)
			) .
			'<h3>Was Kunden im Konto sehen</h3>
			<ul>
				<li><strong>Meine Buchungen:</strong> alle Buchungen mit Artikeln, Zeitraum, Status, Bezahlt-Betrag und Kautionsstatus; offene Beträge können direkt online bezahlt werden.</li>
				<li><strong>Meine Daten:</strong> Stammdaten und E-Mail ändern, Passwort ändern.</li>
				<li>Im Checkout sind die Daten vorausgefüllt.</li>
			</ul>
			<h3>Sicherheit</h3>
			<p>Neue Konten erhalten eine E-Mail zur Bestätigung der Adresse. Erst nach der Bestätigung werden frühere <em>Gastbuchungen</em> mit derselben E-Mail dem Konto zugeordnet – so kann niemand fremde Buchungen sehen, indem er sich mit einer fremden Adresse registriert. Kunden erhalten die Rolle „Mietkunde“ ohne Backend-Rechte.</p>'
		);

		self::section(
			'formular',
			'Buchungsformular',
			'<p>' . self::link( 'admin.php?page=msb-form', 'Vermietung → Buchungsformular' ) . ': Felder per Griff sortieren, mit „+ Feld hinzufügen“ ergänzen, mit dem Papierkorb entfernen.</p>' .
			self::table(
				array(
					array( 'Beschriftung', 'Text über dem Feld. HTML-Links erlaubt (für AGB/Datenschutz).' ),
					array( 'Typ', 'Textzeile, E-Mail, Telefon, Zahl, Datum, mehrzeiliger Text, Auswahlliste, Optionsfelder, Checkbox, Zwischenüberschrift, Hinweistext.' ),
					array( 'Schlüssel', 'Interner Name, z. B. für den Platzhalter <code>{field:street}</code>. Leer lassen = automatisch. <code>first_name</code>/<code>last_name</code> (oder <code>name</code>) bilden den Kundennamen.' ),
					array( 'Pflicht', 'Muss ausgefüllt bzw. angehakt werden.' ),
					array( 'Kundenstamm', 'Wert wird beim Kunden gespeichert, im Konto unter „Meine Daten“ angezeigt und beim nächsten Mal vorausgefüllt. Für buchungsbezogene Felder (Anmerkungen, Abholzeit) abwählen.' ),
					array( 'Breite', 'ganze oder halbe Zeile.' ),
					array( 'Auswahlmöglichkeiten', 'Bei Auswahlliste/Optionsfeldern: eine Option pro Zeile.' ),
				)
			) .
			'<p>Das <strong>erste E-Mail-Feld</strong> ist die Kundenadresse für alle E-Mails und das Kundenkonto. „Standardfelder wiederherstellen“ setzt das Formular zurück.</p>'
		);

		/* ---------------- Einstellungen ---------------- */
		self::section(
			'einstellungen-allgemein',
			'Einstellungen: Allgemein',
			self::table(
				array(
					array( 'Buchungsseite', 'Seite mit <code>[msb_checkout]</code>. Hierher führen „Zur Buchung“, Zahlungslinks und die Rückkehr von Stripe/PayPal.' ),
					array( 'Nach „Zur Buchung hinzufügen“', 'Direkt zur Buchungsseite weiterleiten oder auf der Seite bleiben (Warenkorb mit mehreren Artikeln).' ),
					array( 'Artikelseiten', 'Galerie, Preise, technische Daten und Buchungskalender automatisch unter jedem Artikel anzeigen.' ),
					array( 'Monate im Buchungskalender', '1–3 nebeneinander (auf Smartphones immer 1).' ),
					array( 'Währung', 'Symbol, Position und Nachkommastellen.' ),
					array( 'Preishinweis', 'Erscheint bei allen Preisen, z. B. „inkl. MwSt.“.' ),
					array( 'Präfix Buchungsnummer', 'z. B. MB- → MB-2026-0001.' ),
					array( 'Akzentfarbe', 'Farbe von Buttons, Kalender und E-Mails.' ),
					array( 'Danke-Nachricht', 'Nach dem Buchen angezeigt; Platzhalter möglich.' ),
					array( 'Deinstallation', 'Nur wenn angehakt, werden beim Löschen des Plugins alle Daten entfernt.' ),
				),
				array( 'Einstellung', 'Bedeutung' )
			)
		);

		self::section(
			'einstellungen-regeln',
			'Einstellungen: Buchungsregeln',
			self::table(
				array(
					array( 'Berechnung der Miettage', '<em>Kalendertage</em>: Abhol- und Rückgabetag zählen (Fr–So = 3 Tage, Rückgabe am selben Tag möglich). <em>Nächte</em>: Fr–So = 2 Tage.' ),
					array( 'Puffertage', 'Freie Tage nach jeder Rückgabe, z. B. für Reinigung.' ),
					array( 'Vorlaufzeit', '0 = heute buchbar, 1 = frühestens morgen.' ),
					array( 'Buchbar im Voraus', 'Maximal so viele Tage in die Zukunft (0 = unbegrenzt).' ),
					array( 'Keine Abholung/Rückgabe an', 'Ruhetage. Im Kalender mit Strich markiert.' ),
					array( 'Status neuer Online-Buchungen', '„Angefragt“ (Sie bestätigen manuell) oder „Bestätigt“ (sofort verbindlich).' ),
				),
				array( 'Einstellung', 'Bedeutung' )
			)
		);

		$ph = array();
		foreach ( MSB_Emails::placeholders() as $k => $v ) {
			$ph[] = array( '<code>' . esc_html( $k ) . '</code>', esc_html( $v ) );
		}
		$types = array();
		foreach ( MSB_Emails::types() as $k => $v ) {
			$types[] = '<li>' . esc_html( $v ) . '</li>';
		}
		self::section(
			'emails',
			'E-Mails & Platzhalter',
			'<p>Unter ' . self::link( 'admin.php?page=msb-settings&tab=emails', 'Einstellungen → E-Mails' ) . ' legen Sie Empfänger, Absender und die Texte fest. Jede Vorlage lässt sich einzeln abschalten:</p><ul>' . implode( '', $types ) . '</ul>
			<p>Die Artikeltabelle (<code>{items}</code>) enthält automatisch Rabatte, Kaution und – sobald etwas bezahlt ist – „Bereits bezahlt“ und „Noch offen“. Mit „Test-E-Mail senden“ prüfen Sie den Versand; landen Mails im Spam, hilft ein SMTP-Plugin wie „WP Mail SMTP“ und eine Absenderadresse Ihrer Domain.</p>' .
			self::table( $ph, array( 'Platzhalter', 'Wird ersetzt durch' ) )
		);

		/* ---------------- Shortcodes ---------------- */
		$sc = array(
			array( '[msb_items]', 'Artikelübersicht als Kacheln oder Liste mit Galerie, Kurzbeschreibung, Preis und Button.', '<code>category</code> Kategorie-Slug(s), kommagetrennt · <code>ids</code> bestimmte Artikel · <code>exclude</code> · <code>limit</code> Anzahl · <code>columns</code> 1–6 · <code>layout</code> grid|list · <code>orderby</code> menu_order|title|price|date|rand · <code>order</code> ASC|DESC · <code>show_price</code> · <code>show_deposit</code> · <code>show_excerpt</code> · <code>show_specs</code> · <code>show_stock</code> · <code>show_gallery</code> · <code>show_button</code> (yes|no) · <code>button_text</code> · <code>bookable="yes"</code> nur buchbare · <code>image_size</code>' ),
			array( '[msb_item id="12"]', 'Komplette Artikelansicht: Galerie, Titel, Beschreibung, technische Daten, Preise &amp; Rabatte, Buchungskalender.', '<code>show_image</code> · <code>show_title</code> · <code>show_content</code> · <code>show_specs</code> · <code>show_prices</code> (yes|no) · <code>months</code> 1–3' ),
			array( '[msb_booking id="12"]', 'Nur die Buchungsbox (Kalender, Anzahl, Preis mit Rabatten, Button).', '<code>months</code> · <code>title</code> · <code>button</code>' ),
			array( '[msb_calendar id="12"]', 'Verfügbarkeitskalender nur zum Ansehen.', '<code>months</code> · <code>legend</code> (yes|no)' ),
			array( '[msb_price id="12"]', 'Preis oder Preistabelle (inkl. Staffeln, Grundgebühr, Kaution, Mindestdauer, aktiver Rabattregeln).', '<code>show</code>: from (ab-Preis) · day (Tagespreis) · table' ),
			array( '[msb_specs id="12"]', 'Tabelle der technischen Daten.', '–' ),
			array( '[msb_gallery id="12"]', 'Bildergalerie eines Artikels.', '<code>mode</code> full|card · <code>size</code> Bildgröße' ),
			array( '[msb_availability id="12"]', 'Text „Heute 2 von 3 verfügbar“.', '<code>date</code> (TT.MM.JJJJ)' ),
			array( '[msb_categories]', 'Kategorien als Kacheln oder Liste mit Link zur Kategorieseite.', '<code>layout</code> tiles|list · <code>columns</code> · <code>show_count</code> · <code>show_description</code> · <code>hide_empty</code> · <code>parent</code> (ID)' ),
			array( '[msb_search]', 'Zeitraumsuche: zeigt alle in diesem Zeitraum freien Artikel mit Galerie, Beschreibung und Preis für den Zeitraum (inkl. Rabatte). Der Zeitraum wird in den Buchungskalender übernommen.', '<code>category</code> feste Kategorie · <code>show_category</code> Auswahlfeld · <code>button</code> · <code>columns</code> · <code>layout</code> sowie alle Anzeige-Optionen von [msb_items]' ),
			array( '[msb_cart]', 'Warenkorb mit „Weiter zur Buchung“.', '–' ),
			array( '[msb_checkout]', 'Buchungsseite: Warenkorb, Formular, Kundenkonto-Option, Zahlungsart, Danke-Seite und Zahlungsseite für Zahlungslinks.', '<code>title</code> · <code>button</code> (ohne Online-Zahlung) · <code>button_online</code>' ),
			array( '[msb_cart_link]', 'Link „Meine Auswahl (2)“ zur Buchungsseite, z. B. im Header.', '<code>text</code>' ),
			array( '[msb_account]', 'Kundenkonto: Anmelden, Registrieren, Meine Buchungen, Meine Daten.', '–' ),
		);
		self::section(
			'shortcodes',
			'Alle Shortcodes',
			'<p>Shortcodes fügen Sie im Block-Editor mit dem Block „Shortcode“ ein. Alle Optionen sind optional; Ja/Nein-Optionen akzeptieren <code>yes</code>/<code>no</code>. <code>id</code> darf auf der Artikelseite selbst weggelassen werden. Die IDs Ihrer Artikel stehen unter ' . self::link( 'admin.php?page=msb-shortcodes', 'Shortcodes' ) . ' und in der Artikelliste.</p>' .
			self::table( $sc, array( 'Shortcode', 'Funktion', 'Optionen' ) ) .
			'<h3>Beispiele</h3><ul>
				<li><code>[msb_items category="kraxen" columns="3"]</code> – alle Kraxen in drei Spalten</li>
				<li><code>[msb_items layout="list" show_specs="yes" show_deposit="yes"]</code> – Liste mit Technik und Kaution</li>
				<li><code>[msb_items orderby="price" limit="4" show_excerpt="no"]</code> – die 4 günstigsten Artikel</li>
				<li><code>[msb_search columns="2" show_category="no"]</code> – Zeitraumsuche</li>
				<li><code>[msb_price id="12" show="table"]</code> – Preistabelle inkl. Wochenendrabatt</li>
			</ul>'
		);

		self::section(
			'seiten',
			'Seitenvorschläge',
			self::table(
				array(
					array( 'Startseite', '<code>[msb_search]</code> und darunter <code>[msb_categories]</code>' ),
					array( 'Alle Mietartikel', '<code>[msb_items columns="3"]</code> (alternativ die Archivseite <code>/mietartikel/</code>)' ),
					array( 'Kategorie-Seite', '<code>[msb_items category="fahrradanhaenger"]</code>' ),
					array( 'Buchung abschließen', '<code>[msb_checkout]</code> (automatisch angelegt)' ),
					array( 'Mein Konto', '<code>[msb_account]</code> (automatisch angelegt)' ),
					array( 'Preise', '<code>[msb_price id="…" show="table"]</code> je Artikel' ),
					array( 'Header / Menü', '<code>[msb_cart_link]</code> in einem Shortcode-Block oder Widget' ),
				),
				array( 'Seite', 'Inhalt' )
			)
		);

		/* ---------------- Backend ---------------- */
		self::section(
			'backend',
			'Backend-Seiten',
			self::table(
				array(
					array( 'Buchungen', 'Liste mit Filtern (Status, Artikel, Zeitraum), Suche, Sammelaktionen, CSV-Export, Box „Abholungen/Rückgaben heute &amp; morgen“, Zahlungsspalte.' ),
					array( 'Buchung (Detail)', 'Artikel mit Rabatten, Kundenangaben, Zahlungen &amp; Kaution, Status, interne Notiz, E-Mail erneut senden, Drucken, Löschen.' ),
					array( 'Neue Buchung', 'Telefon- oder Vor-Ort-Buchungen mit Kundenauswahl. Buchungsregeln gelten hier nicht, die Verfügbarkeit schon (abschaltbar).' ),
					array( 'Kalender', 'Monatsansicht aller Buchungen, filterbar nach Artikel; „+“ legt eine Buchung an diesem Tag an.' ),
					array( 'Inventar', 'Freie Stück je Artikel für 21 Tage; Klick zeigt die Buchungen des Tages.' ),
					array( 'Kunden', 'Kundenstamm, siehe <a href="#kunden">Kundenverwaltung</a>.' ),
					array( 'Mietartikel / Kategorien', 'Artikel mit Spalten für Preis, Kaution, Bestand und „heute frei“.' ),
					array( 'Buchungsformular', 'Siehe <a href="#formular">Buchungsformular</a>.' ),
					array( 'Einstellungen', 'Allgemein, Buchungsregeln, Preise &amp; Rabatte, Zahlungen &amp; Kaution, Kundenkonten, E-Mails.' ),
					array( 'Shortcodes', 'Kurzübersicht mit den Shortcodes Ihrer Artikel und Kategorien zum Kopieren.' ),
				),
				array( 'Seite', 'Inhalt' )
			) . '<h3>Rollen</h3>
			<ul>
				<li><strong>Administrator</strong>: alles, auch Einstellungen, Löschen und WordPress selbst.</li>
				<li><strong>Gesellschafter</strong>: Buchungen ansehen, anlegen und bearbeiten, Status setzen, Zahlungen, Erstattungen und Kautionen, Kunden pflegen, Mietartikel und Kategorien anlegen und veröffentlichen (auch mit dem KI-Assistenten).</li>
				<li>Nur für Administratoren: Einstellungen, Buchungsformular, Buchungen und Kunden löschen, Kategorien löschen, Plugins, Benutzer, WordPress-Einstellungen und die Claude-Bridge.</li>
			</ul>
			<p>Einen Gesellschafter legen Sie unter <strong>Benutzer → Neu</strong> mit der Rolle „Gesellschafter“ an.</p>'
		);

		self::section(
			'technik',
			'Automatik, Cron, Datenschutz',
			'<p>Stündlich (WP-Cron) erledigt das Plugin:</p>
			<ul>
				<li>Unbezahlte Online-Buchungen nach Ablauf der Zahlungsfrist stornieren (zuvor wird bei PayPal geprüft, ob doch bezahlt wurde).</li>
				<li>Kautionen für Buchungen reservieren, deren Abholung innerhalb der eingestellten Tage liegt, bzw. den Kunden per Link darum bitten.</li>
			</ul>
			<p>WP-Cron läuft bei Seitenaufrufen. Bei wenig Besuchern empfiehlt sich ein echter Server-Cronjob, der <code>' . esc_html( site_url( 'wp-cron.php' ) ) . '</code> alle 15 Minuten aufruft (beim Hoster einstellbar).</p>
			<h3>Datenschutz (DSGVO)</h3>
			<ul>
				<li>Cookie <code>msb_cart</code> (technisch notwendig, 3 Tage) speichert den Warenkorb.</li>
				<li>Bei Online-Zahlung werden Name, E-Mail, Telefon und Betrag an Stripe bzw. PayPal übermittelt – in der Datenschutzerklärung erwähnen.</li>
				<li>Kundendaten liegen in den Tabellen <code>' . esc_html( MSB_Install::table( 'customers' ) ) . '</code> und <code>' . esc_html( MSB_Install::table( 'bookings' ) ) . '</code>. Auskunft: Kunde öffnen bzw. CSV-Export; Löschung: Kunde löschen.</li>
			</ul>
			<h3>Für Entwickler</h3>
			<p>Hooks: <code>msb_booking_created</code>, <code>msb_booking_status_changed</code>, <code>msb_payment_completed</code>, <code>msb_security_released</code>, <code>msb_account_created</code>; Filter: <code>msb_calculate_price</code>, <code>msb_form_fields</code>, <code>msb_statuses</code>, <code>msb_blocking_statuses</code>, <code>msb_email_args</code>, <code>msb_capability</code>, <code>msb_stripe_hold_days</code>, <code>msb_paypal_hold_days</code>.</p>'
		);

		self::section(
			'probleme',
			'Häufige Fragen & Probleme',
			self::table(
				array(
					array( 'Es kommen keine E-Mails an.', 'Test-E-Mail senden. Absenderadresse Ihrer Domain verwenden und ein SMTP-Plugin installieren.' ),
					array( 'Der Kalender lädt nicht / „Fehler“ beim Buchen.', 'Caching-Plugin: Buchungsseite und <code>admin-ajax.php</code> vom Cache ausschließen. Sicherheits-Plugins dürfen admin-ajax.php nicht blockieren.' ),
					array( 'Artikelseite zeigt 404.', 'Einstellungen → Permalinks öffnen und speichern.' ),
					array( 'Nach Stripe-Zahlung bleibt die Buchung auf „Zahlung ausstehend“.', 'Webhook einrichten (siehe <a href="#stripe">Stripe</a>). Ohne Webhook wird die Zahlung nur erfasst, wenn der Kunde auf die Danke-Seite zurückkehrt.' ),
					array( 'Kaution konnte nicht reserviert werden.', 'Die Bank verlangte eine Bestätigung oder die Karte wurde abgelehnt. Der Kunde hat automatisch einen Link erhalten; Fehler steht in der Zahlungszeile. Alternativ Kaution bar nehmen und „Zahlung vor Ort erfassen“.' ),
					array( 'Wochenendrabatt greift nicht.', 'Abholung muss Fr/Sa, Rückgabe Sa/So/Mo sein und die Dauer innerhalb der eingestellten Kalendertage liegen. Haken „aktiv“ prüfen.' ),
					array( 'Kunde sieht alte Buchungen nicht im Konto.', 'E-Mail-Adresse muss bestätigt sein (Link in der Willkommens-Mail; im Konto erneut anforderbar).' ),
					array( 'Ich möchte eine Buchung ändern.', 'Buchung stornieren und neu anlegen (Neue Buchung → Kunde wählen). Zahlungen ggf. erstatten bzw. neu erfassen.' ),
				),
				array( 'Problem', 'Lösung' )
			)
		);

		self::section( 'changelog', 'Versionshinweise', self::changelog_html() );
	}

	/**
	 * CHANGELOG.md als HTML.
	 */
	public static function changelog_html() {
		$file = MSB_DIR . 'CHANGELOG.md';
		if ( ! file_exists( $file ) ) {
			return '<p>Keine Versionshinweise gefunden.</p>';
		}
		$html = '';
		$open = false;
		foreach ( preg_split( '/\r\n|\n/', (string) file_get_contents( $file ) ) as $line ) { // phpcs:ignore
			$line = rtrim( $line );
			if ( preg_match( '/^## (.+)$/', $line, $m ) ) {
				$html .= ( $open ? '</ul>' : '' ) . '<h3 class="msb-changelog__version">' . esc_html( $m[1] ) . '</h3>';
				$open  = false;
			} elseif ( preg_match( '/^### (.+)$/', $line, $m ) ) {
				$html .= ( $open ? '</ul>' : '' ) . '<h4>' . esc_html( $m[1] ) . '</h4>';
				$open  = false;
			} elseif ( preg_match( '/^[-*] (.+)$/', $line, $m ) ) {
				if ( ! $open ) {
					$html .= '<ul class="msb-changelog">';
					$open  = true;
				}
				$item  = esc_html( $m[1] );
				$item  = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $item );
				$item  = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $item );
				$html .= '<li>' . $item . '</li>';
			}
		}
		return $html . ( $open ? '</ul>' : '' );
	}
}
