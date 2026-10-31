# Changelog – Mietservice Barnim Vermietung

Alle Änderungen pro Version. Die neueste Version steht oben. Dieselben Hinweise finden Sie im Backend unter **Vermietung → Hilfe & Doku → Versionshinweise**.

## 1.8.5 – 28.10.2026

### Geändert
- Strukturierte Daten (Google): Die Verfügbarkeit eines Artikels richtet sich jetzt nach dem Buchungskalender. „Auf Lager“ (InStock) steht nur noch, wenn der Artikel in den nächsten 30 Tagen (ab heute + Vorlauf) an mindestens einem Tag frei ist. Ist er in dieser Zeit ausgebucht oder gesperrt, steht „Nicht verfügbar“ (OutOfStock). Bisher reichten „buchbar“ und Bestand > 0
- Der Zeitraum lässt sich per Filter `msb_schema_availability_days` ändern

### Update-Hinweise
- Keine Datenbankänderung
- Die Seiten liegen im Seiten-Cache des Servers. Die neue Angabe erscheint, sobald eine Seite neu aufgebaut wird

## 1.8.4 – 26.10.2026

### Neu
- Freiwilliger Haken im Buchungsformular: „Ja, Sie dürfen mich nach der Rückgabe einmal per E-Mail um eine Bewertung bei Google bitten …“. Er steht vor der AGB-Checkbox, ist nicht Pflicht und lässt sich im Formular-Builder umformulieren oder verschieben. Auch die Bestätigungsseite für Telefonbuchungen zeigt ihn
- Nur wer den Haken gesetzt hat, bekommt in der Mail zur Rechnung (nach der Rückgabe) einen kurzen Absatz mit Knopf „Bei Google bewerten“ und dem Hinweis, wie man widerspricht. Stornorechnung, Korrektur und Kautionsabrechnung bleiben ohne Bitte. Hintergrund: Eine Bewertungsbitte in einer Rechnungsmail gilt ohne Einwilligung als Werbung (BGH VI ZR 225/17)
- Neues Feld „Google-Bewertung“ unter Einstellungen → Belege, vorbelegt mit dem Link aus dem Unternehmensprofil. Leer = Bitte aus

### Update-Hinweise
- Keine Datenbankänderung. Der Haken wird beim ersten Aufruf des Backends einmal ins gespeicherte Buchungsformular eingefügt
- Buchungen vor 1.8.4 haben den Haken nicht und bekommen keine Bitte
- Datenschutzerklärung um einen Satz zur Bewertungs-Bitte ergänzen

## 1.8.3 – 26.10.2026

### Neu
- Die Mail „Buchung bestätigt“ hängt AGB und Widerrufsbelehrung als PDF an und weist im Text darauf hin. Ein Link auf die AGB-Seite allein gilt nicht als dauerhafter Datenträger (§ 312f BGB)
- Das PDF entsteht aus den Seiten „AGB“ und „Widerrufsbelehrung“ und wird neu erzeugt, sobald eine der Seiten geändert wird. Ältere Fassungen bleiben in uploads/msb-belege (geschützt), damit nachvollziehbar ist, welcher Text verschickt wurde

### Update-Hinweise
- Keine Datenbankänderung. Nach dem Update eine Testbuchung bestätigen lassen und den Anhang prüfen

## 1.8.2 – 25.10.2026

### Geändert
- Kleinanzeigen-Vorlage: Der Text sagt nur noch „Abholung und Rückgabe in Bernau.“ Adresse und Öffnungszeiten blendet Kleinanzeigen im Profil selbst ein
- Anzeigen, die als aktuell markiert waren, erscheinen dadurch einmal als „veraltet“

## 1.8.1 – 25.10.2026

### Geändert
- Kleinanzeigen-Vorlage: Der Text nennt jetzt die aktiven Preisregeln des Artikels (eigene und globale), z. B. „Wochenendrabatt 15 % (Abholung Fr/Sa, Rückgabe bis Mo, max. 3 Tage)“ und „Langzeitrabatt 30 % (ab 7 Tagen)“. Abgelaufene Saisonregeln fallen weg. Gilt nur der höchste Rabatt, steht das dabei
- Anzeigen, die als aktuell markiert waren, erscheinen dadurch einmal als „veraltet“

## 1.8.0 – 25.10.2026

### Neu
- Kasten „Kleinanzeigen-Vorlage“ in jedem veröffentlichten Mietartikel: Titel (max. 65 Zeichen) und Beschreibung zum Kopieren, Tagespreis fürs Preisfeld, Fotos als ZIP (Beitragsbild zuerst). Kleinanzeigen hat keine offene Schnittstelle und verbietet automatisches Einstellen, deshalb wird kopiert und eingefügt
- Die Beschreibung entsteht aus den vorhandenen Feldern: Kurzbeschreibung statt Einleitung (damit der Text anders ist als die Website), Abschnitte des Artikeltexts ohne den Abschluss „… mieten in Bernau“, Technische Daten, Preis, Staffel, Kaution, Abholung, Kurzlink und Hinweis auf die AGB
- Kurzlink je Artikel, z. B. /ka/kinderkraxe. Er leitet mit utm_source=kleinanzeigen auf den Artikel weiter und zählt die Aufrufe je Monat (ohne Vorschauen von Messengern, Suchmaschinen und eigene Aufrufe im Backend)
- Buchungen, die nach einem Klick auf den Kurzlink entstehen, tragen die Quelle „Webseite über Kleinanzeigen“ (Buchungsliste, Buchung, Team-Mail). Die Herkunft liegt neben dem Warenkorb, es gibt kein neues Cookie
- Haken „Anzeige ist jetzt auf dem Stand dieser Vorlage“: ändern sich danach Text, Preis oder Fotos, steht in der neuen Spalte „Kleinanzeigen“ der Artikelliste „veraltet“
- Optionales Feld für den Link zur Anzeige bei Kleinanzeigen

