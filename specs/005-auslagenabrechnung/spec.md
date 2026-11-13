# Feature Specification: Auslagenabrechnung pro Gesellschafter

**Feature Branch**: `005-auslagenabrechnung` (auf Basis von `claude/project-thread-3ehakv`, msb-rental 1.9.1)

**Created**: 2026-11-12

**Status**: Umgesetzt, wartet auf Freigabe (Plan bestätigt am 12.11.2026)

**Input**: Moritz (12.11.2026), mit Vorlage „Auslagenabrechnung_Finom_Kästner_GbR.docx“: „Ich benötige die Funktion in der Vermietungsunternehmen App eine Auslagenabrechnung pro Gesellschafter anzulegen der dann auch bei finom eingelesen und an das privat Konto des Gesellschafters ausgezahlt sowie buchhalterisch in finom auch erkannt wird“

## Hintergrund

Moritz und Eric bezahlen Kleinigkeiten für die GbR privat (Baumarkt, Paketmarken, Büromaterial). Bisher fehlt ein fester Weg, das gesammelt abzurechnen, vom Finom-Geschäftskonto zu erstatten und für die EÜR sauber zu belegen. Die Vorlage gibt den Aufbau vor: Kopf mit GbR und Gesellschafter (Name, private IBAN, Monat, Beleg-Nr. `AUS-…`), Tabelle der Belege, Gesamtbetrag, Zahlungshinweis, Originalbelege als Folgeseiten.

**Was Finom kann** (Recherche 12.11.2026, Quellen im Plan):

- Keine öffentliche Schnittstelle. Das Plugin kann bei Finom **keine Überweisung selbst auslösen**. Jede Zahlung bestätigt ein Mensch in Finom.
- Überweisungen per Datei-Upload (CSV/XML, bis 200) gibt es erst ab dem Tarif Smart. Die GbR hat den Tarif Basis (Stand 13.10.2026).
- Finom hat ein eigenes Beleg-Postfach (E-Mail-Adresse unter „Invoices“). Dort eingehende PDFs und Fotos liest Finom ein. Mit einer Zahlung verknüpft man sie in Finom über „Link payment“.

Daraus folgt der Ablauf: Das Plugin erstellt die Abrechnung, schickt sie an das Finom-Beleg-Postfach und liefert die Überweisungsdaten fertig zum Kopieren und als GiroCode. Die Überweisung bestätigt ein Gesellschafter in Finom, danach wird die Abrechnung im Plugin auf „ausgezahlt“ gesetzt und landet im EÜR-Export.

## Betroffene Anforderungen

| ID | Art | Kurz |
|---|---|---|
| PAY-37 | neu | Auslagen erfassen (Datum, Zweck, Bruttobetrag, Beleg oder Eigenbeleg-Begründung) |
| PAY-38 | neu | Beleg „Auslagenabrechnung“ mit Nummernkreis `AUS-JJJJ-0001`, PDF nach Vorlage, nie ändern, Storno mit Grund |
| PAY-39 | neu | Überweisungsdaten zum Kopieren und als GiroCode (EPC-QR) |
| PAY-40 | neu | Versand an das Finom-Beleg-Postfach |
| PAY-41 | neu | Status offen → ausgezahlt (mit Datum) bzw. storniert |
| PAY-42 | neu | Auslagen im EÜR-Export (Kasten und CSV je Jahr) |
| ADM-16 | neu | Seite Vermietung → Auslagen, Kartenstil auf dem Handy |
| ROL-04 | neu | Auslagen für alle mit dem Recht für Buchungen (Gesellschafter, Administratoren) |
| DSG-12 | neu | Aufbewahrung der Abrechnungen und Belegfotos 8 Jahre, geschützte Ablage |

## User Scenarios & Testing

### User Story 1 – Auslage unterwegs erfassen (Priority: P1)

Eric kauft im Baumarkt Schrauben für 45,90 €. Auf dem Handy öffnet er Vermietung → Auslagen, trägt Datum, „Baumarkt (Schrauben)“ und 45,90 € ein und fotografiert den Kassenbon.

**Acceptance Scenarios**:

