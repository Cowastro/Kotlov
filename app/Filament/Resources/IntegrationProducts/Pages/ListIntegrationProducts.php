<?php

namespace App\Filament\Resources\IntegrationProducts\Pages;

use App\Filament\Resources\IntegrationCategories\IntegrationCategoryResource;
use App\Filament\Resources\IntegrationProducts\IntegrationProductResource;
use App\Models\IntegrationCategory;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Services\Integrations\CommerceMlCatalogImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListIntegrationProducts extends ListRecords
{
    protected static string $resource = IntegrationProductResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getTitle(): string
    {
        return 'Привязка товаров';
    }

    public function getSubheading(): ?string
    {
        if (! IntegrationCategory::query()->exists()) {
            return '1С передала товары без папок. Организуйте каталог через «Категорию сайта»: для привязанных товаров она берётся из карточки, для остальных назначается вручную или массово.';
        }

        return 'Сопоставление товаров 1С и будущих маркетплейсов с существующими карточками kotlov.by';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rematch')
                ->label('Повторить автосопоставление')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->form([
                    Select::make('integration_source_id')
                        ->label('Источник / поставщик')
                        ->options(fn (): array => IntegrationSource::query()
                            ->whereHas('products')
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (IntegrationSource $source): array => [
                                $source->id => $source->name.' · '.$source->products()->inStock()->count().' в наличии',
                            ])
                            ->all())
                        ->default(fn (): ?int => IntegrationSource::query()
                            ->whereHas('products')
                            ->orderBy('name')
                            ->value('id'))
                        ->searchable()
                        ->required()
                        ->helperText('Привязанные и отмеченные «Не для сайта» товары не изменяются.'),
                ])
                ->requiresConfirmation()
                ->modalHeading('Повторить автосопоставление')
                ->modalDescription('Точные однозначные совпадения по артикулу, штрихкоду или коду 1С будут привязаны. Совпадения по названию останутся предложениями до ручного подтверждения.')
                ->modalSubmitActionLabel('Запустить')
                ->action(function (array $data): void {
                    $source = IntegrationSource::query()->findOrFail($data['integration_source_id']);
                    $stats = app(CommerceMlCatalogImporter::class)->rematchSource($source->code);
                    $processed = array_sum($stats);

                    $this->resetTable();

                    Notification::make()
                        ->success()
                        ->title('Автосопоставление завершено')
                        ->body(implode(' · ', [
                            "Проверено: {$processed}",
                            "Точно привязано: {$stats['matched']}",
                            "Предложений: {$stats['suggested']}",
                            "Нужна проверка: {$stats['ambiguous']}",
                            "Не найдено: {$stats['unmatched']}",
                        ]))
                        ->send();
                }),
            Action::make('catalogStructure')
                ->label(fn (): string => IntegrationCategory::query()->exists()
                    ? 'Настроить группы каталога'
                    : 'Группы 1С не переданы')
                ->icon('heroicon-o-folder-open')
                ->color(fn (): string => IntegrationCategory::query()->exists() ? 'gray' : 'warning')
                ->url(IntegrationCategoryResource::getUrl('index')),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Все')
                ->icon('heroicon-o-squares-2x2')
                ->badge(IntegrationProduct::query()->inStock()->count()),
            'matched' => $this->statusTab('Привязаны', 'matched', 'success', 'heroicon-o-link'),
            'suggested' => $this->statusTab('Предложения', 'suggested', 'info', 'heroicon-o-light-bulb'),
            'ambiguous' => $this->statusTab('Нужна проверка', 'ambiguous', 'warning', 'heroicon-o-exclamation-triangle'),
            'unmatched' => $this->statusTab('Не найдены', 'unmatched', 'danger', 'heroicon-o-magnifying-glass'),
            'missing_category' => Tab::make('Без категории')
                ->icon('heroicon-o-folder-minus')
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->missingCategoryQuery($query))
                ->badge($this->missingCategoryQuery(IntegrationProduct::query()->inStock())->count() ?: null)
                ->badgeColor('warning'),
            'ready_for_card' => Tab::make('Категория назначена')
                ->icon('heroicon-o-folder-plus')
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->readyForCardQuery($query))
                ->badge($this->readyForCardQuery(IntegrationProduct::query()->inStock())->count() ?: null)
                ->badgeColor('info'),
            'ignored' => $this->statusTab('Не для сайта', 'ignored', 'gray', 'heroicon-o-eye-slash'),
        ];
    }

    private function statusTab(string $label, string $status, string $color, string $icon): Tab
    {
        $count = IntegrationProduct::query()->inStock()->where('match_status', $status)->count();

        return Tab::make($label)
            ->icon($icon)
            ->modifyQueryUsing(fn (Builder $query) => $query->where('match_status', $status))
            ->badge($count ?: null)
            ->badgeColor($color);
    }

    private function missingCategoryQuery(Builder $query): Builder
    {
        return $query
            ->whereNull('product_id')
            ->whereNull('target_category_id')
            ->whereDoesntHave(
                'integrationCategory',
                fn (Builder $category): Builder => $category->whereNotNull('category_id')
            );
    }

    private function readyForCardQuery(Builder $query): Builder
    {
        return $query
            ->whereNull('product_id')
            ->where(function (Builder $query): void {
                $query
                    ->whereNotNull('target_category_id')
                    ->orWhereHas(
                        'integrationCategory',
                        fn (Builder $category): Builder => $category->whereNotNull('category_id')
                    );
            });
    }
}
