# Mietservice Barnim – Vermietung (WordPress-Plugin) · Version 1.7.2

Alle Einstellungen des Plugins stehen zentral unter **Vermietung → Einstellungen**.

## Artikel mit KI anlegen (ab 1.2.0)

1. API-Schlüssel in der Claude Console (platform.claude.com → API Keys) erzeugen.
2. In WordPress unter **Vermietung → Einstellungen → KI-Assistent** eintragen und speichern.
3. Unter **Vermietung → Neuer Artikel (KI)** Fotos wählen, Eckdaten eintragen, „Text mit KI erstellen“, prüfen, als Entwurf speichern.

Details: Vermietung → Hilfe & Doku → „Artikel mit KI anlegen“.

Die vollständige Dokumentation aller Funktionen, Einstellungen und Shortcodes finden Sie
**im Plugin selbst unter Vermietung → Hilfe & Doku**. Alle Änderungen je Version stehen in `CHANGELOG.md`
(und ebenfalls in der Hilfe unter „Versionshinweise“).

## Installation / Update

**Neu installieren:** Plugins → Installieren → Plugin hochladen → ZIP wählen → Installieren → Aktivieren.

**Update von 1.0.0:** Plugins → Installieren → Plugin hochladen → ZIP wählen → „Aktuelle Version durch die
hochgeladene Version ersetzen“. Alle Buchungen und Einstellungen bleiben erhalten; neue Tabellen und die Seite
„Mein Konto“ werden automatisch angelegt.

Vorher wie immer ein Backup machen – über die Backup-Funktion von netcup (kein Backup-Plugin).

## Nach dem Update einrichten

1. **Einstellungen → Preise & Rabatte** bzw. im Artikel „Dynamische Preise & Rabatte“: z. B. Wochenendrabatt.
2. **Mietartikel → Bildergalerie**: weitere Bilder hinzufügen.
3. **Einstellungen → Zahlungen & Kaution**: Anzahlung, Kaution, Stripe und/oder PayPal – zuerst im Testmodus.
4. **Einstellungen → Kundenkonten**: Konten erlauben (Gastbuchung bleibt möglich).
5. **Buchungsformular**: Spalte „Kundenstamm“ prüfen.
6. Testbuchung durchspielen (Stripe-Testkarte 4242 4242 4242 4242).

Voraussetzungen: WordPress ab 6.0, PHP ab 7.4, HTTPS (für Online-Zahlungen).
