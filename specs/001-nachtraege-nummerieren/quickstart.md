# Quickstart: Prüfung der Nummerierung

Prüft die Erfolgskriterien SC-001 bis SC-004 nach der Umsetzung. Alle Befehle laufen im Wurzelverzeichnis des Repositorys und brauchen nur die Shell.

## SC-001: alle Nummern da, keine doppelt

```sh
# Jede neue ID genau einmal als Abschnitt (erwartet: 37 Zeilen, alle mit "1")
for id in ADM-0{7,8,9} ADM-1{0,1,2,3,4} BKG-1{8,9} BKG-2{0,1} PAY-2{6,7,8,9} PAY-3{0,1,2,3,4,5,6} IF-1{2,3,4,5,6,7,8,9} DSG-0{8,9} ART-0{6,7} BR-19 BR-20; do
  printf '%s %s\n' "$id" "$(grep -c "^### $id ·" specs/anforderungen/*.md | awk -F: '{s+=$2} END {print s}')"
done

# Keine ID doppelt in allen Bereichsdateien (erwartet: keine Ausgabe)
grep -ho '^### [A-Z]*-[0-9]*' specs/anforderungen/*.md | sort | uniq -d
```

Erwartet: 37 Zeilen mit `1`, danach keine Ausgabe.

## SC-001: jeder Nachtrag hat eine Nummer

```sh
# Erwartet: 0 (keine Zeile ohne ID in der Tabelle)
grep '^| ' specs/anforderungen/nachtraege.md | grep -v '^| Funktion\|^|---' | grep -vcE '[A-Z]+-[0-9]+'
```

## SC-002: Übersicht = Abschnitte

```sh
for f in specs/anforderungen/{ADM,BKG,PAY,IF,DSG,ART,BR}-*.md; do
  diff <(grep -oE '^\| [A-Z]+-[0-9]+ \| (MUSS|SOLL)[^|]* \| [^|]*' "$f" | awk -F' \\| ' '{sub(/^\| /,"",$1); split($2,s," "); sub(/ *$/,"",$3); print $1" "s[1]"|"$3}' | sort) \
       <(awk '/^### /{id=$2" "$4; next} /^\*\*Status:\*\*/{sub(/^\*\*Status:\*\* /,""); sub(/ *$/,""); print id"|"$0}' "$f" | sort) >/dev/null \
    && echo "OK   $f" || echo "DIFF $f"
done
```

Erwartet: nur `OK`. Bei `DIFF` die Datei ohne `>/dev/null` erneut prüfen. Verglichen werden ID, Stufe (erstes Wort) und Status. Zusätze wie „(Stripe)“ hinter der Stufe in der Überschrift sind bestehendes Format. Am Stand vom 31.10.2026 vor der Umsetzung getestet: alle sieben Dateien `OK`.

## SC-004: nur Dateien unter specs/

```sh
git diff --name-only main... | grep -v '^specs/'
```

Erwartet: keine Ausgabe.

## Stichprobe gegen den Code (FR-006)

Für drei zufällig gewählte neue Nummern die Stelle im Code aufsuchen, die [research.md](research.md) bzw. der CHANGELOG nennt, und den Text vergleichen. Für A-01 und A-02 muss der Status die Abweichung vermerken.
