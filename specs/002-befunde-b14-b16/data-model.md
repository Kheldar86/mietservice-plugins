# Neufassungen: Befunde B-14 bis B-16 beheben

**Feature**: [spec.md](spec.md) · Fakten: [research.md](research.md) · Format: [001/contracts/anforderung-format.md](../001-nachtraege-nummerieren/contracts/anforderung-format.md)

Die Texte und Status unten werden **wörtlich** übernommen. Stufe bleibt jeweils `MUSS`. Status in Übersicht und Abschnitt zeichengleich.

## ADM-04 (B-14)

**Text neu:**

> Manuelle Buchung im Backend: Die Buchungsregeln werden ignoriert (VFG-04). Die Verfügbarkeit wird geprüft, außer „Verfügbarkeit nicht prüfen (Überbuchung zulassen)“ ist angehakt (Standard: nicht angehakt). Zahlung bar bei Abholung oder online (ADM-11). Vorausgewählt ist der Status „Angefragt“ (Einzelheiten ADM-08).

**Status neu:** `umgesetzt in 1.5.7 (Zahlung seit 1.5.5, Text angepasst am 01.11.2026, B-14)`

**Entfällt:** `überholt in Teilen, siehe ADM-08 und ADM-11 (B-14)`

## IF-06 (B-15)

**Text neu:**

> Mailvorlagen mit Betreff und Text in den Einstellungen: unter „E-Mails“ Team-Benachrichtigung, Eingang, Bestätigung, Stornierung, Zahlungslink und Kaution freigegeben, jede einzeln abschaltbar; unter „Belege“ die Mail zu „Rechnung, Stornorechnung, Korrektur“ und zur „Kautionsabrechnung“, gemeinsam abschaltbar (IF-16). Platzhalter laut Liste in den Einstellungen, inklusive {field:schlüssel} für Formularfelder. Einen festen Text ohne Vorlage haben: die Bitte um Bestätigung bei manuellen Buchungen (BKG-18), die Info an das Team nach dieser Bestätigung (BKG-19), die Storno-Info an das Team, die Bestätigung der E-Mail-Adresse beim Kundenkonto und die Kopie eines Belegs an die Buchungsadresse.

**Status neu:** `umgesetzt in 1.6.2 (E-Mail-Vorlagen seit 1.2.7, Beleg-Vorlagen seit 1.6.0, Text angepasst am 01.11.2026, B-15)`

**Entfällt:** `seit 1.6.0 Beleg-Mails, seit 1.8.3 AGB und Widerrufsbelehrung als PDF an der Mail „Bestätigt“; siehe B-15`. Der AGB-Anhang steht in IF-18.

## DSG-02 (B-16)

**Text neu:**

> Buchungen mit Zahlung oder Beleg, ihre Positionen, Zahlungen und Belege werden 8 Jahre ab Ende des Kalenderjahres aufbewahrt, in dem der letzte Beleg ausgestellt oder die letzte Zahlung gebucht wurde, je nachdem, was später liegt (§ 147 AO, § 257 HGB). Danach werden sie automatisch gelöscht. Siehe F-13.

**Status neu:** `umgesetzt in 1.7.0 (Regel „Buchungen mit Zahlung oder Beleg“, läuft im Probelauf, Text angepasst am 01.11.2026, B-16)`

**Entfällt:** `; siehe B-16`

## Befundliste (`befunde-und-fragen.md`)

| Zeile | Spalte „Status heute“ neu |
|---|---|
| B-14 | `behoben am 01.11.2026 (Spec angepasst)` |
| B-15 | `behoben am 01.11.2026 (Spec angepasst)` |
| B-16 | `behoben am 01.11.2026 (Spec angepasst), offene Frage F-13` |

**Überschrift:** „## Fragen an Moritz (alle beantwortet)“ → „## Fragen an Moritz“

**Neu am Ende der Fragenliste:**

> - **F-13** Offen (01.11.2026): DSG-02 bewahrt alle Belege einer Buchung bis 8 Jahre nach dem letzten Beleg bzw. der letzten Zahlung auf. Ein früher Beleg derselben Buchung bleibt damit ggf. länger als gesetzlich nötig. Ist das mit der Datenminimierung (Art. 5 DSGVO) vereinbar, oder soll jeder Beleg nach seiner eigenen Frist gelöscht werden? Mit Steuerberater bzw. Datenschutz klären.
