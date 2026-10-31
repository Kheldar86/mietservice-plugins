# Nachträge (seit 31.10.2026 nummeriert)

Diese Funktionen sind seit dem 21.10.2026 dazugekommen. Seit dem 31.10.2026 hat jede eine feste Anforderungsnummer im passenden Bereich (Feature [001-nachtraege-nummerieren](../001-nachtraege-nummerieren/spec.md)). Die Tabelle bleibt als Zuordnung stehen. Neue Nachträge gibt es nicht mehr: Jede neue Funktion bekommt ihre Nummer schon in der Spec.

Ohne eigene Zeile sind dazugekommen: [DSG-10](DSG-datenschutz-loeschfristen.md#dsg-10--muss), [DSG-11](DSG-datenschutz-loeschfristen.md#dsg-11--muss) (Kasten „Daten löschen (Art. 17 DSGVO)“ und Sperrhinweis, beide 1.7.0).

| Funktion | Version | Bereich | Nummern |
|---|---|---|---|
| Manuelle Buchung: Der Kunde bestätigt Mietbedingungen und Datenschutz per Link aus der Mail, Status „Angefragt“ wird danach „Bestätigt“ | 1.5.2, 1.5.6, 1.5.7 | ADM, BKG | [BKG-18](BKG-buchungsprozess.md#bkg-18--muss), [BKG-19](BKG-buchungsprozess.md#bkg-19--muss) |
| Manuelle Buchung: Team-Mail immer, Quelle „manuell angelegt von …“, Filter „Quelle“ | 1.5.3 | ADM | [ADM-09](ADM-backend.md#adm-09--muss), [ADM-10](ADM-backend.md#adm-10--muss), [IF-13](IF-schnittstellen.md#if-13--muss) |
| Manuelle Buchung: Auswahl bar bei Abholung oder online, Platzhalter `{payment_info}` | 1.5.5, 1.5.8 | ADM, PAY | [ADM-11](ADM-backend.md#adm-11--muss), [ADM-08](ADM-backend.md#adm-08--muss), [IF-14](IF-schnittstellen.md#if-14--muss), [PAY-26](PAY-zahlung-kaution-storno.md#pay-26--muss) |
| Kundenangaben einer Buchung im Backend bearbeiten; Versandprotokoll der letzten 30 Mails | 1.5.1 | ADM, IF | [ADM-07](ADM-backend.md#adm-07--muss), [IF-12](IF-schnittstellen.md#if-12--muss) |
| Barbuchung: Häkchen „Miete bar erhalten“ und „Kaution bar erhalten“ beim Wechsel auf „Abgeholt“ | 1.6.0 | PAY | [PAY-27](PAY-zahlung-kaution-storno.md#pay-27--muss), [PAY-28](PAY-zahlung-kaution-storno.md#pay-28--muss) |
| Kautionsabrechnung als eigener Beleg mit Nummernkreis KAU-JJJJ-0001 (siehe Abweichung bei PAY-23) | 1.6.0 | PAY | [PAY-29](PAY-zahlung-kaution-storno.md#pay-29--muss) |
| Seite „Belege“ mit Jahres- und Artfilter, Summen und CSV-Export | 1.6.0 | PAY, ADM | [PAY-30](PAY-zahlung-kaution-storno.md#pay-30--muss) |
| Logo auf Belegen und in allen Mails | 1.6.1 | PAY, IF | [PAY-31](PAY-zahlung-kaution-storno.md#pay-31--muss), [IF-15](IF-schnittstellen.md#if-15--muss) |
| Mail zum Beleg mit Kasten und Satz je Belegart, Platzhalter `{document_summary}`, `{document_note}` | 1.6.2 | IF | [IF-16](IF-schnittstellen.md#if-16--muss) |
| Kartenstil auf dem Handy für alle Vermietungsseiten; harmlose Aktionen links, unwiderrufliche rechts und getrennt | 1.6.3 bis 1.6.7 | ADM | [ADM-12](ADM-backend.md#adm-12--muss), [ADM-13](ADM-backend.md#adm-13--muss) |
| Löschregel „Abgeschlossene Buchungen ohne Zahlung und Beleg“ nach 3 Jahren; Probelauf und Protokoll | 1.7.0 | DSG | [DSG-08](DSG-datenschutz-loeschfristen.md#dsg-08--muss), [DSG-09](DSG-datenschutz-loeschfristen.md#dsg-09--muss) |
| Kasten „Zahlungen für die EÜR“ mit Jahressummen und CSV, Erstattungen einzeln mit Datum | 1.7.1 | PAY, ADM | [PAY-32](PAY-zahlung-kaution-storno.md#pay-32--muss), [PAY-33](PAY-zahlung-kaution-storno.md#pay-33--muss) |
| Testbuchungen: Belege TEST-JJJJ-0001, nicht in Summen und Beleg-CSV, Spalte „Test“ im EÜR-Export, Filter „Tests ausblenden“ | 1.7.2 | PAY, ADM | [PAY-34](PAY-zahlung-kaution-storno.md#pay-34--muss), [PAY-35](PAY-zahlung-kaution-storno.md#pay-35--muss), [PAY-36](PAY-zahlung-kaution-storno.md#pay-36--muss), [ADM-14](ADM-backend.md#adm-14--muss) |
| Kleinanzeigen-Vorlage je Artikel, Kurzlinks `/ka/…` mit UTM und Aufrufzähler, Quelle „Webseite über Kleinanzeigen“ | 1.8.0 bis 1.8.2 | ART, IF | [ART-06](ART-mietartikel.md#art-06--muss), [ART-07](ART-mietartikel.md#art-07--soll), [IF-17](IF-schnittstellen.md#if-17--muss), [BKG-20](BKG-buchungsprozess.md#bkg-20--muss) |
| AGB und Widerrufsbelehrung als PDF an der Mail „Bestätigt“ (§ 312f BGB) | 1.8.3 | IF, G | [IF-18](IF-schnittstellen.md#if-18--muss) |
| Freiwilliger Haken zur Google-Bewertung, Bitte nur in der Rechnungsmail | 1.8.4 | BKG, IF | [BKG-21](BKG-buchungsprozess.md#bkg-21--soll), [IF-19](IF-schnittstellen.md#if-19--muss) |
| Bridge: Einstellung „Kundendaten schwärzen“ (Standard an), Belege werden dann nicht ausgeliefert | Bridge 0.4.2 | BR | [BR-19](BR-bridge.md#br-19--muss), [BR-20](BR-bridge.md#br-20--muss) |
