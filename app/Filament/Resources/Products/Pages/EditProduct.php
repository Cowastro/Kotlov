<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
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
        $rawImages = $data['images'] ?? [];
        if (is_string($rawImages)) {
            $rawImages = json_decode($rawImages, true) ?? [];
        }

        $data['existing_images'] = array_values(
            array_map(fn($path) => ['path' => (string) $path], (array) $rawImages)
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
        $existing   = array_column($data['existing_images'] ?? [], 'path');
        $newUploads = array_values(array_filter((array) ($data['images'] ?? [])));

        unset($data['existing_images']);

        $data['images'] = array_merge($existing, $newUploads);

        return $data;
    }
}
