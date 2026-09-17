# Image Sets

> **⚠️ Work in progress.** This addon is not yet ready for production use or the REDAXO installer. Interfaces, set names and the storage format of builder sets may still change. Feedback and issues are welcome; please open an issue before sending a PR.

Responsive image output for REDAXO 5: `srcset`/`sizes` and `<picture>` built from **virtual media manager types**. Instead of creating a media manager type for every target width, an image is requested as `is_<set>__<width>`, e.g. `/media/is_ratio_4_3__800/image.jpg`. The browser always loads the variant that matches the rendered width and pixel density; together with **media_negotiator** it arrives as AVIF or WebP.

Backend: **Media Manager › Image Sets** with the pages *Overview*, *Sets & builder*, *Demo & check* and *Help*.

## How this differs from media_srcset

[media_srcset](https://github.com/FriendsOfREDAXO/media_srcset) solves the same underlying problem (`srcset`/`sizes` without a media manager type per width) with a different approach: there, **every visual use case stays its own real media manager type** in the database (`hero`, `hero_desktop`, `hero_mobile_portrait`, …) with its own crop/ratio/effect setup; the `srcset` effect only auto-generates the **width steps** underneath as virtual sub-types (`hero__400`, `hero__700`, …). Art direction (a different crop per breakpoint) therefore still needs several real, backend-created profiles — one per crop.

`image_sets` goes a step further: **the whole installation only ever has a single media manager type** (`image_sets`, never used directly). Aspect ratio, crop mode and the allowed width steps live in a **set** instead — a pure configuration unit (a PHP array in code, or JSON in the addon config) that can be defined per project or even per module, without ever creating a new entry in the media manager backend. Needing a new image shape for a single module means, with `image_sets`: register a set (one line of code, or a backend form) — not: create, configure and maintain a new media manager type. New width steps for an existing set are even generated **dynamically at request time** the first time they're needed (rounded to sensible steps to keep the number of cache variants bounded) — no backend configuration required up front either.

In short: `media_srcset` automates the width steps below existing media manager types; `image_sets` replaces the media manager types themselves with a project-level configuration layer, needing only a single technical type for the whole installation.

## How it works

1. **Sets** (presets) define aspect ratio, mode and the allowed width steps, e.g. `ratio_4_3` with `400, 800, 1200, 1600, 2000`.
2. An image is requested as `is_<set>__<width>`. The extension point `MEDIA_MANAGER_FILTERSET` injects the `image_sets` effect on the fly; only the base type `image_sets` exists in the database (do not use it directly).
3. The effect crops **first** to the aspect ratio (focus point from the media pool, otherwise centred) and **then** scales down to the step. It never enlarges.
4. `ResponsiveImage` builds `src`, `srcset` and `sizes` from that and guarantees that every descriptor matches the real file width.

Requested widths are **rounded up** to the next step of the set (`is_ratio_4_3__900` delivers the 1200 step), which keeps the number of cache variants small.

## Sets

| Source | Where | Who |
|---|---|---|
| **Code** | the addon's `boot.php` (defaults) or your own addon via `MediaTypeRegistry::registerPreset()` or the `IMAGE_SETS_PRESETS` extension point | developers |
| **Builder** | Backend › Media Manager › Image Sets › *Sets & builder*, stored in the addon config and registered automatically at startup | editors and developers |

Defaults: `ratio_16_9`, `ratio_21_9`, `ratio_4_3`, `ratio_1_1` (focus point crop, 400–2000 px) and `ratio_original` (resize only, 400–2400 px). Code sets are read-only in the backend; builder sets must not reuse their names.

```php
use FriendsOfRedaxo\ImageSets\Config\MediaTypeRegistry;

MediaTypeRegistry::registerPreset('teaser_3_2', [
    'ratio' => '3_2',            // width_height or 'original'
    'mode' => 'focuspoint',      // focuspoint | resize
    'widths' => [400, 800, 1200, 1600],
    'default_width' => 1200,
]);
```

## Sets & builder (backend)

1. **Registered sets** – all code and builder sets with ratio, mode, steps, status and cache size. Builder sets can be edited, activated/deactivated and deleted; the cache of every set can be cleared. A set that is referenced in code can only be deactivated.
2. **Create / edit set** – name (becomes part of the type name `is_<name>__<width>`), aspect ratio (list or free `width_height`), mode, width steps, default width, note, active. Next to it the **assistant**: enter container width, columns per screen class, image share of the column, retina, breakpoints and the largest useful width; it calculates the real image widths per screen class (the same maths as the `sizes` attribute of `ResponsiveImage`), rounds to 100 px, adds 2× steps, caps at the largest useful width and drops steps that are less than 20 % apart. "Apply to the form" fills steps and default width.
3. **Usage & inventory** – which sets and widths modules and templates actually request, and how wide the original images in the media pool are. Resulting hints: unused steps, requested widths without a matching step, steps that would mostly produce upscales, sets used in code but not registered.
4. **Preview & test** – choose an image, layout parameters and optionally a second source for small screens: dimensions and alt state of the tag, generated markup, all variants with real pixel width, file size and descriptor check, a simulation of which step a browser loads at typical screen widths and pixel densities (with a slider for any width), plus what your own browser has just loaded. Inactive builder sets can be tested here as well.

Active builder sets are registered after the code sets at boot. Invalid entries are skipped and logged; the frontend keeps running with the code sets.

## Usage in code

```php
use FriendsOfRedaxo\ImageSets\Media\ResponsiveImage;

echo ResponsiveImage::forFile($file)
    ->withDesktopPreset('ratio_4_3')
    ->withMobilePreset('ratio_1_1')        // optional: different ratio below the mobile breakpoint (<picture>)
    ->withWidths([400, 800, 1200, 1600])   // rounded to the steps of the set
    ->withContainerWidth('uk-container')   // uk-container(-xsmall|-small|-large|-xlarge) or 'expand'
    ->withColumns(3, 2, 1)                 // columns desktop / tablet / mobile
    ->withMediaFraction(0.5)               // share of the column taken by the image
    ->withBreakpoints(960, 1200)           // breakpoints of your layout (default 640/1200)
    ->withSizes('(min-width: 960px) 50vw, 100vw') // optional: set sizes yourself
    ->toImageTag(['alt' => $alt, 'loading' => 'lazy']);
```

Further output: `toImage()` (array with `src`/`srcset`/`sizes`/`width`/`height`/`alt`/`decorative`), `toPicture()` / `toPictureTag()`, `getSrcsetEntries()`, `getEffectiveWidths()`, `getSourceMaxWidth()`, `getDimensions()`, `withCapToSource(false)`.

### Attributes of the `<img>`

`toImageTag()` sets automatically:

- **`width` and `height`** of the `src` variant (calculated for ratio sets, from the source dimensions for `original`/`resize`, the real width when capped at the source) so the browser reserves the space before loading. Values passed in the attribute array take precedence.
- **`alt`** by the rule: passed (`['alt' => …]` or `withAlt()`) > MediaPlace alt field (including language variant) > classic `med_alt` > empty. The media pool title is not an alt text and is never used. Without alt text, or when explicitly marked decorative (MediaPlace "decorative", `med_alt_decorative` or `asDecorative()`), the image gets `alt="" role="presentation"`.
- **`loading="lazy" decoding="async"`**; `withPriority()` produces `loading="eager" fetchpriority="high"` instead, for the largest image above the fold (LCP).

The alt rule is available to your own code as well: `FriendsOfRedaxo\ImageSets\Media\AltText::resolve($media, $clangId)` returns `['alt' => …, 'decorative' => bool]` for a `rex_media` instance.

### Fixed sizes with density descriptors

For logos, avatars or icons that are always rendered at the same width, `withDensities()` replaces `sizes` with `1x/2x/3x` descriptors. The base is the smallest step from `withWidths()`; the set should provide matching small steps (create it in the builder, e.g. 200, 400, 600):

```php
echo ResponsiveImage::forFile($logo)
    ->withDesktopPreset('logo_1_1')
    ->withWidths([200])
    ->withDensities([1, 2, 3])
    ->toImageTag(['alt' => 'Company logo']);
```

If the source is reached earlier, the descriptor is lowered accordingly (e.g. `1.5x`) so it matches the delivered file.

### Art direction with several sources

`withSource(media, set, options)` adds any number of `<source>` elements with their own media query (call order = markup order, the browser takes the first match), optionally with their own `widths` and `sizes`. `withMobilePreset()` remains the short form for one source below the mobile breakpoint (default 639 px, `withMobileBreakpoint()`).

```php
echo ResponsiveImage::forFile($file)
    ->withDesktopPreset('ratio_21_9')
    ->withSource('(max-width: 639px)', 'ratio_1_1', ['widths' => [400, 800], 'sizes' => '100vw'])
    ->withSource('(max-width: 1199px)', 'ratio_4_3')
    ->toPictureTag();
```

### `sizes` from the layout

The container identifiers are only estimates of the maximum inner width (640, 900, 1200, 1400, 1600, 1920 px) and work with any CSS framework; use `withSizes()` otherwise.

`sizes` is calculated as `(min-width: <desktop>px) <container / columns × share>px, (min-width: <tablet>px) <100 / tablet columns × share>vw, <100 / mobile columns × share>vw`.

### Descriptor guarantee

A `srcset` descriptor (`800w`) must match the pixel width of the file. `ResponsiveImage` ensures this by rounding widths to set steps, capping at the source (steps above the reachable width are dropped and the source width is appended with a correct descriptor) and by the effect order crop → resize.

## Related addons

- **focuspoint**: ratio crops follow the focus point stored in the media pool; without it the crop is centred.
- **media_negotiator**: delivers WebP/AVIF by `Accept` header; the cache path is separated per format.
- SVG and GIF are delivered unchanged; use `rex_url::media()` for them.

## Cache and troubleshooting

- After changing sets or the effect: clear the media manager cache or the cache of a single set on *Sets & builder*.
- A type such as `is_ratio_4_3__700` delivers the next larger step (800); unknown sets fall back to the original.
- Very large originals (above 4000 px) take noticeable time on first request; afterwards everything is served from cache.
- If generation fails (empty image), memory (`memory_limit`) is usually missing or the original is broken: re-upload it.

## API documentation

Full reference of all public classes and methods with exact signatures: [API.md](API.md).

## License

MIT
