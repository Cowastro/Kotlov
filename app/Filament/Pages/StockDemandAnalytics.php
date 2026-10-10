<?php

namespace App\Filament\Pages;

use App\Filament\Resources\IntegrationProducts\IntegrationProductResource;
use App\Models\IntegrationSource;
use App\Models\PurchasePlan;
use App\Services\Orders\OrderStockRecommendationService;
use App\Services\Orders\PurchasePlanManager;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
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

    public string $stockDataFilter = 'all';

    public bool $purchaseOnly = true;

    public int $perPage = 25;

    /** @var array<int, int|string> */
    public array $selectedProductIds = [];

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

    public function updatedStockDataFilter(): void
    {
        if (! array_key_exists($this->stockDataFilter, $this->stockDataOptions())) {
            $this->stockDataFilter = 'all';
        }

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

    public function stockDataOptions(): array
    {
        return [
            'all' => 'Все состояния',
            'confirmed' => 'Подтверждено 1С',
            'problems' => 'Нужно проверить',
        ];
    }

    public function matchingUrl(array $row): string
    {
        if (filled($row['stock_integration_product_id'] ?? null)) {
            return IntegrationProductResource::getUrl('edit', [
                'record' => $row['stock_integration_product_id'],
            ]);
        }

        $search = $this->matchingSearchTerm($row);

        return IntegrationProductResource::getUrl('index', [
            'tab' => 'all',
            'search' => $search,
        ]);
    }

    public function matchingActionLabel(array $row): string
    {
        return filled($row['stock_integration_product_id'] ?? null)
            ? 'Открыть связь 1С'
            : 'Найти и привязать';
    }

    public function matchingSearchTerm(array $row): string
    {
        $name = trim((string) ($row['name'] ?? ''));
        preg_match_all('/[\p{L}\d][\p{L}\d._,\/-]{3,}/u', $name, $matches);

        $modelToken = collect($matches[0] ?? [])
            ->filter(fn (string $token): bool => preg_match('/\d/u', $token) === 1
                && preg_match('/\p{L}/u', $token) === 1)
            ->sortByDesc(fn (string $token): int => mb_strlen($token))
            ->first();

        if (filled($modelToken)) {
            return $modelToken;
        }

        $sku = trim((string) ($row['sku'] ?? ''));
        if ($sku !== '' && preg_match('/^(?:KOTLOV|PS)-/i', $sku) !== 1) {
            return $sku;
        }

        return $name;
    }

    public function selectRecommended(): void
    {
        $this->selectedProductIds = $this->filteredRows()
            ->filter(fn (array $row): bool => ($row['recommended_purchase'] ?? 0) > 0
                && ($row['stock_data_ready'] ?? false) === true)
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    public function clearSelection(): void
    {
        $this->selectedProductIds = [];
    }

    public function createPurchasePlan(): void
    {
        abort_unless(auth()->user()?->isManager() === true, 403);

        $source = IntegrationSource::query()->where('code', $this->sourceCode)->first();
        if (! $source) {
            Notification::make()->title('Источник не найден')->danger()->send();

            return;
        }

        $selected = collect($this->selectedProductIds)->map(fn ($id): int => (int) $id)->unique();
        $recommendations = app(OrderStockRecommendationService::class)
            ->recommendations($this->period, $this->sourceCode)
            ->whereIn('product_id', $selected);

        try {
            $result = app(PurchasePlanManager::class)->createDraft(
                $recommendations,
                $source,
                $this->period,
                auth()->user(),
            );
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('План не создан')
                ->body((string) collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();

            return;
        }

        $this->selectedProductIds = [];
        $plan = $result['plan'];
        Notification::make()
            ->title($result['created'] ? "Создан черновик {$plan->number}" : "Черновик {$plan->number} уже существует")
            ->body('До отдельного подтверждения это только снимок рекомендации. Остатки не изменены.')
            ->success()
            ->send();
    }

    public function confirmPurchasePlan(int $planId): void
    {
        abort_unless(auth()->user()?->isManager() === true, 403);
        $this->transitionPurchasePlan($planId, 'confirm');
    }

    public function cancelPurchasePlan(int $planId): void
    {
        abort_unless(auth()->user()?->isManager() === true, 403);
        $this->transitionPurchasePlan($planId, 'cancel');
    }

    public function recentPlans(): Collection
    {
        return PurchasePlan::query()
            ->with(['creator:id,name', 'items:id,purchase_plan_id,product_name,planned_quantity'])
            ->latest()
            ->limit(6)
            ->get();
    }

    public function summary(): array
    {
        $rows = $this->filteredRows();

        return [
            ['label' => 'Подтверждённый дефицит', 'value' => $rows->filter(fn (array $row): bool => ($row['recommended_purchase'] ?? 0) > 0)->count(), 'suffix' => '', 'tone' => 'warning'],
            ['label' => 'Нужно проверить остаток', 'value' => $rows->filter(fn (array $row): bool => ! $row['stock_data_ready'] && $row['quantity_recent'] > 0)->count(), 'suffix' => '', 'tone' => 'danger'],
            ['label' => 'Подтверждённый спрос', 'value' => (int) $rows->sum('quantity_recent'), 'suffix' => ' шт.', 'tone' => 'info'],
            ['label' => 'Неподтверждённые заявки', 'value' => (int) $rows->sum('interest_quantity_recent'), 'suffix' => ' шт.', 'tone' => 'muted'],
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
            ->when($this->stockDataFilter === 'confirmed', fn (Collection $rows): Collection => $rows
                ->filter(fn (array $row): bool => $row['stock_data_ready']))
            ->when($this->stockDataFilter === 'problems', fn (Collection $rows): Collection => $rows
                ->filter(fn (array $row): bool => ! $row['stock_data_ready']))
            ->when($this->purchaseOnly, fn (Collection $rows): Collection => $rows
                ->filter(fn (array $row): bool => ($row['recommended_purchase'] ?? 0) > 0
                    || (! $row['stock_data_ready'] && $row['quantity_recent'] > 0)
                    || $row['interest_quantity_recent'] > 0))
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
        $this->selectedProductIds = [];
        $this->resetPage('stockPage');
    }

    private function transitionPurchasePlan(int $planId, string $transition): void
    {
        $plan = PurchasePlan::query()->with('items')->findOrFail($planId);

        try {
            $plan = $transition === 'confirm'
                ? app(PurchasePlanManager::class)->confirm($plan, auth()->user())
                : app(PurchasePlanManager::class)->cancel($plan, auth()->user());
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('План не изменён')
                ->body((string) collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title($transition === 'confirm' ? "План {$plan->number} подтверждён" : "Черновик {$plan->number} отменён")
            ->body('Остатки 1С и карточки товаров не изменялись.')
            ->success()
            ->send();
    }
}
