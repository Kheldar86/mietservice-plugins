# Implementation Plan: Auslagenabrechnung pro Gesellschafter

**Branch**: `005-auslagenabrechnung` | **Date**: 2026-11-12 | **Spec**: [spec.md](spec.md)

## Summary

Neue Klasse `MSB_Expenses` (`includes/class-msb-expenses.php`) für Auslagen, Abrechnungen, PDF, Versand und EÜR-Zeilen, dazu die Backend-Seite `MSB_Admin_Expenses` (`admin/class-msb-admin-expenses.php`) unter Vermietung → Auslagen. Zwei neue Tabellen, eigener Nummernkreis `AUS-JJJJ-0001` über `msb_next_sequence( 'doc_expense', $jahr )`, Ablage geschützt unter `uploads/msb-belege/auslagen/`. Die PDF entsteht mit `MSB_PDF` nach der Vorlage, der GiroCode mit `MSB_QR` (beides ohne neue Abhängigkeit). Version msb-rental **1.10.0** (vorher 1.9.1), Datenbankversion **1.10.0** (vorher 1.8.1). Die neue Klasse legt ihre Tabellen selbst an und kümmert sich selbst um die Frist, damit bestehende Dateien kaum geändert werden.

## Technical Context

**Language/Version**: PHP 7.4+, WordPress 6.0+

**Storage**:

- Tabelle `msb_expenses` (Positionen): `id`, `user_id` (Gesellschafter), `spent_on` (date), `purpose` (varchar 190), `amount` (decimal 12,2, brutto), `vat_info` (varchar 40, nur Information), `receipt_file` (varchar 255), `receipt_type` (jpeg/pdf/other), `no_receipt_reason` (text), `claim_id` (0 = offen), `created_by`, `created_at`, `updated_at`.
- Tabelle `msb_expense_claims` (Abrechnungen): `id`, `number` (varchar 40), `user_id`, `period_to` (date, Stichtag), `amount`, `status` (issued/paid/cancelled), `data` (JSON: Name, IBAN, Positionen, Aussteller, Verwendungszweck), `file`, `sent_at`, `paid_at`, `paid_by`, `cancel_reason`, `created_by`, `created_at`.
- Benutzer-Meta je Gesellschafter: `msb_partner_iban`, `msb_partner_name` (Name für die Überweisung). Keine Werte im Repository.
- Neue Einstellung `expense_finom_email` in `msb_settings` (Finom-Beleg-Postfach), gepflegt unter Vermietung → Auslagen → Abrechnungen (nur mit Recht für Einstellungen), leer = kein automatischer Versand.

**Testing**: `php -l` für alle geänderten Dateien, `php tests/msb-rental/test-expenses.php` (IBAN-Prüfung, Nummernformat, Summen, EPC-Inhalt, CSV-Zeilen, Sperre nach Abrechnung).

## Constitution Check

Geprüft gegen Constitution **1.1.0**.

| Grundsatz | Prüfung | Ergebnis |
|---|---|---|
| I. Die Spezifikation ist die Quelle | PAY-37 bis PAY-42, ADM-16, ROL-04, DSG-12 neu, Status nach Umsetzung nachgetragen | erfüllt |
| II. Jede Änderung ist eine neue Version | 1.10.0 im Kopf und in `MSB_VERSION`, CHANGELOG, Datenbankversion 1.10.0 mit Update beim ersten Seitenaufruf (`dbDelta`) | erfüllt |
| III. Keine Geheimnisse | keine; Tests mit erfundener Prüf-IBAN `DE02120300000000202051` | erfüllt |
| IV. Live nur über Snapshot und Freigabe | Snapshot, je Datei ein Vorschlag, SHA-256-Abgleich, Vermerk unten | geplant |
| V. Prüfbar und getestet | lokale Tests; live zuerst eine Abrechnung über 0,01 € an Moritz selbst, danach stornieren | geplant |
| VI. Recht und Geld zuerst | eigener lückenloser Nummernkreis AUS, Abrechnung nie geändert (nur Storno), 8 Jahre Aufbewahrung, nur Bruttobeträge (§ 19 UStG). Steuerliche Einordnung als offene Fragen an den Steuerberater, nicht selbst entschieden | erfüllt, Fragen unten |
| VII. Einfach und passend zum Bestand | Aufbau wie `MSB_Documents` und `MSB_Promo_Links`, PDF mit `MSB_PDF`, QR mit `MSB_QR`, keine Abhängigkeit, Kartenstil, „Stornieren“ ganz rechts | erfüllt |

## Ablauf und Oberfläche (ADM-16)

Vermietung → **Auslagen** mit drei Bereichen:

