# KI – KI-Assistent für neue Artikel

> Übernommen am 27.10.2026 aus der [Anforderungsspezifikation](https://claude.ai/artifact/5zkRqvGmXM9q7RA5rLBiDk) (Stand 21.10.2026). Den Status je Anforderung haben wir anhand der CHANGELOGs bis msb-rental 1.8.4 und Bridge 0.4.2 nachgetragen. Ab jetzt ist diese Datei die maßgebliche Fassung.

## Übersicht

| ID | Stufe | Status |
|---|---|---|
| KI-01 | MUSS | umgesetzt in 1.4.0 |
| KI-02 | MUSS | umgesetzt (bis 1.2.7) |
| KI-03 | MUSS | umgesetzt (bis 1.2.7) |
| KI-04 | MUSS | umgesetzt (bis 1.2.7) |
| KI-05 | MUSS | umgesetzt (bis 1.2.7) |
| KI-06 | MUSS | umgesetzt (bis 1.2.7) |
| KI-07 | MUSS | umgesetzt (bis 1.2.7) |
| KI-08 | MUSS | umgesetzt (bis 1.2.7) |

### KI-01 · MUSS

Nutzbar für Administratoren und die Rolle „Gesellschafter“ (G-05, ROL-01).

**Status:** umgesetzt in 1.4.0

### KI-02 · MUSS

Eingabe: Kategorie und 1 bis 6 Fotos (JPEG, PNG, GIF, WebP, je höchstens 4,5 MB), dazu optionale Angaben wie Marke/Modell.

**Status:** umgesetzt (bis 1.2.7)

### KI-03 · MUSS

Modellwahl in den Einstellungen: Sonnet (Standard), Haiku, Opus. Antwort als festes JSON-Schema, höchstens 3000 Tokens, Zeitlimit 120 s. Bis zu 3 veröffentlichte Artikel dienen als Stilbeispiele.

**Status:** umgesetzt (bis 1.2.7)

### KI-04 · MUSS

Der Text folgt dem Styleguide: Einleitung „… mieten statt kaufen:“, Abschnitte Ausstattung & Zubehör, Ideal für, Gut zu wissen, Zustand, Überschrift „[Produktart] mieten in Bernau“, Abschlusssatz mit Abholzeiten, Links auf AGB und Artikelübersicht.

**Status:** umgesetzt (bis 1.2.7)

### KI-05 · MUSS

Speichern verlangt Titel, Kategorie, Preis > 0 und Kaution. Der Artikel wird immer als Entwurf gespeichert.

**Status:** umgesetzt (bis 1.2.7)

### KI-06 · MUSS

Slug „[Produktart] mieten [Modell]“, höchstens 45 Zeichen. Fokus-Keyword „[Produktart] mieten“. Der erste Alt-Text enthält das Keyword. Marke = erstes Wort aus Marke/Modell. Höchstdauer 0, buchbar ja. Meta-Beschreibung und Fokus-Keyword werden in die SEO-Felder des Artikels geschrieben.

**Status:** umgesetzt (bis 1.2.7)

### KI-07 · MUSS

Vollständigkeitsprüfung vor Veröffentlichung: Kategorie, Preis, Kaution, Kurzbeschreibung, technische Daten, Beitragsbild, mindestens 3 Fotos mit Alt-Text, Meta-Beschreibung und Fokus-Keyword. Die Prüfung warnt nur und blockiert das Veröffentlichen nicht.

**Status:** umgesetzt (bis 1.2.7)

### KI-08 · MUSS

Der API-Schlüssel liegt in den KI-Einstellungen und wird nie angezeigt oder weitergegeben (G-10).

**Status:** umgesetzt (bis 1.2.7)
