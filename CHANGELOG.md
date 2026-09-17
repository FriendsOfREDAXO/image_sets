# Changelog

## 0.1.0 (2026-09-17)

Erste öffentliche Veröffentlichung als eigenständiges Repository (work in progress). Entspricht dem Funktionsstand der vorherigen internen Version 1.2.0; die Versionsnummer wurde für den Neustart als öffentliches Addon zurückgesetzt.

## 1.2.0 (2026-09-16)

- Sets & Builder: Backend-Seite zum Anlegen von Sets mit Assistent (Stufen aus Container, Spalten, Bildanteil, Retina), Nutzungs- und Bestandsanalyse, Cache je Set, interaktive Vorschau mit Descriptor-Check und Simulation der Browser-Auswahl.
- Builder-Sets werden in der Konfiguration gespeichert und beim Start registriert; Code-Sets bleiben schreibgeschützt.
- `toImageTag()` setzt `width`/`height`, `decoding="async"` und ermittelt `alt` aus MediaPlace bzw. `med_alt`; ohne Alt-Text oder bei Markierung als dekorativ `alt="" role="presentation"`. Der Medienpool-Titel wird nicht mehr als Alt-Text verwendet.
- Neu: `withAlt()`, `asDecorative()`, `withPriority()`, `withSource()` (mehrere `<source>` mit freien Media Queries), `withDensities()` (1x/2x/3x), `getDimensions()`; `toImage()` liefert zusätzlich `width`, `height`, `alt`, `decorative`.
- Sprachdateien DE/EN vollständig, README DE/EN.

## 1.1.0

- Virtuelle Media-Manager-Typen `is_<set>__<breite>`, `ResponsiveImage`-API, Art Direction per `withMobilePreset()`, Demo & Prüfung.
