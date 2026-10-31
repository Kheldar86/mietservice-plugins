# BKG – Buchungsprozess

> Übernommen am 27.10.2026 aus der [Anforderungsspezifikation](https://claude.ai/artifact/5zkRqvGmXM9q7RA5rLBiDk) (Stand 21.10.2026). Den Status je Anforderung haben wir anhand der CHANGELOGs bis msb-rental 1.8.4 und Bridge 0.4.2 nachgetragen. Ab jetzt ist diese Datei die maßgebliche Fassung.

## Übersicht

| ID | Stufe | Status |
|---|---|---|
| BKG-01 | MUSS | umgesetzt (bis 1.2.7) |
| BKG-02 | MUSS | umgesetzt in 1.2.8 (zusätzlich höchstens 20 Versuche je IP in 10 Minuten) |
| BKG-03 | SOLL | umgesetzt in 1.2.8 |
| BKG-04 | MUSS | umgesetzt (bis 1.2.7); seit 1.8.4 zusätzlich der freiwillige Haken zur Google-Bewertung |
| BKG-05 | MUSS | umgesetzt in 1.2.8 |
| BKG-06 | MUSS | umgesetzt (bis 1.2.7) |
| BKG-07 | MUSS | umgesetzt in 1.3.0 (live mit Präfix MB-, z. B. MB-2026-0019) |
| BKG-08 | MUSS | umgesetzt (bis 1.2.7) |
| BKG-10 | MUSS | umgesetzt (bis 1.2.7) |
| BKG-11 | MUSS | umgesetzt (bis 1.2.7) |
| BKG-12 | MUSS | umgesetzt (bis 1.2.7) |
| BKG-13 | MUSS | umgesetzt (bis 1.2.7) |
| BKG-14 | MUSS | umgesetzt (bis 1.2.7); seit 1.3.0 nur durch Administratoren |
| BKG-15 | MUSS | umgesetzt (bis 1.2.7) |
| BKG-16 | MUSS | umgesetzt in 1.3.0 |
| BKG-17 | MUSS | umgesetzt in 1.5.0 |
| BKG-18 | MUSS | umgesetzt in 1.5.7 (seit 1.5.2, 1.5.6) |
| BKG-19 | MUSS | umgesetzt in 1.5.7 |
| BKG-20 | MUSS | umgesetzt in 1.8.0 |
| BKG-21 | SOLL | umgesetzt in 1.8.4 |

## Ablauf aus Kundensicht

1. Kunde wählt auf der Artikelseite Zeitraum und Menge. Der Kalender zeigt freie Tage (VFG-05), der Preis wird live berechnet (PRS).
2. „In den Warenkorb“ prüft die Verfügbarkeit erneut (KTO-02).
3. Checkout (/buchung/): Formular ausfüllen, Zahlart wählen, optional Konto anlegen, „Zahlungspflichtig buchen“.
4. Bei Online-Zahlung Weiterleitung zu Stripe bzw. PayPal, danach Rückkehr auf die Bestätigungsseite.
5. E-Mails an Kunde und Betreiber (IF-06).

## Anforderungen an den Checkout

### BKG-01 · MUSS

Der Checkout verarbeitet die Schritte in genau dieser Reihenfolge und bricht beim ersten Fehler mit deutscher Meldung ab: (1) Honeypot-Feld msb_website leer und Rate-Limit nicht überschritten, (2) Warenkorb nicht leer, (3) Formular gültig, (4) Zahlart zulässig, (5) optional Konto anlegen, (6) Kunde anlegen oder aktualisieren, (7) Buchung anlegen, (8) Warenkorb leeren, (9) bei Online-Zahlung Weiterleitung starten; schlägt das fehl, Buchung stornieren und Warenkorb wiederherstellen; ist nichts zu zahlen, Buchung abschließen, (10) Bestätigungs-URL zurückgeben.

**Status:** umgesetzt (bis 1.2.7)

### BKG-02 · MUSS

Rate-Limit höchstens 5 Buchungen je IP in 10 Minuten.

**Status:** umgesetzt in 1.2.8 (zusätzlich höchstens 20 Versuche je IP in 10 Minuten)

### BKG-03 · SOLL

Der Checkout ist mit einem Nonce gegen fremde Formulare geschützt.

**Status:** umgesetzt in 1.2.8

### BKG-04 · MUSS

Formularfelder sind in den Einstellungen konfigurierbar. Live: Vorname, Nachname, E-Mail, Telefon, Straße, PLZ, Ort (Pflicht), Abholzeit (Vormittag 9–12 Uhr oder Nachmittag 12–17 Uhr, optional), Nachricht, AGB-Bestätigung (Pflicht, Link /agb), Datenschutz (Pflicht). Das erste E-Mail-Feld ist die Kunden-E-Mail, das erste Telefonfeld die Telefonnummer.

**Status:** umgesetzt (bis 1.2.7); seit 1.8.4 zusätzlich der freiwillige Haken zur Google-Bewertung

### BKG-05 · MUSS

Das Anlegen einer Buchung ist gegen gleichzeitige Doppelbuchung geschützt (Datenbank-Sperre, 10 s). Wird die Sperre nicht erhalten, bricht die Buchung mit Fehlermeldung ab.

**Status:** umgesetzt in 1.2.8

### BKG-06 · MUSS

Vor dem Speichern wird jede Zeile geprüft, inklusive der vorherigen Zeilen derselben Buchung.

**Status:** umgesetzt (bis 1.2.7)

### BKG-07 · MUSS

Buchungsnummer = Präfix + Jahr + „-“ + laufende Nummer vierstellig (z. B. MSB2027-0016). Die laufende Nummer beginnt jedes Kalenderjahr wieder bei 0001.

**Status:** umgesetzt in 1.3.0 (live mit Präfix MB-, z. B. MB-2026-0019)

### BKG-08 · MUSS

Jede Buchung hat ein geheimes Token (32 Hex-Zeichen) für Kundenlinks ohne Login.

**Status:** umgesetzt (bis 1.2.7)

## Buchungsstatus

| Status | Bedeutung | Belegt Bestand | E-Mail an Kunde beim Eintritt |
|---|---|---|---|
| awaiting_payment (wartet auf Zahlung) | Weiterleitung zum Zahlungsanbieter läuft | ja | nein |
| pending (offen) | Buchung eingegangen, noch nicht bestätigt | ja | „Eingegangen“ |
| confirmed (bestätigt) | Buchung angenommen | ja | „Bestätigt“ |
| picked_up (abgeholt) | Artikel beim Kunden | ja | nein |
| returned (zurückgegeben) | Artikel zurück | nein | nein (ggf. „Kaution freigegeben“) |
| cancelled (storniert) | Buchung storniert | nein | „Storniert“ (außer bei Zahlungsabbruch und Zeitablauf) |

### BKG-10 · MUSS

Eine neue Buchung mit Online-Zahlung startet in awaiting_payment. Nach erfolgreicher Zahlung (oder wenn nichts fällig ist) wechselt sie in den Standardstatus (live: bestätigt), erst dann gehen die E-Mails raus.

**Status:** umgesetzt (bis 1.2.7)

### BKG-11 · MUSS

Eine Buchung ohne Online-Zahlung startet direkt im Standardstatus mit E-Mails.

**Status:** umgesetzt (bis 1.2.7)

### BKG-12 · MUSS

Bricht der Kunde beim Zahlungsanbieter ab, wird die Buchung ohne E-Mail storniert und der Warenkorb wiederhergestellt.

**Status:** umgesetzt (bis 1.2.7)

### BKG-13 · MUSS

Eine Buchung, die länger als die Zahlungsfrist (mindestens 15 Minuten, live 60, vorerst so bestätigt) in awaiting_payment steht, wird stündlich ohne E-Mail storniert, nachdem bei PayPal geprüft wurde, ob doch gezahlt wurde.

**Status:** umgesetzt (bis 1.2.7)

### BKG-14 · MUSS

Wird eine stornierte oder zurückgegebene Buchung wieder in einen belegenden Status gesetzt, wird die Verfügbarkeit geprüft (ohne Buchungsregeln).

**Status:** umgesetzt (bis 1.2.7); seit 1.3.0 nur durch Administratoren

### BKG-15 · MUSS

Jeder Statuswechsel löst den Hook msb_booking_status_changed aus. E-Mails nur bei bestätigt und storniert.

**Status:** umgesetzt (bis 1.2.7)

### BKG-16 · MUSS

Erlaubt sind nur diese Statusübergänge: awaiting_payment → pending/confirmed/cancelled; pending → confirmed/cancelled; confirmed → picked_up/cancelled; picked_up → returned; returned und cancelled nur durch den Admin zurück.

**Status:** umgesetzt in 1.3.0

### BKG-17 · MUSS

Stornierung durch den Kunden mit Gebühr nach G-04.

**Status:** umgesetzt in 1.5.0

## Ergänzt am 31.10.2026 (Funktionen seit 1.5.1)

Diese Nummern wurden nachträglich für Funktionen vergeben, die schon live waren (Feature 001).

### BKG-18 · MUSS

Bei einer manuellen Buchung bestätigt der Kunde Mietbedingungen und Datenschutz selbst über einen Link per Mail. Bis dahin stehen die Felder auf „Offen“, danach auf „Ja“ mit Zeitpunkt. Der Link steht in der Mail „Bestätigt“ bzw. „Eingegangen“, eine eigene Mail nur bei abgeschalteter Vorlage oder über „Link erneut senden“.

**Status:** umgesetzt in 1.5.7 (seit 1.5.2, 1.5.6)

### BKG-19 · MUSS

Bestätigt der Kunde per Link, wird eine angefragte Buchung „Bestätigt“. Er erhält die Mail „Bestätigt“, das Team eine Info an die Buchungsadresse.

**Status:** umgesetzt in 1.5.7

### BKG-20 · MUSS

Buchungen nach einem Klick auf den Kurzlink tragen die Quelle „Webseite über Kleinanzeigen“ (Liste, Buchung, Team-Mail). Die Herkunft hängt am Warenkorb, kein neues Cookie.

**Status:** umgesetzt in 1.8.0

### BKG-21 · SOLL

Freiwilliger, nicht verpflichtender Haken im Buchungsformular und auf der Bestätigungsseite für Telefonbuchungen: Einwilligung in eine einmalige Bewertungsbitte.

**Status:** umgesetzt in 1.8.4
