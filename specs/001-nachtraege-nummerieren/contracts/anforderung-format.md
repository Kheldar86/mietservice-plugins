# Format einer Anforderung

Gilt für jede neue Nummer aus dem [Nummernplan](../data-model.md). Es ist dasselbe Format wie bei den bestehenden Anforderungen in `specs/anforderungen/` (FR-003).

## Zeile in der Übersicht

Am Ende der Tabelle unter „## Übersicht“ der Bereichsdatei, in aufsteigender Reihenfolge:

```markdown
| PAY-29 | MUSS | umgesetzt in 1.6.0 |
```

## Abschnitt

Am Ende der Bereichsdatei, in aufsteigender Reihenfolge:

```markdown
### PAY-29 · MUSS

Ein Einbehalt aus der Kaution ergibt einen eigenen Beleg „Kautionsabrechnung“ … (siehe PAY-23).

**Status:** umgesetzt in 1.6.0
```

## Regeln

- ID, Stufe und Status sind in Übersicht und Abschnitt **zeichengleich**.
- Stufe ist `MUSS` oder `SOLL` (FR-007).
- Der Status hat eine dieser Formen:
  - `umgesetzt in X.Y.Z`
  - `umgesetzt in X.Y.Z (seit A.B.C)`
  - `umgesetzt in X.Y.Z, abweichend vom CHANGELOG: …`
  - `umgesetzt in Bridge X.Y.Z`
- Verweise auf andere Anforderungen stehen als ID im Text, z. B. „(siehe G-10)“.
- Keine Schlüssel, Secrets, Werte aus `msb_settings` und keine Kundendaten (FR-011). Links wie der zur Google-Bewertung werden nicht eingetragen.
