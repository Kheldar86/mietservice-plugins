# Spezifikation

Hier steht, **was** die Plugins können müssen. Der Code unter `plugins/` setzt das um. Jede Änderung am Code beginnt mit einer Änderung hier.

## Aufbau

| Ordner | Inhalt |
|---|---|
| [`anforderungen/`](anforderungen) | Alle Anforderungen mit fester Nummer, eine Datei je Bereich. Jede Anforderung hat einen Status. |
| [`aenderungen/`](aenderungen) | Eine Datei je geplanter oder umgesetzter Änderung, angelegt nach der [Vorlage](aenderungen/_vorlage.md). |

### Bereiche

| Kürzel | Datei | Plugin |
|---|---|---|
| G | [Globale Restriktionen](anforderungen/G-global.md) | beide |
| ART | [Mietartikel](anforderungen/ART-mietartikel.md) | msb-rental |
| PRS | [Preisberechnung](anforderungen/PRS-preisberechnung.md) | msb-rental |
| VFG | [Verfügbarkeit](anforderungen/VFG-verfuegbarkeit.md) | msb-rental |
| KTO | [Warenkorb und Kundenkonto](anforderungen/KTO-warenkorb-kundenkonto.md) | msb-rental |
| KI | [KI-Assistent für neue Artikel](anforderungen/KI-artikel-assistent.md) | msb-rental |
| SEO | [Produkt-Schema](anforderungen/SEO-produkt-schema.md) | msb-rental |
| ADM | [Verwaltung im Backend](anforderungen/ADM-backend.md) | msb-rental |
| ROL | [Rollen](anforderungen/ROL-rollen.md) | msb-rental |
| DSG | [Datenschutz und Löschfristen](anforderungen/DSG-datenschutz-loeschfristen.md) | msb-rental |
| BKG | [Buchungsprozess](anforderungen/BKG-buchungsprozess.md) | msb-rental |
| PAY | [Zahlung, Kaution, Stornierung und Belege](anforderungen/PAY-zahlung-kaution-storno.md) | msb-rental |
| IF | [Schnittstellen](anforderungen/IF-schnittstellen.md) | beide |
| BR | [Claude MCP Bridge](anforderungen/BR-bridge.md) | Bridge |

Dazu kommen die [Befunde und Fragen](anforderungen/befunde-und-fragen.md) aus der ersten Fassung und die Liste der [Nachträge](anforderungen/nachtraege.md): Dinge, die schon umgesetzt sind, aber noch keine Nummer haben.

## Schreibweise

- **MUSS** ist verbindlich, **SOLL** ist empfohlen.
- Jede Anforderung hat eine feste Nummer, z. B. `PRS-04`. Eine Nummer wird nie neu vergeben. Fällt eine Anforderung weg, bleibt sie mit dem Status `entfallen in X.Y.Z` stehen.
- Neue Anforderungen bekommen die nächste freie Nummer im Bereich.
- Jede Anforderung hat eine Zeile **Status** und steht zusätzlich in der Übersicht oben in der Datei:
  - `offen`: beschlossen, aber noch nicht umgesetzt
  - `in Arbeit (Änderung 2026-11-…)`: eine Änderung läuft
  - `umgesetzt in 1.5.0`: seit dieser Version live
  - `umgesetzt (bis 1.2.7)`: war schon beim Stand der ersten Fassung (21.10.2026) vorhanden
  - `teilweise …` oder `abweichend …`: mit kurzer Begründung
  - `entfallen in X.Y.Z`
- Entscheidungen von Moritz werden mit Datum vermerkt, z. B. „(Entscheidung 21.10.2026)“.
- **Keine Geheimnisse**, auch nicht als Beispiel: keine Schlüssel, Secrets, Passwörter, Werte aus `msb_settings` oder Kundendaten.

## Ablauf einer Änderung

```
1. Spec      →  2. Plan      →  3. Umsetzung (Pull Request)  →  4. Freigabe und Live
```

1. **Spec.** Was soll sich ändern? Die betroffenen Anforderungen in `anforderungen/` ergänzen oder ändern und auf `offen` setzen. Neue Anforderungen bekommen eine Nummer.
2. **Plan.** Unter `aenderungen/` eine Datei nach der [Vorlage](aenderungen/_vorlage.md) anlegen, Name `JJJJ-MM-TT-kurzname.md`. Sie nennt die Nummern, die neue Version, die betroffenen Dateien, Abnahmekriterien und Testschritte. Moritz bestätigt den Plan.
3. **Umsetzung als Pull Request.** Ein Branch je Änderung. Der Pull Request enthält den Code, die neue Plugin-Version (Kopf und `MSB_VERSION`), den Eintrag im `CHANGELOG.md` und die auf `umgesetzt in X.Y.Z` gesetzten Status. Im Pull Request steht, wie getestet wurde.
4. **Freigabe und Live.**
   1. netcup-Snapshot anlegen.
   2. Claude stellt die Dateien als Freigabe-Vorschläge in der Claude MCP Bridge ein. Moritz gibt sie im WordPress-Backend frei. Die Vorschläge laufen nach etwa einer Stunde ab.
   3. Nach der Freigabe prüft Claude die Live-Dateien gegen den Branch (SHA-256 je Datei) und testet die Abnahmekriterien.
   4. Pull Request mergen. Danach den Tag setzen, z. B. `msb-rental-v1.8.5` (macht Moritz in GitHub, weil die Cloud keine Tags pushen kann).
   5. In der Änderungsdatei Snapshot, Freigabe und Datum eintragen.

Kleine Korrekturen (Tippfehler, Text in einer Mail) brauchen keine eigene Änderungsdatei. Auch sie sind eine neue Version mit CHANGELOG-Eintrag und gehen über einen Pull Request.

## Herkunft

Die Anforderungen wurden am 27.10.2026 aus der [Anforderungsspezifikation](https://claude.ai/artifact/5zkRqvGmXM9q7RA5rLBiDk) vom 21.10.2026 übernommen. Den Status haben wir aus den CHANGELOGs bis msb-rental 1.8.4 und Bridge 0.4.2 nachgetragen. Der Umbau dazwischen steht im [Umbauplan](https://claude.ai/artifact/WvZymKL2FskKtuSv7AohCy). Ab jetzt gilt die Fassung in diesem Ordner.
