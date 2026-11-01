# Quickstart: Prüfung

Alle Befehle im Wurzelverzeichnis des Repositorys.

## SC-001: Texte stimmen mit dem Code

Für jede Anforderung die Fundstellen aus [research.md](research.md) öffnen und mit dem neuen Text vergleichen:

- ADM-04: R-01, R-02
- IF-06: R-03, R-04, R-05
- DSG-02: R-06. Rechenbeispiel aus der Spec: Rechnung 2026, Kautionsabrechnung 2027 → 31.12.2035.

## SC-002: Befunde erledigt, keine alten Verweise

```sh
grep -nE 'siehe B-1[456]|überholt in Teilen' specs/anforderungen/*.md   # erwartet: keine Ausgabe
grep -nE '^\| B-1[456] ' specs/anforderungen/befunde-und-fragen.md | grep -c behoben   # erwartet: 3
grep -c '^- \*\*F-13\*\*' specs/anforderungen/befunde-und-fragen.md   # erwartet: 1
```

## SC-003: Übersicht = Abschnitte

Prüfung SC-002 aus [001/quickstart.md](../001-nachtraege-nummerieren/quickstart.md) für ADM, IF und DSG; erwartet `OK`.

## SC-004: nur specs/

```sh
git fetch -q && git diff --name-only origin/main... | grep -v '^specs/'   # erwartet: keine Ausgabe
```
