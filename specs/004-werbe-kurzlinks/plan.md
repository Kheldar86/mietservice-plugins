# Implementation Plan: Werbe-Kurzlinks für QR-Codes

**Branch**: `claude/project-thread-3ehakv` | **Date**: 2026-11-10 | **Spec**: [spec.md](spec.md)

## Summary

Neue Klasse `MSB_Promo_Links` (`includes/class-msb-promo-links.php`) mit Rewrite `^qr/([a-z0-9-]+)/?$`, Weiterleitung mit UTM, Zähler je Monat in der Option `msb_promo_links` (nicht autoload), Herkunft über `MSB_Cart::set_ref()` wie bei den Kleinanzeigen-Kurzlinks und Backend-Seite „Werbe-Links“. Beim ersten Aufruf wird `anhaenger-1` → `/#neu` angelegt. `MSB_Bookings::source_label()` kennt `qr-…`. Version msb-rental **1.9.0** (vorher 1.8.6).

## Technical Context

**Language/Version**: PHP 7.4+, WordPress 6.0+

**Storage**: Option `msb_promo_links` (Name => Bezeichnung, Ziel, Aufrufe je Monat), Option `msb_qr_rules` für das einmalige Neuaufbauen der Permalinks. Spalte `bookings.channel` (varchar 20) wird mit `qr-<name>` genutzt, keine Schemaänderung.

**Testing**: `php -l`, `php tests/msb-rental/test-promo-links.php`

## Constitution Check

Geprüft gegen Constitution **1.1.0**.

| Grundsatz | Prüfung | Ergebnis |
|---|---|---|
| I. Die Spezifikation ist die Quelle | IF-20, BKG-22, ADM-15 neu, Status nachgetragen | erfüllt |
| II. Jede Änderung ist eine neue Version | 1.9.0 im Kopf und in `MSB_VERSION`, CHANGELOG | erfüllt |
| III. Keine Geheimnisse | keine | erfüllt |
| IV. Live nur über Snapshot und Freigabe | Snapshot, je Datei ein Vorschlag, SHA-256-Abgleich | offen, siehe Freigabe |
| V. Prüfbar und getestet | 22 Fälle lokal, Abnahme live mit Aufruf von `/qr/anhaenger-1` | erfüllt |
| VI. Recht und Geld zuerst | Kein neues Cookie (TTDSG § 25), UTM nur in der Adresse. Belege unberührt | erfüllt |
| VII. Einfach und passend zum Bestand | Aufbau wie `MSB_Kleinanzeigen`, keine Abhängigkeit, Kartenstil auf dem Handy, „Löschen“ rechts | erfüllt |

## Betroffene Dateien

- `plugins/msb-rental/includes/class-msb-promo-links.php` (neu)
- `plugins/msb-rental/includes/class-msb-bookings.php` (Quelle)
- `plugins/msb-rental/msb-rental.php` (Laden, Version)
- `plugins/msb-rental/CHANGELOG.md`
- `specs/anforderungen/IF-schnittstellen.md`, `BKG-buchungsprozess.md`, `ADM-backend.md`
- `tests/msb-rental/test-promo-links.php` (neu, wird nicht live eingespielt)

## Datenbank

Keine Schemaänderung, keine neue Datenbankversion. Neue Optionen siehe oben.

## Abnahmekriterien

- [x] `/qr/anhaenger-1` leitet auf `/?utm_source=qr&utm_medium=offline&utm_campaign=anhaenger-1#neu`
- [x] Zählung ohne Bots, Vorschauen, HEAD und Bearbeiter
- [x] Fremde Ziele abgelehnt
- [x] Quelle an der Buchung bei Warenkorb direkt auf der Zielseite
- [x] Version 1.9.0, CHANGELOG, Status IF-20, BKG-22, ADM-15
- [ ] Live: `/qr/anhaenger-1` vom Handy aufrufen, Zähler unter Vermietung → Werbe-Links steht auf 1

## Testschritte

1. Lokal: `php -l` für die drei PHP-Dateien und `php tests/msb-rental/test-promo-links.php`. Ergebnis am 10.11.2026: keine Syntaxfehler, 22 von 22 Fällen bestanden.
2. Live nach der Freigabe: Vermietung → Werbe-Links öffnen (legt den Link an), abmelden oder privates Fenster, QR-Code scannen, Zähler prüfen.

## Freigabe

| Schritt | Wann | Notiz |
|---|---|---|
| Plan bestätigt von Moritz | 10.11.2026 | Auftrag „Bitte setze das für mich um“ |
| netcup-Snapshot | | |
| Bridge-Vorschläge | | |
| Freigegeben | | |
| Live-Abgleich SHA-256 | | |
| Merge und Tag | | |
