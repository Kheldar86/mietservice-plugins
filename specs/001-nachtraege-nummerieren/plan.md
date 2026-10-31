# Implementation Plan: Nachträge nummerieren

**Branch**: `001-nachtraege-nummerieren` | **Date**: 2026-10-31 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/001-nachtraege-nummerieren/spec.md`

## Summary

Die 17 Nachträge aus [nachtraege.md](../anforderungen/nachtraege.md) bekommen 37 neue Anforderungsnummern in sieben Bereichsdateien, eine je prüfbarer Regel (Entscheidung 31.10.2026). Dazu kommen 2 Nummern für Funktionen aus 1.7.0, die nicht in der Liste stehen (FR-013), zusammen 39. Grundlage ist der CHANGELOG, abgeglichen mit dem Code auf `main` (msb-rental 1.8.5, Bridge 0.4.2). Zwei Abweichungen werden im Status vermerkt (Logo-Fallback, nicht dokumentierte Löschregel). Drei Befunde zu bestehenden Anforderungen kommen als B-14 bis B-16 in die Befundliste; die Texte dieser Anforderungen bleiben unverändert (FR-012). Die Zuordnung steht im [Nummernplan](data-model.md), der Abgleich in [research.md](research.md).

## Technical Context

**Language/Version**: Markdown (GitHub-Flavored), Deutsch

**Primary Dependencies**: keine

**Storage**: Dateien unter `specs/anforderungen/`

**Testing**: Shell-Prüfungen aus [quickstart.md](quickstart.md) (grep, awk, diff, git)

**Target Platform**: GitHub-Ansicht und Editor

**Project Type**: Dokumentation (Spezifikation)

**Performance Goals**: entfällt

**Constraints**: Kein Plugin-Code, keine Plugin-Version, kein Plugin-CHANGELOG (FR-010). Keine Geheimnisse (FR-011). Bestehende Nummern bleiben unverändert (FR-002).

**Scale/Scope**: 39 neue Anforderungen in 7 Dateien, Verweise an 8 bestehenden (ADM-04, PAY-14, PAY-23, IF-06, DSG-02, DSG-05, DSG-06, BR-18), 3 neue Befunde, Umbau von `nachtraege.md`

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Geprüft gegen Constitution **1.1.0** (beschlossen mit #5).

| Grundsatz | Prüfung | Ergebnis |
|---|---|---|
| I. Die Spezifikation ist die Quelle | Das Feature schließt genau die Lücke „Code ohne Anforderung“. Keine Nummer wird neu vergeben. | erfüllt |
| II. Jede Änderung ist eine neue Version | Gilt für Codeänderungen. Hier ändert sich kein Code, deshalb keine neue Version (FR-010). | entfällt, begründet |
| III. Keine Geheimnisse | Texte ohne Schlüssel und ohne Werte aus `msb_settings`. Der vorbelegte Link zur Google-Bewertung wird bewusst nicht übernommen. | erfüllt |
| IV. Live nur über Snapshot und Freigabe | Nichts geht live, keine Bridge-Vorschläge. Laut Ablauf Schritt 5 brauchen reine Doku-Änderungen weder Freigabe noch Tag. | entfällt, durch 1.1.0 ausdrücklich gedeckt |
| V. Prüfbar und getestet | Jede neue Anforderung ist einzeln prüfbar. Die Prüfung steht in [quickstart.md](quickstart.md) und wurde am heutigen Stand getestet. Sie besteht aus Shell-Befehlen und braucht keine Datei unter `tests/`. | erfüllt |
| VI. Recht und Geld zuerst | Texte zu Belegen, EÜR und Löschfristen geben das live laufende Verhalten wieder und entscheiden nichts Neues. Die offene Frage an den Steuerberater bei PAY-23 bleibt stehen. | erfüllt |
| VII. Einfach und passend zum Bestand | Gleiches Format wie die bestehenden Anforderungen ([Format](contracts/anforderung-format.md)). | erfüllt |

**Nach Phase 1 erneut geprüft** (31.10.2026, nach der Klärung): keine Verstöße. Die Zahl der Nummern (39 statt geschätzt 25 bis 30) folgt aus „je prüfbarer Regel“ und FR-013. FR-012 (bestehende Texte bleiben) stützt Grundsatz I: Nummern und Texte bestehender Anforderungen werden nicht stillschweigend geändert.

## Project Structure

### Documentation (this feature)

```text
specs/001-nachtraege-nummerieren/
├── spec.md
├── plan.md                         # diese Datei
├── research.md                     # Abgleich CHANGELOG ↔ Code, Befunde
├── data-model.md                   # Nummernplan (39 Zeilen)
├── quickstart.md                   # Prüfbefehle für SC-001 bis SC-004
├── contracts/anforderung-format.md # Format einer Anforderung
├── checklists/requirements.md
└── tasks.md                        # kommt mit /speckit-tasks
```

### Betroffene Dateien

```text
specs/anforderungen/
├── ADM-backend.md                  # ADM-07 bis ADM-14, Verweis an ADM-04
├── BKG-buchungsprozess.md          # BKG-18 bis BKG-21
├── PAY-zahlung-kaution-storno.md   # PAY-26 bis PAY-36, Verweise an PAY-14, PAY-23
├── IF-schnittstellen.md            # IF-12 bis IF-19, Verweis an IF-06 (B-15)
├── DSG-datenschutz-loeschfristen.md# DSG-08 bis DSG-11, Verweise an DSG-02 (B-16), DSG-05, DSG-06
├── ART-mietartikel.md              # ART-06, ART-07
├── BR-bridge.md                    # BR-19, BR-20, Verweis an BR-18
├── nachtraege.md                   # je Zeile die neuen Nummern, Kopf „alle nummeriert“
└── befunde-und-fragen.md           # B-14 bis B-16
```

**Structure Decision**: Keine neuen Dateien unter `specs/anforderungen/`. Jede Nummer kommt in die bestehende Bereichsdatei, Übersicht und Abschnitt am Ende. `specs/README.md` bleibt unverändert, weil sich kein Bereich ändert.

## Abnahmekriterien

- [ ] Prüfungen aus [quickstart.md](quickstart.md) für SC-001, SC-002 und SC-004 ohne Fehler
- [ ] Stichprobe von drei Nummern gegen den Code (FR-006) ohne Befund
- [ ] A-01 und A-02 im Status vermerkt
- [ ] B-14 bis B-16 in `befunde-und-fragen.md` mit Status `offen`
- [ ] Pull Request mit Link auf diesen Ordner; Moritz bestätigt den Plan vor der Umsetzung

## Freigabe

Reine Doku: kein Snapshot, keine Bridge, kein Tag (Constitution 1.1.0, Ablauf Schritt 5). Nach dem Merge ist das Feature abgeschlossen.

| Schritt | Wann | Notiz |
|---|---|---|
| Plan bestätigt von Moritz | | |
| Pull Request | | |
| Merge | | |

## Complexity Tracking

Keine Verstöße gegen die Constitution.
