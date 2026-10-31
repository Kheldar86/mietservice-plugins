# Spezifikation

Hier steht, **was** die Plugins können müssen. Der Code unter `plugins/` setzt das um. Jede Änderung am Code beginnt mit einer Änderung hier.

## Aufbau

| Ordner | Inhalt |
|---|---|
| [`anforderungen/`](anforderungen) | Alle Anforderungen mit fester Nummer, eine Datei je Bereich. Jede Anforderung hat einen Status. |
| `NNN-kurzname/` | Ein Ordner je Feature oder Änderung, angelegt mit [Spec Kit](https://github.com/github/spec-kit): `spec.md`, `plan.md`, `tasks.md` und bei Bedarf `research.md`, `data-model.md`, `checklists/`. |
| [`aenderungen/`](aenderungen) | Archiv: Änderungen vor Spec Kit, eine Datei je Änderung (z. B. msb-rental 1.8.5). Neue Änderungen kommen nicht mehr hierher. |

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

## Ablauf einer Änderung (Spec Kit)

Die Grundregeln stehen in der [Constitution](../.specify/memory/constitution.md). Die Befehle laufen in Claude Code im Repository:

| Schritt | Befehl | Ergebnis |
|---|---|---|
| 1. Spec | `/speckit-specify <Beschreibung>` | `specs/NNN-kurzname/spec.md`. Nennt die betroffenen Anforderungsnummern, neue bekommen die nächste freie Nummer. |
| 2. Klären | `/speckit-clarify` | Offene Punkte als Fragen an Moritz, Antworten mit Datum in der Spec |
| 3. Plan | `/speckit-plan` | `plan.md` mit neuer Version, Dateien, Datenbank, Abnahmekriterien und Testschritten. Moritz bestätigt den Plan. |
| 4. Aufgaben | `/speckit-tasks`, optional `/speckit-analyze` | `tasks.md`, Prüfung auf Widersprüche zwischen Spec, Plan und Constitution |
| 5. Umsetzung | `/speckit-implement`, dann `/speckit-converge` | Code auf einem eigenen Branch als Pull Request, mit Version, CHANGELOG und nachgetragenem Status in `anforderungen/` |
| 6. Freigabe | ohne Befehl | netcup-Snapshot, Freigabe-Vorschläge in der Claude MCP Bridge (laufen nach etwa einer Stunde ab, je Datei nur ein Vorschlag), SHA-256-Abgleich live gegen Branch, Merge, Tag `msb-rental-vX.Y.Z`. Snapshot, Vorschläge und Freigabe werden in `plan.md` eingetragen, **bevor** der Pull Request gemergt wird. |

Kleine Korrekturen (Tippfehler, Text in einer Mail) brauchen keinen Feature-Ordner. Auch sie sind eine neue Version mit CHANGELOG-Eintrag und gehen über einen Pull Request.

Spec Kit selbst (Version 1.2.4) liegt unter `.specify/` (Vorlagen, Skripte, Constitution) und `.claude/skills/speckit-*` (Befehle). Wer Spec Kit lokal installiert hat (`uv tool install specify-cli==1.2.4`), kann es mit `specify init --here --integration claude --script sh --force` neu einspielen. Danach die Constitution mit Git prüfen, weil sie dabei nicht überschrieben werden soll.

## Herkunft

Die Anforderungen wurden am 27.10.2026 aus der [Anforderungsspezifikation](https://claude.ai/artifact/5zkRqvGmXM9q7RA5rLBiDk) vom 21.10.2026 übernommen. Den Status haben wir aus den CHANGELOGs bis msb-rental 1.8.4 und Bridge 0.4.2 nachgetragen. Der Umbau dazwischen steht im [Umbauplan](https://claude.ai/artifact/WvZymKL2FskKtuSv7AohCy). Ab jetzt gilt die Fassung in diesem Ordner.
