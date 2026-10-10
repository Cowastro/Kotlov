<?php

namespace App\Filament\Pages;

use App\Models\IntegrationSource;
use App\Services\Orders\OrderStockRecommendationService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\WithPagination;

class StockDemandAnalytics extends Page
{
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?string $navigationLabel = 'Спрос и склад';

    protected static ?string $title = 'Спрос и собственный склад';

    protected static ?string $slug = 'stock-demand';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.stock-demand-analytics';

    public int $period = 180;

    public string $sourceCode = 'onec';

    public string $search = '';

    public bool $purchaseOnly = true;

    public int $perPage = 25;

    private ?Collection $rowsCache = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isManager() === true;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Продажи';
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function mount(): void
    {
        if (! IntegrationSource::query()->where('code', $this->sourceCode)->exists()) {
            $this->sourceCode = (string) (IntegrationSource::query()->orderBy('id')->value('code') ?? 'onec');
        }
    }

    public function updatedPeriod(): void
    {
        if (! array_key_exists($this->period, $this->periodOptions())) {
            $this->period = 180;
        }

        $this->resetAnalysis();
    }

    public function updatedSourceCode(): void
    {
        $this->resetAnalysis();
    }

    public function updatedSearch(): void
    {
        $this->resetAnalysis();
    }

    public function updatedPurchaseOnly(): void
    {
        $this->resetAnalysis();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 25, 50, 100], true)) {
            $this->perPage = 25;
        }

        $this->resetPage('stockPage');
    }

    public function periodOptions(): array
    {
        return [
            30 => '30 дней',
            90 => '90 дней',
            180 => '180 дней',
            365 => '365 дней',
        ];
    }

    public function sourceOptions(): array
    {
        return IntegrationSource::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['code', 'name', 'is_active'])
            ->mapWithKeys(fn (IntegrationSource $source): array => [
                $source->code => $source->name.($source->is_active ? '' : ' (выключен)'),
            ])
            ->all();
    }

    public function summary(): array
    {
        $rows = $this->filteredRows();

        return [
            ['label' => 'Позиций в анализе', 'value' => $rows->count(), 'suffix' => '', 'tone' => 'neutral'],
            ['label' => 'Нужно пополнить', 'value' => $rows->where('recommended_purchase', '>', 0)->count(), 'suffix' => '', 'tone' => 'warning'],
            ['label' => 'Рекомендовано единиц', 'value' => (int) $rows->sum('recommended_purchase'), 'suffix' => ' шт.', 'tone' => 'danger'],
            ['label' => 'Спрос за период', 'value' => (int) $rows->sum('quantity_recent'), 'suffix' => ' шт.', 'tone' => 'info'],
        ];
    }

    public function rows(): LengthAwarePaginator
    {
        $rows = $this->filteredRows();
        $page = $this->getPage('stockPage');

        return new LengthAwarePaginator(
            $rows->forPage($page, $this->perPage)->values(),
            $rows->count(),
            $this->perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => 'stockPage',
            ],
        );
    }

    private function filteredRows(): Collection
    {
        if ($this->rowsCache !== null) {
            return $this->rowsCache;
        }

        $search = mb_strtolower(trim($this->search));

        return $this->rowsCache = app(OrderStockRecommendationService::class)
            ->recommendations($this->period, $this->sourceCode)
            ->when($this->purchaseOnly, fn (Collection $rows): Collection => $rows
                ->filter(fn (array $row): bool => $row['recommended_purchase'] > 0))
            ->when($search !== '', fn (Collection $rows): Collection => $rows
                ->filter(fn (array $row): bool => str_contains(
                    mb_strtolower(($row['sku'] ?? '').' '.($row['name'] ?? '')),
                    $search,
                )))
            ->values();
    }

    private function resetAnalysis(): void
    {
        $this->rowsCache = null;
        $this->resetPage('stockPage');
    }
}
