<?php

/**
 * Image Sets
 * Virtuelle Media-Manager-Typen (is_<preset>__<width>) fuer responsive
 * srcset/picture-Bildausgabe, ohne dass fuer jede Zielbreite ein eigener
 * Media-Manager-Typ angelegt werden muss.
 */

use FriendsOfRedaxo\ImageSets\Config\MediaTypeRegistry;

if (rex_addon::get('media_manager')->isAvailable()) {
    rex_media_manager::addEffect(rex_effect_image_sets::class);

    rex_extension::register('MEDIA_MANAGER_FILTERSET', static function (rex_extension_point $ep): array {
        return \FriendsOfRedaxo\ImageSets\MediaManagerFilterset::apply($ep);
    }, rex_extension::EARLY);

    rex_extension::register('MEDIA_MANAGER_INIT', static function (rex_extension_point $ep): void {
        \FriendsOfRedaxo\ImageSets\MediaNegotiatorBridge::adjustCachePath($ep);
    }, rex_extension::EARLY);
}

// Code-Presets (Grundausstattung) und aktive Builder-Presets aus der
// Konfiguration (Backend: Media Manager > Bild-Sets > Sets & Builder)
\FriendsOfRedaxo\ImageSets\Config\PresetStore::registerBuiltin();
\FriendsOfRedaxo\ImageSets\Config\PresetStore::registerActive();