### Update-Hinweise
- Datenbank 1.8.1: neue Spalte channel in msb_bookings (1.8.0 wurde bei der ersten Freigabe ohne die Spalte eingetragen)
- Permalinks werden nach dem Update einmal neu aufgebaut, damit /ka/… funktioniert

## 1.7.2 – 25.10.2026

### Neu
- Testbuchungen: Im Kasten „Belege“ einer Buchung gibt es den Knopf „Als Testbuchung markieren“. Er ändert nur die Buchhaltung, Ablauf, Mails und Zahlungen bleiben wie im echten Betrieb
- Belege einer Testbuchung bekommen eigene Nummern TEST-JJJJ-0001 statt RE oder KAU, damit die echten Nummernkreise lückenlos bleiben. Testbelege zählen nicht in den Summen der Seite Belege und fehlen in der Beleg-CSV
- EÜR-Export: Bargeld aus Testbuchungen fehlt (es ist kein echtes Geld geflossen). Online-Zahlungen aus Testbuchungen bleiben drin, weil das Geld echt über Stripe läuft, und stehen mit „ja“ in der neuen Spalte „Test“
- Buchungsliste: „Test“ hinter der Buchungsnummer und Filter „Tests ausblenden / Nur Tests“
- Der Haken lässt sich nur ändern, solange die Buchung keine gültigen Belege hat

## 1.7.1 – 25.10.2026

### Neu
- Seite Belege: Kasten „Zahlungen für die EÜR“ mit Jahressummen (Bar, Stripe brutto, Erstattungen, Kautionseinbehalte) und CSV-Download. Jede Einnahme, Erstattung und jeder Kautionseinbehalt steht einzeln mit Datum, Zahlungsweg, Buchung, Beleg und steuerlicher Einordnung darin. Gedacht als Ergänzung zum Kontoauszug in WISO
- Erstattungen werden ab jetzt einzeln mit Datum gespeichert

## 1.7.0 – 25.10.2026

### Neu
- Automatische Löschfristen mit täglichem Lauf: abgebrochene Buchungen ohne Zahlung nach 30 Tagen, nie bestätigte Kundenkonten nach 30 Tagen, Kundendaten 3 Jahre nach Ende des Jahres der letzten Buchung oder Anmeldung, Buchungen mit Zahlung oder Beleg 8 Jahre nach Ende des Jahres des letzten Belegs (samt Zahlungen, Belegen und PDFs)
- Probelauf: zuerst wird nur gezählt und protokolliert, was gelöscht würde. Umschalten unter Einstellungen → Löschfristen
- Neuer Reiter „Löschfristen“ mit Regeln, Knopf „Jetzt prüfen“ und Protokoll (nur Datum, Regel und Anzahl, keine personenbezogenen Daten)
- Kasten „Daten löschen (Art. 17 DSGVO)“ am Kunden: zeigt vorher, was gelöscht und was wegen der Aufbewahrungspflicht gesperrt wird
- Gesperrte Buchungen tragen einen Hinweis „gesperrt bis …“

### Geändert
- Buchungen mit Zahlung oder Beleg lassen sich vor Ablauf der Aufbewahrungsfrist nicht mehr löschen, Stornieren geht weiterhin
- Kunden löschen (auch als Sammelaktion) löscht jetzt auch ein reines Kundenkonto und Buchungen ohne Zahlung; laufende Buchungen verhindern das Löschen
- Laufende Buchungen werden nie automatisch gelöscht. Administratoren- und Gesellschafter-Konten werden nie gelöscht

### Update-Hinweise
- Datenbank 1.6.0: neue Tabelle msb_deletion_log, Spalte blocked_until in msb_bookings
- Nach etwa 30 Tagen das Protokoll prüfen und dann „Löschung ausführen“ aktivieren

## 1.6.7 – 25.10.2026

### Geändert
- Handy-Ansicht (Paket B): Kundenliste als Karten mit „Bearbeiten“ links und „Neue Buchung“ rechts; Buchungen beim Kunden als Karten mit Status oben rechts
- Preisregeln im Artikel: jede Regel als Karte mit „Regel aktiv“, Beschriftungen und „Entfernen“ rot und abgesetzt rechts unten
- Inventar: je Artikel eine Karte mit den Tagen als Kacheln (Wochentag, Datum, freie Stück), heute blau umrandet
- Einstellungen: Reiter in einer Zeile zum seitlichen Wischen, der aktive Reiter ist sichtbar
- Am Computer bleibt alles unverändert

## 1.6.6 – 25.10.2026

### Behoben
- Buchungsliste auf dem Handy: „Öffnen“ und „Stornieren“ standen weiter nebeneinander und das Häkchen war nach rechts verrutscht. Ursache: WordPress 7.1 setzt das Häkchen in eine normale Zelle und die Buchungsnummer in eine Kopfzelle. Die Regeln greifen jetzt für beide Varianten

## 1.6.5 – 25.10.2026

### Geändert
- Buchungsliste auf dem Handy: „Öffnen“ ganz links, „Stornieren“ rot ganz rechts in einer eigenen Zeile unter den Angaben, damit man sich nicht vertippt; Häkchen, Nummer und Status sauber in der Kopfzeile
- Belege und Zahlungen auf dem Handy: „Korrigieren“ rechts, „Einbehalten“ durch eine Linie von „Freigeben“ getrennt
- Grundsatz für alle Handy-Ansichten: unwiderrufliche Aktionen rechts und deutlich getrennt von harmlosen

## 1.6.4 – 25.10.2026

