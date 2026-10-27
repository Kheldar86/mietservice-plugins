# Claude MCP Bridge – v0.4.2

WordPress-Plugin, das deine Website als **MCP-Connector für Claude** bereitstellt (Web, Desktop, Claude-App auf dem Handy). Claude liest sofort und schlägt Änderungen vor – **ausgeführt wird jede Änderung erst nach deiner Freigabe im WordPress-Backend**, bei riskanten Aktionen zusätzlich mit Passwort.

## Dokumentation im Plugin

- **Handbuch**: *Claude MCP → Handbuch* (`docs/handbuch.html`) – Einrichtung, Arbeiten mit Claude, Freigaben, Expertenmodus, Tool-Referenz, Sicherheitsmodell, Notfall, Fehlerbehebung
- **Release Notes**: *Claude MCP → Release Notes* (`docs/release-notes.html`)
- `readme.txt` (WordPress-Format) und `CHANGELOG.md`

## Schnellstart

1. ZIP unter *Plugins → Installieren → Plugin hochladen* installieren und aktivieren (HTTPS + Permalinks ≠ „Einfach“ nötig).
2. *Claude MCP → Einstellungen*: „Bridge aktiv“, dann **Kopplung starten (15 Min.)**.
3. In Claude: *Einstellungen → Connectors → Benutzerdefinierten Connector hinzufügen* → Connector-URL einfügen → verbinden → in WordPress **Erlauben**.
4. Empfohlen: *Zwei-Faktor-Bestätigung* mit Aegis einrichten (Einstellungen → „Einrichtung starten“ → „In Authenticator-App öffnen“).
5. Optional: *Freigaben nur mit Zwei-Faktor-Code* (ohne Passwort) einschalten und mehrere Vorschläge unter *Freigaben* gesammelt freigeben.
6. Optional: *Expertenmodus* einzeln einschalten (Passwort + Code nötig), danach Connector in Claude neu verbinden.

## Was geht sofort, was nur mit Freigabe?

| Bereich | Sofort | Nur mit Freigabe |
|---|---|---|
| Inhalte | Beiträge, Seiten, Medien, Kommentare lesen | Anlegen, ändern, Papierkorb, Moderation, Titel/Untertitel |
| Dateien* | Auflisten, lesen, durchsuchen | Anlegen, ändern, löschen (Backup + Rückgängig) |
| PHP* | – | Code ausführen |
| Datenbank* | SELECT/SHOW/DESCRIBE/EXPLAIN | Alle ändernden Anweisungen |
| WP-CLI* | Lesende Befehle (Allowlist) | Alle anderen Befehle |
| Plugins* | Liste, Infos von wordpress.org | Installieren, (de)aktivieren, aktualisieren, löschen |

\* Expertenmodus, standardmäßig aus.

Immer geschwärzt: Passwort-Hashes, Sitzungen und Geheimnisse wie API-Schlüssel und Webhook-Secrets – auch in serialisierten Plugin-Einstellungen (ab 0.4.1).

Standardmäßig geschwärzt (ab 0.4.2, abschaltbar): Kundendaten wie Namen, E-Mail-Adressen, Telefonnummern, Anschriften und Anmerkungen in SQL-Ergebnissen; Belege in uploads/msb-belege werden nicht ausgeliefert.

Immer gesperrt: Zugangsdaten (wp-config.php, .env, Schlüssel, Backups), WordPress-Core, PHP im Upload-Ordner, die Bridge selbst (Dateien, Tabellen, Deaktivieren über Claude).

Details zum Sicherheitsmodell: siehe Handbuch, Kapitel 7.
