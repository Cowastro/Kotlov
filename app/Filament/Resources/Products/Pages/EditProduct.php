<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    public function getMaxContentWidth(): ?string
    {
        return 'full';
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * Перед заполнением формы: переносим существующие изображения в отдельное поле,
     * чтобы FileUpload для новых фото не затирал их при сохранении без новых загрузок.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // ── Images ──────────────────────────────────────────────────────────────
        $rawImages = $this->normalizeImages($data['images'] ?? []);
        $data['existing_images'] = array_values(
            array_map(fn($path) => ['path' => $path], $rawImages)
        );
        $data['images'] = [];

        // ── Specs: normalize both {key:val} and [{key,value,unit}] formats ──────
        $data['specs'] = $this->normalizeSpecs($data['specs'] ?? []);

        return $data;
    }

    /**
     * Перед сохранением: сливаем существующие и новые фото обратно в поле images.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $existingImagesWereSubmitted = array_key_exists('existing_images', $data);

        $existing = $existingImagesWereSubmitted
            ? array_column($data['existing_images'] ?? [], 'path')
            : $this->normalizeImages($this->record->images ?? []);

        $newUploads = $this->normalizeImages($data['images'] ?? []);

        unset($data['existing_images']);

        $data['images'] = array_values(array_unique(array_merge(
            $this->normalizeImages($existing),
            $newUploads
        )));

        return $data;
    }

    /**
     * Convert specs from either storage format to Repeater format [{key,value,unit}].
     * Handles:
     *   - {"Мощность":"2 кВт"}  → [{key:"Мощность",value:"2 кВт",unit:""}]
     *   - [{key:"Мощность",value:"2 кВт"}]  → unchanged
     */
    private function normalizeSpecs(mixed $specs): array
    {
        if (is_string($specs)) {
            $decoded = json_decode($specs, true);
            $specs = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        if (! is_array($specs)) {
            return [];
        }

        // Already in [{key,value,unit}] format
        if (isset($specs[0]) && is_array($specs[0]) && array_key_exists('key', $specs[0])) {
            return array_map(fn($s) => [
                'key'   => $s['key'] ?? '',
                'value' => $s['value'] ?? '',
                'unit'  => $s['unit'] ?? '',
            ], $specs);
        }

        // Convert from {name => value} object format
        $result = [];
        foreach ($specs as $k => $v) {
            if (is_string($k) && ($v !== '' && $v !== null)) {
                $result[] = ['key' => (string) $k, 'value' => (string) $v, 'unit' => ''];
            }
        }

        return $result;
    }

    private function normalizeImages(mixed $images): array
    {
        if (is_string($images)) {
            $decoded = json_decode($images, true);
            $images = json_last_error() === JSON_ERROR_NONE ? $decoded : [$images];
        }

        if (!is_array($images)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn($path) => is_string($path) ? trim($path) : null,
            $images
        )));
    }
}