### Geändert
- Kartenstil auf dem Handy für weitere Seiten, am Computer bleiben die Tabellen
- Belege: jede Zeile als Karte mit Nummer und Betrag oben, darunter Art, Datum, Kunde, Status und Buchung; Kasten „Belege“ in der Buchung ebenso mit Versanddatum und Knöpfen
- Buchungen: jede Buchung als Karte mit Nummer und Status oben und allen Angaben darunter (Kunde, Artikel, Zeitraum, Mietpreis, Zahlung, Eingang); Kacheln Heute/Morgen untereinander
- Buchung: „Gemietete Artikel“ und „Zahlungen“ als Karten (Betrag oben rechts, Knöpfe darunter), Kennzahlen zweispaltig
- Neuer Baustein „Kartentabelle“ (Klasse msb-cards): Beschriftungen, Kopf, Betrag und Aktionen setzt admin.js aus der Tabellenüberschrift

## 1.6.3 – 25.10.2026

### Geändert
- Handy-Ansicht (unter 782 px Breite) übersichtlicher, am Computer bleibt alles gleich
- Buchung manuell anlegen: jeder Artikel als Karte mit Artikel, Abholung, Rückgabe und Anzahl untereinander; Kundendaten einspaltig
- Belegungskalender: Liste der Tage mit Buchungen (Wochentag, Datum, Einträge in voller Länge), Monatsauswahl und Artikelfilter in voller Breite, Hinweis bei einem Monat ohne Buchungen
- Buchungsformular: jedes Feld als Karte mit Beschriftung, Typ, Breite, Schlüssel und antippbaren Häkchen „Pflichtfeld“ und „Im Kundenstamm speichern“; Reihenfolge mit Pfeilen statt Ziehen

## 1.6.2 – 24.10.2026

### Geändert
- Mail zum Beleg übersichtlicher: Kasten mit Belegnummer, Datum, Buchung, Zeitraum, Betrag und Zahlungsvermerk (bei der Kautionsabrechnung auch Kaution, Einbehalt und Grund), darunter der Knopf „PDF ansehen“
- Je Belegart ein passender Satz, z. B. bei einer Korrektur „Dieser Beleg hebt die Rechnung … vollständig auf. Sie müssen nichts weiter tun.“
- Neue Platzhalter {document_summary} (Kasten mit Knopf) und {document_note} (Satz je Belegart). Fehlt {document_summary} in einer eigenen Vorlage, steht der Kasten automatisch vor „Viele Grüße“
- Betreff der Kautionsmail nennt die Belegart, damit eine Korrektur nicht als „Kautionsabrechnung“ erscheint
- Nie geänderte Mailtexte aus 1.6.0 werden einmalig durch die neuen ersetzt

## 1.6.1 – 24.10.2026

### Neu
- Logo oben links auf Rechnungen, Stornorechnungen, Korrekturen und Kautionsabrechnungen (weißer Rand wird automatisch abgeschnitten, transparente PNGs bekommen weißen Hintergrund)
- Logo oben in allen E-Mails des Plugins
- Einstellungen → Belege: „Logo aus der Mediathek wählen“. Ohne Auswahl wird das Website-Icon verwendet

### Behoben
- Sonderzeichen in Artikelnamen standen im PDF als „&amp;amp;“ statt „&amp;“

## 1.6.0 – 24.10.2026

### Neu
- Belege als PDF: Rechnung (RE-JJJJ-0001), Stornorechnung und Rechnungskorrektur im selben Nummernkreis, dazu die Kautionsabrechnung (KAU-JJJJ-0001) mit eigenem Nummernkreis. Beide Zähler beginnen jedes Jahr bei 0001
- Die Rechnung entsteht automatisch beim Status „Zurückgegeben“ und geht als PDF-Anhang an den Kunden, eine Kopie an die Buchungsadresse. Bei einer Stornogebühr entsteht die Stornorechnung, beim Einbehalt aus der Kaution die Kautionsabrechnung (Schadenersatz, nicht umsatzsteuerbar, keine Rechnung)
- Auf jeder Rechnung: Absender, Steuernummer, Hinweis nach § 19 UStG und ein Zahlungsvermerk („Betrag dankend bar erhalten am …“, „Betrag bereits bezahlt via Stripe … am …“ oder der offene Betrag) statt einer Bankverbindung
- Kasten „Belege“ in jeder Buchung: PDF ansehen, erneut senden, korrigieren (ein Korrekturbeleg mit eigener Nummer hebt den alten Beleg auf, Belege werden nie geändert oder gelöscht), Rechnung von Hand erstellen
- Neue Seite **Vermietung → Belege** mit Jahres- und Artfilter, Summen und CSV-Export für den Steuerberater
- Neuer Reiter **Einstellungen → Belege**: Absender, Steuernummer, Hinweis Umsatzsteuer, Fußzeile, Kürzel der Nummernkreise, Automatik und E-Mail-Vorlagen
- Mein Konto: Belege der Buchung zum Herunterladen
- Barbuchung: Im Statuskasten stehen die Häkchen „Miete bar erhalten“ und „Kaution bar erhalten“. Beim Wechsel auf „Abgeholt“ werden die Zahlungen damit vermerkt, das Datum steht später im Zahlungsvermerk der Rechnung
- Barkaution: Knopf „Einbehalten“ mit Betrag und Grund, daraus entsteht die Kautionsabrechnung

### Behoben
- Freigeben oder Einbehalten einer bar erfassten Kaution hat versucht, einen Zahlungsanbieter aufzurufen. Jetzt wird es nur vermerkt. Eine Barkaution wird bei „Zurückgegeben“ nicht mehr automatisch freigegeben, damit ein Einbehalt möglich bleibt

