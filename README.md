# Image Sets

> **⚠️ Work in Progress.** Dieses Addon ist noch nicht für den produktiven Einsatz oder den REDAXO-Installer freigegeben. Schnittstellen, Set-Namen und das Speicherformat der Builder-Sets können sich noch ändern. Feedback und Issues sind willkommen, PRs bitte vorher kurz per Issue absprechen.

Responsive Bildausgabe für REDAXO 5: `srcset`/`sizes` und `<picture>` aus **virtuellen Media-Manager-Typen**. Statt für jede Zielbreite einen eigenen Media-Manager-Typ anzulegen, wird ein Bild als `is_<set>__<breite>` angefragt, zum Beispiel `/media/is_ratio_4_3__800/bild.jpg`. Der Browser lädt so immer die Variante, die zur tatsächlichen Darstellungsbreite und Pixeldichte passt. Zusammen mit **media_negotiator** kommt sie als AVIF oder WebP.

Backend: **Media Manager › Bild-Sets** mit den Seiten *Übersicht*, *Sets & Builder*, *Demo & Prüfung* und *Hilfe*.

## Unterschied zu media_srcset

[media_srcset](https://github.com/FriendsOfREDAXO/media_srcset) löst dasselbe Grundproblem (`srcset`/`sizes` ohne für jede Breite einen eigenen Media-Manager-Typ) mit einem anderen Ansatz: dort bleibt **jedes visuelle Anwendungsfeld ein eigener, echter Media-Manager-Typ** in der Datenbank (`hero`, `hero_desktop`, `hero_mobile_portrait`, …) mit seinem eigenen Zuschnitt/Ratio/Effekt-Setup; der `srcset`-Effekt erzeugt darunter nur automatisch die **Breitenstufen** als virtuelle Unterprofile (`hero__400`, `hero__700`, …). Für Art Direction (unterschiedliche Bildausschnitte je Breakpoint) braucht man dort also weiterhin mehrere echte, im Backend angelegte Profile – eines pro Ausschnitt.

`image_sets` geht einen Schritt weiter: **es gibt in der gesamten Installation nur einen einzigen Media-Manager-Typ** (`image_sets`, wird nie direkt verwendet). Seitenverhältnis, Zuschnittsmodus und die erlaubten Breitenstufen stecken stattdessen in einem **Set** – einer reinen Konfigurationseinheit (PHP-Array im Code oder JSON in der Addon-Konfiguration), die sich projekt- oder sogar modulbezogen definieren lässt, ganz ohne einen neuen Eintrag im Media-Manager-Backend anzulegen. Ein neues Bildformat für ein einzelnes Modul zu brauchen heißt bei `image_sets`: ein Set registrieren (eine Codezeile oder ein Formular im Backend-Builder) – nicht: einen neuen Media-Manager-Typ anlegen, konfigurieren und pflegen. Neue Breitenstufen für ein bestehendes Set entstehen sogar **dynamisch zur Laufzeit**, sobald sie das erste Mal angefragt werden (mit automatischer Rundung auf sinnvolle Stufen, um die Zahl der Cache-Varianten zu begrenzen) – auch das erfordert keine Backend-Konfiguration im Voraus.

Kurz: `media_srcset` automatisiert die Breitenstufen unterhalb bestehender Media-Manager-Typen; `image_sets` ersetzt die Media-Manager-Typen selbst durch eine projektbezogene Konfigurationsebene und braucht dafür nur einen einzigen technischen Typ in der ganzen Installation.

---

## Für Redakteurinnen und Redakteure

Im Alltag ist nichts zu konfigurieren. Drei Dinge helfen, damit Bilder überall gut aussehen:

1. **Originale hochladen, nicht vorschneiden.** Fotos im Querformat mit 1600 bis 2500 px Breite in den Medienpool laden. Alle Zuschnitte und alle Größen entstehen automatisch.
2. **Fokuspunkt setzen.** Im Medienpool beim Bild den Fokuspunkt auf das Motiv legen. Jeder Zuschnitt orientiert sich daran, so wird nie das Wichtige abgeschnitten (Addon *focuspoint*).
3. **Alt-Text pflegen.** Die Bildbeschreibung kommt aus dem Alt-Text-Feld im Medienpool (MediaPlace oder das Metainfo-Feld `med_alt`). Der Titel wird nicht als Alt-Text verwendet. Bilder ohne Alt-Text oder mit der Markierung „dekorativ“ werden für Screenreader ausgeblendet.

Zeichnungen, Grundrisse, Logos und Grafiken werden nicht beschnitten, wenn Module das Set `ratio_original` verwenden. SVG und GIF liefert das Addon immer unverändert aus.

Sieht ein Bild nach dem Ersetzen einer Datei mit gleichem Namen „alt“ aus: Backend › Media Manager › Cache löschen, oder auf der Seite *Sets & Builder* den Cache des betroffenen Sets leeren.

---

## Funktionsweise

1. **Sets** (Presets) definieren Seitenverhältnis, Modus und die erlaubten Breitenstufen, z. B. `ratio_4_3` mit `400, 800, 1200, 1600, 2000`.
2. Ein Bild wird als `is_<set>__<breite>` angefragt. Der Extension Point `MEDIA_MANAGER_FILTERSET` setzt dafür dynamisch den Effekt `image_sets` ein. In der Datenbank existiert nur der Basistyp `image_sets` (nicht direkt verwenden).
3. Der Effekt schneidet **zuerst** auf das Seitenverhältnis (Fokuspunkt aus dem Medienpool, sonst zentriert) und skaliert **danach** breitenbegrenzt auf die Stufe. Vergrößert wird nie.
4. `ResponsiveImage` baut daraus `src`, `srcset` und `sizes` und garantiert, dass jeder Descriptor der tatsächlichen Dateibreite entspricht (siehe *Descriptor-Garantie*).

Angefragte Breiten werden auf die nächste Stufe des Sets **aufgerundet**: `is_ratio_4_3__900` liefert die 1200er-Stufe. So bleibt die Zahl der Cache-Varianten begrenzt.

## Sets

Es gibt zwei Quellen für Sets, beide landen in derselben Registry:

| Quelle | Wo | Wer |
|---|---|---|
| **Code** | `boot.php` des Addons (Grundausstattung) oder ein eigenes Addon per `MediaTypeRegistry::registerPreset()` bzw. Extension Point `IMAGE_SETS_PRESETS` | Entwickler |
| **Builder** | Backend › Media Manager › Bild-Sets › *Sets & Builder*, gespeichert in der Addon-Konfiguration, beim Start automatisch registriert | Redaktion und Entwickler |

Grundausstattung:

| Set | Ratio | Modus | Breiten | Standard |
|---|---|---|---|---|
| `ratio_16_9` | 16:9 | focuspoint | 400, 800, 1200, 1600, 2000 | 1200 |
| `ratio_21_9` | 21:9 | focuspoint | 400, 800, 1200, 1600, 2000 | 1200 |
| `ratio_4_3` | 4:3 | focuspoint | 400, 800, 1200, 1600, 2000 | 1200 |
| `ratio_1_1` | 1:1 | focuspoint | 400, 800, 1200, 1600 | 1200 |
| `ratio_original` | Quelle | resize | 400 … 2400 | 1600 |

Code-Sets sind im Backend schreibgeschützt; Builder-Sets dürfen deren Namen nicht verwenden. Eigene Sets im Code:

```php
use FriendsOfRedaxo\ImageSets\Config\MediaTypeRegistry;

MediaTypeRegistry::registerPreset('teaser_3_2', [
    'ratio' => '3_2',            // Breite_Höhe oder 'original'
    'mode' => 'focuspoint',      // focuspoint | resize
    'widths' => [400, 800, 1200, 1600],
    'default_width' => 1200,
]);
```

## Sets & Builder (Backend)

Die Seite besteht aus vier Bereichen:

1. **Registrierte Sets** – alle Code- und Builder-Sets mit Ratio, Modus, Stufen, Status und Cache-Größe. Builder-Sets lassen sich bearbeiten, aktivieren/deaktivieren und löschen; für jedes Set kann der Cache geleert werden. Ein Set, das im Code verwendet wird, lässt sich nur deaktivieren, nicht löschen.
2. **Set anlegen / bearbeiten** – Name (wird Teil des Typ-Namens `is_<name>__<breite>`), Seitenverhältnis (Auswahl oder frei als `Breite_Höhe`), Modus, Breitenstufen, Standardbreite, Notiz, aktiv. Daneben der **Assistent**: Container-Breite, Spalten je Bildschirmklasse, Bildanteil der Spalte, Retina, Breakpoints und größte sinnvolle Breite eingeben, der Assistent berechnet die tatsächlichen Bildbreiten je Bildschirmklasse (dieselbe Rechnung wie das `sizes`-Attribut von `ResponsiveImage`), rundet auf 100 px, ergänzt 2×-Stufen, kappt an der größten sinnvollen Breite und entfernt Stufen mit weniger als 20 % Abstand. „In das Formular übernehmen“ füllt Stufen und Standardbreite.
3. **Nutzung & Bestand** – welche Sets und Breiten Module und Templates tatsächlich anfordern (`withDesktopPreset`, `withWidths`, direkte `is_*__*`-Typen) und wie breit die Originalbilder im Medienpool sind. Daraus entstehen Hinweise: nicht angeforderte Stufen, angeforderte Breiten ohne passende Stufe, Stufen, die überwiegend Hochskalierungen erzeugen würden, im Code verwendete Sets ohne Registrierung.
4. **Vorschau & Test** – Bild, Layout-Parameter und optional eine zweite Quelle für kleine Bildschirme wählen, dann: Maße und Alt-Status des Tags, erzeugtes Markup, alle Varianten mit tatsächlicher Pixelbreite, Dateigröße und Descriptor-Check, eine Simulation, welche Stufe ein Browser bei typischen Bildschirmbreiten und Pixeldichten lädt (mit Schieberegler für beliebige Breiten), sowie die Anzeige, welche Datei der eigene Browser gerade geladen hat. Auch inaktive Builder-Sets lassen sich hier testen.

Aktive Builder-Sets werden beim Boot nach den Code-Sets registriert. Ungültige Einträge werden übersprungen und geloggt, das Frontend läuft mit den Code-Sets weiter.

## Verwendung im Code

Einfache Variante mit einer Breite:

```php
echo '<img src="' . rex_media_manager::getUrl('is_ratio_16_9__1200', $file) . '" alt="…">';
```

Responsive Ausgabe mit `ResponsiveImage`:

```php
use FriendsOfRedaxo\ImageSets\Media\ResponsiveImage;

echo ResponsiveImage::forFile($file)
    ->withDesktopPreset('ratio_4_3')      // Set für srcset
    ->withMobilePreset('ratio_1_1')       // optional: eigenes Ratio unter dem Mobile-Breakpoint (<picture>)
    ->withWidths([400, 800, 1200, 1600])  // gewünschte Stufen (werden auf Set-Stufen gerundet)
    ->withContainerWidth('uk-container')  // uk-container(-xsmall|-small|-large|-xlarge) oder 'expand'
    ->withColumns(3, 2, 1)                // Spalten Desktop / Tablet / Mobil
    ->withMediaFraction(0.5)              // Anteil des Bildes an der Spalte (z. B. Bild links, Text rechts)
    ->withBreakpoints(960, 1200)          // Breakpoints des eigenen Layouts (Standard 640/1200)
    ->withSizes('(min-width: 960px) 50vw, 100vw') // optional: sizes komplett selbst setzen
    ->toImageTag(['alt' => $alt, 'class' => 'uk-width-1-1', 'loading' => 'lazy']);
```

Weitere Ausgaben: `toImage()` (Array `src`/`srcset`/`sizes`/`width`/`height`/`alt`/`decorative` für eigenes Markup), `toPicture()` / `toPictureTag()`, `getSrcsetEntries()` (Paare aus Typ-Breite und Descriptor), `getEffectiveWidths()`, `getSourceMaxWidth()`, `getDimensions()`, `withCapToSource(false)`.

### Attribute des `<img>`

`toImageTag()` setzt automatisch:

- **`width` und `height`** der `src`-Variante (bei Ratio-Sets rechnerisch, bei `original`/`resize` aus den Quellmaßen, bei Kappung an der Quelle die tatsächliche Breite), damit der Browser den Platz vor dem Laden reserviert. Eigene Werte im Attribut-Array haben Vorrang.
- **`alt`** nach der Regel: übergeben (`['alt' => …]` oder `withAlt()`) > MediaPlace-Alt-Feld (inkl. Sprachvariante) > klassisches `med_alt` > leer. Der Medienpool-Titel ist kein Alt-Text und wird nie verwendet. Ohne Alt-Text oder bei ausdrücklicher Markierung als dekorativ (MediaPlace „decorative“, `med_alt_decorative` oder `asDecorative()`) wird `alt="" role="presentation"` ausgegeben.
- **`loading="lazy" decoding="async"`**; `withPriority()` liefert stattdessen `loading="eager" fetchpriority="high"` für das größte Bild im sichtbaren Bereich (LCP).

Die Alt-Regel steht auch eigenem Code zur Verfügung: `FriendsOfRedaxo\ImageSets\Media\AltText::resolve($media, $clangId)` liefert `['alt' => …, 'decorative' => bool]` für eine `rex_media`-Instanz.

### Feste Größen mit Dichte-Descriptoren

Für Logos, Avatare oder Icons, die immer gleich breit dargestellt werden, ersetzt `withDensities()` das `sizes`-Attribut durch `1x/2x/3x`-Descriptoren. Basis ist die kleinste Stufe aus `withWidths()`; das Set sollte dafür passende kleine Stufen enthalten (im Builder anlegen, z. B. 200, 400, 600):

```php
echo ResponsiveImage::forFile($logo)
    ->withDesktopPreset('logo_1_1')
    ->withWidths([200])
    ->withDensities([1, 2, 3])
    ->toImageTag(['alt' => 'Firmenlogo']);
```

Wird die Quelle vorher erreicht, sinkt der Descriptor entsprechend (z. B. `1.5x`), damit er der gelieferten Datei entspricht.

Die Container-Kennungen (`uk-container` …) sind nur Schätzwerte für die maximale Innenbreite (640, 900, 1200, 1400, 1600, 1920 px) und funktionieren mit jedem CSS-Framework; alternativ `withSizes()` verwenden.

### Art Direction

1. **Bildausschnitt je Bild (Fokuspunkt):** Jeder Ratio-Zuschnitt wird um den im Medienpool gesetzten Fokuspunkt gelegt. Das ist die wichtigste Stellschraube für Redaktionen, ohne Code, pro Bild.
2. **Anderes Seitenverhältnis je Bildschirmbreite (`<picture>`):** `withSource(media, set, optionen)` fügt beliebig viele `<source>`-Elemente mit eigener Media Query hinzu (Reihenfolge der Aufrufe = Reihenfolge im Markup, der Browser nimmt die erste passende). Optional eigene `widths` und `sizes` je Quelle. `withMobilePreset()` bleibt als Kurzform für eine Quelle unterhalb des Mobile-Breakpoints (Standard 639 px, änderbar mit `withMobileBreakpoint()`):

```php
echo ResponsiveImage::forFile($file)
    ->withDesktopPreset('ratio_21_9')
    ->withSource('(max-width: 639px)', 'ratio_1_1', ['widths' => [400, 800], 'sizes' => '100vw'])
    ->withSource('(max-width: 1199px)', 'ratio_4_3')
    ->toPictureTag(['loading' => 'lazy']);
```

Kurzform mit einer Mobil-Quelle, z. B. quadratisch auf dem Handy und 16:9 auf dem Desktop:

```php
echo ResponsiveImage::forFile($file)
    ->withDesktopPreset('ratio_16_9')
    ->withMobilePreset('ratio_1_1')
    ->withMobileBreakpoint(639)
    ->withWidths([400, 800, 1200, 1600])
    ->toPictureTag(['alt' => $alt, 'loading' => 'lazy']);
```

Ergebnis (gekürzt):

```html
<picture>
  <source media="(max-width: 639px)" srcset="…/is_ratio_1_1__400/bild.jpg 400w, …/is_ratio_1_1__800/bild.jpg 800w" sizes="100vw">
  <img src="…/is_ratio_16_9__1200/bild.jpg" srcset="…/is_ratio_16_9__800/bild.jpg 800w, …" sizes="…" alt="…">
</picture>
```

Beide Zuschnitte folgen demselben Fokuspunkt. Nicht vorgesehen ist ein komplett anderes Bild je Breakpoint; dafür zwei Medienfelder anlegen und zwei `<source>`-Elemente selbst ausgeben.

### Was `sizes` berechnet

`(min-width: <desktop>px) <Containerbreite / Spalten × Anteil>px, (min-width: <tablet>px) <100 / Tablet-Spalten × Anteil>vw, <100 / Mobil-Spalten × Anteil>vw`

Die Breakpoints müssen zum CSS des Moduls passen; Module mit eigener Media Query geben sie mit `withBreakpoints()` an.

### Descriptor-Garantie

Ein `srcset`-Descriptor (`800w`) muss der Pixelbreite der Datei entsprechen, sonst wählt der Browser falsch. `ResponsiveImage` sorgt dafür durch:

- Runden der gewünschten Breiten auf Set-Stufen (nur diese Dateien existieren).
- Kappen an der Quelle: Stufen oberhalb der maximal erreichbaren Breite (Quellbreite bzw. bei Ratio-Zuschnitten `min(Breite, Höhe × Ratio)`) entfallen; als größte Variante wird die Quellbreite mit korrektem Descriptor angehängt.
- Die Effekt-Reihenfolge Zuschnitt → Skalierung, damit auch Hochformat-Quellen die volle Zielbreite erreichen.

## Demo & Prüfung

**Media Manager › Bild-Sets › Demo & Prüfung**: Bild und Layout-Parameter wählen, erzeugtes Markup ansehen, serverseitiger Descriptor-Check (jede Variante wird erzeugt und gemessen) und Live-Anzeige, welche Variante der Browser bei aktueller Fensterbreite und Pixeldichte lädt. Die Seite *Sets & Builder* enthält dieselbe Prüfung mit zusätzlicher Simulation für beliebige Bildschirmbreiten.

## Zusammenspiel mit anderen Addons

- **focuspoint**: Ratio-Zuschnitte folgen dem im Medienpool gesetzten Fokuspunkt (`med_focuspoint`); ohne Addon wird zentriert geschnitten.
- **media_negotiator**: liefert WebP/AVIF nach `Accept`-Header (GD oder Imagick); der Cache-Pfad wird pro Format getrennt (`MediaNegotiatorBridge`). Ohne das Addon werden JPG/PNG ausgeliefert.
- SVG und GIF werden unverändert ausgeliefert; Module sollten für diese Formate direkt `rex_url::media()` nutzen.

## Cache und Fehlersuche

- Nach Änderungen an Sets oder am Effekt: Backend › Media Manager › Cache löschen oder auf *Sets & Builder* den Cache des Sets leeren, damit bestehende `is_*`-Varianten neu erzeugt werden.
- Ein Typ wie `is_ratio_4_3__700` liefert die nächsthöhere Stufe (800); unbekannte Sets fallen auf das Original zurück.
- Sehr große Originale (über 4000 px) brauchen beim ersten Aufruf spürbar Rechenzeit; danach kommt alles aus dem Cache.
- Bricht die Erzeugung ab (leeres Bild), fehlt meist Speicher (`memory_limit`) oder das Original ist beschädigt: im Medienpool neu hochladen.

## API-Dokumentation

Vollständige Referenz aller öffentlichen Klassen und Methoden mit exakten Signaturen: [API.md](API.md).

## Lizenz

MIT
