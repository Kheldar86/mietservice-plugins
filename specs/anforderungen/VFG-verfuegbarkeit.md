# VFG – Verfügbarkeit

> Übernommen am 27.10.2026 aus der [Anforderungsspezifikation](https://claude.ai/artifact/5zkRqvGmXM9q7RA5rLBiDk) (Stand 21.10.2026). Den Status je Anforderung haben wir anhand der CHANGELOGs bis msb-rental 1.8.4 und Bridge 0.4.2 nachgetragen. Ab jetzt ist diese Datei die maßgebliche Fassung.

## Übersicht

| ID | Stufe | Status |
|---|---|---|
| VFG-01 | MUSS | umgesetzt (bis 1.2.7) |
| VFG-02 | MUSS | umgesetzt (bis 1.2.7) |
| VFG-03 | MUSS | umgesetzt (bis 1.2.7) |
| VFG-04 | MUSS | umgesetzt (bis 1.2.7) |
| VFG-05 | MUSS | umgesetzt (bis 1.2.7) |
| VFG-06 | MUSS | umgesetzt (bis 1.2.7) |

### VFG-01 · MUSS

Eine Buchung belegt Bestand, solange ihr Status „wartet auf Zahlung“, „offen“, „bestätigt“ oder „abgeholt“ ist.

**Status:** umgesetzt (bis 1.2.7)

### VFG-02 · MUSS

Belegt ist der Zeitraum [Start − Puffer, Ende + Puffer]. Puffer live: 1 Tag.

**Status:** umgesetzt (bis 1.2.7)

### VFG-03 · MUSS

Prüfreihenfolge, die erste Verletzung liefert die Fehlermeldung: (1) Artikel existiert und ist veröffentlicht, (2) Artikel buchbar, (3) Daten gültig und Ende ≥ Start, (4) im Nächte-Modus Start ≠ Ende, (5) Menge ≥ 1 und ≤ Bestand, (6) Mindest- und Höchstdauer, Vorlaufzeit, maximale Vorausbuchung, Schliesstage für Start und Ende, (7) je Tag keine Sperrzeit und frei = Bestand − belegt − andere Zeilen ≥ Menge.

**Status:** umgesetzt (bis 1.2.7)

### VFG-04 · MUSS

Bei manuellen Buchungen durch den Admin entfallen die Schritte 2 und 6. Mit „ohne Prüfung“ entfällt die ganze Prüfung.

**Status:** umgesetzt (bis 1.2.7)

### VFG-05 · MUSS

Der Kalender liefert je Tag die freie Menge sowie die Kennzeichen „Schliesstag“ und „nicht buchbar“. Er zeigt 1 bis 3 Monate (Einstellung).

**Status:** umgesetzt (bis 1.2.7)

### VFG-06 · MUSS

Live-Werte: Vorlauf 1 Tag, Vorausbuchung höchstens 365 Tage, keine Schliesstage.

**Status:** umgesetzt (bis 1.2.7)