### Technisch
- Datenbank 1.5.0: neue Tabelle für Belege. Die PDF-Dateien liegen geschützt unter uploads/msb-belege. Die Belegtabelle bleibt beim Löschen des Plugins erhalten (Aufbewahrungspflicht)

## 1.5.8 – 24.10.2026

### Geändert
- Mail „Bitte bestätigen Sie Ihre Buchung“ und die Bestätigungsseite nennen die Zahlung: bei Barzahlung „Bitte zahlen Sie die Miete von … und die Kaution von … bei der Abholung in bar“, bei Online-Zahlung den Hinweis, dass der Bezahlknopf mit der Buchungsbestätigung kommt
- Kaution bei Barzahlung heißt in Tabellen „bar bei Abholung“

### Behoben
- Team-Mail: Lange Werte in den Kundenangaben (z. B. E-Mail-Adressen) brechen um und laufen nicht mehr über den Rand

## 1.5.7 – 24.10.2026

### Geändert
- Manuelle Buchung, neuer Ablauf: Der Kunde bekommt zuerst nur die Mail „Bitte bestätigen Sie Ihre Buchung“ mit Mietbedingungen, Datenschutz und Knopf „Jetzt bestätigen“. So gibt er zum mündlich abgesprochenen Angebot noch einmal selbst seine Zustimmung
- Bestätigt der Kunde per Link, wird eine angefragte Buchung automatisch „Bestätigt“. Er erhält dann die Mail „Bestätigt“ mit den Zahlungsangaben (bar oder Bezahlknopf), das Team eine Info an die Buchungsadresse
- Neue manuelle Buchung: Vorauswahl Status „Angefragt“, „Kunden per E-Mail informieren“ ist standardmäßig angehakt

## 1.5.6 – 24.10.2026

### Geändert
- Manuelle Buchung mit „Kunden per E-Mail informieren“: Der Kunde bekommt nur noch eine Mail. Der Link zum Bestätigen von Mietbedingungen und Datenschutz steht jetzt in der Mail „Bestätigt“ bzw. „Eingegangen“ (vor dem Gruß). Die eigene Bestätigungsmail kommt nur noch, wenn die passende Vorlage ausgeschaltet ist oder über „Link erneut senden“
- Fehlt die Bestätigung noch, enthält auch eine spätere Mail „Bestätigt“ den Link
- Mein Konto: Bei Barzahlung erscheint kein Knopf „Kaution jetzt online hinterlegen“ mehr. Bei Online-Zahlung kommt zuerst „Restzahlung jetzt online bezahlen“, danach die Kaution
- Storno-Info an das Team geht nur noch an die Empfänger-Adresse aus den Einstellungen (wie die Mail zu neuen Buchungen), nicht mehr zusätzlich an die persönlichen Adressen aller Administratoren und Gesellschafter

### Behoben
- Mails auf dem Handy: Die Artikeltabelle hat nur noch zwei Spalten (Artikel mit Zeitraum, Tagen und Anzahl darunter, rechts der Preis) und läuft nicht mehr über den Rand. Beträge brechen nicht mehr um, die Ränder sind schmaler
- Mein Konto: Knöpfe zeigen wieder weiße Schrift auf Grün (die Linkfarbe des Themes lag darüber und machte sie unlesbar)

## 1.5.5 – 24.10.2026

### Neu
- Manuelle Buchung: Auswahl „Zahlung“ mit „Bar bei Abholung (Miete und Kaution)“ oder „Online“ (Stripe). Bei Online-Zahlung enthält die Kundenmail einen Bezahlknopf für die Miete
- Neuer Platzhalter {payment_info}: setzt je nach Zahlungsart den passenden Absatz ein (Barzahlung von Miete und Kaution bei Abholung oder Online-Zahlung mit Bezahlknopf und Hinweis zur Kautions-Reservierung)
- Fehlt {payment_info} in der Vorlage „Eingegangen“ oder „Bestätigt“ und ist noch eine Online-Zahlung offen, wird der Bezahlknopf trotzdem angehängt
- Einmalig: Der feste Kautionssatz („Haben Sie online mit Karte bezahlt … Bei Barzahlung …“) in der Vorlage „Bestätigt“ wird durch {payment_info} ersetzt
- Wer die Miete über den Zahlungslink zahlt, hinterlegt dabei die Karte; die Kaution wird kurz vor der Abholung automatisch darauf reserviert

### Geändert
- Barzahlung: Für diese Buchungen wird kein automatischer Kautions-Zahlungslink mehr verschickt; die Kaution steht als „bei Abholung“. Über „Kaution jetzt reservieren“ geht es weiterhin von Hand
- Zahlungsart „offline“ wird als „Bar bei Abholung“ angezeigt, wenn als Bezeichnung keine oder eine E-Mail-Adresse eingetragen ist

## 1.5.4 – 23.10.2026

### Behoben
- Buchungsformular: Auswahlmöglichkeiten (Auswahlliste, Optionsfelder) lassen sich jetzt auch mit „;“ trennen. Beim Speichern wird daraus je eine Zeile
- Unter dem Feld steht, wie viele Auswahlmöglichkeiten gespeichert sind. So fällt sofort auf, wenn zwei Einträge in einer Zeile gelandet sind (z. B. weil das schmale Feld den Text nur optisch umbricht)

## 1.5.3 – 23.10.2026

### Neu
- Manuell angelegte Buchungen schicken immer die Benachrichtigung an die Buchungsadresse aus den Einstellungen, auch ohne Haken „Kunden informieren“. Der Haken gilt nur noch für die Kundenmail
- Diese Team-Mail ist als manuell erkennbar: Betreff mit „(manuell angelegt von …)“ und oben die Zeile „Manuell angelegt von … am …“
- Neue Platzhalter {source} (Webseite oder manuell angelegt von …) und {created_by}. Nutzt die Vorlage einen davon, entfällt der automatische Zusatz
- Die Buchung merkt sich, wer sie im Backend angelegt hat. Liste und Buchung zeigen „manuell angelegt von …“
- Filter „Quelle“ (Webseite / manuell angelegt) in der Buchungsliste, auch für den CSV-Export

