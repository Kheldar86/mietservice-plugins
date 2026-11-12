# Tasks: Auslagenabrechnung pro Gesellschafter

- [x] T001 Bestand prüfen: `MSB_Documents` (Nummernkreise, PDF, Ablage), `MSB_PDF`, `MSB_QR`, `MSB_Euer`, `MSB_Retention`, Rollen
- [x] T002 Tabellen `msb_expenses` und `msb_expense_claims`, Datenbankversion 1.10.0 (`MSB_Install`)
- [x] T003 Recht `msb_manage_expenses` (`msb_capability( 'expenses' )`, Gesellschafter, Administratoren), Einstellung `expense_finom_email`
- [x] T004 `MSB_Expenses`: Auslagen, Upload mit JPEG-Umwandlung, Abrechnung mit Nummernkreis AUS, PDF nach Vorlage mit GiroCode und Fotoanlagen, Versand an Finom, ausgezahlt, Storno, EÜR-Kasten und CSV, Fristablauf
- [x] T005 `MSB_Admin_Expenses`: Seite Vermietung → Auslagen (Meine Auslagen, Abrechnungen, Meine Bankverbindung), geschützter Dateiabruf
- [x] T006 Einbindung: `msb-rental.php`, Einstellung und Kasten in `MSB_Documents`, Regel in `MSB_Retention`, Hilfe in `MSB_Docs`
- [x] T007 Test `tests/msb-rental/test-expenses.php`
- [x] T008 Version 1.10.0, CHANGELOG, ANLEITUNG, Anforderungen PAY-37 bis PAY-42, ADM-16, ROL-04, DSG-12
- [ ] T009 netcup-Snapshot (Moritz)
- [ ] T010 Bridge-Vorschläge je Datei, Freigabe (Moritz), SHA-256-Abgleich, in plan.md eintragen
- [ ] T011 Live-Test mit 0,01 € (Abrechnung, PDF, GiroCode, Finom-Postfach, Storno), dann Pull Request, Merge und Tag `msb-rental-v1.10.0`
