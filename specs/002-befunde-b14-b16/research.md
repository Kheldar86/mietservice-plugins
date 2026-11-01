# Research: Befunde B-14 bis B-16 beheben

**Feature**: [spec.md](spec.md) · **Stand**: 01.11.2026 · Grundlage: Code auf `main` (msb-rental 1.8.5)

Im Technical Context gab es keine offenen Punkte. Die Recherche hat die Fakten für die drei neuen Texte im Code bestätigt.

## Abgleich mit dem Code

| Nr. | Frage | Befund im Code | Fundstelle |
|---|---|---|---|
| R-01 | Was ist bei „Buchung anlegen“ vorausgewählt? | Status „Angefragt“ (`pending`), Zahlung „Bar bei Abholung“, „Kunden per E-Mail informieren“ angehakt | `admin/class-msb-admin-bookings.php`, Formular zur manuellen Buchung |
| R-02 | Lässt sich die Verfügbarkeitsprüfung abschalten? | Ja, Haken „Verfügbarkeit nicht prüfen (Überbuchung zulassen)“, standardmäßig aus | ebd., Feld `force` → `skip_check` |
| R-03 | Welche Mailvorlagen gibt es in den Einstellungen? | Unter „E-Mails“ sechs, je mit Betreff, Text und eigenem Schalter: Team-Benachrichtigung, Eingang, Bestätigung, Stornierung, Zahlungslink, Kaution freigegeben | `includes/functions.php`, Schlüssel `email_*_subject/body/enabled` (nur Namen, keine Werte) |
| R-04 | Welche Beleg-Vorlagen gibt es? | Unter „Belege“ zwei: „Rechnung, Stornorechnung, Korrektur“ und „Kautionsabrechnung“, mit einem gemeinsamen Schalter | `includes/class-msb-documents.php`, `includes/class-msb-document-mails.php` |
| R-05 | Welche Mails haben einen festen Text? | Bitte um Bestätigung bei manueller Buchung, Team-Info nach dieser Bestätigung, Storno-Info an das Team, Bestätigung der E-Mail-Adresse beim Kundenkonto, Kopie eines Belegs an die Buchungsadresse | `MSB_Emails::send_raw()` in `class-msb-terms-confirm.php`, `class-msb-cancellation.php`, `class-msb-accounts.php`, `class-msb-documents.php` |
| R-06 | Wie wird die Aufbewahrungsfrist berechnet? | Spätestes Jahr aus allen Belegen und allen Zahlungen mit Geldfluss der Buchung, plus 8 Jahre, bis 31.12. | `MSB_Retention::retained_until()` |

## Entscheidungen

### D-01 Datum

- **Decision**: Status und Befundliste tragen das Datum der Umsetzung (01.11.2026), nicht das der Spec.
- **Rationale**: Der Status sagt, wann der Text geändert wurde.

### D-02 Status-Version

- **Decision**: ADM-04 → `umgesetzt in 1.5.7`, IF-06 → `umgesetzt in 1.6.2`, DSG-02 → `umgesetzt in 1.7.0`, jeweils mit „(…seit …, Text angepasst am 01.11.2026, B-1x)“.
- **Rationale**: Die Version, ab der der heutige Stand gilt. Bei IF-06 haben 1.6.0 die Beleg-Vorlagen gebracht und 1.6.2 deren heutige Form.
- **Alternatives considered**: `umgesetzt (bis 1.2.7)` stehen lassen. Verworfen, weil der beschriebene Stand jünger ist.

### D-03 ADM-04 ohne Wiederholung

- **Decision**: ADM-04 beschreibt den Rahmen und verweist für Vorauswahl und Zahlung auf ADM-08 und ADM-11, statt deren Inhalt zu wiederholen.
- **Rationale**: Edge Case der Spec; doppelte Texte laufen sonst wieder auseinander.

### D-04 Offene Frage zu DSG-02

- **Decision**: Die Frage, ob die längere Aufbewahrung einzelner Belege derselben Buchung mit der Datenminimierung vereinbar ist, kommt als **F-13** in die Fragenliste von `befunde-und-fragen.md`. Die Überschrift „Fragen an Moritz (alle beantwortet)“ wird zu „Fragen an Moritz“, weil F-13 offen ist.
- **Rationale**: Constitution VI verlangt, Rechtsfragen zu nennen, nicht zu entscheiden. Das Risiko ist gering: Die Frist ist nie kürzer als gesetzlich verlangt, und alle Belege einer Buchung bleiben zusammen, was bei einer Prüfung eher hilft.
- **Alternatives considered**: Frist im Code auf „je Beleg“ umstellen. Verworfen, das wäre eine Codeänderung und gehört in ein eigenes Feature, falls F-13 so beantwortet wird.