### Update-Hinweise
- Datenbank 1.4.0: neue Spalte created_by in msb_bookings. Das Update läuft beim ersten Seitenaufruf automatisch. Ältere manuelle Buchungen zeigen nur „manuell angelegt“

## 1.5.2 – 23.10.2026

### Neu
- Manuell angelegte Buchungen: Der Kunde bestätigt Mietbedingungen und Datenschutz selbst über einen Link per E-Mail. Die Mail geht beim Anlegen mit, wenn „Kunden informieren“ angehakt ist, sonst über den Button „Bestätigungslink per E-Mail senden“ in der Buchung
- Bis zur Bestätigung stehen die Checkboxen in den Kundenangaben auf „Offen“, danach auf „Ja“ mit Zeitpunkt der Bestätigung
- Die Mails „Buchung eingegangen“ und „Buchung bestätigt“ enthalten einen Hinweis mit Links zu Mietbedingungen und Datenschutzerklärung (einmalig beim Update eingefügt, unter Einstellungen → E-Mails änderbar)

### Update-Hinweise
- Keine Datenbankänderung. Der Hinweis in den Mails wird beim ersten Aufruf des Backends durch einen Administrator eingefügt

## 1.5.1 – 23.10.2026

### Neu
- Kundenangaben einer Buchung lassen sich im Backend bearbeiten (Button „Kundenangaben bearbeiten“ in der Buchung), für Administratoren und Gesellschafter. Auf Wunsch wird die Änderung auch in den Kundenstamm übernommen. Bestätigungen wie AGB und Datenschutz bleiben unverändert
- Versandprotokoll der letzten 30 E-Mails der Webseite (Option msb_mail_log), damit sich prüfen lässt, ob eine Mail an den Mailserver übergeben wurde

### Behoben
- „Mein Konto“: Der aktive Reiter hatte grüne Schrift auf grünem Grund, jetzt weiß

### Update-Hinweise
- Keine Datenbankänderung

## 1.5.0 – 23.10.2026

### Neu
- Kunden stornieren ihre Buchung selbst in „Mein Konto“, bis Mietbeginn 0:00 Uhr (KTO-07). Vor dem Bestätigen sehen sie Gebühr, bezahlten Betrag und Erstattung
- Stornostaffel (PAY-16, PAY-17): bis 7 Tage vor Mietbeginn kostenlos, ab 48 Stunden vorher 25 %, danach 50 % der Tagesmiete nach Rabatten, ohne Grundgebühr. Fristen zählen ab Mietbeginn 0:00 Uhr
- Die Erstattung läuft automatisch über Stripe bzw. PayPal, eine reservierte Kaution wird freigegeben. Bar oder manuell erfasste Zahlungen werden zur Erstattung von Hand gemeldet
- Info-Mail an Administratoren und Gesellschafter bei jeder Kundenstornierung, mit Gebühr und Erstattung; schlägt die Erstattung fehl, steht der Fehler in der Mail und sie bleibt in der Buchung unter „Zahlungen“ auslösbar
- Neue Platzhalter {cancel_fee} und {refund_amount}. Enthält die Stornomail keinen der beiden, hängt das Plugin Gebühr und Erstattung automatisch an
- „Mein Konto“ zeigt bei stornierten Buchungen Gebühr und erstatteten Betrag

### Geändert
- Stornieren im Backend über den neuen Kasten „Stornieren“ in der Buchung (PAY-15): Er schlägt die Gebühr nach Staffel vor, der Betrag ist änderbar, die Erstattung abschaltbar. „Storniert“ ist dafür nicht mehr in der Statusauswahl
- Schnell- und Sammelaktionen stornieren weiterhin ohne Gebühr und ohne Erstattung
- Die Buchung zeigt, wann und von wem storniert wurde

### Update-Hinweise
- Datenbank 1.3.0: neue Spalten cancel_fee, cancelled_by und cancelled_at in msb_bookings. Das Update läuft beim ersten Seitenaufruf automatisch
- Optional die Stornomail unter Einstellungen → E-Mails um {cancel_fee} und {refund_amount} ergänzen
- Danach AGB Abschnitt 6 um die Stornierung in „Mein Konto“ ergänzen

## 1.4.0 – 23.10.2026

### Neu
- Rolle „Gesellschafter“ (ROL-01, ROL-02): Buchungen ansehen, anlegen und bearbeiten, Status setzen, Rückgabe bestätigen, Zahlungen, Erstattungen und Kautionen, Kunden pflegen, Mietartikel und Kategorien anlegen und veröffentlichen
- Rechte je Bereich (ROL-03): Buchungen, Löschen, Mietartikel und Einstellungen haben eigene Rechte. Einstellungen, Buchungsformular, Shortcodes und das Löschen von Kategorien bleiben Administratoren vorbehalten. Plugins, Benutzer, WordPress-Einstellungen und die Claude-Bridge sieht ein Gesellschafter nicht
- Der KI-Assistent „Neuer Artikel (KI)“ steht auch Gesellschaftern zur Verfügung (KI-01), seine Einstellungen nur Administratoren

### Geändert
- Buchungen und Kunden löschen dürfen nur Administratoren
- Die Schnellaktionen „Bestätigen“ und „Stornieren“ in der Buchungsliste erscheinen nur noch, wenn der Wechsel nach den Statusregeln erlaubt ist
- Sammelaktionen melden, wie viele Buchungen übersprungen wurden, weil der Wechsel nicht erlaubt ist
- Eine neue Buchung im Backend kann nicht mehr mit „Zahlung ausstehend“ angelegt werden

