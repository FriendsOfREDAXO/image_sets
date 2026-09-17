<?php

use FriendsOfRedaxo\ImageSets\Config\MediaTypeRegistry;

$presets = MediaTypeRegistry::getPresets();

$content = '';
$content .= '<p>' . rex_i18n::msg('image_sets_intro') . '</p>';

$content .= '<table class="table table-striped">';
$content .= '<thead><tr>';
$content .= '<th>' . rex_i18n::msg('image_sets_col_name') . '</th><th>' . rex_i18n::msg('image_sets_col_ratio') . '</th><th>' . rex_i18n::msg('image_sets_col_mode') . '</th><th>' . rex_i18n::msg('image_sets_col_widths') . '</th><th>' . rex_i18n::msg('image_sets_col_default') . '</th><th>' . rex_i18n::msg('image_sets_col_source') . '</th><th>Media-Manager-Typ</th>';
$content .= '</tr></thead><tbody>';

foreach ($presets as $name => $config) {
    $widths = implode(', ', $config['widths'] ?? []);
    $exampleWidth = $config['default_width'] ?? ($config['widths'][0] ?? 1200);
    $exampleType = MediaTypeRegistry::buildVirtualType((string) $name, (int) $exampleWidth);

    $content .= '<tr>';
    $content .= '<td><code>' . rex_escape((string) $name) . '</code></td>';
    $content .= '<td>' . rex_escape((string) ($config['ratio'] ?? '-')) . '</td>';
    $content .= '<td>' . rex_escape((string) ($config['mode'] ?? '-')) . '</td>';
    $content .= '<td>' . rex_escape($widths) . '</td>';
    $content .= '<td>' . rex_escape((string) $exampleWidth) . 'px</td>';
    $content .= '<td>' . (\FriendsOfRedaxo\ImageSets\Config\PresetStore::isBuiltin((string) $name) ? '<span class="label label-default">' . rex_i18n::msg('image_sets_source_code') . '</span>' : '<a class="label label-primary" href="' . rex_url::backendPage('media_manager/image_sets/sets', ['edit' => (string) $name]) . '">' . rex_i18n::msg('image_sets_source_builder') . '</a>') . '</td>';
    $content .= '<td><code>' . rex_escape($exampleType) . '</code></td>';
    $content .= '</tr>';
}

$content .= '</tbody></table>';

$content .= '<h3>Nutzung im Code</h3>';
$content .= '<pre>' . rex_escape(
    "use FriendsOfRedaxo\\ImageSets\\Media\\ResponsiveImage;\n\n" .
    "echo ResponsiveImage::forFile(\$mediaFile)\n" .
    "    ->withDesktopPreset('ratio_4_3')\n" .
    "    ->withWidths([400, 800, 1200, 1600])\n" .
    "    ->withColumns(3, 2, 1)\n" .
    "    ->toImageTag(['alt' => \$altText, 'class' => 'uk-width-1-1']);"
) . '</pre>';

$content .= '<h3>Eigene Presets registrieren</h3>';
$content .= '<pre>' . rex_escape(
    "use FriendsOfRedaxo\\ImageSets\\Config\\MediaTypeRegistry;\n\n" .
    "MediaTypeRegistry::registerPreset('ratio_16_9', [\n" .
    "    'ratio' => '16_9',\n" .
    "    'mode' => 'focuspoint',\n" .
    "    'widths' => [400, 800, 1200, 1600],\n" .
    "    'default_width' => 1200,\n" .
    "]);"
) . '</pre>';

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('image_sets_title'), false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
