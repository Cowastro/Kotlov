<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Jobs\RunProductSourceEnrichment;
use App\Services\ProductSourceEnricher;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

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
            Action::make('enrich_from_source_url')
                ->label('Обновить из ссылки')
                ->icon('heroicon-o-link')
                ->color('success')
                ->fillForm(fn (): array => [
                    'source_url' => $this->record->supplierProducts()
                        ->whereNotNull('source_url')
                        ->where('source_url', '!=', '')
                        ->orderBy('id')
                        ->value('source_url'),
                    'preview_only' => false,
                    'update_images' => true,
                    'replace_images' => false,
                    'update_specs' => true,
                    'update_content' => true,
                    'update_service' => true,
                ])
                ->form([
                    TextInput::make('source_url')
                        ->label('Ссылка на карточку товара')
                        ->url()
                        ->required()
                        ->placeholder('https://example.com/product/...'),
                    Toggle::make('preview_only')
                        ->label('Только проверить, без записи')
                        ->helperText('Покажет найденные фото, характеристики и описание в уведомлении. Для обновления карточки выключите.')
                        ->default(false),
                    Toggle::make('update_images')
                        ->label('Загрузить фотографии')
                        ->default(true),
                    Toggle::make('replace_images')
                        ->label('Заменить текущие фото')
                        ->helperText('Оставьте выключенным, чтобы новые фото только добавлялись к существующим.')
                        ->default(false),
                    Toggle::make('update_specs')
                        ->label('Обновить характеристики')
                        ->default(true),
                    Toggle::make('update_content')
                        ->label('SEO-описание через ИИ')
                        ->helperText('Сырой текст поставщика не сохраняется. Если ИИ не настроен, описание не изменится.')
                        ->default(true),
                    Toggle::make('update_service')
                        ->label('Импортер и сервисный центр')
                        ->helperText('Забирает структурные поля: производитель, импортер, сервисный центр, страна происхождения, гарантия.')
                        ->default(true),
                ])
                ->requiresConfirmation()
                ->modalHeading('Обновить карточку из ссылки')
                ->modalDescription('Система попробует взять со страницы фото, характеристики и описание. Результат появится в уведомлениях после выполнения очереди.')
                ->action(fn (array $data) => $this->queueSourceEnrichment($data)),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    private function queueSourceEnrichment(array $data): void
    {
        $options = [
            'update_images' => (bool) ($data['update_images'] ?? true),
            'replace_images' => (bool) ($data['replace_images'] ?? false),
            'update_specs' => (bool) ($data['update_specs'] ?? true),
            'replace_specs' => true,
            'update_content' => (bool) ($data['update_content'] ?? true),
            'update_service' => (bool) ($data['update_service'] ?? false),
        ];

        RunProductSourceEnrichment::dispatch(
            [(int) $this->record->id],
            (int) auth()->id(),
            (string) $data['source_url'],
            $options,
            (bool) ($data['preview_only'] ?? false),
        );

        Notification::make()
            ->success()
            ->title(($data['preview_only'] ?? false) ? 'Проверка поставлена в очередь' : 'Обновление поставлено в очередь')
            ->body('Товар ID ' . $this->record->id . '. После выполнения очереди результат придет в уведомления.')
            ->send();
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
        if ($data['specs'] === []) {
            $data['specs'] = $this->specsFromAttributeValues();
        }
        $data['specs'] = app(ProductSourceEnricher::class)->normalizeSpecsForStorage($data['specs']);

        // ── EUR price virtual field ──────────────────────────────────────────────
        $sp = DB::table('supplier_products')
            ->where('product_id', $this->record->id)
            ->whereNotIn('currency', ['BYN'])
            ->first();
        $data['eur_price_virtual'] = $sp ? (float) $sp->price : null;

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

        $data['specs'] = app(ProductSourceEnricher::class)->normalizeSpecsForStorage(
            $this->normalizeSpecs($data['specs'] ?? [])
        );

        return $data;
    }

    protected function afterSave(): void
    {
        app(ProductSourceEnricher::class)->syncSpecsToAttributeValues(
            $this->record,
            $this->normalizeSpecs($this->record->specs ?? []),
        );

        // ── Если изменили EUR цену — обновить supplier_products ─────────────────
        $formState = $this->form->getState();
        $newEurPrice = isset($formState['eur_price_virtual'])
            ? (float) $formState['eur_price_virtual']
            : null;

        if ($newEurPrice !== null && $newEurPrice > 0) {
            $sp = DB::table('supplier_products')
                ->where('product_id', $this->record->id)
                ->whereNotIn('currency', ['BYN'])
                ->first();

            if ($sp) {
                $newByn = round($newEurPrice * (float) $sp->currency_rate, 2);
                DB::table('supplier_products')
                    ->where('id', $sp->id)
                    ->update([
                        'price'      => $newEurPrice,
                        'price_byn'  => $newByn,
                        'updated_at' => now(),
                    ]);
                // products.price уже сохранён формой (BYN через afterStateUpdated)
            }
        }
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

    private function specsFromAttributeValues(): array
    {
        return $this->record
            ->allAttributeValues()
            ->with(['attribute', 'option'])
            ->get()
            ->sortBy(fn ($value): int => (int) ($value->attribute?->sort_order ?? 0))
            ->map(function ($value): array {
                return [
                    'key' => (string) ($value->attribute?->name ?? ''),
                    'value' => $value->option?->name ?: (string) ($value->value ?? ''),
                    'unit' => (string) ($value->attribute?->suffix ?? ''),
                ];
            })
            ->filter(fn (array $spec): bool => $spec['key'] !== '' && $spec['value'] !== '')
            ->values()
            ->all();
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