### Behoben
- Beim Speichern eines Kunden werden Name und E-Mail eines verknüpften Mitarbeiter- oder Administratorkontos nicht mehr überschrieben

### Update-Hinweise
- Keine Datenbankänderung. Die Rolle wird beim ersten Aufruf einer Backend-Seite automatisch angelegt
- Eric unter **Benutzer → Neu** mit der Rolle „Gesellschafter“ anlegen

## 1.3.0 – 23.10.2026

### Neu
- Feste Statusregeln (BKG-16): Erlaubt sind nur noch Zahlung ausstehend → Angefragt, Bestätigt oder Storniert; Angefragt → Bestätigt oder Storniert; Bestätigt → Abgeholt oder Storniert; Abgeholt → Zurückgegeben. Die Statusauswahl in der Buchung zeigt nur diese Ziele. Schnell- und Sammelaktionen führen nicht erlaubte Wechsel nicht aus
- Zurück aus „Zurückgegeben“ (nach „Abgeholt“) oder „Storniert“ (nach „Angefragt“ oder „Bestätigt“) dürfen nur Administratoren. Dabei wird geprüft, ob die Artikel noch frei sind (BKG-14)
- Buchungsnummer mit Jahreszähler (BKG-07): Präfix + Jahr + laufende Nummer, die jedes Jahr wieder bei 0001 beginnt. Der Zähler liegt in einer neuen Tabelle, die ab 1.6.0 auch die Rechnungsnummern liefert

### Update-Hinweise
- Datenbank 1.2.0: neue Tabelle `msb_sequences`. Das Update läuft beim ersten Seitenaufruf automatisch
- Vorhandene Buchungen behalten ihre Nummer. Der Zähler für 2026 startet hinter der höchsten vorhandenen Nummer, die nächste Buchung bekommt also MB-2026-0018. Ab dem 1.1.2027 beginnt er bei 0001

## 1.2.8 – 23.10.2026

### Behoben
- Checkout ist mit einem Sicherheitstoken gegen fremde Formulare geschützt (BKG-03). Ist eine Buchungsseite sehr lange offen, erscheint der Hinweis, die Seite neu zu laden
- Das Rate-Limit zählt auch fehlgeschlagene Versuche: höchstens 20 Versuche und 5 Buchungen je IP in 10 Minuten (BKG-02)
- Bekommt eine Buchung die Datenbanksperre nicht, bricht sie mit Hinweis ab, statt weiterzulaufen. Verhindert Doppelbuchungen bei gleichzeitigen Anfragen (BKG-05)
- Bei Offline-Buchungen (auch manuelle Buchungen im Backend) wird keine Anzahlung mehr angelegt; „Mein Konto“ zeigt dort keinen Zahlen-Button für die Anzahlung (PAY-04)

### Geändert
- Anleitung: Backups laufen über netcup, kein Backup-Plugin

### Update-Hinweise
- Keine Datenbankänderung. Bereits geöffnete Buchungsseiten einmal neu laden

## 1.2.7 – 21.10.2026

### Neu
- Feld „Marke“ je Mietartikel (Seitenleiste, Box „Suchmaschinen (Google)“). Die Marke wird in den strukturierten Daten als Product.brand ausgegeben und behebt den Search-Console-Hinweis „Keine globale Kennzeichnung angegeben (z. B. GTIN oder Marke)“
- Der KI-Assistent übernimmt die Marke automatisch aus „Marke/Modell“ (erstes Wort)

### Update-Hinweise
- Keine Änderung an der Datenbankstruktur. Für die vorhandenen Artikel wurden die Marken einmalig eingetragen (Thule, Thule, Deuter)
- Search Console: Unter „Händlereinträge“ die Validierung für den Marken-Hinweis starten
- Nicht umgesetzt: Versand (shippingDetails), Rückgaberichtlinie (hasMerchantReturnPolicy) und Bewertungen (review/aggregateRating). Versand und Rückgabe betreffen den Kauf von Waren, nicht die Vermietung mit Abholung. Bewertungen dürfen nur aus echten Kundenbewertungen stammen. Alles sind nicht kritische Hinweise, die die Anzeige nicht verhindern

## 1.2.6 – 17.10.2026

### Behoben
- Hilfe & Doku auf dem Smartphone: Die Korrektur aus 1.2.5 griff nicht, weil die Handy-Regel im Stylesheet vor der Grundregel stand und von ihr überschrieben wurde. Das Inhaltsverzeichnis steht jetzt auf Smartphone und Tablet wirklich oben und scrollt mit dem Text

### Update-Hinweise
- Keine Datenbankänderung; das neue CSS wird über die neue Versionsnummer automatisch geladen

## 1.2.5 – 17.10.2026

### Geändert
- Hilfe & Doku auf dem Smartphone: Das Inhaltsverzeichnis blieb beim Scrollen fixiert und verdeckte den gesamten Text. Auf kleinen Bildschirmen steht es jetzt normal oben (ab Tablet-Breite in zwei Spalten) und scrollt mit; am Desktop bleibt es seitlich fixiert

### Update-Hinweise
- Keine Datenbankänderung; ggf. Seite einmal neu laden, damit das neue CSS geladen wird

## 1.2.4 – 17.10.2026

### Geändert
- Die Mikrodaten des Astra-Themes („CreativeWork“ und „Person“ mit Autor „admin“) werden abgeschaltet, solange Rank Math die strukturierten Daten liefert. Sie waren doppelt und lösten im Schema-Validator einen Fehler aus
- Ohne Rank Math bleiben die Theme-Mikrodaten unverändert aktiv

