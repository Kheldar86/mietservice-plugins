# Tasks: Nachträge nummerieren

**Input**: Design documents from `specs/001-nachtraege-nummerieren/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [data-model.md](data-model.md) (Nummernplan), [research.md](research.md), [contracts/anforderung-format.md](contracts/anforderung-format.md), [quickstart.md](quickstart.md)

**Tests**: Keine Testdateien (die Spec verlangt keine). Geprüft wird mit den Shell-Befehlen aus [quickstart.md](quickstart.md) in der letzten Phase.

**Organization**: Nach User Stories aus der Spec. US1 legt die Nummern an, US2 räumt `nachtraege.md` auf, US3 setzt Querverweise und Befunde.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: kann parallel laufen (andere Datei, keine offene Abhängigkeit)
- **[Story]**: US1, US2, US3 aus [spec.md](spec.md)
- Verweistexte in US3 stehen wörtlich in der Tabelle „Verweise an bestehenden Anforderungen“ des [Nummernplans](data-model.md) und werden mit „; “ an den bestehenden Status angehängt.

## Path Conventions

Alle Pfade relativ zum Repository. Geändert werden nur Dateien unter `specs/` (FR-010, SC-004).

## Regeln für alle Aufgaben

- Text, Stufe und Status je Nummer **wörtlich** aus der Spalte „Kurzinhalt“ bzw. „Status“ des [Nummernplans](data-model.md) übernehmen.
- Format genau nach [contracts/anforderung-format.md](contracts/anforderung-format.md): Zeile am Ende der Übersichtstabelle, Abschnitt `### ID · STUFE` am Dateiende, Text, Leerzeile, `**Status:** …`. ID, Stufe und Status in Übersicht und Abschnitt zeichengleich.
- Bestehende Nummern und Texte nicht ändern (FR-002, FR-012). Erlaubt ist nur der Verweis im Status aus US3.
- Keine Schlüssel, Links aus `msb_settings` oder Kundendaten (FR-011).

---

## Phase 1: Setup

**Purpose**: Ausgangslage sichern

- [X] T001 Bestätigung des Plans durch Moritz mit Datum in der Freigabetabelle von `specs/001-nachtraege-nummerieren/plan.md` eintragen (Zeile „Plan bestätigt von Moritz“). Ohne Bestätigung nicht weitermachen.
- [X] T002 Konsistenzprüfung SC-002 aus `specs/001-nachtraege-nummerieren/quickstart.md` am unveränderten Stand ausführen; erwartet: alle sieben Bereichsdateien `OK`.

---

## Phase 2: Foundational

**Purpose**: Nummern gegen den aktuellen Stand absichern, bevor geschrieben wird

- [X] T003 In `specs/anforderungen/{ADM,BKG,PAY,IF,DSG,ART,BR}-*.md` die letzte vergebene Nummer je Bereich prüfen. Erwartet: ADM-06, BKG-17, PAY-25, IF-11, DSG-07, ART-05, BR-18. Weicht eine ab, abbrechen und den Nummernplan in `specs/001-nachtraege-nummerieren/data-model.md` zuerst anpassen.

**Checkpoint**: Nummern frei, Ausgangsdateien konsistent

---

## Phase 3: User Story 1 – Jede live laufende Funktion hat eine Nummer (Priority: P1) 🎯 MVP

**Goal**: Alle 39 Nummern aus dem Nummernplan stehen in den Bereichsdateien.

**Independent Test**: Prüfung SC-001 und SC-005 aus [quickstart.md](quickstart.md): 39 Zeilen mit `1`, keine doppelte ID.

