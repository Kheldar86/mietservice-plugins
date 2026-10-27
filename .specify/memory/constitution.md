# Mietservice Barnim Plugins – Constitution

Grundregeln für die Arbeit an den WordPress-Plugins **msb-rental** und **Claude MCP Bridge** von mietservice-barnim.de. Sie gelten für jede Spezifikation, jeden Plan, jede Aufgabe und jede Umsetzung und gehen den einzelnen Anforderungen vor.

## Grundsätze

### I. Die Spezifikation ist die Quelle

Was die Plugins können müssen, steht in `specs/anforderungen/`: eine Datei je Bereich, jede Anforderung mit fester Nummer (z. B. `PRS-04`) und Status. Neue Features (`specs/NNN-name/`) nennen in `spec.md` die Nummern, die sie anlegen oder ändern. Nach der Umsetzung werden diese Anforderungen in `specs/anforderungen/` ergänzt und ihr Status auf `umgesetzt in X.Y.Z` gesetzt. Code ohne passende Anforderung gibt es nicht. Eine Nummer wird nie neu vergeben.

### II. Jede Änderung ist eine neue Version (nicht verhandelbar)

Jede Änderung am Code erhöht die Plugin-Version: im Plugin-Kopf und bei msb-rental zusätzlich in `MSB_VERSION`. Dazu gehört ein Eintrag im `CHANGELOG.md` des Plugins mit den Abschnitten Neu / Geändert / Behoben / Update-Hinweise. Eine Änderung an der Datenbank erhöht die Datenbankversion und braucht ein automatisches Update beim ersten Seitenaufruf. Stille Änderungen an einer schon live gegangenen Version gibt es nicht.

### III. Keine Geheimnisse im Repository (nicht verhandelbar)

Ins Repository, in Spezifikationen, Pläne, Aufgaben, Tests, Commits und Pull Requests kommen nie: Stripe- oder PayPal-Schlüssel, Webhook-Secrets, der Claude-API-Schlüssel, Passwörter, Werte aus `msb_settings` oder `msb_ai_settings`, Datenbank-Exporte oder Kundendaten. Beispiele verwenden erfundene Platzhalter. Auch im Betrieb erscheinen Geheimnisse nie in Seiten, E-Mails, Exporten, Logs oder Antworten an eine KI (G-10).

### IV. Live nur über Snapshot und Freigabe (nicht verhandelbar)

Code geht nur über die Claude MCP Bridge live. Vorher wird ein netcup-Snapshot angelegt. Claude stellt Freigabe-Vorschläge ein, Moritz gibt sie im WordPress-Backend frei. Vorschläge laufen nach etwa einer Stunde ab. Nach der Freigabe werden die Live-Dateien per SHA-256 mit dem Branch abgeglichen. Kein anderes Werkzeug ändert die Live-Seite.

### V. Prüfbar und getestet

Jede Anforderung ist so formuliert, dass sie sich prüfen lässt. Jeder Plan nennt Abnahmekriterien und Testschritte. Getestet wird zuerst lokal, live nur mit Buchungen, die als Testbuchung markiert sind (Belege `TEST-JJJJ-0001`). Echte Kunden bekommen keine Testmails.

### VI. Recht und Geld zuerst

Rechtliche Vorgaben und Regeln zum Geld stehen über Komfort: Kleinunternehmerregelung (§ 19 UStG, kein Umsatzsteuerausweis), Button „Zahlungspflichtig buchen“ (§ 312j BGB), Mietbedingungen nur in den AGB (G-03), lückenlose Nummernkreise für Rechnungen, Belege werden nie geändert oder gelöscht, Aufbewahrung 8 Jahre, Löschfristen nach DSG. Wo eine Änderung Steuer oder Recht berührt, nennt der Plan die offene Frage an Steuerberater oder Anwalt, statt sie zu entscheiden.

### VII. Einfach und passend zum Bestand

Die Plugins bleiben eigenständiges WordPress-PHP ohne Build-Schritt und ohne neue externe Abhängigkeiten. Neuer Code folgt dem Stil der vorhandenen Klassen (`MSB_…`, `CMB_…`). Oberfläche, Fehlermeldungen und E-Mails sind auf Deutsch (G-15). Im Backend gilt auf dem Handy der Kartenstil: harmlose Aktionen ganz links, unwiderrufliche ganz rechts und deutlich getrennt.

## Rahmen

- Zielumgebung: WordPress auf netcup, Zeitzone Europe/Berlin, Beträge in Euro mit 2 Nachkommastellen.
- Externe Dienste: Stripe, PayPal, Anthropic Messages API (msb-rental) und OAuth 2.1/MCP mit Claude (Bridge). Zugangsdaten liegen nur in den Einstellungen.
- Rollen: Administrator (Moritz) und Gesellschafter (Eric) nach ROL-01 bis ROL-03. Nur Administratoren nutzen die Bridge.

## Ablauf

1. `/speckit-specify` legt `specs/NNN-name/spec.md` an. Dabei werden die betroffenen Anforderungsnummern genannt.
2. `/speckit-clarify` klärt offene Punkte. Entscheidungen von Moritz werden mit Datum vermerkt.
3. `/speckit-plan` erstellt den Plan mit neuer Version, betroffenen Dateien, Datenbankänderung, Abnahmekriterien und Testschritten. Moritz bestätigt den Plan.
4. `/speckit-tasks` und `/speckit-implement` setzen ihn um, auf einem eigenen Branch als Pull Request. Der PR enthält Code, Version, CHANGELOG und die nachgetragenen Status in `specs/anforderungen/`.
5. Freigabe nach Grundsatz IV, dann Merge und Tag `msb-rental-vX.Y.Z` bzw. `claude-mcp-bridge-vX.Y.Z`.

## Governance

Diese Constitution geht allen anderen Vorgaben im Repository vor. Jeder Plan prüft sie im Abschnitt „Constitution Check“, und jeder Pull Request bestätigt sie über die PR-Vorlage. Änderungen an der Constitution beschließt Moritz. Sie erhöhen deren Version: MAJOR, wenn ein Grundsatz wegfällt oder sich grundlegend ändert, MINOR für einen neuen Grundsatz oder Abschnitt, PATCH für Klarstellungen.

**Version**: 1.0.0 | **Ratified**: 2026-10-27 | **Last Amended**: 2026-10-27