### Update-Hinweise
- Keine Datenbankänderung
- Prüfen mit validator.schema.org: Auf Artikelseiten sollten „Product“ und „BreadcrumbList“ ohne Fehler erscheinen

## 1.2.3 – 17.10.2026

### Neu
- **Strukturierte Daten für Mietartikel:** Jede Artikelseite enthält jetzt ein schema.org-„Product“ mit Mietangebot (Tagespreis pro Tag, Währung, Verfügbarkeit, Kategorie, Bilder, Anbieter). Google kann damit Preis und Verfügbarkeit direkt im Suchergebnis anzeigen
- Mit Rank Math wird das Produkt in dessen strukturierte Daten eingefügt, ohne Rank Math als eigenes Skript ausgegeben
- Neuer Filter `msb_product_schema` zum Anpassen der Daten

### Update-Hinweise
- Keine Datenbankänderung; die Daten werden aus Preis, Bestand, Galerie und Kategorie des Artikels erzeugt
- Prüfen mit dem Google-Test für Rich-Suchergebnisse (search.google.com/test/rich-results)

## 1.2.2 – 11.10.2026

### Neu
- KI-Assistent: neue Felder **Produktart** (z. B. „Kinderkraxe“) und **SEO-Titel**; beides schlägt die KI vor
- Jede neue Beschreibung erhält die Zwischenüberschrift **„[Produktart] mieten in Bernau“** mit Abholhinweis und internen Links zu den AGB und den Mietartikeln
- Fokus-Keyword „[Produktart] mieten“, kurze URL („kinderkraxe-mieten-deuter-kid-comfort“) und Alt-Text des Beitragsbilds mit Keyword werden automatisch gesetzt; der SEO-Titel wird an Rank Math übergeben
- Vollständigkeitsprüfung meldet zusätzlich ein fehlendes Fokus-Keyword

### Geändert
- Einleitung beginnt immer mit „[Produktart] mieten statt kaufen: …“ (wird ergänzt, falls die KI es auslässt)
- Neuer Standard-Styleguide mit SEO-Regeln; ein unverändert gespeicherter Styleguide aus 1.2.0/1.2.1 wird automatisch ersetzt, ein selbst angepasster bleibt erhalten

### Update-Hinweise
- Keine Datenbankänderung
- Die drei bestehenden Artikel wurden bereits manuell auf diesen Aufbau umgestellt (Keyword, SEO-Titel, URL, Zwischenüberschrift, Links, Alt-Texte); alte Artikel-URLs leiten automatisch weiter

## 1.2.1 – 11.10.2026

### Geändert
- Die KI-Einstellungen (API-Schlüssel, Modell, Abschlusssatz, Styleguide) stehen jetzt zentral unter **Vermietung → Einstellungen → KI-Assistent** – alle Einstellungen des Plugins sind damit an einem Ort
- Die Seite „Neuer Artikel (KI)“ enthält nur noch den Assistenten; Hinweise und Fehlermeldungen verlinken auf den neuen Reiter
- Hilfe & Doku: Abschnitt „Artikel mit KI anlegen“ ergänzt (in 1.2.0 nicht übernommen)

### Update-Hinweise
- Keine Datenbankänderung; bereits gespeicherte KI-Einstellungen bleiben erhalten

## 1.2.0 – 11.10.2026

### Neu
- **KI-Artikelassistent** unter Vermietung → „Neuer Artikel (KI)“: Fotos auswählen, Eckdaten eintragen, die KI (Claude API) schreibt Titel, Kurzbeschreibung, Beschreibung, technische Daten, Alt-Texte, Meta-Beschreibung und Fokus-Keyword nach dem Styleguide
- Abfrage aller Buchungsparameter im Assistenten: Kategorie, Preis pro Tag, Kaution, Bestand, Mindestmietdauer, Grundgebühr, Staffelpreise und Rabatte (global, keine oder eigene)
- Unsichere Angaben der KI werden unter „Bitte prüfen“ markiert; alle Felder sind vor dem Speichern editierbar
- Der Artikel wird immer als **Entwurf** gespeichert – mit Kategorie, Preisen, Beitragsbild, Galerie, Alt-Texten und Rank-Math-Daten
- Einheitlicher Aufbau jeder Beschreibung: Einleitung, „Ausstattung & Zubehör“, „Ideal für“, „Gut zu wissen“ (optional), „Zustand“, Abschlusssatz
- Neuer Reiter „KI-Einstellungen“: API-Schlüssel (verdeckt gespeichert), Modellauswahl, Abschlusssatz und anpassbarer Styleguide
- **Vollständigkeitsprüfung** auf der Bearbeitungsseite jedes Mietartikels: Hinweis auf fehlende Kategorie, Preis, Kaution, Kurzbeschreibung, technische Daten, Beitragsbild, Fotos (mind. 3), Alt-Texte und Meta-Beschreibung
- Hilfe & Doku: neuer Abschnitt „Artikel mit KI anlegen“

### Update-Hinweise
- Keine Datenbankänderung; neue Einstellungen werden in der Option `msb_ai_settings` gespeichert
- Der Assistent ist erst nutzbar, wenn unter „KI-Einstellungen“ ein API-Schlüssel eingetragen ist; ohne Schlüssel ändert sich am bisherigen Verhalten nichts
- An die KI werden nur ausgewählte Fotos, Eckdaten und Beispieltexte bestehender Artikel übertragen – keine Kunden- oder Buchungsdaten

## 1.1.1 – 10.10.2026

### Geändert
- Bestell-Button auf der Buchungsseite heißt jetzt „Zahlungspflichtig buchen“ (vorher „Weiter zur Zahlung“ bzw. „Verbindlich buchen“) – Anforderung nach § 312j Abs. 3 BGB („Button-Lösung“)
- Die Beschriftung lässt sich weiterhin über die Shortcode-Attribute `button` und `button_online` von `[msb_checkout]` anpassen

