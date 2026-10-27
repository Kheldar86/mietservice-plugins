# Mietservice Barnim – WordPress-Plugins

Quellcode der eigenen Plugins von [mietservice-barnim.de](https://mietservice-barnim.de/).

| Plugin | Ordner | Stand |
|---|---|---|
| Mietservice Barnim Vermietung (`msb-rental`) | [`plugins/msb-rental`](plugins/msb-rental) | 1.8.4 |
| Claude MCP Bridge (`claude-mcp-bridge`) | [`plugins/claude-mcp-bridge`](plugins/claude-mcp-bridge) | 0.4.2 |

Der erste Stand wurde am 27.10.2026 direkt von der Live-Seite übernommen und Datei für Datei per SHA-256 mit den Live-Dateien abgeglichen. Versionshinweise stehen im jeweiligen `CHANGELOG.md`.

## Spezifikation

Was die Plugins können müssen, steht unter [`specs/`](specs): alle Anforderungen mit fester Nummer und Status, dazu der Ablauf einer Änderung mit [Spec Kit](https://github.com/github/spec-kit) (`/speckit-specify` → `/speckit-plan` → `/speckit-tasks` → `/speckit-implement` → Freigabe). Die Grundregeln stehen in der [Constitution](.specify/memory/constitution.md).

## Tests

Unter [`tests/`](tests) liegen Tests, die die Plugin-Dateien mit nachgebildetem WordPress laden. Sie brauchen nur PHP auf der Kommandozeile und werden nicht live eingespielt.

```
php tests/msb-rental/test-schema-availability.php
```

## Regeln

1. **Jede Änderung ist eine neue Plugin-Version** mit Eintrag im `CHANGELOG.md` (Neu / Geändert / Behoben / Update-Hinweise) und hochgezählter Version im Plugin-Kopf (bei msb-rental auch `MSB_VERSION`).
2. **Live geht nur über die Freigabe in der Claude MCP Bridge.** Vor jeder Freigabe einen netcup-Snapshot anlegen. Freigabe-Vorschläge laufen nach etwa einer Stunde ab.
3. **Das Repository ist die Quelle.** Erst hier ändern und committen, dann live einspielen. Nach dem Einspielen den Stand mit einem Tag markieren, z. B. `msb-rental-v1.8.5`.
4. **Keine Geheimnisse ins Repository:** keine Stripe- oder PayPal-Schlüssel, kein Webhook-Secret, kein Claude-API-Schlüssel, keine Werte aus `msb_settings`, keine Datenbank-Exporte, keine Kundendaten. Diese Werte liegen nur in der WordPress-Datenbank.

## Tags

Jeder Live-Stand bekommt einen Tag je Plugin: `msb-rental-vX.Y.Z` bzw. `claude-mcp-bridge-vX.Y.Z`.
