<?php

namespace App\Filament\Resources\IntegrationProducts\Pages;

use App\Filament\Resources\IntegrationCategories\IntegrationCategoryResource;
use App\Filament\Resources\IntegrationProducts\IntegrationProductResource;
use App\Models\IntegrationProduct;
use Filament\Actions\Action;
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
        return 'Сопоставление товаров 1С и будущих маркетплейсов с существующими карточками kotlov.by';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('catalogStructure')
                ->label('Настроить группы каталога')
                ->icon('heroicon-o-folder-open')
                ->color('gray')
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
}
