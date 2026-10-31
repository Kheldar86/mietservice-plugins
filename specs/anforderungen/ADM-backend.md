# ADM – Verwaltung im Backend

> Übernommen am 27.10.2026 aus der [Anforderungsspezifikation](https://claude.ai/artifact/5zkRqvGmXM9q7RA5rLBiDk) (Stand 21.10.2026). Den Status je Anforderung haben wir anhand der CHANGELOGs bis msb-rental 1.8.4 und Bridge 0.4.2 nachgetragen. Ab jetzt ist diese Datei die maßgebliche Fassung.

## Übersicht

| ID | Stufe | Status |
|---|---|---|
| ADM-01 | MUSS | umgesetzt (bis 1.2.7); seit 1.6.0 und 1.7.0 zusätzlich die Tabs Belege und Löschfristen |
| ADM-02 | MUSS | umgesetzt (bis 1.2.7) |
| ADM-03 | MUSS | umgesetzt (bis 1.2.7) |
| ADM-04 | MUSS | umgesetzt (bis 1.2.7) |
| ADM-05 | MUSS | umgesetzt (bis 1.2.7) |
| ADM-06 | MUSS | umgesetzt in 1.7.0 (Löschsperre) |
| ADM-07 | MUSS | umgesetzt in 1.5.1 |
| ADM-08 | MUSS | umgesetzt in 1.5.7 (Zahlung seit 1.5.5) |
| ADM-09 | MUSS | umgesetzt in 1.5.3 |
| ADM-10 | MUSS | umgesetzt in 1.5.3 |
| ADM-11 | MUSS | umgesetzt in 1.5.5 |
| ADM-12 | MUSS | umgesetzt in 1.6.7 (seit 1.6.3) |
| ADM-13 | MUSS | umgesetzt in 1.6.5 |
| ADM-14 | MUSS | umgesetzt in 1.7.2 |

### ADM-01 · MUSS

Einstellungen in den Tabs Allgemein, Regeln, Preise, Zahlungen, Konten, E-Mails, KI. Grenzen beim Speichern u. a.: Nachkommastellen ≤ 4, Kalender 1 bis 3 Monate, Zahlungsfrist ≥ 15 Minuten.

**Status:** umgesetzt (bis 1.2.7); seit 1.6.0 und 1.7.0 zusätzlich die Tabs Belege und Löschfristen

### ADM-02 · MUSS

Buchungsliste mit Schnell-Status, Sammelaktionen (Status, Löschen), Bearbeiten mit optionaler Kundenbenachrichtigung, E-Mail erneut senden, Löschen.

**Status:** umgesetzt (bis 1.2.7)

### ADM-03 · MUSS

Zahlungsaktionen je Buchung: Kaution freigeben, einbehalten (auch teilweise), erstatten, Kaution blockieren, Zahlungslink senden, manuelle Zahlung erfassen, manuelle Kaution freigeben.

**Status:** umgesetzt (bis 1.2.7)

### ADM-04 · MUSS

Manuelle Buchung durch den Admin: Regeln werden ignoriert, Prüfung optional abschaltbar, Zahlart offline, Standardstatus „bestätigt“.

**Status:** umgesetzt (bis 1.2.7)

### ADM-05 · MUSS

CSV-Export mit Semikolon, UTF-8-BOM und Schutz gegen Formel-Injektion.

**Status:** umgesetzt (bis 1.2.7)

### ADM-06 · MUSS

Löschen einer Buchung entfernt Buchung, Positionen und Zahlungen endgültig. Eine Buchung mit Zahlung oder Rechnung darf vor Ablauf der Aufbewahrungsfrist nicht gelöscht werden (DSG-02).

**Status:** umgesetzt in 1.7.0 (Löschsperre)

### ADM-07 · MUSS

Kundenangaben einer Buchung lassen sich im Backend bearbeiten (mit der Berechtigung für Buchungen), auf Wunsch auch im Kundenstamm. Bestätigungen wie AGB und Datenschutz bleiben unverändert.

**Status:** umgesetzt in 1.5.1

### ADM-08 · MUSS

Vorauswahl bei neuer manueller Buchung: Status „Angefragt“, Zahlung „Bar bei Abholung“, „Kunden per E-Mail informieren“ angehakt.

**Status:** umgesetzt in 1.5.7 (Zahlung seit 1.5.5)

### ADM-09 · MUSS

Manuell angelegte Buchungen schicken immer die Team-Mail an die Buchungsadresse, unabhängig von „Kunden informieren“. Betreff und erste Zeile nennen „manuell angelegt von …“.

**Status:** umgesetzt in 1.5.3

### ADM-10 · MUSS

Die Buchung speichert, wer sie im Backend angelegt hat. Liste und Buchung zeigen es. Filter „Quelle“ in der Buchungsliste und im CSV-Export.

**Status:** umgesetzt in 1.5.3

### ADM-11 · MUSS

Manuelle Buchung mit Auswahl „Bar bei Abholung (Miete und Kaution)“ oder „Online“. Bei Online enthält die Kundenmail einen Bezahlknopf für die Miete.

**Status:** umgesetzt in 1.5.5

### ADM-12 · MUSS

Unter 782 px Breite zeigen alle Vermietungsseiten im Backend Tabellen als Karten. Am Computer bleiben die Tabellen.

**Status:** umgesetzt in 1.6.7 (seit 1.6.3)

### ADM-13 · MUSS

Auf dem Handy stehen harmlose Aktionen links, unwiderrufliche rechts und deutlich getrennt.

**Status:** umgesetzt in 1.6.5

### ADM-14 · MUSS

Buchungsliste: „Test“ hinter der Buchungsnummer, Filter „Tests ausblenden / Nur Tests“.

**Status:** umgesetzt in 1.7.2
