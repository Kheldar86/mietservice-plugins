# Befunde und Fragen aus der Spezifikation

> Übernommen am 27.10.2026 aus der [Anforderungsspezifikation](https://claude.ai/artifact/5zkRqvGmXM9q7RA5rLBiDk) (Stand 21.10.2026). Den Status je Anforderung haben wir anhand der CHANGELOGs bis msb-rental 1.8.4 und Bridge 0.4.2 nachgetragen. Ab jetzt ist diese Datei die maßgebliche Fassung.

## Befunde (Abweichungen zwischen Code und Regeln, Stand 21.10.2026)

| Nr. | Dringlichkeit | Befund | Betrifft | Status heute |
|---|---|---|---|---|
| B-01 | hoch | Die Bridge gibt beim SQL-Lesen den Stripe-Live-Schlüssel und das Webhook-Secret ungeschwärzt aus, weil die Schwärzung nur Optionsnamen prüft. Der Anthropic-Schlüssel ist vermutlich genauso betroffen. Empfehlung: Schlüssel rotieren, Schwärzung auf Inhalte serialisierter Optionen erweitern. | BR-18, G-10 | behoben: Bridge 0.4.1, Schlüssel rotiert am 22.10.2026 |
| B-02 | hoch | Stornogebühr (48 h / 50 %) und Stornierung durch den Kunden fehlen. | G-04, PAY-16, PAY-17 | behoben in 1.5.0 |
| B-03 | mittel | Erledigt: Die Kleinunternehmerregelung gilt, der § 19-Hinweis ist richtig. | G-06, F-02 | erledigt |
| B-04 | mittel | KI-Assistent und Backend von msb-rental verlangen die Administrator-Rolle. | G-05, KI-01 | behoben in 1.4.0 |
| B-05 | mittel | Anzahlung wird auch bei Offline-Buchungen gespeichert. | PAY-04 | behoben in 1.2.8 |
| B-06 | mittel | Checkout ohne Nonce; Rate-Limit zählt nur Erfolge. | BKG-02, BKG-03 | behoben in 1.2.8 |
| B-07 | mittel | Ergebnis der Buchungssperre wird nicht geprüft. | BKG-05 | behoben in 1.2.8 |
| B-08 | mittel | Jeder Statuswechsel ist erlaubt. | BKG-16 | behoben in 1.3.0 |
| B-09 | niedrig | Die Plugin-Anleitung (ANLEITUNG.md) empfiehlt ein Backup-Plugin; Backups laufen laut Projektregel nur über netcup. | Doku msb-rental | behoben in 1.2.8 |
| B-10 | niedrig | Buchungsnummer ist die globale ID, kein Jahreszähler. | BKG-07 | behoben in 1.3.0 |
| B-11 | niedrig | Schema-Verfügbarkeit ist nicht datumsbezogen. | SEO-02 | behoben in 1.8.5 ([Änderung 2026-10-28](../aenderungen/2026-10-28-schema-verfuegbarkeit.md)) |
| B-12 | niedrig | Erledigt: Dass die Vollständigkeitsprüfung nur warnt, ist so gewollt. | KI-07 | erledigt |
| B-13 | niedrig | Keine Rechnungserzeugung (PAY-19 bis PAY-24) und keine automatischen Löschfristen (DSG). | PAY-19 | behoben: Rechnungen 1.6.0, Löschfristen 1.7.0 |

## Fragen an Moritz (alle beantwortet)

- **F-01** Stornierung: Entschieden am 21.10.2026: Der Kunde storniert selbst in „Mein Konto“, mit gestaffelter Stornogebühr. Staffel: bis 7 Tage vorher kostenlos, 7 Tage bis 48 h 25 %, unter 48 h 50 % der Miete. Außerdem: Grundlage ist nur die Tagesmiete, ohne Grundgebühr. Bezugspunkt ist der Mietbeginn um 0 Uhr. Erstattung automatisch über Stripe, mit Info-Mail (PAY-17).
- **F-02** Entschieden: Die Kleinunternehmerregelung gilt.
- **F-03** Entschieden: Rechnungen als PDF per E-Mail, Nummer mit Jahr, jährlich zurückgesetzt (PAY-19 bis PAY-24).
- **F-04** Entschieden: eigene Rolle „Gesellschafter“ (ROL).
- **F-05** Entschieden: Die Übergänge aus BKG-16 gelten.
- **F-06** Entschieden: bar bei Abholung, Online bleibt bevorzugt (PAY-14).
- **F-07** Entschieden: Die Buchungsnummer beginnt jedes Jahr bei 0001 (BKG-07).
- **F-08** Entschieden: übliche Fristen nach DSGVO, AO und HGB (DSG-01 bis DSG-07).
- **F-09** Entschieden: 60 Minuten bleiben vorerst.
- **F-10** Entschieden: nein, nur warnen.
- **F-11** Rechnung: Entschieden, siehe PAY-20, PAY-23 und PAY-25.
- **F-12** Rolle „Gesellschafter“: Entschieden, siehe ROL-01.
