# PRS – Preisberechnung

> Übernommen am 27.10.2026 aus der [Anforderungsspezifikation](https://claude.ai/artifact/5zkRqvGmXM9q7RA5rLBiDk) (Stand 21.10.2026). Den Status je Anforderung haben wir anhand der CHANGELOGs bis msb-rental 1.8.4 und Bridge 0.4.2 nachgetragen. Ab jetzt ist diese Datei die maßgebliche Fassung.

## Übersicht

| ID | Stufe | Status |
|---|---|---|
| PRS-01 | MUSS | umgesetzt (bis 1.2.7) |
| PRS-02 | MUSS | umgesetzt (bis 1.2.7) |
| PRS-03 | MUSS | umgesetzt (bis 1.2.7) |
| PRS-04 | MUSS | umgesetzt (bis 1.2.7) |
| PRS-05 | MUSS | umgesetzt (bis 1.2.7) |
| PRS-06 | MUSS | umgesetzt (bis 1.2.7) |
| PRS-07 | MUSS | umgesetzt (bis 1.2.7) |
| PRS-08 | MUSS | umgesetzt (bis 1.2.7) |
| PRS-09 | MUSS | umgesetzt (bis 1.2.7) |
| PRS-10 | MUSS | umgesetzt (bis 1.2.7) |

### PRS-01 · MUSS

Die Zählweise ist einstellbar. „Tage“: Miettage = (Ende − Start) + 1. „Nächte“: Miettage = max(1, Ende − Start), der letzte Tag wird nicht berechnet. Live: Tage.

**Status:** umgesetzt (bis 1.2.7)

### PRS-02 · MUSS

Tagessatz = Preis der größten Staffel, deren Mindesttage ≤ Miettage sind; sonst der Tagespreis.

**Status:** umgesetzt (bis 1.2.7)

### PRS-03 · MUSS

Tagesregeln (Wochentage, Zeitraum) wirken je berechnetem Tag, erst die Artikelregeln, dann die globalen Regeln. Modi: Rabatt % (× (1 − min(100, v)/100)), Aufschlag % (× (1 + v/100)), fester Tagespreis (= v).

**Status:** umgesetzt (bis 1.2.7)

### PRS-04 · MUSS

Buchungsregeln: Wochenende (Start Fr oder Sa, Ende Sa, So oder Mo, Dauer 2 bis max. Tage, Standard 4), Dauer (min ≤ Tage ≤ max, max 0 = offen), Frühbucher (Tage bis Start ≥ min), Last Minute (Tage bis Start ≤ max). Modi: Rabatt %, Aufschlag %, Rabatt Betrag, Paketpreis.

**Status:** umgesetzt (bis 1.2.7)

### PRS-05 · MUSS

Prozent-Buchungsregeln rechnen auf die Miete nach Tagesregeln. Ein Paketpreis ersetzt alle Rabatte; gelten mehrere, gewinnt der niedrigste. Ohne „Rabatte kombinieren“ gilt nur der größte Rabatt. Kein Rabatt ist höher als die aktuelle Miete. Aufschläge gelten immer.

**Status:** umgesetzt (bis 1.2.7)

### PRS-06 · MUSS

Miete = max(0, gerundet). Zeilenpreis = (Grundgebühr + Miete) × Menge. Kaution = Kaution × Menge. Ausgewiesener Rabatt = (Tagessatz × Tage + Grundgebühr) × Menge − Zeilenpreis.

**Status:** umgesetzt (bis 1.2.7)

### PRS-07 · MUSS

Anpassungen werden nach Bezeichnung zusammengefasst und angezeigt, wenn der Betrag mindestens 0,005 € ist.

**Status:** umgesetzt (bis 1.2.7)

### PRS-08 · MUSS

Regeln mit Wert ≤ 0 werden beim Speichern verworfen, außer bei festen Preisen. Werte werden auf 2 Nachkommastellen gerundet.

**Status:** umgesetzt (bis 1.2.7)

### PRS-09 · MUSS

Der Filter msb_calculate_price darf das Ergebnis verändern; ohne Filter gilt die Rechnung oben.

**Status:** umgesetzt (bis 1.2.7)

### PRS-10 · MUSS (Testfälle mit den Live-Regeln: Wochenende −15 % bis 3 Tage, ab 7 Tagen −30 %, nicht kombiniert)

Kraxe 5 €/Tag, Fr bis So = 15,00 − 2,25 = **12,75 €**. Kraxe 7 Tage = 35,00 − 10,50 = **24,50 €**.

**Status:** umgesetzt (bis 1.2.7)
