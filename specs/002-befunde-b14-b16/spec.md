# Feature Specification: Befunde B-14 bis B-16 beheben

**Feature Branch**: `002-befunde-b14-b16`

**Created**: 2026-10-31

**Status**: Draft

**Input**: User description: "Befunde B-14 bis B-16 beheben: ADM-04, IF-06 und DSG-02 an den Code anpassen"

## Hintergrund

Beim Nummerieren der Nachträge (Feature 001) fiel auf, dass drei bestehende Anforderungen nicht mehr zum live laufenden Verhalten passen. Sie wurden dort nur als Befunde B-14 bis B-16 notiert (Entscheidung 31.10.2026, FR-012 in 001). Dieses Feature passt die drei Texte an. Das Verhalten der Plugins ändert sich nicht.

| Befund | Anforderung | Was nicht stimmt |
|---|---|---|
| B-14 | ADM-04 | nennt „Standardstatus bestätigt“ und nur „Zahlart offline“. Seit 1.5.5 und 1.5.7 sind „Angefragt“ und „Bar bei Abholung“ vorausgewählt, online ist wählbar. |
| B-15 | IF-06 | nennt nur die sechs Vorlagen unter Einstellungen → E-Mails. Die Vorlagen für Beleg-Mails (Einstellungen → Belege) und die Mails mit festem Text fehlen. |
| B-16 | DSG-02 | rechnet die 8 Jahre ab dem Jahr „der Rechnung“. Gelöscht wird erst 8 Jahre nach Ende des Jahres des letzten Belegs oder der letzten Zahlung der Buchung. |

## User Scenarios & Testing *(mandatory)*

### User Story 1 – Die Spezifikation beschreibt, was live passiert (Priority: P1)

Moritz oder Claude liest ADM-04, IF-06 oder DSG-02, bevor er etwas an manuellen Buchungen, Mails oder Löschfristen ändert. Der Text stimmt mit dem überein, was das Plugin heute tut, und widerspricht keiner anderen Anforderung.

**Why this priority**: Eine falsche Anforderung führt dazu, dass eine spätere Änderung gegen den falschen Stand geplant und getestet wird.

**Independent Test**: Für jede der drei Anforderungen das beschriebene Verhalten im Backend bzw. im Code nachsehen. Es gibt keinen Unterschied.

**Acceptance Scenarios**:

1. **Given** ADM-04, **When** Moritz im Backend „Buchung anlegen“ öffnet, **Then** sind Status, Zahlung und Prüfung so vorausgewählt bzw. wählbar, wie ADM-04 es beschreibt, und ADM-04 widerspricht weder ADM-08 noch ADM-11.
2. **Given** IF-06, **When** man die Einstellungen unter „E-Mails“ und „Belege“ öffnet, **Then** findet sich jede dort pflegbare Vorlage in IF-06, und IF-06 nennt keine Vorlage, die es nicht gibt.
3. **Given** eine Buchung mit Rechnung 2026 und Kautionsabrechnung 2027, **When** man die Frist nach DSG-02 berechnet, **Then** ergibt sich der 31.12.2035, wie im Backend angezeigt („gesperrt bis …“ bzw. Löschsperre).

---

### User Story 2 – Die Befunde sind abgeschlossen (Priority: P2)

Wer die Befundliste öffnet, sieht B-14 bis B-16 als behoben, mit Datum. Die Verweise „siehe B-14/15/16“ im Status der drei Anforderungen sind ersetzt.

**Why this priority**: Offene Befunde, die längst erledigt sind, verwässern die Liste.

**Independent Test**: In `befunde-und-fragen.md` und den drei Bereichsdateien kommt „offen“ bei B-14 bis B-16 bzw. „siehe B-1x“ nicht mehr vor.

**Acceptance Scenarios**:

1. **Given** die Befundliste, **When** man B-14 bis B-16 liest, **Then** steht in „Status heute“ jeweils „behoben am <Datum der Umsetzung> (Spec angepasst)“.

### Edge Cases

