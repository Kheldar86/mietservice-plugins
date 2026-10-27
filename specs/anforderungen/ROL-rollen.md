# ROL – Rollen

> Übernommen am 27.10.2026 aus der [Anforderungsspezifikation](https://claude.ai/artifact/5zkRqvGmXM9q7RA5rLBiDk) (Stand 21.10.2026). Den Status je Anforderung haben wir anhand der CHANGELOGs bis msb-rental 1.8.4 und Bridge 0.4.2 nachgetragen. Ab jetzt ist diese Datei die maßgebliche Fassung.

## Übersicht

| ID | Stufe | Status |
|---|---|---|
| ROL-01 | MUSS | umgesetzt in 1.4.0 |
| ROL-02 | MUSS | umgesetzt in 1.4.0 |
| ROL-03 | MUSS | umgesetzt in 1.4.0 |

### ROL-01 · MUSS

Es gibt eine Rolle „Gesellschafter“ (für Eric). Sie darf Buchungen ansehen und bearbeiten, den Status ändern (insbesondere die Rückgabe bestätigen) sowie neue Mietartikel anlegen, auch mit dem KI-Assistenten (Entscheidung 21.10.2026). Außerdem darf sie Erstattungen auslösen, Kautionen freigeben oder einbehalten und Artikel veröffentlichen. Sie darf keine Einstellungen von msb-rental ändern und keine Buchungen löschen.

**Status:** umgesetzt in 1.4.0

### ROL-02 · MUSS

Die Rolle „Gesellschafter“ darf keine Plugins, Themes, Nutzer oder WordPress-Einstellungen ändern und hat keinen Zugang zur Bridge (BR-01).

**Status:** umgesetzt in 1.4.0

### ROL-03 · MUSS

Die Rechte werden über eigene Berechtigungen je Bereich vergeben (z. B. Buchungen, Artikel, Einstellungen) statt über manage_options.

**Status:** umgesetzt in 1.4.0
