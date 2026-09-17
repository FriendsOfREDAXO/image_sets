<?php

namespace FriendsOfRedaxo\ImageSets\Config;

use rex_extension;
use rex_extension_point;

/**
 * Verwaltet Presets fuer virtuelle Media-Manager-Typen (Schema is_<preset>__<width>).
 * Erlaubt beliebige Zielbreiten pro Preset ohne dass fuer jede Breite ein eigener
 * Media-Manager-Typ in der Datenbank angelegt werden muss.
 */
class MediaTypeRegistry
{
    /** @var array<string, array{ratio: string, mode?: string, widths?: list<int>, default_width?: int}> */
    private static array $runtimePresets = [];

    /**
     * @param array{ratio: string, mode?: string, widths?: list<int>, default_width?: int} $config
     */
    public static function registerPreset(string $name, array $config): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }

        $ratio = trim((string) ($config['ratio'] ?? ''));
        if ($ratio === '') {
            return;
        }

        $mode = trim((string) ($config['mode'] ?? 'focuspoint'));
        if (!in_array($mode, ['focuspoint', 'resize'], true)) {
            $mode = 'focuspoint';
        }

        $widthsRaw = $config['widths'] ?? [];
        $widths = [];
        if (is_array($widthsRaw)) {
            foreach ($widthsRaw as $width) {
                $widths[] = max(1, (int) $width);
            }
        }

        if ($widths === []) {
            $widths = [max(1, (int) ($config['default_width'] ?? 1200))];
        }

        $widths = array_values(array_unique($widths));
        sort($widths);

        $defaultWidth = max(1, (int) ($config['default_width'] ?? ($widths[0] ?? 1200)));
        if (!in_array($defaultWidth, $widths, true)) {
            $defaultWidth = self::normalizeWidth(['widths' => $widths], $defaultWidth);
        }

        self::$runtimePresets[$name] = [
            'ratio' => $ratio,
            'mode' => $mode,
            'widths' => $widths,
            'default_width' => $defaultWidth,
        ];
    }

    /**
     * @param array<string, array{ratio: string, mode?: string, widths?: list<int>, default_width?: int}> $presets
     */
    public static function registerPresets(array $presets): void
    {
        foreach ($presets as $name => $config) {
            if (!is_string($name) || !is_array($config)) {
                continue;
            }

            self::registerPreset($name, $config);
        }
    }

    /**
     * @return array<string, array{ratio: string, mode?: string, widths?: list<int>, default_width?: int}>
     */
    public static function getPresets(): array
    {
        $presets = self::$runtimePresets;

        /** @var array<string, array{ratio: string, mode?: string, widths?: list<int>, default_width?: int}> $presets */
        $presets = rex_extension::registerPoint(new rex_extension_point(
            'IMAGE_SETS_PRESETS',
            $presets
        ));

        return $presets;
    }

    public static function buildVirtualType(string $preset, int $width): string
    {
        return 'is_' . $preset . '__' . $width;
    }

    /**
     * @return array{preset: string, width: int}|null
     */
    public static function parseVirtualType(string $mediaType): ?array
    {
        if (!str_starts_with($mediaType, 'is_')) {
            return null;
        }

        $raw = substr($mediaType, 3);
        if ($raw === '') {
            return null;
        }

        $parts = explode('__', $raw);
        if ($parts === []) {
            return null;
        }

        $widthRaw = array_pop($parts);
        $preset = implode('__', $parts);
        if ($preset === '' || $widthRaw === null || preg_match('/^[0-9]+$/', $widthRaw) !== 1) {
            return null;
        }

        return [
            'preset' => $preset,
            'width' => (int) $widthRaw,
        ];
    }

    /**
     * @param array{widths?: list<int>} $presetConfig
     */
    public static function normalizeWidth(array $presetConfig, int $requestedWidth): int
    {
        $widths = $presetConfig['widths'] ?? [];
        if ($widths === []) {
            return max(1, $requestedWidth);
        }

        sort($widths);
        foreach ($widths as $width) {
            if ($requestedWidth <= $width) {
                return (int) $width;
            }
        }

        return (int) end($widths);
    }
}
