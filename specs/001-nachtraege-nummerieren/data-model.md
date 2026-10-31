# Nummernplan: Nachträge nummerieren

**Feature**: [spec.md](spec.md) · Abgleich mit dem Code: [research.md](research.md)

## Entitäten

- **Nachtrag** (N-01 bis N-17): Zeile in [nachtraege.md](../anforderungen/nachtraege.md), hier in der Reihenfolge der Datei nummeriert.
- **Anforderung**: neue Nummer mit Stufe, Kurzinhalt und Status. Das Format steht in [contracts/anforderung-format.md](contracts/anforderung-format.md).

Regeln: Jede Nummer kommt genau einmal vor. Jeder Nachtrag hat mindestens eine Nummer. Zwei Zeilen kommen nicht aus `nachtraege.md`, sondern aus FR-013. Die Nummern je Bereich schließen lückenlos an die letzte bestehende an.

## Zuordnung

| Nachtrag | ID | Stufe | Kurzinhalt | Status |
|---|---|---|---|---|
| N-01 Bestätigung per Link | BKG-18 | MUSS | Bei einer manuellen Buchung bestätigt der Kunde Mietbedingungen und Datenschutz selbst über einen Link per Mail. Bis dahin stehen die Felder auf „Offen“, danach auf „Ja“ mit Zeitpunkt. Der Link steht in der Mail „Bestätigt“ bzw. „Eingegangen“, eine eigene Mail nur bei abgeschalteter Vorlage oder über „Link erneut senden“. | umgesetzt in 1.5.7 (seit 1.5.2, 1.5.6) |
| N-01 | BKG-19 | MUSS | Bestätigt der Kunde per Link, wird eine angefragte Buchung „Bestätigt“. Er erhält die Mail „Bestätigt“, das Team eine Info an die Buchungsadresse. | umgesetzt in 1.5.7 |
| N-02 Team-Mail, Quelle | ADM-09 | MUSS | Manuell angelegte Buchungen schicken immer die Team-Mail an die Buchungsadresse, unabhängig von „Kunden informieren“. Betreff und erste Zeile nennen „manuell angelegt von …“. | umgesetzt in 1.5.3 |
| N-02 | ADM-10 | MUSS | Die Buchung speichert, wer sie im Backend angelegt hat. Liste und Buchung zeigen es. Filter „Quelle“ in der Buchungsliste und im CSV-Export. | umgesetzt in 1.5.3 |
| N-02 | IF-13 | MUSS | Platzhalter `{source}` und `{created_by}`. Nutzt die Vorlage einen davon, entfällt der automatische Zusatz. | umgesetzt in 1.5.3 |
| N-03 Zahlungsauswahl | ADM-11 | MUSS | Manuelle Buchung mit Auswahl „Bar bei Abholung (Miete und Kaution)“ oder „Online“. Bei Online enthält die Kundenmail einen Bezahlknopf für die Miete. | umgesetzt in 1.5.5 |
| N-03 | ADM-08 | MUSS | Vorauswahl bei neuer manueller Buchung: Status „Angefragt“, Zahlung „Bar bei Abholung“, „Kunden per E-Mail informieren“ angehakt. | umgesetzt in 1.5.7 (Zahlung seit 1.5.5) |
| N-03 | IF-14 | MUSS | Platzhalter `{payment_info}` mit dem Absatz zur Zahlungsart. Fehlt er in „Eingegangen“ oder „Bestätigt“ und ist eine Online-Zahlung offen, wird der Bezahlknopf angehängt. | umgesetzt in 1.5.8 (seit 1.5.5) |
| N-03 | PAY-26 | MUSS | Bei Barzahlung wird kein automatischer Kautions-Zahlungslink verschickt. Die Kaution steht als „bar bei Abholung“. Von Hand reservieren bleibt möglich. | umgesetzt in 1.5.5 |
| N-04 Kundenangaben | ADM-07 | MUSS | Kundenangaben einer Buchung lassen sich im Backend bearbeiten (mit der Berechtigung für Buchungen), auf Wunsch auch im Kundenstamm. Bestätigungen wie AGB und Datenschutz bleiben unverändert. | umgesetzt in 1.5.1 |
| N-04 | IF-12 | MUSS | Versandprotokoll der letzten 30 Mails der Webseite, ohne automatisches Laden. | umgesetzt in 1.5.1 |
| N-05 Barbuchung | PAY-27 | MUSS | Bei Barzahlung stehen beim Wechsel auf „Abgeholt“ die Häkchen „Miete bar erhalten“ und „Kaution bar erhalten“. Sie vermerken die Zahlungen, das Datum erscheint im Zahlungsvermerk der Rechnung. | umgesetzt in 1.6.0 |
| N-05 | PAY-28 | MUSS | Eine Barkaution wird bei „Zurückgegeben“ nicht automatisch freigegeben. „Einbehalten“ mit Betrag und Grund erzeugt die Kautionsabrechnung. | umgesetzt in 1.6.0 |
| N-06 Kautionsabrechnung | PAY-29 | MUSS | Ein Einbehalt aus der Kaution ergibt einen eigenen Beleg „Kautionsabrechnung“ (Schadenersatz, keine Rechnung) mit Nummernkreis KAU-JJJJ-0001, jährlich ab 0001. | umgesetzt in 1.6.0; Abweichung zu PAY-23 |
| N-07 Seite Belege | PAY-30 | MUSS | Seite „Belege“ mit Jahres- und Artfilter, Summen und CSV-Export. | umgesetzt in 1.6.0 |
| N-08 Logo | PAY-31 | MUSS | Logo oben links auf allen Belegen: Auswahl aus der Mediathek, sonst Theme-Logo, sonst Website-Icon. | umgesetzt in 1.6.1, abweichend vom CHANGELOG (A-01) |
| N-08 | IF-15 | MUSS | Dasselbe Logo oben in allen Mails des Plugins. | umgesetzt in 1.6.1, abweichend vom CHANGELOG (A-01) |
| N-09 Mail zum Beleg | IF-16 | MUSS | Mail zum Beleg mit Kasten (Nummer, Datum, Buchung, Zeitraum, Betrag, Zahlungsvermerk, Knopf „PDF ansehen“) und einem Satz je Belegart. Platzhalter `{document_summary}` und `{document_note}`; fehlt der Kasten in der Vorlage, steht er vor „Viele Grüße“. Der Betreff der Kautionsmail nennt die Belegart. | umgesetzt in 1.6.2 |
| N-10 Kartenstil | ADM-12 | MUSS | Unter 782 px Breite zeigen alle Vermietungsseiten im Backend Tabellen als Karten. Am Computer bleiben die Tabellen. | umgesetzt in 1.6.7 (seit 1.6.3) |
| N-10 | ADM-13 | MUSS | Auf dem Handy stehen harmlose Aktionen links, unwiderrufliche rechts und deutlich getrennt. | umgesetzt in 1.6.5 |
| N-11 Löschregel, Probelauf | DSG-08 | MUSS | Buchungen mit Status „zurückgegeben“ oder „storniert“ ohne Zahlung und Beleg werden 3 Jahre nach Ende des Jahres der letzten Änderung bzw. des Mietendes gelöscht. | umgesetzt in 1.7.0 (im CHANGELOG nicht genannt, A-02) |
| N-11 | DSG-09 | MUSS | Die Löschfristen laufen zuerst im Probelauf: Es wird nur gezählt und protokolliert. „Löschung ausführen“ wird unter Einstellungen → Löschfristen eingeschaltet. Knopf „Jetzt prüfen“. | umgesetzt in 1.7.0 |
| N-12 EÜR | PAY-32 | MUSS | Kasten „Zahlungen für die EÜR“ auf der Seite Belege mit Jahressummen (Bar, Stripe brutto, Erstattungen, Kautionseinbehalte) und CSV mit jeder Bewegung einzeln. | umgesetzt in 1.7.1 |
| N-12 | PAY-33 | MUSS | Erstattungen werden einzeln mit Datum gespeichert. | umgesetzt in 1.7.1 |
| N-13 Testbuchungen | PAY-34 | MUSS | „Als Testbuchung markieren“ ändert nur die Buchhaltung, Ablauf, Mails und Zahlungen bleiben echt. Änderbar nur, solange die Buchung keine gültigen Belege hat. | umgesetzt in 1.7.2 |
| N-13 | PAY-35 | MUSS | Belege einer Testbuchung bekommen TEST-JJJJ-0001 und zählen nicht in Summen und Beleg-CSV. | umgesetzt in 1.7.2 |
| N-13 | PAY-36 | MUSS | EÜR-Export: Bargeld aus Testbuchungen fehlt, Online-Zahlungen bleiben mit „ja“ in der Spalte „Test“. | umgesetzt in 1.7.2 |
| N-13 | ADM-14 | MUSS | Buchungsliste: „Test“ hinter der Buchungsnummer, Filter „Tests ausblenden / Nur Tests“. | umgesetzt in 1.7.2 |
| N-14 Kleinanzeigen | ART-06 | MUSS | Kasten „Kleinanzeigen-Vorlage“ in jedem veröffentlichten Artikel: Titel (höchstens 65 Zeichen), Beschreibung aus den vorhandenen Feldern mit aktiven Preisregeln und „Abholung und Rückgabe in Bernau.“, Tagespreis, Fotos als ZIP. Kein automatisches Einstellen. | umgesetzt in 1.8.2 (seit 1.8.0, 1.8.1) |
| N-14 | ART-07 | SOLL | Haken „Anzeige ist auf dem Stand dieser Vorlage“. Ändern sich danach Text, Preis oder Fotos, zeigt die Artikelliste „veraltet“. Optionales Feld für den Link zur Anzeige. | umgesetzt in 1.8.0 |
| N-14 | IF-17 | MUSS | Kurzlink `/ka/…` je Artikel mit Weiterleitung und `utm_source=kleinanzeigen`. Aufrufe je Monat, ohne HEAD-Anfragen, Bots und Vorschauen und ohne Nutzer, die Artikel bearbeiten dürfen. | umgesetzt in 1.8.0 |
| N-14 | BKG-20 | MUSS | Buchungen nach einem Klick auf den Kurzlink tragen die Quelle „Webseite über Kleinanzeigen“ (Liste, Buchung, Team-Mail). Die Herkunft hängt am Warenkorb, kein neues Cookie. | umgesetzt in 1.8.0 |
| N-15 AGB-PDF | IF-18 | MUSS | Die Mail „Bestätigt“ hängt AGB und Widerrufsbelehrung als PDF an (§ 312f BGB, siehe G-03). Das PDF wird neu erzeugt, sobald sich eine der Seiten ändert. Ältere Fassungen bleiben geschützt erhalten. | umgesetzt in 1.8.3 |
| N-16 Google-Bewertung | BKG-21 | SOLL | Freiwilliger, nicht verpflichtender Haken im Buchungsformular und auf der Bestätigungsseite für Telefonbuchungen: Einwilligung in eine einmalige Bewertungsbitte. | umgesetzt in 1.8.4 |
| N-16 | IF-19 | MUSS | Die Bewertungsbitte steht nur in der Mail zur Rechnung, nur mit Haken und nur, wenn der Link „Google-Bewertung“ gesetzt ist. Stornorechnung, Korrektur und Kautionsabrechnung bleiben ohne Bitte. | umgesetzt in 1.8.4 |
| N-17 Bridge | BR-19 | MUSS | Einstellung „Kundendaten schwärzen“, Standard an: Kundendaten kommen in SQL-Ergebnissen als `[geschwärzt]` an (siehe G-10, BR-18). | umgesetzt in Bridge 0.4.2 |
| 1.7.0, nicht in nachtraege.md (FR-013) | DSG-10 | MUSS | Kasten „Daten löschen (Art. 17 DSGVO)“ am Kunden, nur mit der Berechtigung zum Löschen: zeigt vorher, wie viele Buchungen gelöscht und welche wegen der Aufbewahrungspflicht gesperrt werden. Bei laufenden Buchungen nicht möglich. Kunden löschen, einzeln und als Sammelaktion, läuft über diesen Weg. | umgesetzt in 1.7.0 |
| 1.7.0, nicht in nachtraege.md (FR-013) | DSG-11 | MUSS | Eine gesperrte Buchung zeigt im Backend den Hinweis, dass sie nach Art. 18 DSGVO gesperrt ist und nur wegen der Aufbewahrungspflicht bis zu einem genannten Datum gespeichert bleibt. | umgesetzt in 1.7.0 |
| N-17 | BR-20 | MUSS | Bei aktiver Einstellung liefert die Bridge keine Belege (PDF, CSV) aus `uploads/msb-belege` aus. | umgesetzt in Bridge 0.4.2 |