### Update-Hinweise
- Keine Datenbankänderung; das Update ist sofort aktiv

## 1.1.0 – 07.10.2026

### Neu
- **Dynamische Preise & Rabatte** pro Artikel und global: Wochenende, bestimmte Wochentage, Zeitraum/Saison, Mietdauer, Frühbucher, Last Minute – als Rabatt in %, Aufschlag in %, Rabatt in €, Pauschalpreis oder fester Tagespreis
- Rabatte werden im Kalender, im Warenkorb, in den E-Mails und im Backend einzeln ausgewiesen; aktive Regeln erscheinen in der Preistabelle
- Einstellung „Rabatte addieren“ (Standard: nur der höchste Rabatt zählt)
- **Bildergalerie** je Artikel (Mediathek, sortierbar); Anzeige auf der Artikelseite mit Vorschaubildern und Vergrößerung, in Artikellisten und in der Zeitraumsuche mit Pfeilen
- Zeitraumsuche `[msb_search]` zeigt Galerie, Beschreibung und den Preis für den gesuchten Zeitraum inkl. Rabatten
- **Online-Zahlung mit Stripe** (Karte, Apple Pay, Google Pay u. a.) und **PayPal** über die gehosteten Bezahlseiten der Anbieter
- **Anzahlung** wählbar: keine, Prozent, Festbetrag oder kompletter Mietpreis
- **Kaution online reservieren**, getrennt von der Mietgebühr: automatische Reservierung X Tage vor Abholung, Freigabe bei Rückgabe, teilweises Einbehalten bei Schäden
- Zahlungslinks für Anzahlung, Restbetrag und Kaution (per E-Mail oder zum Kopieren)
- Zahlungen im Backend: Freigeben, Einbehalten, Erstatten, Barzahlung erfassen; neue Spalte „Zahlung / Kaution“ in der Buchungsliste
- Stripe-Webhook für zuverlässige Zahlungserfassung; stündliche Prüfung offener PayPal-Bestellungen
- Neuer Status „Zahlung ausstehend“; unbezahlte Online-Buchungen werden nach der Zahlungsfrist automatisch storniert, bei Abbruch liegt die Auswahl wieder im Warenkorb
- **Kundenverwaltung**: Kundenstamm mit allen Stammdaten, interner Notiz, Buchungshistorie, Suche, Filter und CSV-Export; Kunden werden bei jeder Buchung automatisch angelegt
- Neue Buchung im Backend mit Auswahl eines bestehenden Kunden
- **Optionale Kundenkonten**: Registrierung beim Buchen oder auf „Mein Konto“, Gastbuchung bleibt möglich; Bestätigung der E-Mail-Adresse
- Neuer Shortcode `[msb_account]`: Anmelden, Registrieren, „Meine Buchungen“ (mit Zahlungs- und Kautionsstatus) und „Meine Daten“ (inkl. Passwort ändern)
- Eingeloggte Kunden finden ihre Daten im Buchungsformular vorausgefüllt
- Formular-Builder: neue Option „Kundenstamm“ je Feld
- **Hilfe & Dokumentation** im Backend mit allen Einstellungen, Shortcodes, Beispielen und Suchfeld; Hilfe-Links auf allen Plugin-Seiten
- Neuer Shortcode `[msb_gallery]`
- Neue E-Mail-Vorlagen „Zahlungslink“ und „Kaution freigegeben“; neue Platzhalter u. a. `{payment_link}`, `{prepay}`, `{paid}`, `{open_amount}`, `{discount}`, `{account_link}`

### Geändert
- Die Seiten „Buchung abschließen“ und „Mein Konto“ werden bei Bedarf automatisch angelegt
- Die Buchungsseite zeigt nach dem Abschluss eine Danke-Seite mit Zusammenfassung und Zahlungsstatus
- Preisaufstellung im Kalender jetzt mit Einzelbeträgen
- Mietartikel werden im klassischen Editor bearbeitet, damit Preise, Preisregeln und Galerie direkt sichtbar sind (Block-Editor per Filter `msb_use_block_editor` wieder aktivierbar)

### Update-Hinweise
- Das Update läuft automatisch: neue Datenbanktabellen für Kunden und Zahlungen werden angelegt, bestehende Buchungen bleiben unverändert
- Bestehende Formularfelder erhalten automatisch die Option „Kundenstamm“ (Kontaktfelder an, Anmerkungen/Abholzeit aus)
- Zahlungen sind nach dem Update **nicht** aktiv – bitte unter Einstellungen → Zahlungen & Kaution einrichten und zuerst im Testmodus prüfen

## 1.0.0 – 07.10.2026

### Neu
- Mietartikel mit Kategorien, Bestand, Tagespreis, Staffelpreisen, Grundgebühr, Kaution, Mindest-/Höchstmietdauer, technischen Daten und gesperrten Zeiträumen
- Buchungskalender mit Verfügbarkeit, Preisberechnung und Warenkorb für mehrere Artikel
- Buchungsregeln: Kalendertage oder Nächte, Puffertage, Vorlaufzeit, Ruhetage, maximale Vorausbuchung
- Frei konfigurierbares Buchungsformular
- Buchungsübersicht mit Filtern, Suche, CSV-Export, Detailseite, manueller Buchung, Belegungskalender und Inventarübersicht
- E-Mails an Kunde und Betreiber mit Platzhaltern
- Shortcodes `[msb_items]`, `[msb_item]`, `[msb_booking]`, `[msb_calendar]`, `[msb_price]`, `[msb_specs]`, `[msb_availability]`, `[msb_categories]`, `[msb_search]`, `[msb_cart]`, `[msb_checkout]`, `[msb_cart_link]`
