# Feature Specification: KI-Artikelassistent bricht mit max_tokens ab

**Feature Branch**: `claude/project-thread-00bpeg`

**Created**: 2026-11-08

**Status**: Live seit 08.11.2026 (msb-rental 1.8.6)

**Input**: Meldung von Moritz (08.11.2026): „Ich wollte grade einen Artikel mit der KI Funktion erstellen, erhalte aber die Rückmeldung: Die Antwort war unvollständig (max_tokens). Bitte erneut versuchen.“

## Hintergrund

Der KI-Assistent (KI-03) schickt Fotos und Angaben an die Claude API und erwartet eine JSON-Antwort nach festem Schema. Die Antwortgrenze steht seit 1.2.x fest bei 3000 Tokens.

Die drei wählbaren Modelle (Claude Sonnet 5.5, Haiku 5.5, Opus 5.5) denken seit ihrer Einführung immer adaptiv vor der Antwort. Abschalten lässt sich das bei diesen Modellen nicht. Die Denk-Tokens zählen zur Grenze `max_tokens`. Sonnet 5.5 denkt standardmäßig mit Aufwand „high“. Denken und ein vollständiger Artikel (Titel, Einleitung, Ausstattung, technische Daten, Meta-Beschreibung, Alt-Texte für bis zu 6 Bilder) passen damit nicht mehr in 3000 Tokens. Die API beendet die Antwort mit `stop_reason: max_tokens`, das JSON ist abgeschnitten, und das Plugin zeigt „Die Antwort der KI war unvollständig (max_tokens)“. Ein neuer Versuch scheitert meist genauso.

## Betroffene Anforderungen

| ID | Art | Kurz |
|---|---|---|
| KI-03 | geändert | Antwortgrenze 16000 statt 3000 Tokens, Denkaufwand „medium“, Zeitlimit 170 s statt 120 s, eigene Fehlermeldungen für `max_tokens` und `refusal` |

Keine neue Nummer.

## User Scenarios & Testing

### User Story 1 – Vorschlag kommt vollständig an (Priority: P1)

Moritz oder Eric wählt unter Vermietung → KI-Assistent eine Kategorie und 1 bis 6 Fotos, trägt Marke/Modell ein und klickt auf „Vorschlag erstellen“. Nach höchstens etwa zwei Minuten steht ein vollständiger Vorschlag zum Prüfen da.

**Independent Test**: Mit dem Standardmodell Sonnet 5.5 und 6 Fotos einen Vorschlag erstellen. Es erscheint kein Fehler, alle Felder sind gefüllt.

**Acceptance Scenarios**:

1. **Given** Sonnet 5.5 und 6 Fotos, **When** „Vorschlag erstellen“, **Then** kommt ein vollständiger Vorschlag ohne Fehlermeldung.
2. **Given** Haiku 5.5 oder Opus 5.5, **When** „Vorschlag erstellen“, **Then** ebenso.

### User Story 2 – Verständliche Fehlermeldung (Priority: P2)

Wird eine Antwort trotzdem abgeschnitten oder lehnt die KI ab, sagt die Meldung, was passiert ist und was hilft.

**Acceptance Scenarios**:

1. **Given** die API antwortet mit `stop_reason: max_tokens`, **Then** lautet die Meldung „Die Antwort der KI war zu lang und wurde abgeschnitten (max_tokens) …“ mit dem Hinweis auf weniger Fotos oder kürzere Notizen.
2. **Given** `stop_reason: refusal`, **Then** lautet die Meldung „Die KI hat die Anfrage abgelehnt (refusal) …“.
3. **Given** ungültiges JSON bei anderem `stop_reason`, **Then** bleibt die bisherige Meldung „unvollständig (…)“.

## Requirements

- **FR-001**: Die Anfrage setzt `max_tokens` auf 16000. Das ist die übliche Grenze für Anfragen ohne Streaming und bleibt unter dem Zeitlimit.
- **FR-002**: Die Anfrage setzt `output_config.effort` auf `medium`, für alle drei Modelle gleich. Ein Feld `thinking` wird nicht gesendet (die Modelle denken adaptiv, `disabled` lehnt die API bei Sonnet 5.5 und Opus 5.5 ab).
- **FR-003**: Zeitlimit der HTTP-Anfrage 170 s, PHP-Laufzeit 240 s.
- **FR-004**: Eigene Meldungen für `max_tokens` und `refusal` (User Story 2).
- **FR-005**: Keine neue Einstellung, keine Datenbankänderung, keine Änderung an Prompt, Schema oder Speichern.

## Success Criteria

- **SC-001**: Ein Vorschlag mit Sonnet 5.5 und 6 Fotos kommt live ohne Fehler an.
- **SC-002**: Der lokale Test `tests/msb-rental/test-ki-max-tokens.php` besteht.

## Annahmen

- Effort „medium“ reicht für Artikeltexte. Wirken die Texte flacher als früher, kann der Wert in einer späteren Version auf „high“ steigen (Kosten und Dauer höher).
- Der Seitenserver lässt AJAX-Anfragen bis etwa 3 Minuten laufen. Bisher liefen Anfragen bis 120 s durch.