- [X] T004 [P] [US1] ADM-07 bis ADM-14 (8 Nummern) in `specs/anforderungen/ADM-backend.md` anlegen. Stufe überall `MUSS`.
- [X] T005 [P] [US1] BKG-18 bis BKG-21 (4 Nummern) in `specs/anforderungen/BKG-buchungsprozess.md` anlegen. BKG-21 hat Stufe `SOLL`, die anderen `MUSS`.
- [X] T006 [P] [US1] PAY-26 bis PAY-36 (11 Nummern) in `specs/anforderungen/PAY-zahlung-kaution-storno.md` anlegen. PAY-29 mit Verweis „(siehe PAY-23)“ im Text und Status `umgesetzt in 1.6.0; Abweichung zu PAY-23`. PAY-31 mit Status `umgesetzt in 1.6.1, abweichend vom CHANGELOG: zuerst Theme-Logo` (A-01).
- [X] T007 [P] [US1] IF-12 bis IF-19 (8 Nummern) in `specs/anforderungen/IF-schnittstellen.md` anlegen. IF-15 mit Status `umgesetzt in 1.6.1, abweichend vom CHANGELOG: zuerst Theme-Logo` (A-01). IF-18 mit Verweis „(§ 312f BGB, siehe G-03)“ im Text.
- [X] T008 [P] [US1] DSG-08 bis DSG-11 (4 Nummern) in `specs/anforderungen/DSG-datenschutz-loeschfristen.md` anlegen. DSG-08 mit Status `umgesetzt in 1.7.0 (im CHANGELOG nicht genannt)` (A-02). DSG-10 und DSG-11 stammen aus FR-013.
- [X] T009 [P] [US1] ART-06 und ART-07 in `specs/anforderungen/ART-mietartikel.md` anlegen. ART-07 hat Stufe `SOLL`.
- [X] T010 [P] [US1] BR-19 und BR-20 in `specs/anforderungen/BR-bridge.md` anlegen, Status `umgesetzt in Bridge 0.4.2`. BR-19 mit Verweis „(siehe G-10, BR-18)“ im Text.
- [X] T011 [US1] Prüfungen SC-001 (39 × `1`, keine Doppelten) und SC-002 (alle `OK`) aus `specs/001-nachtraege-nummerieren/quickstart.md` ausführen. Fehler in der betroffenen Datei beheben (depends on T004–T010).

**Checkpoint**: US1 vollständig und prüfbar. Damit ist das MVP erreicht.

---

## Phase 4: User Story 2 – Die Liste der Nachträge ist erledigt (Priority: P2)

**Goal**: `nachtraege.md` zeigt je Zeile, wohin der Nachtrag gewandert ist.

**Independent Test**: Prüfung „jeder Nachtrag hat eine Nummer“ aus [quickstart.md](quickstart.md) liefert `0`.

- [X] T012 [US2] In `specs/anforderungen/nachtraege.md` die Tabelle um die Spalte „Nummern“ ergänzen und je Zeile die IDs aus der Spalte „Nachtrag“ des Nummernplans eintragen, jede als Link auf die Bereichsdatei (z. B. `[PAY-29](PAY-zahlung-kaution-storno.md#pay-29--muss)`). Den Einleitungssatz ersetzen durch: Alle Einträge haben seit dem 31.10.2026 eine Nummer, die Tabelle bleibt als Zuordnung stehen (FR-008). Zusätzlich vermerken, dass DSG-10 und DSG-11 ohne eigene Zeile dazugekommen sind (FR-013).
- [X] T013 [US2] Prüfung „jeder Nachtrag hat eine Nummer“ aus `specs/001-nachtraege-nummerieren/quickstart.md` ausführen; erwartet `0`. Die Links stichprobenartig in der GitHub-Vorschau öffnen (depends on T012).

**Checkpoint**: US1 und US2 unabhängig prüfbar

---

## Phase 5: User Story 3 – Querbezüge stimmen (Priority: P3)

**Goal**: Bestehende Anforderungen verweisen auf die neuen Nummern bzw. auf die neuen Befunde.

**Independent Test**: Jede Zeile der Tabelle „Verweise an bestehenden Anforderungen“ im Nummernplan findet sich im Status der genannten Anforderung, in Übersicht und Abschnitt gleich (SC-002).

