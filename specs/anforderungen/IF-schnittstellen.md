# IF – Schnittstellen

> Übernommen am 27.10.2026 aus der [Anforderungsspezifikation](https://claude.ai/artifact/5zkRqvGmXM9q7RA5rLBiDk) (Stand 21.10.2026). Den Status je Anforderung haben wir anhand der CHANGELOGs bis msb-rental 1.8.4 und Bridge 0.4.2 nachgetragen. Ab jetzt ist diese Datei die maßgebliche Fassung.

## Übersicht

| ID | Stufe | Status |
|---|---|---|
| IF-01 | MUSS | umgesetzt (bis 1.2.7) |
| IF-02 | MUSS | umgesetzt (bis 1.2.7) |
| IF-03 | MUSS | umgesetzt (bis 1.2.7) |
| IF-04 | MUSS | umgesetzt (bis 1.2.7) |
| IF-05 | MUSS | umgesetzt (bis 1.2.7) |
| IF-06 | MUSS | umgesetzt (bis 1.2.7); seit 1.6.0 Beleg-Mails, seit 1.8.3 AGB und Widerrufsbelehrung als PDF an der Mail „Bestätigt“; siehe B-15 |
| IF-07 | MUSS | umgesetzt (bis 1.2.7) |
| IF-08 | MUSS | umgesetzt (bis 1.2.7) |
| IF-09 | MUSS | umgesetzt (bis 1.2.7) |
| IF-10 | MUSS | umgesetzt (bis 1.2.7) |
| IF-11 | MUSS | umgesetzt (bis 1.2.7) |
| IF-12 | MUSS | umgesetzt in 1.5.1 |
| IF-13 | MUSS | umgesetzt in 1.5.3 |
| IF-14 | MUSS | umgesetzt in 1.5.8 (seit 1.5.5) |
| IF-15 | MUSS | umgesetzt in 1.6.1, abweichend vom CHANGELOG: zuerst Theme-Logo |
| IF-16 | MUSS | umgesetzt in 1.6.2 |
| IF-17 | MUSS | umgesetzt in 1.8.0 |
| IF-18 | MUSS | umgesetzt in 1.8.3 |
| IF-19 | MUSS | umgesetzt in 1.8.4 |

## Shortcodes (msb-rental)

| Shortcode | Zweck |
|---|---|
| [msb_items] | Artikelliste |
| [msb_item] | einzelner Artikel |
| [msb_booking] | Buchungsbox (Zeitraum, Menge, Preis) |
| [msb_calendar] | Verfügbarkeitskalender |
| [msb_price] | Preisangabe |
| [msb_specs] | technische Daten |
| [msb_availability] | Verfügbarkeitsanzeige |
| [msb_categories] | Kategorien |
| [msb_search] | Suche nach Zeitraum und Kategorie |
| [msb_cart] | Warenkorb |
| [msb_checkout] | Checkout (Seite /buchung/) |
| [msb_cart_link] | Link zum Warenkorb |
| [msb_gallery] | Galerie |
| [msb_account] | Kundenkonto (Seite /mein-konto/) |

### IF-01 · MUSS

Ist „Buchungsbox automatisch“ aktiv, wird die Buchungsbox an jede Artikelseite angehängt.

**Status:** umgesetzt (bis 1.2.7)

## AJAX-Aktionen (msb-rental, öffentlich und eingeloggt)

### IF-02 · MUSS

msb_availability (Kalenderdaten), msb_quote (Preisberechnung), msb_cart_add, msb_cart_remove, msb_checkout (Ablauf BKG-01). Antworten als JSON mit deutscher Fehlermeldung.

**Status:** umgesetzt (bis 1.2.7)

## REST-Endpunkte

### IF-03 · MUSS (msb-rental)

POST /wp-json/msb/v1/stripe-webhook (PAY-08).

**Status:** umgesetzt (bis 1.2.7)

### IF-04 · MUSS (Bridge)

MCP-Endpunkt und OAuth-Endpunkte (BR-03 bis BR-05).

**Status:** umgesetzt (bis 1.2.7)

## URL-Parameter (msb-rental)

### IF-05 · MUSS

msb_start, msb_end, msb_cat (Vorauswahl Zeitraum und Kategorie); msb_pay, for, pid, token (Zahlen einer Buchung); msb_done, msb_err, msb_return, session_id, msb_cancel, msb_cancelled (Rückkehr vom Zahlungsanbieter); msb_verify, u, k (Kontobestätigung).

**Status:** umgesetzt (bis 1.2.7)

## E-Mails (msb-rental)

### IF-06 · MUSS

Vorlagen in den Einstellungen: Admin-Benachrichtigung, Eingang, Bestätigung, Stornierung, Zahlungslink, Kaution freigegeben. Platzhalter laut Liste in den Einstellungen, inklusive {field:schlüssel} für Formularfelder.

**Status:** umgesetzt (bis 1.2.7); seit 1.6.0 Beleg-Mails, seit 1.8.3 AGB und Widerrufsbelehrung als PDF an der Mail „Bestätigt“; siehe B-15

### IF-07 · MUSS

Die Admin-Mail geht an die eingestellte Adresse (live: buchung@…) mit Antwort-an = Kunde. Absender aus den Einstellungen (live: kontakt@…). Filter msb_email_args.

**Status:** umgesetzt (bis 1.2.7)

## Zeitgesteuerte Aufgaben (msb-rental)

### IF-08 · MUSS

Stündlicher Cron msb_cron_hourly: abgelaufene Zahlungen stornieren (BKG-13) und fällige Kautionen blockieren (PAY-10).

**Status:** umgesetzt (bis 1.2.7)

## Hooks und Filter (msb-rental)

### IF-09 · MUSS

msb_capability, msb_calculate_price, msb_booking_status_changed, msb_email_args, msb_product_schema.

**Status:** umgesetzt (bis 1.2.7)

## Externe Dienste

### IF-10 · MUSS

msb-rental nutzt Stripe (API 2024-06-20: Checkout Sessions, PaymentIntents, Webhook), PayPal Orders v2 (Capture und Authorize) und die Anthropic Messages API (KI-Assistent). Die Bridge spricht OAuth 2.1 und MCP mit Claude.

**Status:** umgesetzt (bis 1.2.7)

### IF-11 · MUSS

Zugangsdaten zu allen Diensten liegen nur in den Einstellungen und werden nach G-10 geschützt.

**Status:** umgesetzt (bis 1.2.7)

## Ergänzt am 31.10.2026 (Funktionen seit 1.5.1)

Diese Nummern wurden nachträglich für Funktionen vergeben, die schon live waren (Feature 001).

### IF-12 · MUSS

Versandprotokoll der letzten 30 Mails der Webseite, ohne automatisches Laden.

**Status:** umgesetzt in 1.5.1

### IF-13 · MUSS

Platzhalter `{source}` und `{created_by}`. Nutzt die Vorlage einen davon, entfällt der automatische Zusatz.

**Status:** umgesetzt in 1.5.3

### IF-14 · MUSS

Platzhalter `{payment_info}` mit dem Absatz zur Zahlungsart. Fehlt er in „Eingegangen“ oder „Bestätigt“ und ist eine Online-Zahlung offen, wird der Bezahlknopf angehängt.

**Status:** umgesetzt in 1.5.8 (seit 1.5.5)

### IF-15 · MUSS

Dasselbe Logo oben in allen Mails des Plugins.

**Status:** umgesetzt in 1.6.1, abweichend vom CHANGELOG: zuerst Theme-Logo

### IF-16 · MUSS

Mail zum Beleg mit Kasten (Nummer, Datum, Buchung, Zeitraum, Betrag, Zahlungsvermerk, Knopf „PDF ansehen“) und einem Satz je Belegart. Platzhalter `{document_summary}` und `{document_note}`; fehlt der Kasten in der Vorlage, steht er vor „Viele Grüße“. Der Betreff der Kautionsmail nennt die Belegart.

**Status:** umgesetzt in 1.6.2

### IF-17 · MUSS

Kurzlink `/ka/…` je Artikel mit Weiterleitung und `utm_source=kleinanzeigen`. Aufrufe je Monat, ohne HEAD-Anfragen, Bots und Vorschauen und ohne Nutzer, die Artikel bearbeiten dürfen.

**Status:** umgesetzt in 1.8.0

### IF-18 · MUSS

Die Mail „Bestätigt“ hängt AGB und Widerrufsbelehrung als PDF an (§ 312f BGB, siehe G-03). Das PDF wird neu erzeugt, sobald sich eine der Seiten ändert. Ältere Fassungen bleiben geschützt erhalten.

**Status:** umgesetzt in 1.8.3

### IF-19 · MUSS

Die Bewertungsbitte steht nur in der Mail zur Rechnung, nur mit Haken und nur, wenn der Link „Google-Bewertung“ gesetzt ist. Stornorechnung, Korrektur und Kautionsabrechnung bleiben ohne Bitte.

**Status:** umgesetzt in 1.8.4
