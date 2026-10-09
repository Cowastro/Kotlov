<?php

namespace App\Filament\Resources\IntegrationProducts\Pages;

use App\Filament\Resources\IntegrationProducts\IntegrationProductResource;
use App\Models\IntegrationProduct;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListIntegrationProducts extends ListRecords
{
    protected static string $resource = IntegrationProductResource::class;

    public function getTitle(): string
    {
        return 'Привязка товаров';
    }

    public function getSubheading(): ?string
    {
        return 'Сопоставление товаров 1С и будущих маркетплейсов с существующими карточками kotlov.by';
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Все')->badge(IntegrationProduct::query()->count()),
            'matched' => $this->statusTab('Привязаны', 'matched', 'success'),
            'suggested' => $this->statusTab('Предложения', 'suggested', 'info'),
            'ambiguous' => $this->statusTab('Нужна проверка', 'ambiguous', 'warning'),
            'unmatched' => $this->statusTab('Не найдены', 'unmatched', 'danger'),
            'ignored' => $this->statusTab('Не для сайта', 'ignored', 'gray'),
        ];
    }

    private function statusTab(string $label, string $status, string $color): Tab
    {
        $count = IntegrationProduct::query()->where('match_status', $status)->count();

        return Tab::make($label)
            ->modifyQueryUsing(fn (Builder $query) => $query->where('match_status', $status))
            ->badge($count ?: null)
            ->badgeColor($color);
    }
}
