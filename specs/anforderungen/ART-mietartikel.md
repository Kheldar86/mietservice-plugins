# ART – Mietartikel

> Übernommen am 27.10.2026 aus der [Anforderungsspezifikation](https://claude.ai/artifact/5zkRqvGmXM9q7RA5rLBiDk) (Stand 21.10.2026). Den Status je Anforderung haben wir anhand der CHANGELOGs bis msb-rental 1.8.4 und Bridge 0.4.2 nachgetragen. Ab jetzt ist diese Datei die maßgebliche Fassung.

## Übersicht

| ID | Stufe | Status |
|---|---|---|
| ART-01 | MUSS | umgesetzt (bis 1.2.7) |
| ART-02 | MUSS | umgesetzt (bis 1.2.7) |
| ART-03 | MUSS | umgesetzt (bis 1.2.7) |
| ART-04 | MUSS | umgesetzt (bis 1.2.7) |
| ART-05 | SOLL | umgesetzt (bis 1.2.7) |
| ART-06 | MUSS | umgesetzt in 1.8.2 (seit 1.8.0, 1.8.1) |
| ART-07 | SOLL | umgesetzt in 1.8.0 |

### ART-01 · MUSS

Mietartikel sind ein eigener Beitragstyp msb_item (URL-Pfad /mieten/, Archiv /mietartikel/) mit der Kategorie-Taxonomie msb_category (Pfad /mietkategorie/). Bearbeitet wird im klassischen Editor.

**Status:** umgesetzt (bis 1.2.7)

### ART-02 · MUSS

Jeder Artikel hat diese Felder: Tagespreis, Grundgebühr je Buchung, Kaution, Bestand (Standard 1), Mindestmietdauer (≥ 1 Tag), Höchstmietdauer (0 = unbegrenzt), „buchbar“ (ja/nein), Staffelpreise, technische Daten, Sperrzeiträume, eigene Preisregeln, „globale Regeln ignorieren“, Galerie, Marke.

**Status:** umgesetzt (bis 1.2.7)

### ART-03 · MUSS

Staffelpreise werden als Zeilen „Tage=Preis“ gepflegt, technische Daten als Zeilen „Bezeichnung: Wert“, Sperrzeiten als Datumsbereiche.

**Status:** umgesetzt (bis 1.2.7)

### ART-04 · MUSS

Nur veröffentlichte Artikel sind buchbar und erscheinen im Warenkorb. Ein Artikel, der nach dem Einlegen unveröffentlicht wird, fällt aus dem Warenkorb.

**Status:** umgesetzt (bis 1.2.7)

### ART-05 · SOLL

Mietbedingungen stehen nicht im Artikeltext (G-03).

**Status:** umgesetzt (bis 1.2.7)

### ART-06 · MUSS

Kasten „Kleinanzeigen-Vorlage“ in jedem veröffentlichten Artikel: Titel (höchstens 65 Zeichen), Beschreibung aus den vorhandenen Feldern mit aktiven Preisregeln und „Abholung und Rückgabe in Bernau.“, Tagespreis, Fotos als ZIP. Kein automatisches Einstellen.

**Status:** umgesetzt in 1.8.2 (seit 1.8.0, 1.8.1)

### ART-07 · SOLL

Haken „Anzeige ist auf dem Stand dieser Vorlage“. Ändern sich danach Text, Preis oder Fotos, zeigt die Artikelliste „veraltet“. Optionales Feld für den Link zur Anzeige.

**Status:** umgesetzt in 1.8.0
