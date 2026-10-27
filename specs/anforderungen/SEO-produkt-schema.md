# SEO – Produkt-Schema

> Übernommen am 27.10.2026 aus der [Anforderungsspezifikation](https://claude.ai/artifact/5zkRqvGmXM9q7RA5rLBiDk) (Stand 21.10.2026). Den Status je Anforderung haben wir anhand der CHANGELOGs bis msb-rental 1.8.4 und Bridge 0.4.2 nachgetragen. Ab jetzt ist diese Datei die maßgebliche Fassung.

## Übersicht

| ID | Stufe | Status |
|---|---|---|
| SEO-01 | MUSS | umgesetzt (bis 1.2.7) |
| SEO-02 | MUSS | umgesetzt (bis 1.2.7); offen: Verfügbarkeit ist nicht datumsbezogen (B-11) |
| SEO-03 | MUSS | umgesetzt (bis 1.2.7) |

### SEO-01 · MUSS

Jede Artikelseite gibt ein Product-Schema (JSON-LD) aus: Name, Marke (höchstens 70 Zeichen), Kategorie, bis zu 5 Bilder, Beschreibung (SEO-Beschreibung, sonst Kurzbeschreibung, sonst Text; höchstens 300 Zeichen).

**Status:** umgesetzt (bis 1.2.7)

### SEO-02 · MUSS

Ein Angebot (LeaseOut, Preis je Tag) erscheint nur bei Preis > 0. Verfügbarkeit = buchbar und Bestand > 0.

**Status:** umgesetzt (bis 1.2.7); offen: Verfügbarkeit ist nicht datumsbezogen (B-11)

### SEO-03 · MUSS

Das Schema wird in vorhandenes JSON-LD der Seite eingefügt oder, wenn keines existiert, eigenständig ausgegeben. Filter msb_product_schema.

**Status:** umgesetzt (bis 1.2.7)
