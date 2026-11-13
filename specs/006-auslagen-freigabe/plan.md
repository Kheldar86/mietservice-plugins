# Implementation Plan: Freigabe der Auslagenabrechnung und Hinweis über 250 €

**Branch**: `005-auslagenabrechnung` | **Date**: 2026-11-13 | **Spec**: [spec.md](spec.md)

## Summary

`MSB_Expenses`: neue Spalten `approved_at`, `approved_by` (Datenbankversion 1.10.1 über `maybe_install()`), `can_approve()`, `status_text()`, `approve()` (PDF neu mit Vermerk, dann Versand an Finom), `notify_approvers()` (Mail an Rolle Gesellschafter und Admin-Adresse ohne Empfänger und Ersteller), `mark_paid()` nur nach Freigabe, GiroCode nur nach Freigabe, Konstante `SMALL = 250`. `MSB_Admin_Expenses`: Freigabe-Kasten, Knopf „Prüfen und freigeben“, Zähler im Reiter, Hinweis über 250 € (Live-Anzeige und Meldung). Version msb-rental **1.10.1**, Datenbankversion **1.10.1**.

## Constitution Check (1.1.0)

| Grundsatz | Ergebnis |
|---|---|
| I. Spezifikation | PAY-43, PAY-44 neu, PAY-38/40/41, ROL-04, ADM-16 nachgetragen |
| II. Version | 1.10.1 im Kopf und in `MSB_VERSION`, CHANGELOG, Datenbankversion 1.10.1 mit Update beim ersten Seitenaufruf |
| III. Geheimnisse | keine |
| IV. Snapshot und Freigabe | vor den Vorschlägen neuer netcup-Snapshot |
| V. Prüfbar | 54 lokale Testfälle; live: Freigabe mit zweitem Konto |
| VI. Recht und Geld | Vier-Augen-Prinzip vor jeder Auszahlung; die PDF ändert sich bei der Freigabe nur um Vermerk und GiroCode. Datum der Betriebsausgabe weiter offen beim Steuerberater |
| VII. Einfach | keine Abhängigkeit, Aufbau wie 1.10.0 |

## Betroffene Dateien

- `plugins/msb-rental/includes/class-msb-expenses.php`
- `plugins/msb-rental/admin/class-msb-admin-expenses.php`
- `plugins/msb-rental/includes/class-msb-install.php` (nur `DB_VERSION`)
- `plugins/msb-rental/msb-rental.php` (nur Version)
- `plugins/msb-rental/admin/class-msb-docs.php` (Hilfe), `plugins/msb-rental/CHANGELOG.md`
- `specs/anforderungen/PAY-zahlung-kaution-storno.md`, `ROL-rollen.md`, `ADM-backend.md`
- `tests/msb-rental/test-expenses.php`

## Abnahmekriterien

- [x] Lokal: Vier-Augen-Regeln, Status, PDF ohne GiroCode vor und mit Vermerk und GiroCode nach der Freigabe (54 von 54)
- [ ] Live: Abrechnung von Moritz wartet auf Freigabe, Eric bekommt Mail und kann freigeben, Moritz selbst nicht
- [ ] Live: Hinweis bei 289,00 € sichtbar

## Freigabe

| Schritt | Wann | Notiz |
|---|---|---|
| Plan bestätigt von Moritz | 13.11.2026 | „Ja beides“ |
| netcup-Snapshot | 13.11.2026, ca. 07:00 | von Moritz bestätigt („Schnappschuss fertig“); Live-Dateien vorher per SHA-256 geprüft, alle 6 gleich 1.10.0 |
| Bridge-Vorschläge 1.10.1 | 13.11.2026, ca. 07:20 | 6 Vorschläge, je Datei einer: `class-msb-expenses.php` (ganz, 1b9c0921…), `class-msb-admin-expenses.php` (ganz, 953eeedc…), `class-msb-install.php` (nur `DB_VERSION`, c2a494c7…), `msb-rental.php` (2 Versionsstellen, 9dbbe588…), `class-msb-docs.php` (Hilfe, c7361f9d…), `CHANGELOG.md` (a8c08e9f…) |
| Freigegeben 1.10.1 | 13.11.2026, ca. 07:15 | alle 6 von Moritz freigegeben, Status „approved“, Ergebnis „Gespeichert“ |
| Live-Abgleich SHA-256 | 13.11.2026 | alle 6 Dateien identisch mit Commit 8029a11; Optionen `msb_db_version` und `msb_expenses_db` = 1.10.1, Spalten `approved_at` und `approved_by` vorhanden |
| Merge und Tag | | Tags `msb-rental-v1.10.0` und `msb-rental-v1.10.1` legt Moritz an |
