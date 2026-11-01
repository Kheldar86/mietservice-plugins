# Implementation Plan: Befunde B-14 bis B-16 beheben

**Branch**: `002-befunde-b14-b16` | **Date**: 2026-11-01 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/002-befunde-b14-b16/spec.md`

## Summary

Die Texte von ADM-04, IF-06 und DSG-02 werden an das live laufende Verhalten angepasst, das im Code geprüft wurde ([research.md](research.md)). Die Neufassungen stehen wörtlich in [data-model.md](data-model.md). Die Befunde B-14 bis B-16 werden auf „behoben“ gesetzt. Für DSG-02 kommt eine offene Frage F-13 zur Datenminimierung in die Fragenliste. Kein Code, keine Version.

## Technical Context

**Language/Version**: Markdown, Deutsch

**Primary Dependencies**: keine

**Storage**: `specs/anforderungen/`

**Testing**: Shell-Prüfungen aus [quickstart.md](quickstart.md) und Abgleich mit den Fundstellen in research.md

**Target Platform**: GitHub-Ansicht

**Project Type**: Dokumentation (Spezifikation)

**Performance Goals**: entfällt

**Constraints**: Kein Plugin-Code, keine Version (FR-008). Keine neuen Nummern (FR-007). Keine Werte aus `msb_settings`, nur Namen von Einstellungen.

**Scale/Scope**: 3 Anforderungen in 3 Dateien, 3 Befunde und 1 neue Frage in `befunde-und-fragen.md`

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Geprüft gegen Constitution **1.1.0**.

| Grundsatz | Prüfung | Ergebnis |
|---|---|---|
| I. Die Spezifikation ist die Quelle | Drei Anforderungen werden wieder richtig. Nummern bleiben, keine neue Nummer. | erfüllt |
| II. Jede Änderung ist eine neue Version | Kein Code geändert. | entfällt |
| III. Keine Geheimnisse | Nur Namen von Einstellungen, keine Werte. | erfüllt |
| IV. Live nur über Snapshot und Freigabe | Nichts geht live; reine Doku braucht weder Freigabe noch Tag (Ablauf Schritt 5). | entfällt |
| V. Prüfbar und getestet | Jeder neue Text ist an einer Fundstelle im Code prüfbar; Prüfbefehle in quickstart.md. | erfüllt |
| VI. Recht und Geld zuerst | DSG-02 beschreibt die gesetzliche Frist richtig. Die Frage zur Datenminimierung wird als F-13 gestellt, nicht entschieden. | erfüllt |
| VII. Einfach und passend zum Bestand | Format wie in Feature 001. | erfüllt |

**Nach Phase 1 erneut geprüft**: keine Verstöße.

## Project Structure

### Documentation (this feature)

```text
specs/002-befunde-b14-b16/
├── spec.md
├── plan.md            # diese Datei
├── research.md        # Fakten aus dem Code (R-01 bis R-06), Entscheidungen D-01 bis D-04
├── data-model.md      # Neufassungen, wörtlich zu übernehmen
├── quickstart.md      # Prüfbefehle
├── checklists/requirements.md
└── tasks.md           # kommt mit /speckit-tasks
```

Kein `contracts/`: Das Format einer Anforderung steht schon in [001/contracts/anforderung-format.md](../001-nachtraege-nummerieren/contracts/anforderung-format.md).

### Betroffene Dateien

```text
specs/anforderungen/
├── ADM-backend.md                    # ADM-04: Text und Status
├── IF-schnittstellen.md              # IF-06: Text und Status
├── DSG-datenschutz-loeschfristen.md  # DSG-02: Text und Status
└── befunde-und-fragen.md             # B-14 bis B-16 behoben, Überschrift, F-13
```

**Structure Decision**: Nur bestehende Abschnitte ändern, nichts verschieben.

## Abnahmekriterien

- [ ] Prüfungen aus [quickstart.md](quickstart.md) für SC-001 bis SC-004 ohne Befund
- [ ] Texte und Status zeichengleich mit [data-model.md](data-model.md)
- [ ] F-13 steht in der Fragenliste, die Überschrift ist angepasst
- [ ] Pull Request mit Link auf diesen Ordner; Moritz bestätigt den Plan vor der Umsetzung

## Freigabe

Reine Doku: kein Snapshot, keine Bridge, kein Tag (Constitution 1.1.0, Ablauf Schritt 5).

| Schritt | Wann | Notiz |
|---|---|---|
| Plan bestätigt von Moritz | | |
| Pull Request | | |
| Merge | | |

## Complexity Tracking

Keine Verstöße gegen die Constitution.
