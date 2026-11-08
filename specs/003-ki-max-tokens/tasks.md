# Tasks: KI-Artikelassistent bricht mit max_tokens ab

- [x] T001 Ursache prüfen: live 1.8.5 per SHA-256 gleich `main`, `max_tokens` 3000 in `class-msb-assistant.php`, Modelle denken adaptiv
- [x] T002 Konstanten `MAX_TOKENS` und `EFFORT`, Anfrage und Zeitlimit anpassen
- [x] T003 Meldungen für `max_tokens` und `refusal` trennen
- [x] T004 Test `tests/msb-rental/test-ki-max-tokens.php` schreiben, gegen 1.8.5 und 1.8.6 laufen lassen
- [x] T005 Version 1.8.6, CHANGELOG, KI-03 nachtragen
- [ ] T006 netcup-Snapshot (Moritz)
- [ ] T007 Bridge-Vorschläge je Datei, Freigabe (Moritz), SHA-256-Abgleich, in plan.md eintragen
- [ ] T008 Live-Test mit einem Vorschlag, dann Merge und Tag `msb-rental-v1.8.6`
