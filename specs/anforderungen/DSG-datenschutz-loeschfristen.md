# DSG – Datenschutz und Löschfristen

> Übernommen am 27.10.2026 aus der [Anforderungsspezifikation](https://claude.ai/artifact/5zkRqvGmXM9q7RA5rLBiDk) (Stand 21.10.2026). Den Status je Anforderung haben wir anhand der CHANGELOGs bis msb-rental 1.8.4 und Bridge 0.4.2 nachgetragen. Ab jetzt ist diese Datei die maßgebliche Fassung.

## Übersicht

| ID | Stufe | Status |
|---|---|---|
| DSG-01 | MUSS | umgesetzt in 1.7.0 |
| DSG-02 | MUSS | umgesetzt in 1.7.0 (Regel „Buchungen mit Zahlung oder Beleg“, läuft im Probelauf, Text angepasst am 01.11.2026, B-16) |
| DSG-03 | MUSS | umgesetzt in 1.7.0 (Hinweis „gesperrt bis …“) |
| DSG-04 | MUSS | umgesetzt in 1.7.0, läuft im Probelauf seit 25.10.2026 |
| DSG-05 | MUSS | umgesetzt in 1.7.0, läuft im Probelauf seit 25.10.2026; Buchungen ohne Zahlung nach Abschluss siehe DSG-08 |
| DSG-06 | MUSS | umgesetzt in 1.7.0 (Kasten „Daten löschen“ am Kunden); Vorschau und Ablauf siehe DSG-10, Hinweis an gesperrten Buchungen siehe DSG-11 |
| DSG-07 | MUSS | umgesetzt in 1.7.0, Probelauf; Umstellung auf „Löschung ausführen“ nach Blick ins Protokoll (Erinnerung 24.11.2026) |
| DSG-08 | MUSS | umgesetzt in 1.7.0 (im CHANGELOG nicht genannt) |
| DSG-09 | MUSS | umgesetzt in 1.7.0 |
| DSG-10 | MUSS | umgesetzt in 1.7.0 |
| DSG-11 | MUSS | umgesetzt in 1.7.0 |
| DSG-12 | MUSS | umgesetzt in 1.10.0 |

Die DSGVO nennt selbst keine festen Fristen. Sie verlangt, Daten nur so lange zu speichern wie nötig (Art. 5 Abs. 1 lit. e, Art. 17). Die konkreten Fristen ergeben sich aus den Aufbewahrungspflichten in AO und HGB; seit dem 1.1.2025 gelten für Buchungsbelege und Rechnungen 8 statt 10 Jahre (Viertes Bürokratieentlastungsgesetz). Die Fristen unten sind die übliche Empfehlung, keine Rechtsberatung.

### DSG-01 · MUSS

Personenbezogene Daten werden nur so lange gespeichert, wie es für die Buchung oder eine gesetzliche Aufbewahrungspflicht nötig ist.

**Status:** umgesetzt in 1.7.0

### DSG-02 · MUSS

Buchungen mit Zahlung oder Beleg, ihre Positionen, Zahlungen und Belege werden 8 Jahre ab Ende des Kalenderjahres aufbewahrt, in dem der letzte Beleg ausgestellt oder die letzte Zahlung gebucht wurde, je nachdem, was später liegt (§ 147 AO, § 257 HGB). Danach werden sie automatisch gelöscht. Die gemeinsame Aufbewahrung aller Belege einer Buchung bis zum Ablauf der jüngsten Frist ist gewollt (Entscheidung 01.11.2026, F-13).

**Status:** umgesetzt in 1.7.0 (Regel „Buchungen mit Zahlung oder Beleg“, läuft im Probelauf, Text angepasst am 01.11.2026, B-16)

### DSG-03 · MUSS

Während dieser Aufbewahrung sind die Daten für andere Zwecke gesperrt (Art. 18 DSGVO), z. B. keine Werbung.

**Status:** umgesetzt in 1.7.0 (Hinweis „gesperrt bis …“)

### DSG-04 · MUSS

Kundenkonto und Kundendatensatz ohne laufende Aufbewahrungspflicht werden 3 Jahre nach Ende des Jahres der letzten Buchung oder Anmeldung gelöscht (regelmäßige Verjährung, § 195 BGB).

**Status:** umgesetzt in 1.7.0, läuft im Probelauf seit 25.10.2026

### DSG-05 · MUSS

Abgebrochene Buchungen ohne Zahlung und nie bestätigte Kundenkonten werden nach 30 Tagen gelöscht.

**Status:** umgesetzt in 1.7.0, läuft im Probelauf seit 25.10.2026; Buchungen ohne Zahlung nach Abschluss siehe DSG-08

### DSG-06 · MUSS

Auf Antrag eines Kunden (Art. 17 DSGVO) löscht der Admin dessen Daten sofort. Daten, die unter DSG-02 fallen, werden stattdessen gesperrt.

**Status:** umgesetzt in 1.7.0 (Kasten „Daten löschen“ am Kunden); Vorschau und Ablauf siehe DSG-10, Hinweis an gesperrten Buchungen siehe DSG-11

### DSG-07 · MUSS

Die Löschung läuft automatisch über einen täglichen Cron und wird im Backend ohne personenbezogene Inhalte protokolliert.

**Status:** umgesetzt in 1.7.0, Probelauf; Umstellung auf „Löschung ausführen“ nach Blick ins Protokoll (Erinnerung 24.11.2026)

### DSG-08 · MUSS

Buchungen mit Status „zurückgegeben“ oder „storniert“ ohne Zahlung und Beleg werden 3 Jahre nach Ende des Jahres der letzten Änderung bzw. des Mietendes gelöscht.

**Status:** umgesetzt in 1.7.0 (im CHANGELOG nicht genannt)

### DSG-09 · MUSS

Die Löschfristen laufen zuerst im Probelauf: Es wird nur gezählt und protokolliert. „Löschung ausführen“ wird unter Einstellungen → Löschfristen eingeschaltet. Knopf „Jetzt prüfen“.

**Status:** umgesetzt in 1.7.0

### DSG-10 · MUSS

Kasten „Daten löschen (Art. 17 DSGVO)“ am Kunden, nur mit der Berechtigung zum Löschen: zeigt vorher, wie viele Buchungen gelöscht und welche wegen der Aufbewahrungspflicht gesperrt werden. Bei laufenden Buchungen nicht möglich. Kunden löschen, einzeln und als Sammelaktion, läuft über diesen Weg.

**Status:** umgesetzt in 1.7.0

### DSG-11 · MUSS

Eine gesperrte Buchung zeigt im Backend den Hinweis, dass sie nach Art. 18 DSGVO gesperrt ist und nur wegen der Aufbewahrungspflicht bis zu einem genannten Datum gespeichert bleibt.

**Status:** umgesetzt in 1.7.0

### DSG-12 · MUSS

Auslagenabrechnungen, ihre Auslagen und Belegdateien werden 8 Jahre ab Ende des Jahres der Abrechnung aufbewahrt (§ 147 AO) und danach gelöscht. Der tägliche Lauf (msb_cron_daily) folgt dem Schalter der Löschfristen (im Probelauf nur gezählt) und schreibt ins Löschprotokoll (Regel „Auslagenabrechnungen (8 Jahre)“). Offene Auslagen ohne Abrechnung werden nicht automatisch gelöscht. Die Dateien liegen unter uploads/msb-belege/auslagen/ ohne öffentlichen Zugriff und sind nur über das Backend mit Recht und Nonce abrufbar.

**Status:** umgesetzt in 1.10.0
