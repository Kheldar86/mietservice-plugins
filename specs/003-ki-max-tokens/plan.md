# Implementation Plan: KI-Artikelassistent bricht mit max_tokens ab

**Branch**: `claude/project-thread-00bpeg` | **Date**: 2026-11-08 | **Spec**: [spec.md](spec.md)

## Summary

`MSB_Assistant::ajax_generate()` bekommt zwei Konstanten `MAX_TOKENS = 16000` und `EFFORT = 'medium'`. Die Anfrage nutzt beide, das Zeitlimit steigt auf 170 s. Die Auswertung von `stop_reason` trennt `max_tokens`, `refusal` und ungültiges JSON. Version msb-rental **1.8.6** (vorher 1.8.5).

## Technical Context

**Language/Version**: PHP 7.4+, WordPress 6.0+

**Primary Dependencies**: Anthropic Messages API (`/v1/messages`, `anthropic-version: 2023-06-01`), unverändert per `wp_remote_post`

**Storage**: keine Änderung

**Testing**: `php -l`, `php tests/msb-rental/test-ki-max-tokens.php` (nachgebildetes WordPress, keine Netzanfrage)

**Constraints**: Keine neue Abhängigkeit, kein Streaming (würde den Ablauf im Browser ändern), kein API-Schlüssel im Test (Platzhalter)

## Constitution Check

Geprüft gegen Constitution **1.1.0**.

| Grundsatz | Prüfung | Ergebnis |
|---|---|---|
| I. Die Spezifikation ist die Quelle | KI-03 geändert, Status nachgetragen | erfüllt |
| II. Jede Änderung ist eine neue Version | 1.8.6 im Kopf und in `MSB_VERSION`, CHANGELOG mit Behoben / Geändert / Update-Hinweise | erfüllt |
| III. Keine Geheimnisse | Test nutzt den Platzhalter „platzhalter“, keine Werte aus `msb_ai_settings` | erfüllt |
| IV. Live nur über Snapshot und Freigabe | Snapshot, dann je Datei ein Bridge-Vorschlag, SHA-256-Abgleich | offen, siehe Freigabe |
| V. Prüfbar und getestet | Test lokal, Abnahme live mit einem Vorschlag, der nicht gespeichert werden muss | erfüllt |
| VI. Recht und Geld zuerst | Betrifft weder Recht noch Belege. Kosten je Vorschlag steigen leicht (Denk-Tokens) | erfüllt |
| VII. Einfach und passend zum Bestand | Zwei Konstanten, Meldungen auf Deutsch | erfüllt |

## Betroffene Dateien

- `plugins/msb-rental/admin/class-msb-assistant.php`
- `plugins/msb-rental/msb-rental.php` (Version)
- `plugins/msb-rental/CHANGELOG.md`
- `specs/anforderungen/KI-artikel-assistent.md`
- `tests/msb-rental/test-ki-max-tokens.php` (neu, wird nicht live eingespielt)

## Datenbank

Keine Änderung.

## Abnahmekriterien

- [x] Anfrage mit `max_tokens` 16000 und `output_config.effort` „medium“, ohne `thinking` und `temperature`
- [x] Zeitlimit 170 s
- [x] Eigene Meldungen für `max_tokens` und `refusal`, bisherige Meldung bei ungültigem JSON
- [x] Version 1.8.6, CHANGELOG, Status KI-03
- [ ] Live: Vorschlag mit Sonnet 5.5 und mehreren Fotos kommt vollständig an

## Testschritte

1. Lokal: `php -l plugins/msb-rental/admin/class-msb-assistant.php` und `php tests/msb-rental/test-ki-max-tokens.php`. Ergebnis am 08.11.2026: keine Syntaxfehler, 10 von 10 Fällen bestanden. Gegen 1.8.5 schlagen 5 Fälle fehl.
2. Live nach der Freigabe: Vermietung → KI-Assistent, Kategorie wählen, 3 bis 6 Fotos des neuen Artikels, „Vorschlag erstellen“. Der Vorschlag ist vollständig, die Token-Zahl steht darunter. Speichern ist für den Test nicht nötig (es entsteht ohnehin nur ein Entwurf).

## Freigabe

| Schritt | Wann | Notiz |
|---|---|---|
| Plan bestätigt von Moritz | 08.11.2026 | durch Merge von PR #9 |
| netcup-Snapshot | 08.11.2026 | von Moritz angelegt (Name nicht notiert) |
| Bridge-Vorschläge | 08.11.2026 | 87a18564 (class-msb-assistant.php), 0cc7fb29 (msb-rental.php), 93861ea3 (CHANGELOG.md), je Datei einer |
| Freigegeben | 08.11.2026 | alle drei |
| Live-Abgleich SHA-256 | 08.11.2026 | alle drei Dateien gleich dem Branch (83d62de2…, 80cf218a…, c1e23546…) |
| Merge und Tag | 08.11.2026 | PR #9 gemergt vor der Freigabe; Tag `msb-rental-v1.8.6` legt Moritz in GitHub an |
