# Hinweise für Claude

- Antworten und Texte auf Deutsch.
- Vor jeder Arbeit die Constitution lesen: `.specify/memory/constitution.md`. Sie geht allem anderen vor.
- Anforderungen stehen in `specs/anforderungen/` (feste Nummern wie `PRS-04`, Status je Anforderung). Features und Änderungen laufen mit Spec Kit (`/speckit-specify`, `/speckit-plan`, `/speckit-tasks`, `/speckit-implement`) und landen unter `specs/NNN-kurzname/`. Der Ablauf steht in `specs/README.md`.
- Jede Codeänderung bekommt eine neue Plugin-Version und einen CHANGELOG-Eintrag. Danach den Status der Anforderungen nachtragen.
- Nie Schlüssel, Secrets, Werte aus `msb_settings` oder Kundendaten ins Repository schreiben.
- Live nur über Freigabe-Vorschläge in der Claude MCP Bridge, nach einem netcup-Snapshot. Danach die Live-Dateien per SHA-256 mit dem Branch abgleichen.