- [X] T014 [US3] Status von ADM-04 in Übersicht und Abschnitt von `specs/anforderungen/ADM-backend.md` um „; überholt in Teilen, siehe ADM-08 und ADM-11 (B-14)“ ergänzen. Text von ADM-04 nicht ändern (FR-012) (depends on T004).
- [X] T015 [US3] Status von PAY-14 um „; Barkaution siehe PAY-26 bis PAY-28“ und von PAY-23 um „; Kautionsabrechnung siehe PAY-29“ ergänzen, jeweils in Übersicht und Abschnitt von `specs/anforderungen/PAY-zahlung-kaution-storno.md` (depends on T006).
- [X] T016 [US3] Status von DSG-05 um „; Buchungen ohne Zahlung nach Abschluss siehe DSG-08“ und von DSG-06 um „; Vorschau und Ablauf siehe DSG-10, Hinweis an gesperrten Buchungen siehe DSG-11“ ergänzen, in Übersicht und Abschnitt von `specs/anforderungen/DSG-datenschutz-loeschfristen.md`. Bei DSG-02 „; siehe B-16“ ergänzen (depends on T008).
- [X] T017 [US3] Status von IF-06 in `specs/anforderungen/IF-schnittstellen.md` um „; siehe B-15“ ergänzen, in Übersicht und Abschnitt (depends on T007).
- [X] T018 [US3] Status von BR-18 in `specs/anforderungen/BR-bridge.md` um „; Kundendaten siehe BR-19, BR-20“ ergänzen, in Übersicht und Abschnitt (depends on T010).
- [X] T019 [P] [US3] In `specs/anforderungen/befunde-und-fragen.md` die Befunde B-14, B-15 und B-16 aus `specs/001-nachtraege-nummerieren/research.md` als neue Zeilen der Befundtabelle anlegen: Dringlichkeit `niedrig`, Spalte „Betrifft“ wie in research.md, Status `offen`.

**Checkpoint**: Alle User Stories fertig

---

## Phase 6: Polish & Cross-Cutting

- [X] T020 Alle Prüfungen aus `specs/001-nachtraege-nummerieren/quickstart.md` erneut ausführen: SC-001, SC-002, „jeder Nachtrag hat eine Nummer“, SC-004 (nur `specs/` im Diff gegen `main`).
- [X] T021 Stichprobe FR-006: drei zufällige neue Nummern gegen den Code in `plugins/` prüfen. Außerdem PAY-31, IF-15 und DSG-08 auf den Abweichungsvermerk prüfen. Für dieselben drei Nummern die Version aus dem Status im CHANGELOG nachschlagen; das muss je Nummer in unter einer Minute gelingen (SC-003).
- [X] T022 Mit `grep -rnE 'sk_live|whsec_|g\.page/r/'` unter `specs/anforderungen/` und `specs/001-nachtraege-nummerieren/` sicherstellen, dass keine Schlüssel und keine Links aus `msb_settings` im Diff stehen (FR-011).
- [X] T023 In `specs/001-nachtraege-nummerieren/plan.md` die Abnahmekriterien abhaken und in der Freigabetabelle „Pull Request“ eintragen.
- [X] T024 Branch `001-nachtraege-nummerieren` pushen und einen Pull Request nach der PR-Vorlage öffnen: Link auf `specs/001-nachtraege-nummerieren/`, Betroffene Anforderungen = 39 neue IDs plus die Verweise aus US3, keine Version, Prüfergebnisse aus T020 bis T022.

---

## Dependencies & Execution Order

- **Setup (T001–T002)** → **Foundational (T003)** → US1 → US2 und US3 → Polish
- **US1** blockiert US2, weil die Links in `nachtraege.md` auf die neuen Abschnitte zeigen.
- **US1** blockiert US3 je Datei: T014 nach T004, T015 nach T006, T016 nach T008, T017 nach T007, T018 nach T010. T019 hängt nur an research.md und kann jederzeit nach T003 laufen.
- **US2 und US3** sind voneinander unabhängig.

### Parallel Opportunities

- T004 bis T010 sind sieben verschiedene Dateien und laufen parallel.
- T019 läuft parallel zu allem ab Phase 3.
- T012 (US2) läuft parallel zu T014 bis T018 (US3), weil die Dateien verschieden sind.

## Parallel Example: User Story 1

```text
T004 ADM-backend.md · T005 BKG-buchungsprozess.md · T006 PAY-zahlung-kaution-storno.md
T007 IF-schnittstellen.md · T008 DSG-datenschutz-loeschfristen.md · T009 ART-mietartikel.md · T010 BR-bridge.md
→ danach T011 (Prüfung)
```

## Implementation Strategy

1. **MVP = US1** (T001 bis T011): Alle 39 Nummern stehen und sind konsistent. Damit ist Grundsatz I der Constitution erfüllt.
2. **US2** macht die Zuordnung in `nachtraege.md` sichtbar.
3. **US3** setzt die Querverweise und legt die Befunde für das Folge-Feature an.
4. Alles geht in **einem** Pull Request. Reine Doku braucht weder Freigabe noch Tag (Constitution 1.1.0, Ablauf Schritt 5).
