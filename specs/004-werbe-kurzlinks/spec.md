# Feature Specification: Werbe-Kurzlinks für QR-Codes

**Feature Branch**: `claude/project-thread-3ehakv`

**Created**: 2026-11-10

**Status**: Umgesetzt, wartet auf Freigabe

**Input**: Moritz (10.11.2026) bestellt QR-Aufkleber für den neuen PKW-Anhänger und möchte sehen, wie viele Leute über den QR-Code auf die Seite kommen: „Bitte setze das für mich um.“

## Hintergrund

Die Kurzlinks `/ka/…` (IF-17) gehören fest zu einem Artikel und zählen nur für Kleinanzeigen. Für Aufkleber, Flyer oder Aushänge braucht es Links, die nicht an einen Artikel gebunden sind, deren Ziel sich später ändern lässt (gedruckt ist gedruckt) und die getrennt gezählt werden. Der Anhänger ist noch nicht buchbar, der erste Link zeigt deshalb auf den Abschnitt „Neu“ der Startseite und später auf den Artikel.

## Betroffene Anforderungen

| ID | Art | Kurz |
|---|---|---|
| IF-20 | neu | Kurzlink `/qr/<name>` mit Ziel, UTM und Aufrufzähler |
| BKG-22 | neu | Quelle „Webseite über QR-Code (…)“ an der Buchung |
| ADM-15 | neu | Seite Vermietung → Werbe-Links |

## User Scenarios & Testing

### User Story 1 – QR-Code am Anhänger zählt (Priority: P1)

Ein Passant scannt den QR-Code am Anhänger und landet auf der Startseite im Abschnitt „Neu“. Moritz sieht unter Vermietung → Werbe-Links die Zahl der Aufrufe im Monat.

**Acceptance Scenarios**:

1. **Given** Link `anhaenger` mit Ziel `/#neu`, **When** jemand `/qr/anhaenger` aufruft, **Then** Weiterleitung auf `/?utm_source=qr&utm_medium=offline&utm_campaign=anhaenger#neu` und der Zähler steigt um 1.
2. **Given** ein Bot, eine Messenger-Vorschau, eine HEAD-Anfrage oder ein angemeldeter Bearbeiter, **When** Aufruf, **Then** Weiterleitung ohne Zählung.

### User Story 2 – Ziel später ändern (Priority: P1)

Sobald der Anhänger veröffentlicht ist, stellt Moritz das Ziel auf die Artikelseite um. Der gedruckte Code bleibt gültig.

### User Story 3 – Buchung über den QR-Code erkennen (Priority: P2)

Zeigt das Ziel auf einen Artikel und bucht der Besucher, steht an der Buchung „Webseite über QR-Code (Aufkleber am PKW-Anhänger)“.

### Edge Cases

- Unbekannter Name → Startseite, keine Zählung.
- Ziel auf fremder Seite oder `//…` oder `javascript:` → wird beim Speichern abgelehnt.
- Name länger als 17 Zeichen → gekürzt (Spalte `channel` hat 20 Zeichen).

## Nicht Teil dieser Änderung

- Kein QR-Bild im Backend (keine neue Abhängigkeit). Die QR-Datei für die Druckerei erstellt Claude einmalig.
- Keine Zuordnung, wenn der Besucher erst über die Startseite zum Artikel klickt (bräuchte ein Cookie mit Einwilligung).