1. **Given** Eric ist als Gesellschafter angemeldet, **When** er eine Auslage mit Foto speichert, **Then** steht sie unter „Meine offenen Auslagen“ mit Summe, das Foto liegt geschützt (nicht über eine öffentliche Adresse abrufbar).
2. **Given** kein Beleg vorhanden (z. B. Parkautomat ohne Quittung), **When** er „Kein Beleg“ wählt, **Then** muss er eine Begründung eingeben, und die Position wird als Eigenbeleg gekennzeichnet.
3. **Given** eine offene, noch nicht abgerechnete Auslage, **When** er sie bearbeitet oder löscht, **Then** geht das. Nach der Abrechnung nicht mehr.

### User Story 2 – Monatsabrechnung erstellen (Priority: P1)

Am Monatsende klickt Moritz auf „Abrechnung erstellen“. Es entsteht `AUS-2026-0001` als PDF: Kopf wie in der Vorlage, alle offenen Positionen bis zum gewählten Stichtag, Gesamtbetrag, Zahlungshinweis, danach je Fotobeleg eine Seite „Anlage zu Pos. n“.

**Acceptance Scenarios**:

1. **Given** drei offene Auslagen über 105,79 €, **When** Abrechnung erstellt, **Then** Nummer `AUS-2026-0001`, Betrag 105,79 €, die drei Positionen sind gesperrt.
2. **Given** keine hinterlegte IBAN, **When** Abrechnung erstellen, **Then** Hinweis „Bitte zuerst die Bankverbindung eintragen“, keine Nummer wird verbraucht.
3. **Given** eine Abrechnung enthält einen Fehler, **When** „Stornieren“ mit Grund, **Then** Status „storniert“, die Nummer bleibt belegt, die Positionen sind wieder offen und können neu abgerechnet werden.

### User Story 3 – Auszahlen über Finom (Priority: P1)

Moritz öffnet die Abrechnung. Er sieht Empfänger, IBAN, Betrag und Verwendungszweck „AUS-2026-0001 Auslagen 11/2026“ mit Kopier-Knöpfen und einen GiroCode. Er überweist in Finom, verknüpft dort den Beleg und klickt im Plugin „Als ausgezahlt markieren“.

**Acceptance Scenarios**:

1. **Given** unter Vermietung → Auslagen → Abrechnungen ist die Finom-Beleg-Adresse eingetragen, **When** eine Abrechnung entsteht, **Then** geht sie automatisch als PDF an diese Adresse, PDF-Belege als zusätzliche Anhänge; die Versandzeit steht an der Abrechnung, „Erneut senden“ ist möglich.
2. **When** „Als ausgezahlt markieren“ mit Datum, **Then** Status „ausgezahlt“ mit Datum und Bearbeiter.

### User Story 4 – Jahresabschluss (Priority: P2)

Unter Vermietung → Auslagen → Abrechnungen gibt es einen Kasten „Auslagen JJJJ für die EÜR“ mit Summe und CSV: jede Position einzeln mit Belegdatum, Zweck, Betrag, Gesellschafter, AUS-Nummer, Status und Erstattungsdatum.

### Edge Cases

- Foto als PNG oder HEIC → wird für die PDF in JPEG umgewandelt; klappt das nicht, wird das Original aufbewahrt und in der PDF als „Anlage separat“ vermerkt.
- PDF-Beleg (z. B. Online-Rechnung) → kann ohne fremde Bibliothek nicht in die Abrechnungs-PDF eingefügt werden; er wird separat aufbewahrt, im Versand an Finom mitgeschickt und in der PDF als „Anlage n (PDF, separat)“ genannt.
- Datei größer als 10 MB (Grenze des Finom-Postfachs) → abgelehnt mit Hinweis.
- Betrag ≤ 0 oder Datum in der Zukunft → abgelehnt.
- Ungültige IBAN (Prüfziffer) → abgelehnt.
- Zwei Gesellschafter rechnen im selben Monat ab → zwei fortlaufende Nummern, keine Kollision.

## Nicht Teil dieser Änderung

- Kein automatisches Auslösen der Überweisung (keine Finom-Schnittstelle, und das soll ein Mensch bestätigen).
- Keine Überweisungsdatei für den Finom-Datei-Upload (erst ab Tarif Smart; die Spalten der Finom-Vorlage sind ohne Konto nicht einsehbar). Kann später ergänzt werden.
- Keine Fahrtkosten-Pauschalen (km-Geld) und keine Verpflegungsmehraufwände.
- Keine handschriftliche Unterschrift: Die PDF nennt, wer sie wann im Backend erstellt hat (siehe offene Frage an den Steuerberater im Plan).
