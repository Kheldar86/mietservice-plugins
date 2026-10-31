# Research: Nachträge nummerieren

**Feature**: [spec.md](spec.md) · **Stand**: 31.10.2026 · Grundlage: CHANGELOG msb-rental 1.5.1 bis 1.8.4, Bridge 0.4.2, Code in `plugins/` auf `main` (msb-rental 1.8.5)

Es gab im Technical Context keine offenen Punkte. Die Recherche diente dem Abgleich CHANGELOG ↔ Code (FR-006) und der Zuordnung zu Bereichen.

## Entscheidungen

### R-01 Granularität

- **Decision**: 37 neue Nummern für 17 Nachträge, eine je prüfbarer Regel (Entscheidung 31.10.2026, Q1: A). Die Spec hatte 25 bis 30 geschätzt. Mehr sind es, weil einige Zeilen (Testbuchungen, Belege, manuelle Buchung) jeweils drei oder vier eigenständige Regeln enthalten.
- **Rationale**: Jede Nummer lässt sich einzeln prüfen und später einzeln ändern.
- **Alternatives considered**: Zusammenfassen auf etwa 25 Nummern. Verworfen, weil dann Regeln mit unterschiedlicher Version (z. B. 1.5.5 und 1.5.7) unter einer Nummer stünden.

### R-02 Bereich bei zwei genannten Bereichen

- **Decision**: Die Regel kommt in den Bereich, in dem das Verhalten liegt. Mails, Platzhalter und Kurzlinks gehen nach IF, Geld und Belege nach PAY, Backend-Bedienung nach ADM, Kundenablauf nach BKG. Bereich G bekommt keine neue Nummer. Die AGB-PDF (§ 312f BGB) ist eine Mail-Regel (IF-18) mit Verweis auf G-03.
- **Alternatives considered**: Doppelte Nummern in beiden Bereichen. Verworfen, weil die Spec das nur bei wirklich unterschiedlichen Regeln erlaubt.

### R-03 Version im Status

- **Decision**: Der Status nennt die Version, ab der der heutige Stand gilt. Frühere Versionen stehen in Klammern, z. B. `umgesetzt in 1.5.7 (seit 1.5.2)`.

## Abgleich CHANGELOG ↔ Code

Der Code gilt. Bei einer Abweichung beschreibt der Text den Code, und der Status vermerkt sie (FR-005, FR-006).

| Nr. | Regel | CHANGELOG | Code | Folge |
|---|---|---|---|---|
| A-01 | Logo auf Belegen und in Mails (PAY-31, IF-15) | 1.6.1: ohne Auswahl „das Website-Icon“ | `MSB_Documents::logo_id()`: Auswahl in den Einstellungen, sonst das Logo des Themes (`custom_logo`), sonst das Website-Icon | Text nach Code, Status `umgesetzt in 1.6.1, abweichend vom CHANGELOG: zuerst Theme-Logo` |
| A-02 | Löschregel ohne Zahlung und Beleg (DSG-08) | 1.7.0 nennt die Regel nicht | `MSB_Retention::rules()['booking_nomoney']`: Status zurückgegeben **oder storniert**, ohne Zahlung und Beleg, 3 Jahre nach Ende des Jahres der letzten Änderung bzw. des Mietendes | Text nach Code, Status `umgesetzt in 1.7.0 (im CHANGELOG nicht genannt)` |
| A-03 | Aufrufzähler Kurzlink (IF-17) | „ohne … eigene Aufrufe im Backend“ | Gezählt wird nicht, wenn HEAD-Anfrage, leerer oder Bot-User-Agent oder der Nutzer Artikel bearbeiten darf (`edit_msb_items`) | Text nach Code, keine Abweichung im Sinn |
| A-04 | Manuelle Buchung, Vorauswahl (ADM-08) | 1.5.7: Status „Angefragt“, „Kunden informieren“ angehakt | bestätigt; zusätzlich Zahlung „Bar bei Abholung“ vorausgewählt (1.5.5) | Text nach Code |
| A-05 | Barkaution (PAY-28) | 1.6.0: keine automatische Freigabe bei „Zurückgegeben“ | bestätigt: `on_status_change` überspringt Anbieter `manual` | – |
| A-06 | Testbuchung sperren (PAY-34) | 1.7.2: nur ohne gültige Belege | bestätigt: `test_locked()` zählt Belege mit Status `issued`, ohne Korrekturbelege | – |
| A-07 | Bewertungsbitte (IF-19) | 1.8.4 | bestätigt: nur Belegart `invoice`, nur mit Haken, nur mit Link | – |
| A-08 | AGB-PDF (IF-18) | 1.8.3 | bestätigt: nur bei Mail-Typ `confirmed` | – |
| A-09 | Bridge „Kundendaten schwärzen“ (BR-19, BR-20) | 0.4.2: Standard an | bestätigt: `redact_pii => true`, Belege `.pdf`/`.csv` unter `uploads/msb-belege` gesperrt | – |
| A-10 | Herkunft Kleinanzeigen ohne Cookie (BKG-20) | 1.8.0 | bestätigt: Herkunft als Transient am vorhandenen Warenkorb-Token, kein neues Cookie | – |

## Befunde außerhalb des Umfangs

Diese Punkte betreffen bestehende Anforderungen, nicht die Nachträge. Sie werden nicht umgeschrieben, sondern als Befund in [befunde-und-fragen.md](../anforderungen/befunde-und-fragen.md) aufgenommen.

| Nr. | Befund | Betrifft |
|---|---|---|
| B-14 | ADM-04 sagt „Standardstatus bestätigt“. Seit 1.5.7 ist bei der manuellen Buchung „Angefragt“ vorausgewählt (ADM-08). | ADM-04 |
| B-15 | IF-06 zählt nicht alle heutigen Vorlagen auf: Es fehlen u. a. die Mail mit dem Bestätigungslink (1.5.2) und die Mails zu Belegen (1.6.0). | IF-06 |
| B-16 | DSG-02 rechnet die 8 Jahre ab dem Jahr „der Rechnung“. Der Code rechnet ab dem letzten Beleg bzw. der letzten Zahlung. | DSG-02 |
| B-17 | Live, aber nicht in `nachtraege.md` und ohne Nummer: Kasten „Daten löschen (Art. 17 DSGVO)“ mit Vorschau und Hinweis „gesperrt bis …“ (1.7.0). DSG-06 deckt nur das Löschen ab, nicht die Vorschau. | DSG-06 |

Bei ADM-04 und PAY-23 kommt zusätzlich ein Verweis auf die neue Nummer in den Status (FR-009). Der Text der bestehenden Anforderung bleibt.