- Das Verhalten im Code ist rechtlich strenger oder großzügiger als der alte Text: Der neue Text beschreibt den Code. Ob das Verhalten selbst geändert werden soll, ist eine neue Anforderung und nicht Teil dieses Features.
- Die Anforderung überschneidet sich mit einer neueren Nummer (ADM-04 mit ADM-08, ADM-11): ADM-04 beschreibt nur noch den Rahmen und verweist für Einzelheiten auf die neueren Nummern, statt sie zu wiederholen.
- Eine Mail hat einen festen Text ohne Vorlage: Sie gehört nicht in die Liste der Vorlagen, wird aber als feste Mail genannt, damit klar ist, dass sie sich nicht in den Einstellungen ändern lässt.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Der Text von ADM-04 MUSS die manuelle Buchung so beschreiben, wie sie live läuft: Buchungsregeln werden ignoriert (VFG-04), die Verfügbarkeitsprüfung lässt sich abschalten, Zahlung „Bar bei Abholung“ oder online (ADM-11), Vorauswahl Status „Angefragt“ (ADM-08).
- **FR-002**: Der Text von IF-06 MUSS alle Mailvorlagen nennen, die sich in den Einstellungen pflegen lassen: unter „E-Mails“ Team-Benachrichtigung, Eingang, Bestätigung, Stornierung, Zahlungslink, Kaution freigegeben; unter „Belege“ die Vorlagen für die Mail zur Rechnung und zur Kautionsabrechnung. Die Platzhalter und `{field:schlüssel}` bleiben erwähnt.
- **FR-003**: IF-06 MUSS zusätzlich die Mails mit festem Text nennen, die sich nicht über Vorlagen ändern lassen: Bitte um Bestätigung bei manuellen Buchungen (BKG-18), Info an das Team nach dieser Bestätigung (BKG-19), Storno-Info an das Team, Bestätigung der E-Mail-Adresse beim Kundenkonto und Kopie eines Belegs an die Buchungsadresse.
- **FR-004**: Der Text von DSG-02 MUSS die Frist so beschreiben, wie sie berechnet wird: 8 Jahre ab Ende des Kalenderjahres des letzten Belegs oder der letzten Zahlung der Buchung, je nachdem, was später liegt. Die Rechtsgrundlagen (§ 147 AO, § 257 HGB) bleiben genannt.
- **FR-005**: Der Status der drei Anforderungen MUSS die Anpassung mit Datum und Befund vermerken, mit dem Datum der Umsetzung, z. B. `umgesetzt in 1.5.7 (Text angepasst am 01.11.2026, B-14)`. Die Verweise „siehe B-14/15/16“ und „überholt in Teilen …“ entfallen.
- **FR-006**: In `befunde-und-fragen.md` MUSS der Status von B-14, B-15 und B-16 auf `behoben am <Datum der Umsetzung> (Spec angepasst)` gesetzt werden.
- **FR-007**: Nummern bleiben unverändert. Es entstehen keine neuen Anforderungen.
- **FR-008**: Die Änderung DARF keinen Plugin-Code, keine Plugin-Version und kein Plugin-CHANGELOG ändern.

### Key Entities

- **Anforderung**: ADM-04, IF-06, DSG-02 mit Text und Status in Übersicht und Abschnitt.
- **Befund**: B-14, B-15, B-16 in der Befundliste mit Status.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Für 3 von 3 Anforderungen ergibt ein Vergleich mit dem Backend bzw. dem Code keinen Unterschied.
- **SC-002**: 3 von 3 Befunden stehen auf „behoben“; in den Bereichsdateien kommt „siehe B-14“, „siehe B-15“ oder „siehe B-16“ nicht mehr vor.
- **SC-003**: In ADM, IF und DSG stimmen Übersicht und Abschnitte weiterhin überein (gleiche Prüfung wie in Feature 001).
- **SC-004**: Der Diff berührt nur Dateien unter `specs/`.

## Assumptions

- Maßgeblich ist der Code auf `main` (msb-rental 1.8.5).
- Datum in Status und Befundliste ist der Tag der Umsetzung, nicht der Tag der Spec.
- Die Frist nach DSG-02 läuft im Code nie kürzer als ab dem Jahr der Rechnung, weil das spätere von letztem Beleg und letzter Zahlung nie vor dem Jahr der Rechnung liegt. Die gesetzliche Mindestfrist ist damit eingehalten. Ob die etwas längere Aufbewahrung einzelner Belege derselben Buchung mit der Datenminimierung vereinbar ist, entscheidet dieses Feature nicht (Constitution VI). Die Frage wird als offene Frage an den Steuerberater bzw. Datenschutz vermerkt, nicht beantwortet.
- Die Status-Version für ADM-04 ist 1.5.7, weil seitdem der heutige Stand gilt (Zahlung seit 1.5.5). IF-06 bekommt 1.6.2 (letzte Änderung an Vorlagen), DSG-02 bleibt bei 1.7.0.
- Reine Doku: weder Freigabe noch Tag (Constitution 1.1.0, Ablauf Schritt 5).
