---
name: "release"
description: "Neue Version von msb-rental oder claude-mcp-bridge fertig machen, live bringen und abschließen: Version, Tests, Snapshot, Bridge-Freigabe, SHA-256-Abgleich, Pull Request, Tag-Befehle für Moritz, Pläne nachtragen."
argument-hint: "<plugin> <version>, z. B. msb-rental 1.10.2"
---

# /release <plugin> <version>

Die Constitution (`.specify/memory/constitution.md`) geht vor. Bei Widerspruch gilt sie. Reine Doku-Änderungen ohne Code brauchen weder Version noch Snapshot, Freigabe oder Tag.

## 0. Vorab prüfen

- `php -v` (für die Tests), `gh auth status` und `git config --get credential.helper` (für Push und Pull Request).
- Bridge erreichbar: `site_info`, dazu `list_pending_changes`. Liegt schon ein offener Vorschlag auf einer Datei, die sich ändert, erst klären.
- Fehlt etwas, sofort Moritz sagen, was fehlt. Nicht herumprobieren.

## 1. Code fertig machen (eigener Branch, nie `main`)

- Version erhöhen: Plugin-Kopf und Konstante (`MSB_VERSION` bzw. `CMB_VERSION`). Bei Änderungen an Tabellen auch `DB_VERSION` in `includes/class-msb-install.php` bzw. `CMB_DB_VERSION`.
- `CHANGELOG.md` ergänzen, Status in `specs/anforderungen/` nachtragen.
- Tests: `for t in tests/msb-rental/*.php; do php "$t" || break; done`. Weiter nur, wenn alle grün sind.
- Diff auf Geheimnisse prüfen (Grundsatz III), dann committen.

## 2. Live bringen (Grundsatz IV)

1. Moritz um einen netcup-Snapshot bitten und auf seine Bestätigung warten.
2. Live-Stand prüfen: `read_file` liefert `sha256`. Jede betroffene Live-Datei muss der Branch-Basis entsprechen (`shasum -a 256`). Abweichungen erst melden.
3. Je geänderter Datei genau einen Vorschlag (`propose_write_file` oder `propose_edit_file`). Mehrere Änderungen an einer Datei zusammenfassen, denn ein zweiter Vorschlag wird nach Freigabe des ersten verworfen. IDs notieren.
4. Moritz gibt im WordPress-Backend unter „Claude-Freigaben“ frei. Vorschläge laufen nach etwa einer Stunde ab.
5. `get_change_status` je ID, dann SHA-256 jeder Live-Datei mit dem Branch vergleichen. Bei Datenbank-Änderungen Optionen und Spalten per `db_select` prüfen.
6. Snapshot, Vorschläge (Uhrzeit, IDs), Freigabe und Abgleich in der Tabelle „Freigabe“ in `specs/NNN-…/plan.md` vermerken.

## 3. Pull Request

- Vor `git push` und `gh pr create` Moritz fragen.
- PR-Text mit den Überschriften aus `.github/pull_request_template.md`: Änderung, Betroffene Anforderungen, Version, Test, Freigabe.
- Nie selbst `gh pr merge`. Moritz mergt.
- Nach dem Merge prüfen, dass `main` die Live-Dateien enthält: `git rev-parse origin/main:<pfad>` gleich dem Stand des freigegebenen Commits.

## 4. Tag (setzt Moritz)

- Den Commit der Versionserhöhung ermitteln: `git log origin/main -G"define\( 'MSB_VERSION'" --format='%h %s' -- plugins/msb-rental/msb-rental.php`.
- Moritz die fertigen Befehle geben:
  - `git tag -a <plugin>-v<version> <commit> -m "<plugin> <version>"`
  - `git push origin <plugin>-v<version>`
- Danach mit `git ls-remote --tags origin` prüfen, ob der Tag auf den richtigen Commit zeigt.

## 5. Abschluss

- In `plan.md` die Zeile „Merge und Tag“ ausfüllen, in `tasks.md` die Aufgaben abhaken. Das geht als kleiner Doku-PR ohne Version und Tag.
- Was live noch nicht geprüft ist, offen lassen und im Plan so benennen.
