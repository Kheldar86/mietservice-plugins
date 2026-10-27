# Changelog

Ausführliche Release Notes stehen im Plugin unter **Claude MCP → Release Notes** (`docs/release-notes.html`).

## 0.4.2 – 2026-10-26
### Datenschutz
- Neue Einstellung „Kundendaten schwärzen“ (standardmäßig an): Namen, E-Mail-Adressen, Telefonnummern, Anschriften und Anmerkungen von Kunden kommen in SQL-Ergebnissen nur als `[geschwärzt]` bei Claude an. Erkannt über Spaltennamen (z. B. `customer_name`, `user_email`, `first_name`), Benutzer-Metadaten (`first_name`, `billing_*`), Felder in JSON-/serialisierten Werten (z. B. Buchungsformular, Stripe-Daten) und E-Mail-Adressen fremder Domains in jedem Wert
- Belege (PDF/CSV) in `uploads/msb-belege` werden bei aktiver Einstellung nicht ausgeliefert
### Hinweis
- Schutz gegen versehentliche Ausgabe, wie bei 0.4.1: Umbenannte Spalten (`AS`) oder Teilabfragen werden nur über das E-Mail-Muster erkannt
### Tests
- Lokale Tests (Buchungen, Formulardaten, Benutzer-Metadaten, Stripe-Daten, Geheimnisse weiter geschwärzt, Einstellung aus)

## 0.4.1 – 2026-10-22
### Sicherheit
- Schwärzung von Geheimnissen auch in serialisierten und JSON-Werten (z. B. `msb_settings`, `msb_ai_settings`): Werte werden entpackt und nach Schlüsselnamen (secret, password, api_key, token, webhook …) geschwärzt. Bisher wurden nur Options- bzw. Meta-Namen geprüft, sodass Stripe-Schlüssel und Webhook-Secret ungeschwärzt zurückkamen (BR-18)
- Bekannte Schlüsselformate (Stripe `sk_`/`rk_`/`whsec_`, Anthropic, OpenAI, GitHub, Slack, AWS, private Schlüssel, Passwort-Hashes) werden in jeder Antwort an Claude geschwärzt, auch in Dateien, WP-CLI- und PHP-Ausgaben und Fehlermeldungen. Das Präfix bleibt sichtbar (z. B. `rk_live_[geschwärzt]`), damit die Art des Schlüssels erkennbar ist
### Hinweis
- Schutz gegen versehentliche Ausgabe. Wer gezielt Teile eines Werts abfragt (z. B. `SUBSTRING`, `HEX`), kann die Schwärzung umgehen; SQL im Expertenmodus bleibt Administratoren vorbehalten
### Tests
- Lokale Tests der Schwärzung (serialisiert, verschachtelt, JSON, Freitext, unveränderte Ausgabe ohne Geheimnisse)

## 0.4.0 – 2026-10-17
### Neu
- Option „Freigaben nur mit Zwei-Faktor-Code“: Freigaben (hoch/kritisch) ohne WordPress-Passwort, nur mit Aegis-Code. Standardmäßig aus; greift nur bei eingerichteter Zwei-Faktor-Bestätigung
- Sammelfreigabe auf der Seite *Freigaben*: Vorschläge markieren und mit einer Bestätigung freigeben oder ablehnen
- Sichere Ausführungsreihenfolge: älteste zuerst, Plugin-Hauptdateien (Kopfzeile „Plugin Name:“) zuletzt; Abbruch beim ersten Fehler, Rest bleibt offen
- Hinweis nach Einzelfreigabe, wie viele Vorschläge noch offen sind
### Unverändert (bewusst)
- Rückgängig, Expertenmodus und Zwei-Faktor-Einstellungen verlangen weiterhin Passwort und Code
- Sperre nach 5 Fehlversuchen, Integritäts- und Veraltungsprüfung je Vorschlag
### Tests
- 44 neue End-to-End-Tests gegen WordPress 6.8.3 (Bestätigung, Sammelfreigabe, Reihenfolge, Abbruch, Integrität, Nonce, Oberfläche)

## 0.3.0 – 2026-10-10
### Neu
- Zwei-Faktor-Bestätigung für Freigaben (TOTP nach RFC 6238, kompatibel mit Aegis): otpauth-Link, manueller Schlüssel, 10 Backup-Codes
- Code-Abfrage bei riskanten Freigaben, Rückgängig und Expertenmodus; optional auch für normale Freigaben; Pflicht-Richtlinie
### Sicherheit
- Schlüssel verschlüsselt (libsodium), Backup-Codes als HMAC, Replay-Schutz, gemeinsame Fehlversuchssperre
### Tests
- 213 End-to-End-Tests (81 Basis + 99 Expertenmodus + 33 Zwei-Faktor)

## 0.2.0 – 2026-10-10
### Neu
- Expertenmodus (jede Kategorie einzeln, standardmäßig aus, Einschalten nur mit Passwort):
  - Dateien: `list_files`, `read_file`, `search_files`, `propose_write_file`, `propose_edit_file`, `propose_delete_file`
  - PHP: `propose_run_php`
  - Datenbank: `db_select`, `propose_db_query`
  - WP-CLI: `wp_cli_read`, `propose_wp_cli`
  - Plugins: `list_plugins`, `plugin_directory_info`, `propose_plugin_action`
- Risikostufen normal/hoch/kritisch, Passwortabfrage ab „hoch“, Gültigkeit 24 h / 6 h / 1 h
- Rückgängig machen für Datei-Änderungen
- OAuth-Scope `wp:power`, Kill-Switch `CMB_DISABLE_POWER_TOOLS`
- Integriertes Handbuch und Release Notes

### Sicherheit
- Pfad-Sandbox (kein `..`, keine Symlink-Ausbrüche), Sperrlisten für Zugangsdaten, Backups, Core, PHP in Uploads und die Bridge selbst
- PHP-Syntaxprüfung vor dem Schreiben
- SQL: eine Anweisung, doppelte Analyse, gesperrte Anweisungsarten, READ-ONLY-Transaktion für Lesezugriffe, Schwärzung von Passwort-Hashes
- WP-CLI ohne Shell, gesperrte Befehle/Optionen, Zeitlimit
- Erneute Prüfung bei Ausführung (Bereich aktiv, Ziel unverändert)

### Tests
- 180 End-to-End-Tests (81 Basis + 99 Expertenmodus)

## 0.1.0 – 2026-10-10
- Erste Version: MCP-Server, OAuth 2.1 mit PKCE und Kopplungsfenster, Lese-Tools, Vorschläge mit Freigabe für Inhalte, Protokoll, Widerruf
