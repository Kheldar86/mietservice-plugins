# PAY – Zahlung, Kaution, Stornierung und Belege

> Übernommen am 27.10.2026 aus der [Anforderungsspezifikation](https://claude.ai/artifact/5zkRqvGmXM9q7RA5rLBiDk) (Stand 21.10.2026). Den Status je Anforderung haben wir anhand der CHANGELOGs bis msb-rental 1.8.4 und Bridge 0.4.2 nachgetragen. Ab jetzt ist diese Datei die maßgebliche Fassung.

## Übersicht

| ID | Stufe | Status |
|---|---|---|
| PAY-01 | MUSS | umgesetzt (bis 1.2.7) |
| PAY-02 | MUSS | umgesetzt (bis 1.2.7) |
| PAY-03 | MUSS | umgesetzt (bis 1.2.7) |
| PAY-04 | MUSS | umgesetzt in 1.2.8 |
| PAY-05 | MUSS | umgesetzt (bis 1.2.7) |
| PAY-06 | MUSS | umgesetzt (bis 1.2.7) |
| PAY-07 | MUSS | umgesetzt (bis 1.2.7) |
| PAY-08 | MUSS | umgesetzt (bis 1.2.7) |
| PAY-09 | MUSS | umgesetzt (bis 1.2.7) |
| PAY-10 | MUSS | umgesetzt (bis 1.2.7) |
| PAY-11 | MUSS | umgesetzt (bis 1.2.7) |
| PAY-12 | MUSS | umgesetzt (bis 1.2.7) |
| PAY-13 | MUSS | umgesetzt (bis 1.2.7) |
| PAY-14 | MUSS | umgesetzt in 1.5.5 und 1.6.0 (Barkaution, Einbehalt mit Kautionsabrechnung) |
| PAY-15 | MUSS | umgesetzt (bis 1.2.7); seit 1.5.0 Kasten „Stornieren“ im Backend, seit 1.7.1 Erstattungen einzeln mit Datum |
| PAY-16 | MUSS | umgesetzt in 1.5.0 |
| PAY-17 | MUSS | umgesetzt in 1.5.0 |
| PAY-18 | MUSS | umgesetzt in den AGB (Abschnitt 6, live seit 21.10.2026) |
| PAY-19 | MUSS | umgesetzt in 1.6.0 |
| PAY-20 | MUSS | umgesetzt in 1.6.0 |
| PAY-21 | MUSS | umgesetzt in 1.6.0 |
| PAY-22 | MUSS | umgesetzt in 1.6.0 |
| PAY-23 | MUSS | umgesetzt in 1.6.0, abweichend: Einbehalt als eigene Kautionsabrechnung (KAU-JJJJ-0001) statt Zeile auf der Rechnung. Offen: Bestätigung Steuerberater |
| PAY-24 | MUSS | umgesetzt in 1.6.0 (auch Download in „Mein Konto“) |
| PAY-25 | MUSS | umgesetzt in 1.6.0 |

## Zahlarten und Anzahlung

### PAY-01 · MUSS

Zahlarten sind Stripe und PayPal (einzeln aktivierbar) sowie offline (Barzahlung/Überweisung). Offline wird angeboten, wenn es aktiviert ist oder kein Online-Anbieter aktiv ist. Live: nur Stripe.

**Status:** umgesetzt (bis 1.2.7)

### PAY-02 · MUSS

Anzahlungsmodus: keine, Prozent, fester Betrag, voll. Live: voll.

**Status:** umgesetzt (bis 1.2.7)

### PAY-03 · MUSS

Zahlungsposten haben den Typ Anzahlung, Restzahlung oder Kaution und einen der Status offen, bezahlt, geplant, angefordert, blockiert, freigegeben, einbehalten, erstattet, fehlgeschlagen, storniert.

**Status:** umgesetzt (bis 1.2.7)

### PAY-04 · MUSS

Die Anzahlung gilt nur für Online-Zahlung.

**Status:** umgesetzt in 1.2.8

## Ablauf bei Stripe und PayPal

### PAY-05 · MUSS (Stripe)

Ist eine Anzahlung fällig, startet eine Checkout-Session im Zahlmodus; ist zusätzlich eine Kaution zu blockieren, wird die Karte für später gespeichert. Ist nur die Kaution fällig, startet eine Session im Setup-Modus. Ist nichts fällig, keine Weiterleitung.

**Status:** umgesetzt (bis 1.2.7)

### PAY-06 · MUSS (PayPal)

Die Kaution wird nur mitautorisiert, wenn das Mietende höchstens 28 Tage in der Zukunft liegt (Absicht AUTHORIZE).

**Status:** umgesetzt (bis 1.2.7)

### PAY-07 · MUSS

Rückkehr und Webhook schließen die Zahlung idempotent ab. Die Stripe-Session-ID bzw. das PayPal-Token muss zur gespeicherten Referenz passen.

**Status:** umgesetzt (bis 1.2.7)

### PAY-08 · MUSS

Der Stripe-Webhook prüft die Signatur (HMAC-SHA256, 300 s Toleranz) und verarbeitet nur checkout.session.completed.

**Status:** umgesetzt (bis 1.2.7)

## Kaution

### PAY-09 · MUSS

Kautionsmodus „blockieren“: Die Kaution wird als Vormerkung auf der Karte gehalten, nicht abgebucht. Live: aktiv, 2 Tage vor Mietbeginn.

**Status:** umgesetzt (bis 1.2.7)

### PAY-10 · MUSS

Die Blockierung startet, wenn Kaution > 0, Status offen oder bestätigt und frühester Mietbeginn ≤ heute + Vorlauftage. Zuerst wird die gespeicherte Stripe-Karte ohne Kunden belastet; klappt das nicht, geht ein einziger Zahlungslink raus (Status angefordert).

**Status:** umgesetzt (bis 1.2.7)

### PAY-11 · MUSS

Eine Blockierung gilt bei Stripe 7 Tage, bei PayPal 29 Tage.

**Status:** umgesetzt (bis 1.2.7)

### PAY-12 · MUSS

Bei Rückgabe wird die Kaution automatisch freigegeben, wenn „automatisch freigeben“ aktiv ist (live: ja). Bei Stornierung wird eine blockierte Kaution ohne E-Mail freigegeben und offene Anforderungen werden storniert. Bei Bestätigung wird eine Blockierung versucht.

**Status:** umgesetzt (bis 1.2.7)

### PAY-13 · MUSS

Einbehalt (auch teilweise) ist höchstens bis zur Höhe der Kaution möglich.

**Status:** umgesetzt (bis 1.2.7)

### PAY-14 · MUSS

Bei Offline-Zahlung wird die Kaution bar bei Abholung genommen und als manuelle Kaution erfasst; bei Rückgabe wird sie manuell freigegeben. SOLL: Online-Zahlung bleibt die bevorzugte Zahlart, offline wird nur angeboten, wenn es in den Einstellungen aktiviert ist.

**Status:** umgesetzt in 1.5.5 und 1.6.0 (Barkaution, Einbehalt mit Kautionsabrechnung)

## Stornierung und Erstattung

### PAY-15 · MUSS

Erstattung nur für bezahlte Posten, die keine Kaution sind. Teilerstattung ist erlaubt; bei voller Erstattung wird der Posten „erstattet“.

**Status:** umgesetzt (bis 1.2.7); seit 1.5.0 Kasten „Stornieren“ im Backend, seit 1.7.1 Erstattungen einzeln mit Datum

### PAY-16 · MUSS

Storniert der Kunde spätestens 7 Tage (168 Stunden) vor Abholung, ist die Stornierung kostenlos und die bezahlte Miete wird voll erstattet. Bezugspunkt aller Stornofristen ist der Mietbeginn um 0:00 Uhr (Europe/Berlin), z. B. Mietbeginn Sa → 48-Stunden-Grenze Do 0:00 Uhr (Entscheidung 21.10.2026).

**Status:** umgesetzt in 1.5.0

### PAY-17 · MUSS

Storniert der Kunde zwischen 7 Tagen und 48 Stunden vor Abholung, behält der Vermieter 25 % der Miete ein; storniert er weniger als 48 Stunden vorher, 50 %. Der Rest wird erstattet. Grundlage der Prozente ist nur die Tagesmiete (nach Rabatten), nicht die Grundgebühr (Entscheidung 21.10.2026). Die Erstattung läuft bei Online-Zahlung automatisch und sofort über den Zahlungsanbieter; Administratoren und Gesellschafter erhalten dazu eine Info-Mail mit Buchung, Gebühr und Erstattungsbetrag. Schlägt die Erstattung fehl, nennt die Mail den Fehler und die Erstattung bleibt im Backend manuell auslösbar.

**Status:** umgesetzt in 1.5.0

### PAY-18 · MUSS

Nicht-Erscheinen und Stornierung durch den Vermieter sind in den AGB geregelt (G-03). ❓ F-01.

**Status:** umgesetzt in den AGB (Abschnitt 6, live seit 21.10.2026)

## Belege

### PAY-19 · MUSS

Das Plugin erzeugt Rechnungen als PDF und schickt sie per E-Mail an den Kunden.

**Status:** umgesetzt in 1.6.0

### PAY-20 · MUSS

Rechnungsnummer = Präfix + Jahr + „-“ + laufende Nummer, vierstellig (z. B. RE-2027-0001). Die laufende Nummer beginnt jedes Kalenderjahr wieder bei 0001, ist lückenlos und wird nie doppelt vergeben. Das Präfix ist „RE“ und steht in den Einstellungen.

**Status:** umgesetzt in 1.6.0

### PAY-21 · MUSS

Die Rechnung enthält die Pflichtangaben nach § 14 Abs. 4 UStG ohne Umsatzsteuerausweis und mit dem Hinweis „Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.“: Name und Anschrift der GbR und des Kunden, Steuernummer, Rechnungsdatum, Rechnungsnummer, Leistung (Artikel und Mietzeitraum) und Entgelt.

**Status:** umgesetzt in 1.6.0

### PAY-22 · MUSS

Eine erstellte Rechnung wird nie geändert oder gelöscht. Korrekturen und Erstattungen laufen über eine Rechnungskorrektur mit eigener Nummer aus demselben Nummernkreis.

**Status:** umgesetzt in 1.6.0

### PAY-23 · MUSS

Die Kaution selbst ist kein Rechnungsposten. Ein Einbehalt aus der Kaution (z. B. bei Schaden) erscheint als eigene Zeile „Schadenersatz aus Kaution“ mit Begründung auf der Rechnung nach Rückgabe. Vom Steuerberater zu bestätigen.

**Status:** umgesetzt in 1.6.0, abweichend: Einbehalt als eigene Kautionsabrechnung (KAU-JJJJ-0001) statt Zeile auf der Rechnung. Offen: Bestätigung Steuerberater

### PAY-24 · MUSS

Rechnungs-PDFs sind nicht öffentlich abrufbar und stehen im Backend zur Verfügung. SOLL: Der Kunde kann sie in „Mein Konto“ herunterladen.

**Status:** umgesetzt in 1.6.0 (auch Download in „Mein Konto“)

### PAY-25 · MUSS

Die Rechnung wird erstellt und verschickt, sobald die Buchung den Status „zurückgegeben“ erreicht; pro Buchung gibt es genau eine Rechnung. Bei einer Stornierung mit Gebühr > 0 wird sofort eine Rechnung über die Stornogebühr erstellt; kostenlose Stornierungen erhalten keine Rechnung. Als Zahlungsbestätigung bei der Buchung dient die Quittung des Zahlungsanbieters.

**Status:** umgesetzt in 1.6.0
