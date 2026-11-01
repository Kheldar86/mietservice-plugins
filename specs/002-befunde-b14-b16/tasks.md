# Tasks: Befunde B-14 bis B-16 beheben

**Input**: Design documents from `specs/002-befunde-b14-b16/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [data-model.md](data-model.md) (Neufassungen), [research.md](research.md), [quickstart.md](quickstart.md)

**Tests**: Keine Testdateien. Geprüft wird mit [quickstart.md](quickstart.md) in der letzten Phase.

**Organization**: US1 passt die drei Anforderungen an, US2 schließt die Befunde ab und legt F-13 an.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: kann parallel laufen (andere Datei, keine offene Abhängigkeit)
- **[Story]**: US1, US2 aus [spec.md](spec.md)

## Regeln für alle Aufgaben

- Text und Status **wörtlich** aus [data-model.md](data-model.md) übernehmen (Inhalt der Zitatblöcke bzw. der Backticks).
- Status in der Übersichtstabelle und im Abschnitt zeichengleich setzen. Stufe bleibt `MUSS`.
- Nur den Abschnitt der jeweiligen Anforderung ändern, nichts verschieben (FR-007, Structure Decision im Plan).
- Datum ist der Tag der Umsetzung. Weicht er vom 01.11.2026 ab, das Datum in allen Texten und Status entsprechend ersetzen (D-01).

---

## Phase 1: Setup

- [X] T001 Bestätigung des Plans durch Moritz mit Datum in der Freigabetabelle von `specs/002-befunde-b14-b16/plan.md` eintragen. Ohne Bestätigung nicht weitermachen.
- [X] T002 Prüfen, dass ADM-04, IF-06 und DSG-02 in `specs/anforderungen/ADM-backend.md`, `specs/anforderungen/IF-schnittstellen.md` und `specs/anforderungen/DSG-datenschutz-loeschfristen.md` im Status noch die Teile enthalten, die unter „Entfällt“ in `specs/002-befunde-b14-b16/data-model.md` genannt sind (also seit Feature 001 unverändert sind). Weicht einer ab, abbrechen und data-model.md zuerst anpassen.

---

## Phase 2: Foundational

Keine Aufgaben: Es gibt keine gemeinsame Vorarbeit, die User Stories blockiert.

---

## Phase 3: User Story 1 – Die Spezifikation beschreibt, was live passiert (Priority: P1) 🎯 MVP

**Goal**: ADM-04, IF-06 und DSG-02 stimmen mit dem Code überein.

**Independent Test**: SC-001 aus [quickstart.md](quickstart.md) (Abgleich mit R-01 bis R-06) und SC-003 (Übersicht = Abschnitte für ADM, IF, DSG).

- [X] T003 [P] [US1] In `specs/anforderungen/ADM-backend.md` den Text von ADM-04 durch „Text neu“ aus data-model.md ersetzen und den Status in Übersicht und Abschnitt auf `umgesetzt in 1.5.7 (Zahlung seit 1.5.5, Text angepasst am 01.11.2026, B-14)` setzen.
- [X] T004 [P] [US1] In `specs/anforderungen/IF-schnittstellen.md` den Text von IF-06 durch „Text neu“ aus data-model.md ersetzen und den Status in Übersicht und Abschnitt auf `umgesetzt in 1.6.2 (E-Mail-Vorlagen seit 1.2.7, Beleg-Vorlagen seit 1.6.0, Text angepasst am 01.11.2026, B-15)` setzen.
- [X] T005 [P] [US1] In `specs/anforderungen/DSG-datenschutz-loeschfristen.md` den Text von DSG-02 durch „Text neu“ aus data-model.md ersetzen und den Status in Übersicht und Abschnitt auf `umgesetzt in 1.7.0 (Regel „Buchungen mit Zahlung oder Beleg“, läuft im Probelauf, Text angepasst am 01.11.2026, B-16)` setzen.
- [X] T006 [US1] SC-001 und SC-003 aus `specs/002-befunde-b14-b16/quickstart.md` ausführen. Rechenbeispiel DSG-02 nachvollziehen: Rechnung 2026, Kautionsabrechnung 2027 → 31.12.2035 (depends on T003–T005).

**Checkpoint**: Die drei Anforderungen sind richtig. Damit ist das MVP erreicht.

---

## Phase 4: User Story 2 – Die Befunde sind abgeschlossen (Priority: P2)

**Goal**: B-14 bis B-16 stehen auf „behoben“, F-13 ist als offene Frage angelegt.

**Independent Test**: SC-002 aus [quickstart.md](quickstart.md): keine Ausgabe, `3`, `1`.

- [X] T007 [US2] In `specs/anforderungen/befunde-und-fragen.md` die Spalte „Status heute“ von B-14, B-15 und B-16 wie in der Tabelle „Befundliste“ in data-model.md setzen (B-16 mit „offene Frage F-13“).
- [X] T008 [US2] In `specs/anforderungen/befunde-und-fragen.md` die Überschrift „## Fragen an Moritz (alle beantwortet)“ in „## Fragen an Moritz“ ändern und F-13 wörtlich aus data-model.md als letzten Eintrag der Fragenliste anfügen (depends on T007, gleiche Datei).
- [X] T009 [US2] SC-002 aus `specs/002-befunde-b14-b16/quickstart.md` ausführen (depends on T003–T005, T007, T008, weil die Prüfung auch die Bereichsdateien auf „siehe B-1x“ durchsucht).

**Checkpoint**: Beide User Stories fertig

---

## Phase 5: Polish & Cross-Cutting

- [ ] T010 SC-004 aus `specs/002-befunde-b14-b16/quickstart.md` ausführen (nur `specs/` im Diff gegen `origin/main`).
- [ ] T011 Mit `grep -nE 'sk_live|sk_test|whsec_|g\.page/r/'` in `specs/anforderungen/` und `specs/002-befunde-b14-b16/` sicherstellen, dass keine Schlüssel und keine Werte aus `msb_settings` im Diff stehen.
- [ ] T012 In `specs/002-befunde-b14-b16/plan.md` die Abnahmekriterien abhaken und in der Freigabetabelle „Pull Request“ eintragen.
- [ ] T013 Branch `002-befunde-b14-b16` pushen und einen Pull Request nach der PR-Vorlage öffnen: Link auf `specs/002-befunde-b14-b16/`, Betroffene Anforderungen ADM-04, IF-06, DSG-02 (geändert), Befunde B-14 bis B-16, neue Frage F-13, keine Version, Prüfergebnisse aus T006, T009 bis T011. Danach die PR-Nummer in `specs/002-befunde-b14-b16/plan.md` eintragen und pushen.

---

## Dependencies & Execution Order

- Setup (T001–T002) → US1 (T003–T006) → US2 (T007–T009) → Polish (T010–T013)
- US2 hängt an US1 nur über die Prüfung T009. T007 und T008 können parallel zu US1 laufen, weil sie eine andere Datei ändern.
- T007 und T008 betreffen dieselbe Datei und laufen nacheinander.

### Parallel Opportunities

- T003, T004 und T005: drei verschiedene Dateien.
- T007 und T008 parallel zu T003 bis T005.

## Parallel Example: User Story 1

```text
T003 ADM-backend.md · T004 IF-schnittstellen.md · T005 DSG-datenschutz-loeschfristen.md
→ danach T006 (Prüfung)
```

## Implementation Strategy

1. **MVP = US1** (T001 bis T006): Die drei Anforderungen stimmen wieder mit dem Code.
2. **US2** schließt die Befunde ab und stellt F-13.
3. Ein Pull Request; reine Doku braucht weder Freigabe noch Tag (Constitution 1.1.0, Ablauf Schritt 5).
