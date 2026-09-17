<?php

/**
 * Legt den zentralen Media-Manager-Basistyp fuer is_* Ableitungen an.
 * Die tatsaechliche Effekt-Konfiguration pro Preset/Breite lauft dynamisch
 * ueber MEDIA_MANAGER_FILTERSET (siehe boot.php), nicht ueber diesen Typ direkt.
 */

if (rex_addon::get('media_manager')->isAvailable()) {
    rex_media_manager::addEffect(rex_effect_image_sets::class);

    $sql = rex_sql::factory();
    $sql->setQuery('SELECT id FROM ' . rex::getTable('media_manager_type') . ' WHERE name = :name', ['name' => 'image_sets']);

    if (!$sql->getRows()) {
        $sql->setTable(rex::getTable('media_manager_type'));
        $sql->setValue('name', 'image_sets');
        $sql->setValue('description', 'Image Sets: zentraler Medientyp fuer is_* Ableitungen (nicht direkt verwenden)');
        $sql->addGlobalCreateFields();
        $sql->insert();
        $typeId = $sql->getLastId();

        $sql->setTable(rex::getTable('media_manager_type_effect'));
        $sql->setValue('type_id', $typeId);
        $sql->setValue('effect', 'image_sets');
        $sql->setValue('priority', 1);
        $sql->setValue('parameters', json_encode([
            'rex_effect_image_sets' => [
                'preset' => 'gallery_4_3',
                'ratio' => '4_3',
                'mode' => 'focuspoint',
                'width' => 1200,
                'allow_enlarge' => 'not_enlarge',
            ],
        ]));
        $sql->addGlobalCreateFields();
        $sql->insert();
    }

    rex_media_manager::deleteCache();
}

$this->setProperty('install', true);
