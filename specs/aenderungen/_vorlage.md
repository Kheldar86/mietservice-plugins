# Änderung: <kurzer Titel>

<!-- Datei kopieren als JJJJ-MM-TT-kurzname.md. Keine Schlüssel, Secrets oder Kundendaten eintragen. -->

| | |
|---|---|
| Datum | JJJJ-MM-TT |
| Plugin | msb-rental / claude-mcp-bridge |
| Neue Version | X.Y.Z (vorher X.Y.Z) |
| Status | geplant / bestätigt / in Arbeit / live / verworfen |
| Pull Request | #… |

## Betroffene Anforderungen

| ID | Art | Kurz |
|---|---|---|
| PRS-11 | neu | … |
| BKG-04 | geändert | … |

## Ziel

Was soll danach anders sein, aus Sicht von Kunde, Moritz oder Eric? Zwei bis drei Sätze.

## Plan

1. …
2. …

### Betroffene Dateien

- `plugins/msb-rental/…`

### Datenbank

Keine Änderung. / Neue Spalte … (Datenbankversion X.Y.Z), Update läuft beim ersten Seitenaufruf.

## Abnahmekriterien

- [ ] …
- [ ] Version im Plugin-Kopf und in `MSB_VERSION` erhöht
- [ ] `CHANGELOG.md` ergänzt (Neu / Geändert / Behoben / Update-Hinweise)
- [ ] Status der Anforderungen auf `umgesetzt in X.Y.Z` gesetzt

## Testschritte

1. Lokal: …
2. Nach der Freigabe live: … (z. B. Testbuchung anlegen und als Testbuchung markieren)

## Freigabe

| Schritt | Wann | Notiz |
|---|---|---|
| Plan bestätigt von Moritz | | |
| netcup-Snapshot | | Name des Snapshots |
| Bridge-Vorschläge | | Nummern der Vorschläge |
| Freigegeben | | |
| Live-Abgleich SHA-256 | | alle Dateien gleich |
| Merge und Tag | | `msb-rental-vX.Y.Z` |

## Nachher

Was ist aufgefallen, was ist offen geblieben?
