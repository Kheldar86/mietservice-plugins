# KTO – Warenkorb und Kundenkonto

> Übernommen am 27.10.2026 aus der [Anforderungsspezifikation](https://claude.ai/artifact/5zkRqvGmXM9q7RA5rLBiDk) (Stand 21.10.2026). Den Status je Anforderung haben wir anhand der CHANGELOGs bis msb-rental 1.8.4 und Bridge 0.4.2 nachgetragen. Ab jetzt ist diese Datei die maßgebliche Fassung.

## Übersicht

| ID | Stufe | Status |
|---|---|---|
| KTO-01 | MUSS | umgesetzt (bis 1.2.7) |
| KTO-02 | MUSS | umgesetzt (bis 1.2.7) |
| KTO-03 | MUSS | umgesetzt (bis 1.2.7) |
| KTO-04 | MUSS | umgesetzt (bis 1.2.7) |
| KTO-05 | MUSS | umgesetzt (bis 1.2.7) |
| KTO-06 | MUSS | umgesetzt (bis 1.2.7) |
| KTO-07 | MUSS | umgesetzt in 1.5.0 |
| KTO-08 | MUSS | umgesetzt (bis 1.2.7) |

### KTO-01 · MUSS

Der Warenkorb liegt serverseitig, verknüpft über das Cookie msb_cart (32 Hex-Zeichen, httponly, SameSite Lax), Lebensdauer 3 Tage.

**Status:** umgesetzt (bis 1.2.7)

### KTO-02 · MUSS

Beim Hinzufügen wird die Verfügbarkeit unter Einrechnung der Zeilen im Warenkorb geprüft. Gleiche Zeilen (Artikel und Zeitraum) werden zusammengeführt.

**Status:** umgesetzt (bis 1.2.7)

### KTO-03 · MUSS

Gäste werden über die E-Mail-Adresse (klein geschrieben) als Kunde erkannt, eingeloggte Nutzer über ihr Konto. Im Kundendatensatz werden nur als Profilfeld markierte Formularfelder gespeichert.

**Status:** umgesetzt (bis 1.2.7)

### KTO-04 · MUSS

Kundenkonten (Rolle msb_customer) sind abschaltbar und optional Pflicht (live: aktiv, nicht Pflicht). Passwort mindestens 8 Zeichen, E-Mail eindeutig.

**Status:** umgesetzt (bis 1.2.7)

### KTO-05 · MUSS

Ein neues Konto wird per Link mit gehashtem Schlüssel bestätigt. Danach werden alle Gastbuchungen mit dieser E-Mail dem Konto zugeordnet.

**Status:** umgesetzt (bis 1.2.7)

### KTO-06 · MUSS

Kunden haben keinen Zugang zum WordPress-Backend und sehen keine Admin-Leiste.

**Status:** umgesetzt (bis 1.2.7)

### KTO-07 · MUSS

„Mein Konto“ zeigt die Buchungen des Kunden und offene Zahlungen. Kunden können ihre Buchung dort selbst stornieren, die Gebühr wird nach PAY-16/PAY-17 berechnet und vor dem Bestätigen angezeigt (Entscheidung 21.10.2026).

**Status:** umgesetzt in 1.5.0

### KTO-08 · MUSS

Wird ein Kunde gelöscht, bleiben seine Buchungen ohne Kundenbezug erhalten. Fristen nach DSG-01 bis DSG-07.

**Status:** umgesetzt (bis 1.2.7)
