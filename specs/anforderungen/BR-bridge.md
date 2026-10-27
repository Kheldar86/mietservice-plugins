# BR – Claude MCP Bridge

> Übernommen am 27.10.2026 aus der [Anforderungsspezifikation](https://claude.ai/artifact/5zkRqvGmXM9q7RA5rLBiDk) (Stand 21.10.2026). Den Status je Anforderung haben wir anhand der CHANGELOGs bis msb-rental 1.8.4 und Bridge 0.4.2 nachgetragen. Ab jetzt ist diese Datei die maßgebliche Fassung.

## Übersicht

| ID | Stufe | Status |
|---|---|---|
| BR-01 | MUSS | umgesetzt (bis 0.4.0) |
| BR-02 | MUSS | umgesetzt (bis 0.4.0) |
| BR-03 | MUSS | umgesetzt (bis 0.4.0) |
| BR-04 | MUSS | umgesetzt (bis 0.4.0) |
| BR-05 | MUSS | umgesetzt (bis 0.4.0) |
| BR-06 | MUSS | umgesetzt (bis 0.4.0) |
| BR-07 | MUSS | umgesetzt (bis 0.4.0) |
| BR-08 | MUSS | umgesetzt (bis 0.4.0) |
| BR-09 | MUSS | umgesetzt (bis 0.4.0) |
| BR-10 | MUSS | umgesetzt (bis 0.4.0) |
| BR-11 | MUSS | umgesetzt (bis 0.4.0) |
| BR-12 | MUSS | umgesetzt (bis 0.4.0) |
| BR-13 | MUSS | umgesetzt (bis 0.4.0) |
| BR-14 | MUSS | umgesetzt (bis 0.4.0) |
| BR-15 | MUSS | umgesetzt (bis 0.4.0) |
| BR-16 | MUSS | umgesetzt (bis 0.4.0) |
| BR-17 | MUSS | umgesetzt (bis 0.4.0) |
| BR-18 | MUSS | umgesetzt in 0.4.1; 0.4.2 schwärzt zusätzlich Kundendaten (abschaltbar) |

### BR-01 · MUSS

Nach Installation ist die Bridge aus. Zugriff nur für Nutzer mit manage_options (fest, nicht einstellbar).

**Status:** umgesetzt (bis 0.4.0)

### BR-02 · MUSS

Verbinden nur in einem 15-Minuten-Kopplungsfenster, höchstens 20 Clients.

**Status:** umgesetzt (bis 0.4.0)

### BR-03 · MUSS

OAuth 2.1 mit PKCE S256. Weiterleitung nur an https://claude.ai/api/mcp/auth_callback (Loopback optional). Code 120 s, einmalig. Zugriffstoken 1 h, Refresh 30 Tage mit Rotation; Wiederverwendung sperrt die ganze Token-Familie.

**Status:** umgesetzt (bis 0.4.0)

### BR-04 · MUSS

Tokens werden nur als HMAC gespeichert und an die Ressource gebunden. Sie verfallen bei Löschung des Nutzers, Rollen- oder Passwortwechsel und Deaktivierung.

**Status:** umgesetzt (bis 0.4.0)

### BR-05 · MUSS

MCP-Endpunkt nur per POST, Origin-Allowlist, optional IP-Allowlist (160.79.104.0/21), Body ≤ 1 MB, keine Batch-Requests, höchstens 300 Anfragen/min je IP und 120/min je Token-Familie.

**Status:** umgesetzt (bis 0.4.0)

### BR-06 · MUSS

Scopes wp:read, wp:propose, wp:power, offline_access.

**Status:** umgesetzt (bis 0.4.0)

### BR-07 · MUSS

Jede Schreibaktion ist ein Vorschlag. Höchstens 25 offene je Nutzer. Gültigkeit 24 h (niedrig), 6 h (hoch), 1 h (kritisch).

**Status:** umgesetzt (bis 0.4.0)

### BR-08 · MUSS

Nur derselbe Admin entscheidet im Backend. Hoch und kritisch verlangen Passwort oder TOTP; TOTP ist Pflicht, wenn eingerichtet oder erzwungen. 5 Fehlversuche sperren 15 Minuten.

**Status:** umgesetzt (bis 0.4.0)

### BR-09 · MUSS

Vorschläge sind per HMAC gegen Veränderung geschützt und werden verworfen, wenn sich das Ziel seit dem Vorschlag geändert hat. Statusübergänge sind atomar.

**Status:** umgesetzt (bis 0.4.0)

### BR-10 · MUSS

Sammelfreigabe läuft vom ältesten zum neuesten, Plugin-Hauptdateien zuletzt, und stoppt beim ersten Fehler.

**Status:** umgesetzt (bis 0.4.0)

### BR-11 · MUSS

Inhalte werden mit wp_kses_post gefiltert. Neu anlegen nur Beiträge und Seiten, Löschen nur in den Papierkorb. Website-Einstellungen nur Titel und Untertitel.

**Status:** umgesetzt (bis 0.4.0)

### BR-12 · MUSS

Expertenmodus mit den Bereichen Dateien, PHP, SQL, WP-CLI, Plugins, alle standardmäßig aus. Not-Aus über die Konstante CMB_DISABLE_POWER_TOOLS. Live aktiv: Dateien, SQL, Plugins.

**Status:** umgesetzt (bis 0.4.0)

### BR-13 · MUSS

Dateizugriff nur in einer Sandbox mit Sperrlisten für Lesen und Schreiben. Die Bridge darf sich selbst, den WordPress-Kern und PHP in uploads nicht verändern.

**Status:** umgesetzt (bis 0.4.0)

### BR-14 · MUSS

PHP- und .htaccess-Dateien sind kritisch, alle anderen Dateien hoch. Vor dem Schreiben von PHP läuft eine Syntaxprüfung. Zurücksetzen nur, wenn die Datei seitdem unverändert ist.

**Status:** umgesetzt (bis 0.4.0)

### BR-15 · MUSS

SQL nur als einzelne Anweisung, mit Sperrliste für Schlüsselwörter, ohne Zugriff auf die eigenen cmb_-Tabellen. SELECT läuft in einer Nur-Lese-Transaktion, höchstens 200 Zeilen.

**Status:** umgesetzt (bis 0.4.0)

### BR-16 · MUSS

WP-CLI nur mit Allow- und Deny-Liste. Plugin-Installation nur aus wordpress.org.

**Status:** umgesetzt (bis 0.4.0)

### BR-17 · MUSS

Audit-Log aller Aktionen, 90 Tage aufbewahrt.

**Status:** umgesetzt (bis 0.4.0)

### BR-18 · MUSS

Lesende Werkzeuge (SQL, Dateien) schwärzen alle Geheimnisse, auch innerhalb serialisierter Optionen wie msb_settings und msb_ai_settings.

**Status:** umgesetzt in 0.4.1; 0.4.2 schwärzt zusätzlich Kundendaten (abschaltbar)
