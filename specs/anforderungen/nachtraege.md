# Nachträge ohne Anforderungs-ID

Diese Funktionen sind seit dem 21.10.2026 dazugekommen und laufen live, stehen aber noch nicht als nummerierte Anforderung in der Spezifikation. Sie sollen nach und nach eine Nummer im passenden Bereich bekommen. Bis dahin gilt der Text im `CHANGELOG.md` der genannten Version.

| Funktion | Version | Bereich |
|---|---|---|
| Manuelle Buchung: Der Kunde bestätigt Mietbedingungen und Datenschutz per Link aus der Mail, Status „Angefragt“ wird danach „Bestätigt“ | 1.5.2, 1.5.6, 1.5.7 | ADM, BKG |
| Manuelle Buchung: Team-Mail immer, Quelle „manuell angelegt von …“, Filter „Quelle“ | 1.5.3 | ADM |
| Manuelle Buchung: Auswahl bar bei Abholung oder online, Platzhalter `{payment_info}` | 1.5.5, 1.5.8 | ADM, PAY |
| Kundenangaben einer Buchung im Backend bearbeiten; Versandprotokoll der letzten 30 Mails | 1.5.1 | ADM, IF |
| Barbuchung: Häkchen „Miete bar erhalten“ und „Kaution bar erhalten“ beim Wechsel auf „Abgeholt“ | 1.6.0 | PAY |
| Kautionsabrechnung als eigener Beleg mit Nummernkreis KAU-JJJJ-0001 (siehe Abweichung bei PAY-23) | 1.6.0 | PAY |
| Seite „Belege“ mit Jahres- und Artfilter, Summen und CSV-Export | 1.6.0 | PAY, ADM |
| Logo auf Belegen und in allen Mails | 1.6.1 | PAY, IF |
| Mail zum Beleg mit Kasten und Satz je Belegart, Platzhalter `{document_summary}`, `{document_note}` | 1.6.2 | IF |
| Kartenstil auf dem Handy für alle Vermietungsseiten; harmlose Aktionen links, unwiderrufliche rechts und getrennt | 1.6.3 bis 1.6.7 | ADM |
| Löschregel „Abgeschlossene Buchungen ohne Zahlung und Beleg“ nach 3 Jahren; Probelauf und Protokoll | 1.7.0 | DSG |
| Kasten „Zahlungen für die EÜR“ mit Jahressummen und CSV, Erstattungen einzeln mit Datum | 1.7.1 | PAY, ADM |
| Testbuchungen: Belege TEST-JJJJ-0001, nicht in Summen und Beleg-CSV, Spalte „Test“ im EÜR-Export, Filter „Tests ausblenden“ | 1.7.2 | PAY, ADM |
| Kleinanzeigen-Vorlage je Artikel, Kurzlinks `/ka/…` mit UTM und Aufrufzähler, Quelle „Webseite über Kleinanzeigen“ | 1.8.0 bis 1.8.2 | ART, IF |
| AGB und Widerrufsbelehrung als PDF an der Mail „Bestätigt“ (§ 312f BGB) | 1.8.3 | IF, G |
| Freiwilliger Haken zur Google-Bewertung, Bitte nur in der Rechnungsmail | 1.8.4 | BKG, IF |
| Bridge: Einstellung „Kundendaten schwärzen“ (Standard an), Belege werden dann nicht ausgeliefert | Bridge 0.4.2 | BR |
