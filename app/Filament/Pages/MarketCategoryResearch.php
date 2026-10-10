<?php

namespace App\Filament\Pages;

use App\Services\Market\MarketCategoryResearch as MarketCategoryResearchService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\WithPagination;

class MarketCategoryResearch extends Page
{
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static ?string $navigationLabel = 'Исследование категорий';

    protected static ?string $title = 'Рынок по категориям';

    protected static ?string $slug = 'market-category-research';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.market-category-research';

    public string $search = '';

    public int $perPage = 25;

    private ?Collection $rowsCache = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isManager() === true;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Аналитика';
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function updatedSearch(): void
    {
        $this->resetResearch();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 25, 50, 100], true)) {
            $this->perPage = 25;
        }

        $this->resetPage('categoryPage');
    }

    public function summary(): array
    {
        $rows = $this->filteredRows();

        return [
            ['label' => 'Категорий с рыночными данными', 'value' => $rows->count(), 'tone' => 'info'],
            ['label' => 'Товаров со свежими ценами', 'value' => (int) $rows->sum('fresh_products_count'), 'tone' => 'success'],
            ['label' => 'Подтверждённый спрос за 90 дней', 'value' => (int) $rows->sum('demand_quantity_90d'), 'tone' => 'warning'],
            ['label' => 'Спрос при дефиците', 'value' => $rows->where('opportunity.tone', 'danger')->count(), 'tone' => 'danger'],
        ];
    }

    public function rows(): LengthAwarePaginator
    {
        $rows = $this->filteredRows();
        $page = $this->getPage('categoryPage');

        return new LengthAwarePaginator(
            $rows->forPage($page, $this->perPage)->values(),
            $rows->count(),
            $this->perPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'categoryPage'],
        );
    }

    private function filteredRows(): Collection
    {
        if ($this->rowsCache !== null) {
            return $this->rowsCache;
        }

        $search = mb_strtolower(trim($this->search));

        return $this->rowsCache = app(MarketCategoryResearchService::class)
            ->rows()
            ->when($search !== '', fn (Collection $rows): Collection => $rows->filter(
                fn (array $row): bool => str_contains(mb_strtolower($row['category_name']), $search),
            ))
            ->values();
    }

    private function resetResearch(): void
    {
        $this->rowsCache = null;
        $this->resetPage('categoryPage');
    }
}
