=== Claude MCP Bridge ===
Contributors: moritzkaestner
Tags: claude, mcp, ai, oauth, approval
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.4.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Verbindet WordPress als MCP-Connector mit Claude (Web, Desktop, Mobile). Jede Änderung braucht deine Freigabe im Backend.

== Description ==

Claude kann Inhalte lesen und Änderungen vorschlagen. Ausgeführt wird jede Änderung erst nach deiner Freigabe in WordPress – bei riskanten Aktionen mit Passwortbestätigung.

* OAuth 2.1 mit PKCE, Kopplungsfenster, Refresh-Token-Rotation
* Lesen sofort, Ändern nur als Vorschlag mit Freigabe-Link
* Optionaler Expertenmodus: Dateien, PHP, SQL, WP-CLI, Plugins – einzeln schaltbar
* Vorher/Nachher-Diff, Veraltungsschutz, Rückgängig für Dateiänderungen, Protokoll
* Zwei-Faktor-Bestätigung für Freigaben mit Aegis oder jeder TOTP-App, optional ohne Passwort
* Sammelfreigabe: mehrere Vorschläge mit einem Code, Plugin-Hauptdateien automatisch zuletzt

Das vollständige Handbuch und die Release Notes sind im Plugin unter „Claude MCP → Handbuch“ bzw. „Release Notes“ enthalten (Dateien `docs/handbuch.html` und `docs/release-notes.html`).

== Installation ==

1. ZIP unter Plugins → Installieren → Plugin hochladen installieren und aktivieren.
2. Claude MCP → Einstellungen: „Bridge aktiv“ setzen, „Kopplung starten“ klicken.
3. In Claude: Einstellungen → Connectors → Benutzerdefinierten Connector hinzufügen, Connector-URL einfügen, verbinden und in WordPress zustimmen.

== Frequently Asked Questions ==

= Kann Claude ohne meine Zustimmung etwas ändern? =

Nein. Alle ändernden Tools legen nur Vorschläge an. Ausgeführt wird im WordPress-Backend durch dich (eingeloggt, Nonce, ab Risiko „hoch“ mit Passwort).

= Wie schalte ich alles sofort ab? =

Claude MCP → Verbindungen → „Alle Verbindungen trennen“, oder in wp-config.php `define( 'CMB_DISABLE_POWER_TOOLS', true );` für den Expertenmodus.

== Changelog ==

= 0.4.2 – 2026-10-26 =
* Datenschutz: Neue Einstellung „Kundendaten schwärzen“ (standardmäßig an). Namen, E-Mail, Telefon, Anschrift und Anmerkungen von Kunden werden in SQL-Ergebnissen geschwärzt, Belege in uploads/msb-belege nicht ausgeliefert.

= 0.4.1 – 2026-10-22 =
* Sicherheit: Lesende Werkzeuge schwärzen Geheimnisse jetzt auch in serialisierten und JSON-Optionen (z. B. Stripe- und API-Schlüssel in Plugin-Einstellungen) sowie bekannte Schlüsselformate in jeder Antwort an Claude.

= 0.4.0 – 2026-10-17 =
* Neu: Option „Freigaben nur mit Zwei-Faktor-Code“ – Freigaben ohne WordPress-Passwort, nur mit Aegis-Code.
* Neu: Sammelfreigabe – mehrere Vorschläge mit einer Bestätigung freigeben oder ablehnen.
* Neu: Sichere Ausführungsreihenfolge – Plugin-Hauptdateien werden zuletzt geschrieben, Abbruch beim ersten Fehler.

= 0.3.0 – 2026-10-10 =
* Neu: Zwei-Faktor-Bestätigung (TOTP, kompatibel mit Aegis) für riskante Freigaben, Rückgängig und Expertenmodus.
* Neu: Backup-Codes, Richtlinien „erzwingen“ und „auch für normale Freigaben“.
* Sicherheit: verschlüsselter Schlüssel, Replay-Schutz, gemeinsame Fehlversuchssperre.

= 0.2.0 – 2026-10-10 =
* Neu: Expertenmodus mit Dateien, PHP-Ausführung, SQL, WP-CLI und Plugin-Verwaltung – alle Änderungen nur nach Freigabe.
* Neu: Risikostufen (normal/hoch/kritisch), Passwortabfrage ab „hoch“, kürzere Gültigkeit für riskante Vorschläge.
* Neu: Rückgängig machen für Dateiänderungen, Diff-Ansicht für Dateien.
* Neu: OAuth-Berechtigung wp:power, Kill-Switch CMB_DISABLE_POWER_TOOLS.
* Neu: Integriertes Handbuch und Release Notes.
* Sicherheit: Pfad-Sandbox, Sperrlisten, PHP-Syntaxprüfung, SQL-Analyse in zwei Modi, WP-CLI ohne Shell.

= 0.1.0 – 2026-10-10 =
* Erste Version: MCP-Server, OAuth 2.1, Lese-Tools, Vorschläge mit Freigabe für Inhalte.

== Upgrade Notice ==

= 0.4.2 =
Datenschutz-Update: Kundendaten werden beim Lesen über Claude geschwärzt. Standardmäßig an, abschaltbar unter Claude MCP → Einstellungen.

= 0.4.1 =
Sicherheitsupdate: Geheimnisse in Plugin-Einstellungen werden beim Lesen über Claude geschwärzt. Keine Einstellungen nötig.

= 0.4.0 =
Neue Option unter Claude MCP → Einstellungen → Zwei-Faktor-Bestätigung: Freigaben nur mit Code. Standardmäßig aus.

= 0.3.0 =
Neue Zwei-Faktor-Bestätigung für Freigaben. Einrichtung unter Claude MCP → Einstellungen.

= 0.2.0 =
Expertenmodus ist standardmäßig aus. Nach dem Einschalten den Connector in Claude neu verbinden.
