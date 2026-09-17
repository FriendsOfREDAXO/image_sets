# Öffentliche API

Referenz aller öffentlichen Klassen und Methoden, die außerhalb des Addons aufgerufen werden können. Interne Backend-Hilfsklassen (`Config\PresetStore`, `Config\SetBuilder`) sind am Ende kurz erwähnt, sind aber für die Bild-Ausgabe in Modulen/Templates nicht relevant.

Alle Klassen liegen im Namespace `FriendsOfRedaxo\ImageSets`.

## Inhalt

- [`Media\ResponsiveImage`](#mediaresponsiveimage) – Bild-Ausgabe (Haupt-API)
- [`Media\AltText`](#mediaalttext) – Alt-Text-Ermittlung
- [`Config\MediaTypeRegistry`](#configmediatyperegistry) – Sets registrieren/abfragen
- [`rex_effect_image_sets`](#rex_effect_image_sets) – der Media-Manager-Effekt selbst
- [Extension Point `IMAGE_SETS_PRESETS`](#extension-point-image_sets_presets)
- [Backend-interne Klassen](#backend-interne-klassen)

---

## `Media\ResponsiveImage`

Fluent Builder. Ein Objekt pro Bild, alle `with*()`-Methoden geben `self` zurück und können beliebig verkettet werden. Erzeugt wird das Objekt ausschließlich über `forFile()`.

```php
use FriendsOfRedaxo\ImageSets\Media\ResponsiveImage;
```

### Erzeugen

```php
public static function forFile(string $file): self
```

`$file` ist der Dateiname im Medienpool (wie bei `rex_media::get()`), keine Instanz von `rex_media`.

### Preset / Quellen wählen

| Methode | Signatur | Zweck |
|---|---|---|
| `withDesktopPreset` | `(string $preset): self` | Set für die Standard-`src`/`srcset`. Pflicht für jede Ausgabe. |
| `withMobilePreset` | `(string $preset): self` | Kurzform für eine `<source>` unterhalb des Mobile-Breakpoints (`<picture>`). Optional, nur bei `toPicture()`/`toPictureTag()` wirksam. |
| `withSource` | `(string $media, string $preset, array $options = []): self` | Beliebig viele zusätzliche `<source>`-Elemente für Art Direction. `$options`: `{widths?: list<int>, sizes?: string}`. Aufrufreihenfolge = Reihenfolge im Markup. |

### Breiten / Dichten

| Methode | Signatur | Zweck |
|---|---|---|
| `withWidths` | `(array $widths): self` — `list<int>` | Gewünschte Zielbreiten; werden auf die Stufen des Presets gerundet. Ungültige/leere Eingabe wird ignoriert (Default bleibt `[400, 800, 1200, 1600]`). |
| `withDensities` | `(array $densities): self` — `list<float\|int>` | Ersetzt `sizes` durch `1x`/`2x`/`3x`-Descriptoren, für Elemente mit fester Darstellungsbreite (Logo, Icon, Avatar). Basisbreite ist die kleinste Stufe aus `withWidths()`. Werte außerhalb `1–4` werden verworfen. |
| `withCapToSource` | `(bool $cap): self` | Standard `true`: Stufen oberhalb der tatsächlich erreichbaren Quellbreite werden verworfen und durch die Quellbreite mit korrektem Descriptor ersetzt, damit kein `srcset`-Descriptor größer als die gelieferte Datei ist. |

### Alt-Text / Priorität

| Methode | Signatur | Zweck |
|---|---|---|
| `withAlt` | `(string $alt): self` | Setzt den Alt-Text explizit, hat Vorrang vor der automatischen Ermittlung (siehe [`AltText`](#mediaalttext)). Leerer String macht das Bild dekorativ. |
| `asDecorative` | `(bool $decorative = true): self` | Erzwingt `alt="" role="presentation"`, unabhängig vom Medienpool. |
| `withPriority` | `(bool $priority = true): self` | Für das größte Bild im sichtbaren Bereich (LCP): `loading="eager" fetchpriority="high"` statt `loading="lazy" decoding="async"`. |

### `sizes`-Berechnung

| Methode | Signatur | Zweck |
|---|---|---|
| `withContainerWidth` | `(string $containerWidth): self` | Schätzwert der maximalen Container-Innenbreite. Erkannte Schlüsselwörter: `xsmall` → 640px, `small` → 900px, `large` → 1400px, `xlarge` → 1600px, `expand`/leer → 1920px, alles andere → 1200px (z. B. `uk-container`). Funktioniert mit jedem CSS-Framework, da nur der String nach diesen Teilstrings durchsucht wird. |
| `withColumns` | `(int $desktop, int $tablet, int $mobile): self` | Spaltenzahl je Bildschirmklasse (jeweils mind. 1). |
| `withMediaFraction` | `(float $fraction): self` | Anteil der Spaltenbreite, den das Bild einnimmt (`0.05`–`1.0`, wird geklemmt). Für z. B. „Bild nimmt nur die halbe Spalte neben Text ein". |
| `withBreakpoints` | `(int $tablet, int $desktop): self` | Eigene Breakpoints (Standard 640/1200), müssen zum CSS des Moduls passen. |
| `withMobileBreakpoint` | `(int $breakpoint): self` | Schwelle für `withMobilePreset()` (Standard 639px). |
| `withSizes` | `(string $sizes): self` | Überschreibt die automatische Berechnung komplett, z. B. `'(min-width: 960px) 50vw, 100vw'`. |

Berechnungsformel (wenn `withSizes()` nicht gesetzt ist):

```
(min-width: <desktop>px) <Containerbreite / Spalten × Anteil>px,
(min-width: <tablet>px) <100 / Tablet-Spalten × Anteil>vw,
<100 / Mobil-Spalten × Anteil>vw
```

### Ausgabe

| Methode | Rückgabe | Beschreibung |
|---|---|---|
| `toImage()` | `array{src: string, srcset: string, sizes: string, width: int, height: int, alt: string, decorative: bool}` | Rohdaten für eigenes Markup. Leeres Array-Skelett (leere Strings, `0`) wenn kein Datei-/Preset-Kontext auflösbar ist. |
| `toPicture()` | `array{sources: list<array{media: string, srcset: string, sizes: string}>, img: <wie toImage()>}` | `sources` enthält alle via `withSource()` und ggf. `withMobilePreset()` definierten `<source>`-Kandidaten in Aufrufreihenfolge. |
| `toImageTag(array $attributes = [])` | `string` | Fertiges `<img>`-Tag. `$attributes` (`array<string, scalar\|null>`) überschreiben die automatisch gesetzten Attribute; `null`/`false` unterdrückt ein Attribut. Ein selbst gesetztes, nicht-leeres `alt` hebt den `role="presentation"`-Status wieder auf. |
| `toPictureTag(array $imgAttributes = [], array $pictureAttributes = [])` | `string` | `<picture>` mit allen `<source>`-Elementen plus abschließendem `<img>` (Fallback). Ohne `<source>`-Kandidaten wird nur das `<img>`-Tag zurückgegeben (kein leeres `<picture>`-Wrapper). |

Von `toImageTag()` automatisch gesetzte Attribute (sofern nicht in `$attributes` überschrieben):

- `src`, `srcset` (nur wenn nicht leer), `sizes` (nur wenn nicht leer)
- `width`/`height` — Pixelmaße der `src`-Variante, siehe `getDimensions()`; nur gesetzt wenn beide `> 0`
- `alt` — siehe Auflösungsreihenfolge oben; `role="presentation"` zusätzlich bei dekorativen Bildern
- `loading` — `lazy` (Standard) oder `eager` (`withPriority()`)
- `decoding="async"`, zusätzlich `fetchpriority="high"` bei `withPriority()`

### Berechnungs-/Introspektions-Hilfsmethoden

Nützlich für eigene Markup-Generierung oder Debugging, ohne ein komplettes Tag zu erzeugen:

| Methode | Signatur | Beschreibung |
|---|---|---|
| `getDimensions` | `(string $preset = '', int $width = 0): array{width: int, height: int}` | Pixelmaße der Variante mit Descriptor-Breite `$width` (Standard: automatisch ermittelte `src`-Breite). Bei Ratio-Presets rechnerisch aus dem Seitenverhältnis, bei `mode: resize`/`ratio: original` aus den tatsächlichen Quellmaßen. |
| `getEffectiveWidths` | `(string $preset = ''): list<int>` | Die tatsächlich erreichbaren Descriptor-Breiten für dieses Bild+Preset (nach Rundung auf Preset-Stufen und Kappung an der Quelle). |
| `getSrcsetEntries` | `(string $preset = '', ?array $widths = null): list<array{int,int}>` | Paare `[Typ-Breite, Descriptor-Breite]`. Typ-Breite = auf Preset-Stufen gerundet (das ist die Breite im virtuellen Media-Manager-Typnamen); Descriptor-Breite = tatsächliche Pixelbreite der gelieferten Datei (kann bei der größten Stufe kleiner sein, wenn an der Quelle gekappt wird). |
| `getSourceMaxWidth` | `(string $preset = ''): int` | Maximale Ausgabebreite, die die Originaldatei im Ratio des Presets überhaupt hergibt, ohne zu vergrößern. `0` wenn die Datei nicht ermittelbar ist. |

---

## `Media\AltText`

```php
use FriendsOfRedaxo\ImageSets\Media\AltText;

public static function resolve(?rex_media $media, ?int $clangId = null): array
// Rückgabe: array{alt: string, decorative: bool}
```

Ermittelt den Alt-Text unabhängig von `ResponsiveImage`, z. B. für eigenes `<img>`-Markup außerhalb des Builders. Reihenfolge:

1. **MediaPlace** eigenes Alt-Widget (JSON-Feld `med_json_data`), sprachspezifisch nach `$clangId`, mit `decorative`-Flag — nur wenn das `mediaplace`-Addon verfügbar ist.
2. **Klassisches Metainfo-Feld** `med_alt` (plus `med_alt_decorative`), falls die Spalten existieren.
3. Leer (`decorative: false`), wenn nichts gefunden wird.

Der Medienpool-**Titel** wird nie als Alt-Text verwendet. `$media === null` liefert sofort `['alt' => '', 'decorative' => false]`. Ergebnisse werden pro Datei+Sprache prozessintern gecacht.

---

## `Config\MediaTypeRegistry`

```php
use FriendsOfRedaxo\ImageSets\Config\MediaTypeRegistry;
```

Die Registrierungs- und Nachschlage-Schicht für Sets. Ein **Set** (Preset) ist rein konfigurativ — kein Datenbank-Eintrag, keine Media-Manager-Typ-Anlage nötig.

### Set registrieren

```php
public static function registerPreset(string $name, array $config): void
```

`$config`: `array{ratio: string, mode?: string, widths?: list<int>, default_width?: int}`

- `ratio` (**Pflicht**) — `"Breite_Höhe"` (z. B. `"4_3"`, `"16_9"`) oder `"original"`. Leer oder fehlend → Aufruf wird ignoriert.
- `mode` — `"focuspoint"` (Standard; Zuschnitt folgt dem Fokuspunkt aus dem Medienpool) oder `"resize"` (keine Beschneidung, nur Skalierung). Ungültige Werte fallen auf `"focuspoint"` zurück.
- `widths` — erlaubte Breitenstufen in Pixeln. Leer/fehlend → eine einzelne Stufe aus `default_width` (bzw. `1200`). Wird dedupliziert und sortiert.
- `default_width` — Breite für die einfache `src`-Ausgabe ohne `srcset`. Nicht in `widths` enthalten → wird auf die nächsthöhere Stufe gerundet.

Ein leerer `$name` wird ignoriert (kein Fehler). Aufrufe mit demselben Namen überschreiben eine vorherige Registrierung.

```php
public static function registerPresets(array $presets): void
```

Batch-Variante: `array<string, array{...wie oben...}>`. Nicht-string-Keys oder nicht-array-Werte werden übersprungen.

### Sets abfragen

```php
public static function getPresets(): array
// array<string, array{ratio: string, mode?: string, widths?: list<int>, default_width?: int}>
```

Liefert alle aktuell registrierten Sets (Code- **und** Builder-Sets, da beide in dieselbe Laufzeit-Registrierung schreiben) und filtert sie zusätzlich durch den Extension Point `IMAGE_SETS_PRESETS` (siehe unten).

### Virtuelle Typnamen

```php
public static function buildVirtualType(string $preset, int $width): string
// z.B. buildVirtualType('ratio_4_3', 800) === 'is_ratio_4_3__800'

public static function parseVirtualType(string $mediaType): ?array
// array{preset: string, width: int}|null — null wenn $mediaType nicht dem Schema is_<preset>__<width> entspricht
```

`parseVirtualType()` erlaubt Unterstriche im Preset-Namen selbst (wird von rechts anhand des letzten `__<Zahl>`-Segments geparst).

```php
public static function normalizeWidth(array $presetConfig, int $requestedWidth): int
```

Rundet `$requestedWidth` auf die nächsthöhere in `$presetConfig['widths']` definierte Stufe auf; liegt die Anfrage über der größten Stufe, wird die größte Stufe zurückgegeben. Ohne definierte `widths` wird die angefragte Breite unverändert (min. `1`) durchgereicht.

---

## `rex_effect_image_sets`

Der eigentliche Media-Manager-Effekt (globaler Namespace, REDAXO-Konvention). Wird nicht direkt instanziiert — REDAXO ruft `execute()` über den Media Manager selbst auf. Eine Methode ist auch für eigenen Code nützlich:

```php
public static function resolveRatio(string $ratio): array
// array{int, int} — z.B. resolveRatio('16_9') === [16, 9]; resolveRatio('original') === [0, 0]
```

Akzeptiert `_` oder `:` als Trenner (`"16_9"` und `"16:9"` sind gleichwertig). Ungültige Eingaben liefern `[0, 0]`.

---

## Extension Point `IMAGE_SETS_PRESETS`

```php
rex_extension::register('IMAGE_SETS_PRESETS', function (rex_extension_point $ep) {
    $presets = $ep->getSubject(); // array<string, array{ratio, mode?, widths?, default_width?}>

    $presets['footer_logo'] = [
        'ratio' => 'original',
        'mode' => 'resize',
        'widths' => [150, 300],
    ];

    return $presets;
});
```

Wird bei jedem `MediaTypeRegistry::getPresets()`-Aufruf durchlaufen — Subject ist das Array aller bisher registrierten Sets (Code + Builder). Alternative zu `registerPreset()`, wenn Sets nachträglich modifiziert oder aus einer externen Quelle (z. B. einer YAML-Datei) ergänzt werden sollen, ohne dass der aufrufende Code die Boot-Reihenfolge kennen muss.

---

## Backend-interne Klassen

Nicht für die Bild-Ausgabe in Modulen/Templates gedacht, der Vollständigkeit halber erwähnt:

- **`Config\PresetStore`** — Speichert/lädt Builder-Sets als JSON in der Addon-Konfiguration (`rex_config`), inkl. Cache-Introspektion (`cacheInfo()`, `clearCache()`) für die Backend-Seite *Sets & Builder*.
- **`Config\SetBuilder`** — Berechnungshilfen für den Backend-Assistenten (Breiten-Vorschläge, Aufrundung) sowie `scanUsage()`, das Modul-/Template-Content nach tatsächlich verwendeten Sets/Breiten durchsucht (für die Seite *Nutzung & Bestand*).
- **`MediaManagerFilterset`** — hängt sich in den Extension Point `MEDIA_MANAGER_FILTERSET` und übersetzt einen virtuellen Typnamen zur Laufzeit in die Effekt-Konfiguration, die der Media Manager anwendet. Das ist der Kernmechanismus hinter „nur ein Media-Manager-Typ" — siehe [README](README.md#funktionsweise).
- **`MediaNegotiatorBridge`** — optionale Kopplung an das separate Addon `media_negotiator` für WebP/AVIF-Auslieferung nach `Accept`-Header.
