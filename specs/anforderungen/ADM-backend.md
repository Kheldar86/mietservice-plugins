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
