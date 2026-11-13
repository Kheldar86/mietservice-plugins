# Feature Specification: Freigabe der Auslagenabrechnung und Hinweis über 250 €

**Feature Branch**: `005-auslagenabrechnung` (Folgeänderung zu 005, msb-rental 1.10.0 ist live)

**Created**: 2026-11-13

**Status**: Umgesetzt, wartet auf Freigabe

**Input**: Moritz (13.11.2026) brachte Antworten auf die Steuerfragen aus Plan 005 ein: eine Unterschrift ist nicht vorgeschrieben, eine digitale Freigabe durch den zweiten Gesellschafter ist aber dringend anzuraten (Vier-Augen-Prinzip); Kassenbons ohne Firmennamen reichen bis 250 €. Auf den Vorschlag, beides einzubauen: „Ja beides“.

## Betroffene Anforderungen

| ID | Art | Kurz |
|---|---|---|
| PAY-43 | neu | Freigabe durch den zweiten Gesellschafter vor der Auszahlung |
| PAY-44 | neu | Hinweis beim Erfassen über 250 € |
| PAY-38 | geändert | PDF bekommt bei der Freigabe einmal den Freigabevermerk und den GiroCode |
| PAY-40 | geändert | Versand an Finom erst nach der Freigabe |
| PAY-41 | geändert | Status „wartet auf Freigabe“ und „freigegeben, nicht ausgezahlt“ |
| ROL-04 | geändert | Freigeben darf nur, wer die Abrechnung weder bekommt noch erstellt hat |
| ADM-16 | geändert | „Prüfen und freigeben“, Zähler im Reiter Abrechnungen |

## User Scenarios & Testing

### User Story 1 – Eric rechnet ab, Moritz gibt frei (Priority: P1)

1. **Given** Eric erstellt `AUS-2026-0002`, **Then** Status „wartet auf Freigabe“, Moritz bekommt eine Mail mit Link, der Reiter zeigt „Abrechnungen (1 zur Freigabe)“, kein „Auszahlen“, kein GiroCode, noch kein Versand an Finom.
2. **When** Moritz „Freigeben“ klickt, **Then** Status „freigegeben, nicht ausgezahlt“, die PDF trägt „Freigegeben (Vier-Augen-Prinzip) von Moritz … am …“ und den GiroCode, die Abrechnung geht an das Finom-Beleg-Postfach, „Auszahlen“ erscheint.
3. **Given** Eric öffnet seine eigene Abrechnung, **Then** sieht er „Wartet auf Freigabe durch den anderen Gesellschafter“ und keinen Knopf „Freigeben“.

### User Story 2 – Hinweis über 250 € (Priority: P2)

**When** jemand 289,00 € einträgt, **Then** erscheint unter dem Betrag der Hinweis zu Name und Anschrift der GbR, und nach dem Speichern steht er auch in der Meldung.

### Edge Cases

- Als ausgezahlt markieren ist vor der Freigabe nicht möglich.
- Stornieren geht vor und nach der Freigabe, nur nicht nach der Auszahlung.
- Bestehende Abrechnungen aus 1.10.0 haben keine Freigabe und warten nach dem Update auf Freigabe.
