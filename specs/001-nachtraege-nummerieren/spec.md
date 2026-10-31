# Feature Specification: Nachträge nummerieren

**Feature Branch**: `001-nachtraege-nummerieren`

**Created**: 2026-10-31

**Status**: Draft

**Input**: User description: "Die Nachträge nummerieren: Die 17 Funktionen aus specs/anforderungen/nachtraege.md, die seit dem 21.10.2026 live sind, bekommen feste Anforderungsnummern im jeweils passenden Bereich (ADM, BKG, PAY, IF, DSG, ART, G, BR) mit Status „umgesetzt in X.Y.Z“ laut CHANGELOG. Reine Doku-Änderung, kein Plugin-Code, keine neue Version."

## Hintergrund

Seit der ersten Fassung der Spezifikation (21.10.2026) sind 17 Funktionen live gegangen, die noch keine Anforderungsnummer haben. Sie stehen nur als Liste in [nachtraege.md](../anforderungen/nachtraege.md), und für sie gilt bis jetzt der Text im CHANGELOG. Nach Grundsatz I der Constitution gibt es keinen Code ohne passende Anforderung. Dieses Feature schließt die Lücke.

## User Scenarios & Testing *(mandatory)*

### User Story 1 – Jede live laufende Funktion hat eine Nummer (Priority: P1)

Moritz (oder Claude bei einer späteren Änderung) sucht die Regel zu einer Funktion, z. B. „Barbuchung: Miete bar erhalten“. Er findet sie in der Bereichsdatei unter einer festen Nummer mit Status und Version, statt im CHANGELOG suchen zu müssen.

**Why this priority**: Das ist der eigentliche Zweck. Spätere Änderungen können sich auf die Nummer beziehen („PAY-28 geändert“).

**Independent Test**: Für jede der 17 Zeilen in `nachtraege.md` gibt es in genau einer Bereichsdatei eine Anforderung mit Nummer, Stufe, Text und Status. Das lässt sich per Liste abhaken.

**Acceptance Scenarios**:

1. **Given** die Zeile „Logo auf Belegen und in allen Mails (1.6.1)“, **When** man in der passenden Bereichsdatei nachsieht, **Then** gibt es dort eine neue Nummer mit dem Status `umgesetzt in 1.6.1` und einem prüfbaren Text.
2. **Given** eine Funktion, die über mehrere Versionen entstanden ist (z. B. 1.5.2, 1.5.6, 1.5.7), **When** man den Status liest, **Then** nennt er die Version, ab der der heutige Stand gilt, und die früheren Versionen stehen im Text oder Status.
3. **Given** die Übersichtstabelle oben in einer Bereichsdatei, **When** man sie mit den Abschnitten darunter vergleicht, **Then** stehen alle neuen Nummern in beiden.

---

### User Story 2 – Die Liste der Nachträge ist erledigt (Priority: P2)

Wer `nachtraege.md` öffnet, sieht, dass alle Einträge eine Nummer bekommen haben und wohin sie gewandert sind.

**Why this priority**: Verhindert, dass dieselbe Funktion später doppelt nummeriert wird.

**Independent Test**: `nachtraege.md` nennt je Zeile die neue Nummer bzw. Nummern, oder die Datei verweist nur noch auf die neuen Nummern.

**Acceptance Scenarios**:

1. **Given** `nachtraege.md` nach der Änderung, **When** man eine Zeile liest, **Then** steht dort die zugehörige neue Nummer mit Link auf die Bereichsdatei.

---

### User Story 3 – Querbezüge stimmen (Priority: P3)

Wo ein Nachtrag eine bestehende Anforderung berührt (z. B. Kautionsabrechnung und die Abweichung bei PAY-23), verweisen beide aufeinander.

**Why this priority**: Nützlich, aber nicht nötig, damit die Nummern selbst funktionieren.

**Independent Test**: Bei jeder neuen Anforderung, die eine bestehende berührt, steht die bestehende Nummer im Text, und umgekehrt.

**Acceptance Scenarios**:

1. **Given** PAY-23 mit dem Vermerk „abweichend: Einbehalt als eigene Kautionsabrechnung“, **When** die Kautionsabrechnung eine eigene Nummer bekommt, **Then** verweist PAY-23 auf diese Nummer.

### Edge Cases

- Ein Nachtrag nennt zwei Bereiche (z. B. „ADM, BKG“): Er bekommt eine Nummer im Bereich, in dem das Verhalten hauptsächlich liegt, und der andere Bereich verweist darauf. Neue Nummern in beiden Bereichen gibt es nur, wenn dort wirklich unterschiedliche Regeln stehen.
- Ein Nachtrag fasst mehrere Funktionen zusammen (z. B. Kleinanzeigen: Vorlage, Kurzlinks, Aufrufzähler, Quelle): Jede prüfbare Regel bekommt eine eigene Nummer (FR-004).
- Der CHANGELOG-Text und das tatsächliche Verhalten im Code weichen voneinander ab: Der Status wird `abweichend …` mit kurzer Begründung, statt das Verhalten stillschweigend anzupassen.
- Eine Funktion wurde in einer späteren Version wieder geändert (z. B. Kartenstil 1.6.3 bis 1.6.7): Maßgeblich ist der Stand der letzten Version.

