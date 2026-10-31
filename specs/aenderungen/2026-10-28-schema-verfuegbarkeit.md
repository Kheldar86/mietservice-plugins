# Änderung: Schema-Verfügbarkeit datumsbezogen

| | |
|---|---|
| Datum | 2026-10-28 |
| Plugin | msb-rental |
| Neue Version | 1.8.5 (vorher 1.8.4) |
| Status | in Arbeit |
| Pull Request | #2 |

## Betroffene Anforderungen

| ID | Art | Kurz |
|---|---|---|
| SEO-02 | geändert | Verfügbarkeit im Angebot richtet sich nach freien Tagen in den nächsten 30 Tagen statt nur nach „buchbar“ und Bestand (Befund B-11) |

## Ziel

Google zeigt bei einem Artikel nur dann „auf Lager“, wenn man ihn in absehbarer Zeit auch mieten kann. Ist ein Artikel die nächsten 30 Tage komplett ausgebucht oder gesperrt, steht im Schema `OutOfStock`. Bisher stand dort `InStock`, sobald der Artikel buchbar war und Bestand hatte, auch wenn kein einziger Tag frei war.

## Plan

1. In `MSB_Schema` eine Methode `available( $id )` ergänzen:
   - Prüfzeitraum: `$from = msb_add_days( msb_today(), lead_days )`, `$to = $from + 29 Tage`. Die Länge kommt aus einer Klassenkonstante `AVAILABILITY_DAYS = 30` und lässt sich über den Filter `msb_schema_availability_days` ändern.
   - `MSB_Availability::calendar( $id, $from, $to )` aufrufen. Das ist ein einziger SQL-Abruf für den ganzen Zeitraum. Der Kalender berücksichtigt schon „buchbar“, Bestand, Buchungen mit Puffer (VFG-01, VFG-02), Sperrzeiten, Vorlauf und maximale Vorausbuchung.
   - Rückgabe `true`, sobald ein Tag `x = 0` und `a > 0` hat. Das Kennzeichen `c` (Schliesstag) wird bewusst nicht beachtet.
2. In `MSB_Schema::product()` die Zeile `$available = $item['bookable'] && $item['stock'] > 0;` durch `self::available( $id )` ersetzen. Für einen nicht buchbaren Artikel oder einen ohne Bestand liefert der Kalender ohnehin keinen freien Tag. Der Filter `msb_product_schema` (SEO-03) bleibt unverändert.
3. Dateikopf von `class-msb-schema.php` um eine Zeile „Ab 1.8.5: …“ ergänzen, wie bei den früheren Versionen.
4. Version auf 1.8.5 (Plugin-Kopf und `MSB_VERSION`), Eintrag im `CHANGELOG.md`.
5. SEO-02 auf `umgesetzt in 1.8.5` setzen, B-11 in [befunde-und-fragen.md](../anforderungen/befunde-und-fragen.md) auf „behoben in 1.8.5“.

Nicht Teil dieser Änderung: `availabilityStarts` oder der nächste freie Tag im Schema. Google wertet das für Produkt-Snippets nicht aus, und der Wert würde bei jedem Seiten-Cache veralten.

### Betroffene Dateien

- `plugins/msb-rental/includes/class-msb-schema.php`
- `plugins/msb-rental/msb-rental.php` (Version)
- `plugins/msb-rental/CHANGELOG.md`
- `specs/anforderungen/SEO-produkt-schema.md`
- `specs/anforderungen/befunde-und-fragen.md`
- `tests/msb-rental/test-schema-availability.php` (neu, wird nicht live eingespielt)

### Datenbank

Keine Änderung.

## Entscheidungen und Hinweise

- **Prüfzeitraum 30 Tage** (Entscheidung 28.10.2026).
- **Seiten-Cache:** Die Live-Seite läuft hinter einem nginx-Seiten-Cache. Der Header `x-cache-status` liefert MISS, STALE und UPDATING. Die Lebensdauer ist nicht bekannt. Das Schema ist also so alt wie die gecachte Seite. Für Google reicht das, weil Google Seiten ohnehin nur in größeren Abständen neu einliest. Diese Änderung leert den Cache bei neuen Buchungen nicht. Bei der Live-Prüfung (Testschritt 3) den Wert `x-cache-status` mit notieren und, falls die Lebensdauer im netcup-Panel zu finden ist, unter „Nachher“ eintragen.

## Abnahmekriterien

- [ ] Ein Artikel mit freiem Tag in den nächsten 30 Tagen (ab Vorlauf) hat `"availability": "https://schema.org/InStock"`
- [ ] Ein Artikel, der in diesem Zeitraum an jedem Tag ausgebucht oder gesperrt ist, hat `OutOfStock`, auch wenn er buchbar ist und Bestand hat
- [ ] Ein nicht buchbarer Artikel oder einer mit Bestand 0 hat weiterhin `OutOfStock`
- [ ] Ein freier Tag, der nur durch den Puffer belegt ist, zählt nicht als frei (gleiche Zahl wie im Kalender auf der Artikelseite)
- [ ] Ohne Preis > 0 gibt es weiterhin kein Angebot (SEO-02)
- [ ] Ausgabe mit Rank Math (im Graph) und ohne Rank Math (eigenes Skript) unverändert bis auf `availability` (SEO-03)
- [ ] Version im Plugin-Kopf und in `MSB_VERSION` erhöht
- [ ] `CHANGELOG.md` ergänzt (Neu / Geändert / Behoben / Update-Hinweise)
- [ ] Status der Anforderungen auf `umgesetzt in 1.8.5` gesetzt

## Testschritte

1. Lokal: `php -l` auf die geänderten Dateien und `php tests/msb-rental/test-schema-availability.php`. Ergebnis am 31.10.2026: keine Syntaxfehler, 19 von 19 Fällen bestanden. Gegen 1.8.4 schlagen 6 Fälle fehl (buchbar, aber kein freier Tag).
2. Lokal oder auf einer Staging-Kopie: Artikel A mit Bestand 1 anlegen, Quelltext der Artikelseite prüfen, Ergebnis `InStock`. Einen Sperrzeitraum über die nächsten 30 Tage ab Vorlauf eintragen, Ergebnis `OutOfStock`. Den Sperrzeitraum um einen Tag kürzen, Ergebnis `InStock`.
3. Nach der Freigabe live: Den Quelltext von zwei echten Artikelseiten prüfen (`availability` im JSON-LD) und die Seiten im Rich-Results-Test von Google ohne Fehler prüfen. Dabei keine Testbuchung anlegen, sondern höchstens einen Sperrzeitraum an einem Testartikel setzen und danach wieder entfernen.

## Freigabe

| Schritt | Wann | Notiz |
|---|---|---|
| Plan bestätigt von Moritz | 28.10.2026 | Prüfzeitraum 30 Tage |
| netcup-Snapshot | 31.10.2026 | Name des Snapshots nachtragen |
| Bridge-Vorschläge | | |
| Freigegeben | | |
| Live-Abgleich SHA-256 | | alle Dateien gleich |
| Merge und Tag | | `msb-rental-v1.8.5` |

## Nachher