## Summen

| Bereich | neu | Nummern |
|---|---|---|
| ADM | 8 | ADM-07 bis ADM-14 |
| BKG | 4 | BKG-18 bis BKG-21 |
| PAY | 11 | PAY-26 bis PAY-36 |
| IF | 8 | IF-12 bis IF-19 |
| DSG | 4 | DSG-08 bis DSG-11 |
| ART | 2 | ART-06, ART-07 |
| BR | 2 | BR-19, BR-20 |
| **gesamt** | **39** | |

## Verweise an bestehenden Anforderungen (FR-009)

| Bestehend | Ergänzung im Status |
|---|---|
| ADM-04 | „überholt in Teilen, siehe ADM-08 und ADM-11 (B-14)“ |
| PAY-23 | „Kautionsabrechnung siehe PAY-29“ |
| PAY-14 | „Barkaution siehe PAY-26 bis PAY-28“ |
| DSG-05 | „Buchungen ohne Zahlung nach Abschluss siehe DSG-08“ |
| DSG-06 | „Vorschau und Ablauf siehe DSG-10, Hinweis an gesperrten Buchungen siehe DSG-11“ |
| BR-18 | „Kundendaten siehe BR-19, BR-20“ |
| IF-06 | „siehe B-15“ |
| DSG-02 | „siehe B-16“ |