## Clarifications

### Session 2026-10-31

- Q: Eine Nummer je Nachtrag-Zeile oder je prüfbarer Regel? → A: Je prüfbarer Regel (FR-004).
- Q: Text nur aus dem CHANGELOG oder zusätzlich mit dem Code abgleichen? → A: CHANGELOG plus Abgleich mit dem Code im Repository (FR-006).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Jeder der 17 Einträge in `nachtraege.md` MUSS mindestens eine Anforderungsnummer in einer Bereichsdatei unter `specs/anforderungen/` bekommen.
- **FR-002**: Neue Nummern MÜSSEN die nächste freie Nummer im Bereich sein (Stand heute: ADM-07, BKG-18, PAY-26, IF-12, DSG-08, ART-06, G-16, BR-19). Bestehende Nummern werden nicht umnummeriert.
- **FR-003**: Jede neue Anforderung MUSS die gleiche Form haben wie die bestehenden: Zeile in der Übersicht (ID, Stufe, Status), Abschnitt `### ID · STUFE`, prüfbarer Text, Zeile `**Status:**`.
- **FR-004**: Fasst ein Nachtrag mehrere unabhängige Regeln zusammen, MUSS jede prüfbare Regel eine eigene Nummer bekommen, nicht nur jede Zeile in `nachtraege.md` (Entscheidung 31.10.2026). Der Plan kommt auf 37 neue Nummern.
- **FR-005**: Der Status MUSS `umgesetzt in X.Y.Z` lauten, mit der Version aus dem CHANGELOG, ab der der heutige Stand gilt. Bei Abweichung zwischen CHANGELOG und Code gilt `abweichend …` mit Begründung.
- **FR-006**: Der Text der Anforderung MUSS das heutige Verhalten beschreiben, das live ist (msb-rental 1.8.5, Bridge 0.4.2). Grundlage ist der CHANGELOG-Text, abgeglichen mit dem Code im Repository (Entscheidung 31.10.2026). Weicht der Code vom CHANGELOG ab, beschreibt der Text den Code, und der Status vermerkt die Abweichung.
- **FR-007**: Die Stufe MUSS `MUSS` sein, außer der CHANGELOG beschreibt das Verhalten als optional oder abschaltbar. Dann ist sie `SOLL`.
- **FR-008**: `nachtraege.md` MUSS danach je Eintrag die neue Nummer (mit Link) nennen und im Kopf vermerken, dass alle Einträge nummeriert sind.
- **FR-009**: Berührt ein Nachtrag eine bestehende Anforderung, MÜSSEN beide aufeinander verweisen (z. B. PAY-23).
- **FR-010**: Die Änderung DARF keinen Plugin-Code, keine Plugin-Version und kein CHANGELOG eines Plugins ändern.
- **FR-011**: Die Texte DÜRFEN keine Schlüssel, Secrets, Werte aus `msb_settings` oder Kundendaten enthalten (Grundsatz III). Konkrete Einstellwerte, die heute schon in der Spec stehen (z. B. „live: 1 Tag“), sind erlaubt.

### Key Entities

- **Nachtrag**: Zeile in `nachtraege.md` mit Funktion, Version(en) und Bereich(en).
- **Anforderung**: Eintrag in einer Bereichsdatei mit fester Nummer, Stufe, Text und Status.
- **Bereichsdatei**: eine Datei je Bereich unter `specs/anforderungen/` mit Übersichtstabelle und Abschnitten.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 17 von 17 Nachträgen haben mindestens eine Nummer, jede prüfbare Regel hat genau eine, und keine Nummer ist doppelt vergeben.
- **SC-002**: In jeder geänderten Bereichsdatei stimmen die Übersicht und die Abschnitte überein: gleiche IDs, gleiche Stufen, gleicher Status.
- **SC-003**: Zu jeder neuen Anforderung lässt sich in höchstens einer Minute die Version im CHANGELOG finden, die im Status steht.
- **SC-004**: Der Diff berührt nur Dateien unter `specs/`.

## Assumptions

- Maßgeblich sind die CHANGELOGs bis msb-rental 1.8.5 und Bridge 0.4.2.
- Kurzlinks `/ka/…` (IF/ART) und Kleinanzeigen-Vorlage gehören fachlich zu ART, die Quelle „Webseite über Kleinanzeigen“ und die UTM-Parameter zu IF.
- Die Bridge-Einstellung „Kundendaten schwärzen“ gehört zu BR, mit Verweis auf G-10.
- Die Löschregel „Abgeschlossene Buchungen ohne Zahlung und Beleg nach 3 Jahren“ ergänzt DSG-01 bis DSG-07 und bekommt eine neue DSG-Nummer.
- Die Zuordnung zu Bereichen im Einzelnen legt der Plan fest.
- Die Änderung ist reine Doku. Sie geht trotzdem über einen Pull Request, braucht aber keine Freigabe über die Bridge.