1. **Meine Auslagen**: Formular (Datum, Lieferant/Zweck, Betrag brutto, optional „darin MwSt.“ als Info, Beleg-Foto oder PDF mit Kamera-Aufnahme auf dem Handy, alternativ „Kein Beleg“ mit Pflicht-Begründung). Liste der offenen Positionen mit Summe, Bearbeiten und Löschen solange offen. Knopf „Abrechnung erstellen“ mit Stichtag (Standard: letzter Tag des Vormonats, wählbar).
2. **Abrechnungen**: alle Abrechnungen beider Gesellschafter mit Status. Je Abrechnung: PDF ansehen, Überweisungsdaten (Empfänger, IBAN, Betrag, Verwendungszweck `AUS-2026-0001 Auslagen 11/2026`) mit Kopier-Knöpfen, GiroCode, „An Finom senden“ bzw. „Erneut senden“, „Als ausgezahlt markieren“ (Datum), ganz rechts abgesetzt „Stornieren“ (mit Grund).
3. **Meine Bankverbindung**: Name für die Überweisung und IBAN (Prüfung nach ISO 13616, Anzeige maskiert außer in der Abrechnung).

## Rechte (ROL-04)

Kein neues Recht: Die Auslagen nutzen `msb_capability( 'bookings' )` (über `MSB_Expenses::cap()`), das Gesellschafter und Administratoren schon haben. Jeder erfasst und ändert nur **eigene** offene Auslagen und nur die **eigene** Bankverbindung. Beide sehen alle Abrechnungen (Transparenz unter Gesellschaftern) und dürfen „ausgezahlt“ setzen. Die Finom-Adresse ändern nur Administratoren (Einstellungen).

## PDF (PAY-38)

Seite 1 nach der Vorlage: Logo und Aussteller wie bei den Belegen (`MSB_Documents::issuer()`), Titel „Auslagenabrechnung / Eigenbeleg“, Gesellschafter mit Name und IBAN, Zeitraum, Nummer, Tabelle (Pos., Datum, Lieferant/Verwendungszweck, Betrag brutto, Beleg), Gesamtbetrag, Zahlungshinweis aus der Vorlage, Hinweis „Kleinunternehmer nach § 19 UStG, Beträge brutto“, GiroCode, Zeile „Erstellt im Backend von <Name> am <Datum, Uhrzeit>“. Folgeseiten: je Fotobeleg eine Seite „Anlage n zu Pos. n“ (JPEG, auf die Seite eingepasst). PDF-Belege werden als „Anlage n (PDF, separat beigefügt)“ genannt.

## Versand an Finom (PAY-40)

Mit `MSB_Emails::send_raw()` an `expense_finom_email`: Betreff „Auslagenabrechnung AUS-2026-0001 – <Name> – 105,79 €“, Anhänge: Abrechnungs-PDF und alle PDF-Belege. Bei leerer Adresse kein Versand, Hinweis auf der Seite. Kopie an `admin_email`. Größe je Datei ≤ 10 MB (Grenze des Finom-Postfachs).

## EÜR (PAY-42)

Kasten unter Vermietung → Auslagen → Abrechnungen (mit Jahresauswahl): Summe der ausgezahlten und der offenen Auslagen des Jahres, CSV `auslagen-euer-JJJJ.csv` mit Belegdatum, Lieferant/Zweck, Betrag, Gesellschafter, AUS-Nummer, Status, Erstattungsdatum, Eigenbeleg ja/nein. Stornierte Abrechnungen erscheinen nicht, ihre Positionen zählen erst mit der neuen Abrechnung.

## Aufbewahrung (DSG-12)

Abrechnungen, Positionen und Dateien bleiben 8 Jahre ab Ende des Jahres der Abrechnung (§ 147 AO) und werden dann gelöscht: `MSB_Expenses::retention()` hängt am täglichen Cron `msb_cron_daily`, folgt `MSB_Retention::live()` (Probelauf zählt nur) und schreibt ins Löschprotokoll. Offene Positionen ohne Abrechnung werden nicht automatisch gelöscht. Ablage `uploads/msb-belege/auslagen/` mit `.htaccess` „Require all denied“, Abruf nur über das Backend mit Recht und Nonce.

## Betroffene Dateien

- `plugins/msb-rental/includes/class-msb-expenses.php` (neu)
- `plugins/msb-rental/admin/class-msb-admin-expenses.php` (neu)
- `plugins/msb-rental/includes/class-msb-install.php` (nur `DB_VERSION` 1.10.0; die Tabellen legt `MSB_Expenses::maybe_install()` an)
- `plugins/msb-rental/includes/class-msb-documents.php` (nur `issuer()` und `logo_jpeg()` öffentlich, damit die Abrechnung Absender und Logo der Belege nutzt)
- `plugins/msb-rental/msb-rental.php` (Laden, Version 1.10.0)
- `plugins/msb-rental/CHANGELOG.md`, `plugins/msb-rental/ANLEITUNG.md`, `plugins/msb-rental/admin/class-msb-docs.php` (Hilfe)
- `specs/anforderungen/PAY-zahlung-kaution-storno.md`, `ADM-backend.md`, `ROL-rollen.md`, `DSG-datenschutz-loeschfristen.md`
- `tests/msb-rental/test-expenses.php` (neu, wird nicht live eingespielt)

## Datenbank

Zwei neue Tabellen über `dbDelta` in `MSB_Expenses::maybe_install()` (Option `msb_expenses_db`), Datenbankversion 1.10.0 in `MSB_Install`. Bestehende Tabellen bleiben unverändert. Neuer Zähler `doc_expense` in `msb_sequences`.

