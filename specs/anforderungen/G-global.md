# G – Globale Restriktionen

> Übernommen am 27.10.2026 aus der [Anforderungsspezifikation](https://claude.ai/artifact/5zkRqvGmXM9q7RA5rLBiDk) (Stand 21.10.2026). Den Status je Anforderung haben wir anhand der CHANGELOGs bis msb-rental 1.8.4 und Bridge 0.4.2 nachgetragen. Ab jetzt ist diese Datei die maßgebliche Fassung.

Diese Regeln gelten für beide Plugins und gehen jeder Einzelanforderung vor.

## Übersicht

| ID | Stufe | Status |
|---|---|---|
| G-01 | MUSS | Regel, gilt dauerhaft |
| G-02 | MUSS | umgesetzt (bis 1.2.7); seit 1.6.0 und 1.7.0 zusätzlich die Tabs Belege und Löschfristen |
| G-03 | MUSS | umgesetzt (bis 1.2.7), im KI-Assistenten |
| G-04 | MUSS | umgesetzt in 1.5.0 |
| G-05 | MUSS | umgesetzt in 1.4.0 |
| G-06 | MUSS | umgesetzt (bis 1.2.7); Kleinunternehmerregelung gilt (§ 19 UStG) |
| G-07 | MUSS | umgesetzt (bis 1.2.7) |
| G-08 | MUSS | umgesetzt (bis 1.2.7) |
| G-09 | MUSS | umgesetzt (bis 1.2.7); seit 1.4.0 eigene Rechte je Bereich (ROL-03) |
| G-10 | MUSS | umgesetzt in Bridge 0.4.1 (Geheimnisse), 0.4.2 (Kundendaten); Schlüssel am 22.10.2026 rotiert |
| G-11 | MUSS | umgesetzt (bis 1.2.7); live aus. Belegtabelle bleibt seit 1.6.0 immer erhalten (Aufbewahrungspflicht) |
| G-12 | MUSS | umgesetzt (bis 1.2.7) |
| G-13 | MUSS | umgesetzt (bis 1.2.7) |
| G-14 | MUSS | umgesetzt (bis 1.2.7) |
| G-15 | SOLL | umgesetzt (bis 1.2.7) |

### G-01 · MUSS

Jede Änderung an msb-rental erhöht die Plugin-Version (Header und Konstante) und ergänzt die Release Notes. Keine stillen Änderungen im laufenden Release.

**Status:** Regel, gilt dauerhaft

### G-02 · MUSS

Alle Einstellungen von msb-rental liegen zentral im Plugin unter „Einstellungen“ (Tabs Allgemein, Regeln, Preise, Zahlungen, Konten, E-Mails, KI). Werte, die nur einen Artikel betreffen, liegen am Artikel.

**Status:** umgesetzt (bis 1.2.7); seit 1.6.0 und 1.7.0 zusätzlich die Tabs Belege und Löschfristen

### G-03 · MUSS

Mietbedingungen (Storno, Kaution, Haftung, Abholung, Rückgabe) stehen nur in den AGB (/agb). Artikeltexte verlinken darauf und wiederholen keine Bedingungen.

**Status:** umgesetzt (bis 1.2.7), im KI-Assistenten

### G-04 · MUSS

Stornierung ist gestaffelt: bis 7 Tage vor Abholung kostenlos, von 7 Tagen bis 48 Stunden vorher 25 % der Miete, unter 48 Stunden 50 % der Miete (Entscheidung 21.10.2026).

**Status:** umgesetzt in 1.5.0

### G-05 · MUSS

Der Mitgesellschafter hat die Rolle „Gesellschafter“ und kann damit Buchungen bearbeiten (auch die Rückgabe bestätigen) und neue Artikel anlegen, auch mit dem KI-Assistenten (ROL).

**Status:** umgesetzt in 1.4.0

### G-06 · MUSS

Der Steuerhinweis zu Preisen kommt aus einer Einstellung (price_note), nicht aus dem Code.

**Status:** umgesetzt (bis 1.2.7); Kleinunternehmerregelung gilt (§ 19 UStG)

### G-07 · MUSS

Zeitzone ist Europe/Berlin. Mietzeiträume sind Kalendertage (JJJJ-MM-TT) ohne Uhrzeit.

**Status:** umgesetzt (bis 1.2.7)

### G-08 · MUSS

Alle Beträge in Euro, Rechenergebnisse kaufmännisch auf 2 Nachkommastellen gerundet.

**Status:** umgesetzt (bis 1.2.7)

### G-09 · MUSS

Backend-Funktionen von msb-rental sind nur mit der Berechtigung aus msb_capability() erreichbar (Standard manage_options, per Filter änderbar).

**Status:** umgesetzt (bis 1.2.7); seit 1.4.0 eigene Rechte je Bereich (ROL-03)

### G-10 · MUSS

Geheimnisse (API-Schlüssel, Webhook-Secrets, Passwörter) erscheinen nie in Seiten, E-Mails, Exporten, Logs oder Antworten an eine KI.

**Status:** umgesetzt in Bridge 0.4.1 (Geheimnisse), 0.4.2 (Kundendaten); Schlüssel am 22.10.2026 rotiert

### G-11 · MUSS

Deinstallation löscht Tabellen, Einstellungen und Daten nur, wenn „Daten beim Löschen entfernen“ aktiv ist.

**Status:** umgesetzt (bis 1.2.7); live aus. Belegtabelle bleibt seit 1.6.0 immer erhalten (Aufbewahrungspflicht)

### G-12 · MUSS

Der Bestellbutton heißt „Zahlungspflichtig buchen“ (§ 312j BGB).

**Status:** umgesetzt (bis 1.2.7)

### G-13 · MUSS

Der Checkout verlangt die Bestätigung von AGB und Datenschutz als Pflichtfelder.

**Status:** umgesetzt (bis 1.2.7)

### G-14 · MUSS

Keine Änderung an der Website über die Bridge ohne ausdrückliche Freigabe eines Administrators.

**Status:** umgesetzt (bis 1.2.7)

### G-15 · SOLL

Oberfläche, Fehlermeldungen und E-Mails sind auf Deutsch.

**Status:** umgesetzt (bis 1.2.7)
