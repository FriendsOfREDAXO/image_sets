<?php

/**
 * Generischer Media-Manager-Effekt fuer virtuelle Bildtypen (is_<preset>__<width>).
 * Wird nicht direkt einem Media-Manager-Typ zugeordnet, sondern dynamisch ueber
 * den Extension Point MEDIA_MANAGER_FILTERSET aufgerufen (siehe MediaManagerFilterset).
 *
 * Reihenfolge ist entscheidend fuer korrekte srcset-Descriptoren:
 *  1. Ratio-Crop in voller Quellaufloesung (Focuspoint bzw. zentriert)
 *  2. Breitenbegrenztes Resize auf die Zielbreite (Hoehe folgt dem Ratio,
 *     nie vergroessern)
 * So ist die Ausgabebreite immer min(Zielbreite, croppbare Quellbreite) -
 * unabhaengig davon, ob die Quelle hoch- oder querformatig ist.
 */
class rex_effect_image_sets extends rex_effect_abstract
{
    public function execute()
    {
        $sourcePath = (string) $this->media->getSourcePath();
        $sourceFile = (string) $this->media->getMediaFilename();
        $mimeType = strtolower((string) rex_file::mimeType($sourcePath));
        $extension = strtolower((string) rex_file::extension($sourceFile !== '' ? $sourceFile : $sourcePath));

        // SVG immer unveraendert ausliefern (kein Rasterizing).
        if ($mimeType === 'image/svg+xml' || $extension === 'svg') {
            return;
        }

        if (!$this->isSupportedRasterImage($mimeType, $extension)) {
            return;
        }

        try {
            $this->media->asImage();
        } catch (Throwable) {
            return;
        }

        $ratio = trim((string) ($this->params['ratio'] ?? '16_9'));
        $mode = trim((string) ($this->params['mode'] ?? 'focuspoint'));
        $width = max(1, (int) ($this->params['width'] ?? 1200));
        $allowEnlarge = (string) ($this->params['allow_enlarge'] ?? 'not_enlarge');

        if ($mode === 'focuspoint' && $ratio !== 'original') {
            $this->cropToRatio($ratio);
        }

        // Breitenbegrenzt skalieren: nur width setzen, die Hoehe berechnet
        // rex_effect_resize proportional (style maximum).
        $resize = new rex_effect_resize();
        $resize->setMedia($this->media);
        $resize->setParams([
            'width' => $width,
            'height' => '',
            'style' => 'maximum',
            'allow_enlarge' => $allowEnlarge,
        ]);
        $resize->execute();
    }

    private function cropToRatio(string $ratio): void
    {
        [$ratioW, $ratioH] = self::resolveRatio($ratio);

        if (class_exists('rex_effect_focuspoint_fit')) {
            // fr-Angaben: Crop in voller Quellaufloesung um den Fokuspunkt
            $focuspoint = new rex_effect_focuspoint_fit();
            $focuspoint->setMedia($this->media);
            $focuspoint->setParams([
                'width' => $ratioW . 'fr',
                'height' => $ratioH . 'fr',
                'zoom' => '100%',
                'meta' => 'med_focuspoint',
                'focus' => '50.0,50.0',
            ]);
            $focuspoint->execute();

            return;
        }

        // Fallback ohne focuspoint-Addon: zentrierter Crop auf das gewuenschte Ratio.
        $currentWidth = (int) $this->media->getWidth();
        $currentHeight = (int) $this->media->getHeight();
        if ($currentWidth < 1 || $currentHeight < 1) {
            return;
        }

        $targetWidth = $currentWidth;
        $targetHeight = (int) floor($targetWidth * $ratioH / $ratioW);

        if ($targetHeight > $currentHeight) {
            $targetHeight = $currentHeight;
            $targetWidth = (int) floor($targetHeight * $ratioW / $ratioH);
        }

        $targetWidth = max(1, min($targetWidth, $currentWidth));
        $targetHeight = max(1, min($targetHeight, $currentHeight));

        $crop = new rex_effect_crop();
        $crop->setMedia($this->media);
        $crop->setParams([
            'width' => $targetWidth,
            'height' => $targetHeight,
            'hpos' => 'center',
            'vpos' => 'middle',
            'offset_width' => 0,
            'offset_height' => 0,
        ]);
        $crop->execute();
    }

    public function getName()
    {
        return 'Image Sets (virtuelle Bildtypen)';
    }

    public function getParams()
    {
        return [
            [
                'label' => 'Preset',
                'name' => 'preset',
                'type' => 'string',
                'default' => 'gallery_4_3',
            ],
            [
                'label' => 'Ratio',
                'name' => 'ratio',
                'type' => 'string',
                'default' => '16_9',
            ],
            [
                'label' => 'Mode',
                'name' => 'mode',
                'type' => 'select',
                'options' => ['focuspoint', 'resize'],
                'default' => 'focuspoint',
            ],
            [
                'label' => 'Width',
                'name' => 'width',
                'type' => 'int',
                'default' => 1200,
            ],
            [
                'label' => 'Allow Enlarge',
                'name' => 'allow_enlarge',
                'type' => 'select',
                'options' => ['enlarge', 'not_enlarge'],
                'default' => 'not_enlarge',
            ],
        ];
    }

    /**
     * "16_9" / "16:9" -> [16, 9]; "original" oder ungueltig -> [0, 0].
     *
     * @return array{int,int}
     */
    public static function resolveRatio(string $ratio): array
    {
        $normalized = str_replace(':', '_', trim($ratio));
        if (preg_match('/^(\d+)_+(\d+)$/', $normalized, $matches) !== 1) {
            return [0, 0];
        }

        return [max(1, (int) $matches[1]), max(1, (int) $matches[2])];
    }

    private function isSupportedRasterImage(string $mimeType, string $extension): bool
    {
        $supportedMimeTypes = [
            'image/jpeg',
            'image/jpg',
            'image/pjpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/avif',
            'image/vnd.wap.wbmp',
        ];
        if (in_array($mimeType, $supportedMimeTypes, true)) {
            return true;
        }

        $supportedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'wbmp'];
        return in_array($extension, $supportedExtensions, true);
    }
}