## Offene Fragen an den Steuerberater (nicht im Plugin entschieden)

1. **Zeitpunkt der Betriebsausgabe:** Belegdatum (Zahlung durch den Gesellschafter) oder Datum der Erstattung durch die GbR? Die CSV enthält beide Daten.
2. **Unterschrift:** Reicht die elektronisch erstellte Abrechnung mit Name und Zeitstempel, oder muss sie unterschrieben werden?
3. **Belege auf den Namen des Gesellschafters** (z. B. Kassenbon ohne Firmennamen): genügt das mit der Abrechnung als Zuordnung?

## Abweichung von der Vorlage

- Nummer `AUS-JJJJ-0001` (fortlaufend je Jahr) statt `AUS-2026-11`, damit zwei Abrechnungen im selben Monat nicht kollidieren und der Nummernkreis lückenlos bleibt. Der Monat steht im Verwendungszweck und im Kopf.
- Netto und MwSt. entfallen als Pflichtspalten (Kleinunternehmer, kein Vorsteuerabzug). Eine enthaltene MwSt. kann als Information eingetragen werden.

## Abnahmekriterien

- [ ] Live: Auslage mit Foto und mit Eigenbeleg-Begründung speichern, bearbeiten, löschen (nur solange offen)
- [ ] Abrechnung `AUS-JJJJ-0001` mit richtiger Summe, Positionen danach gesperrt, ohne IBAN keine Nummer
- [x] PDF wie Vorlage, Fotobelege als Folgeseiten, PDF-Belege genannt (lokal mit Beispieldaten)
- [ ] GiroCode mit Empfänger, IBAN, Betrag, Verwendungszweck (mit Banking-App geprüft)
- [ ] Versand an die Finom-Adresse mit Anhängen, Versandzeit vermerkt
- [ ] Ausgezahlt und Storno mit Grund; Storno gibt die Positionen frei, die Nummer bleibt belegt
- [ ] Eric sieht nur seine offenen Auslagen und seine Bankverbindung, aber alle Abrechnungen; keine Einstellungen
- [ ] Kasten und CSV „Auslagen JJJJ für die EÜR“
- [ ] Dateien nicht öffentlich abrufbar
- [x] Version 1.10.0, Datenbankversion 1.10.0, CHANGELOG, Status PAY-37 bis PAY-42, ADM-16, ROL-04, DSG-12

## Testschritte

1. Lokal: `php -l` für alle geänderten Dateien und `php tests/msb-rental/test-expenses.php`. Ergebnis am 12.11.2026 (PHP 8.5): keine Syntaxfehler, 40 von 40 Fällen bestanden; die übrigen Tests unter `tests/msb-rental/` bestehen weiter. Die Beispiel-PDF entspricht der Vorlage.
2. Live nach der Freigabe: Bankverbindung eintragen, Auslage über 0,01 € mit Foto erfassen, Abrechnung erstellen, PDF prüfen, GiroCode mit dem Handy scannen (nicht ausführen), Eingang im Finom-Beleg-Postfach prüfen, danach stornieren. Erst dann echte Auslagen erfassen.

## Quellen zu Finom (Stand 12.11.2026)

- Sammelzahlung per Datei, nur Smart/Pro/Grow: https://help.finom.co/en/articles/12919925-how-to-make-bulk-payments-via-a-file
- Beleg-Postfach per E-Mail, Texterkennung, „Link payment“: https://help.finom.co/en/articles/5901360-how-to-collect-documents-using-finom
- Dokumente an Zahlungen hängen: https://help.finom.co/en/articles/4786775-how-to-upload-additional-documents-to-a-transaction
- Keine öffentliche Schnittstelle bekannt: https://apis.io/providers/finom/

## Änderung vor der Freigabe (12.11.2026)

Damit an bestehenden Dateien möglichst wenig geändert wird (kleinere Freigabe-Vorschläge), liegen das Feld für das Finom-Beleg-Postfach und der EÜR-Kasten nicht unter Einstellungen → Belege bzw. auf der Seite Belege, sondern auf der neuen Seite unter Vermietung → Auslagen → Abrechnungen. In `class-msb-documents.php` werden nur zwei Methoden öffentlich, in `class-msb-install.php` ändert sich nur die Datenbankversion. `functions.php` und `class-msb-retention.php` bleiben unverändert: kein eigenes Recht (Recht für Buchungen), eigene Tabellen-Anlage und eigener Fristen-Lauf in `MSB_Expenses`. Die Hilfe verlinkt die Seite direkt (Abschnitt `#auslagen`), ohne neuen Eintrag im Inhaltsverzeichnis.

## Freigabe

| Schritt | Wann | Notiz |
|---|---|---|
| Plan bestätigt von Moritz | 12.11.2026 | „Ja bau es“ |
| netcup-Snapshot | | |
| Bridge-Vorschläge 1.10.0 | | |
| Freigegeben 1.10.0 | | |
| Live-Abgleich SHA-256 | | |
| Merge und Tag | | Tag `msb-rental-v1.10.0` legt Moritz in GitHub an |
