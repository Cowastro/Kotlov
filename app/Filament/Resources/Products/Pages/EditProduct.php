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
        $rawImages = $this->normalizeImages($data['images'] ?? []);

        $data['existing_images'] = array_values(
            array_map(fn($path) => ['path' => $path], $rawImages)
        );

        // FileUpload — только для новых загрузок
        $data['images'] = [];

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
