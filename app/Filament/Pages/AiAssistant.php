<?php

namespace App\Filament\Pages;

use App\Jobs\RunProductCatalogAiReview;
use App\Models\Product;
use App\Models\SupplierReviewDecision;
use App\Models\SupplierSync;
use App\Services\AiContentEnricher;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Livewire\WithPagination;
use Throwable;

class AiAssistant extends Page
{
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;
    protected static ?string $navigationLabel = 'ИИ-помощник';
    protected static ?string $title = 'ИИ-помощник';
    protected static ?int $navigationSort = 6;
    protected string $view = 'filament.pages.ai-assistant';

    public int $decisionsPerPage = 10;
    public string $decisionSearch = '';
    public string $decisionStatus = SupplierReviewDecision::STATUS_PENDING;
    public string $decisionType = '';
    public string $decisionChange = '';
    public string $decisionSupplier = '';
    public array $selectedDecisionIds = [];
    public string $qualityIssue = 'without_content';

    public static function getNavigationGroup(): ?string
    {
        return 'Каталог';
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('catalog_quality_audit')
                ->label('AI-аудит каталога')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->form([
                    Select::make('audit_type')
                        ->label('Что проверить')
                        ->options($this->catalogAuditTypeOptions())
                        ->default('suspicious_category')
                        ->required()
                        ->native(false),
                    TextInput::make('limit')
                        ->label('Сколько товаров взять')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(200)
                        ->default(20)
                        ->required(),
                    Toggle::make('use_ai')
                        ->label('Использовать DeepSeek/AI')
                        ->helperText('Если выключить, будут использованы только локальные правила.')
                        ->default(true),
                ])
                ->requiresConfirmation()
                ->modalHeading('Запустить AI-аудит каталога')
                ->modalDescription('Товары не изменятся. AI подготовит pending-решения только там, где видит смену категории, бренда или возможный дубль.')
                ->action(fn (array $data) => $this->queueCatalogQualityAudit($data)),
            Action::make('recalculate_pending')
                ->label('Пересчитать pending')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->form([
                    TextInput::make('limit')
                        ->label('Сколько решений пересчитать')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(200)
                        ->default(50)
                        ->required(),
                    Toggle::make('use_ai')
                        ->label('Использовать DeepSeek/AI')
                        ->helperText('Если выключить, будут использованы только локальные правила.')
                        ->default(true),
                ])
                ->requiresConfirmation()
                ->modalHeading('Пересчитать pending-решения')
                ->modalDescription('Товары не изменятся. Будут заново рассчитаны AI-подсказки для текущих pending-решений каталога.')
                ->action(fn (array $data) => $this->queuePendingRecalculation($data)),
        ];
    }

    public function metrics(): array
    {
        $activeProducts = Product::query()
            ->where('is_archived', false);

        return [
            [
                'label' => 'Активных товаров',
                'value' => number_format((clone $activeProducts)->count(), 0, '.', ' '),
                'tone' => 'neutral',
            ],
            [
                'label' => 'Без SEO-описания',
                'value' => number_format($this->productsWithoutContent(), 0, '.', ' '),
                'tone' => 'warning',
            ],
            [
                'label' => 'Без фото',
                'value' => number_format($this->productsWithoutImages(), 0, '.', ' '),
                'tone' => 'danger',
            ],
            [
                'label' => 'Pending-решений',
                'value' => number_format($this->pendingDecisionsCount(), 0, '.', ' '),
                'tone' => 'info',
            ],
        ];
    }

    public function workstreams(): array
    {
        return [
            [
                'title' => 'Качество карточек',
                'status' => 'Следующий шаг',
                'summary' => 'Находим товары без текста, фото, бренда, категории и источника. ИИ должен предлагать действие, но не применять его без подтверждения.',
                'items' => [
                    'Собрать аудит проблемных карточек по фильтрам.',
                    'Добавить запуск AI-разбора прямо из этого центра.',
                    'Писать результат в SupplierReviewDecision.',
                ],
                'url' => url('/admin/products'),
            ],
            [
                'title' => 'Очередь решений',
                'status' => 'Уже есть база',
                'summary' => 'Система pending-решений уже используется для поставщиков и AI-разбора каталога. Ее нужно сделать удобной для менеджера.',
                'items' => [
                    'Показать понятный список: что изменится и почему.',
                    'Добавить действия применить / отклонить / открыть товар.',
                    'Разделить решения по типам: бренд, категория, дубль, цена, связь.',
                ],
                'url' => url('/admin/import-reports'),
            ],
            [
                'title' => 'SEO и описания',
                'status' => 'Рабочий сервис есть',
                'summary' => 'AiContentEnricher уже генерирует short_description и content. Нужно дать управляемые массовые сценарии.',
                'items' => [
                    'Пакетная генерация только для пустых карточек.',
                    'Отдельный режим проверки без записи.',
                    'Логировать, какие поля изменены и каким провайдером.',
                ],
                'url' => url('/admin/products'),
            ],
            [
                'title' => 'Синхронизации поставщиков',
                'status' => 'Зона Claude',
                'summary' => 'Claude продолжает поставщиков: парсинг, цены, остатки, создание товаров. Наша зона начинается после импорта: качество и решения.',
                'items' => [
                    'Не менять Sync*Command без согласования.',
                    'Использовать supplier_syncs как источник статусов.',
                    'AI-помощник читает результаты и готовит безопасные решения.',
                ],
                'url' => url('/admin/supplier-syncs'),
            ],
        ];
    }

    public function issueGroups(): array
    {
        return [
            [
                'label' => 'Без SEO-описания',
                'count' => $this->productsWithoutContent(),
                'hint' => 'Кандидаты на product:enrich-content или AI-генерацию из source_url.',
            ],
            [
                'label' => 'Без фото',
                'count' => $this->productsWithoutImages(),
                'hint' => 'Кандидаты на ProductSourceEnricher или supplier:enrich-images.',
            ],
            [
                'label' => 'Без бренда',
                'count' => Product::query()->where('is_archived', false)->whereNull('brand_id')->count(),
                'hint' => 'Кандидаты на AI-разбор бренда по названию, поставщику и URL.',
            ],
            [
                'label' => 'Без категории',
                'count' => Product::query()->where('is_archived', false)->whereNull('category_id')->count(),
                'hint' => 'Кандидаты на AI-разбор категории перед публикацией.',
            ],
            [
                'label' => 'Без источника поставщика',
                'count' => Product::query()
                    ->where('is_archived', false)
                    ->whereDoesntHave('supplierProducts', fn ($query) => $query->whereNotNull('source_url')->where('source_url', '!=', ''))
                    ->count(),
                'hint' => 'Для таких карточек сложнее автоматически обогащать фото и характеристики.',
            ],
        ];
    }

    public function qualityIssueOptions(): array
    {
        return [
            'without_content' => 'Без SEO-описания',
            'without_images' => 'Без фото',
            'without_brand' => 'Без бренда',
            'without_category' => 'Без категории',
            'without_source' => 'Без источника',
        ];
    }

    public function updatedQualityIssue(): void
    {
        if (! array_key_exists($this->qualityIssue, $this->qualityIssueOptions())) {
            $this->qualityIssue = 'without_content';
        }
    }

    public function qualitySamples(): array
    {
        $issue = array_key_exists($this->qualityIssue, $this->qualityIssueOptions())
            ? $this->qualityIssue
            : 'without_content';

        return $this->qualityIssueQuery($issue)
            ->with(['brand', 'category', 'supplierProducts.supplier'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (Product $product): array => [
                'id' => (int) $product->id,
                'title' => (string) $product->name,
                'sku' => $product->sku ?: '-',
                'brand' => $product->brand?->name ?: '-',
                'category' => $product->category?->name ?: '-',
                'source' => $this->productSourceLabel($product),
                'problems' => $this->productQualityProblems($product),
                'url' => url('/admin/products/' . $product->id),
            ])
            ->all();
    }

    public function pendingDecisions()
    {
        $perPage = in_array($this->decisionsPerPage, [5, 10, 25, 50, 100], true)
            ? $this->decisionsPerPage
            : 10;

        return $this->decisionQuery()
            ->latest('id')
            ->paginate($perPage, ['*'], 'decisionsPage')
            ->through(function (SupplierReviewDecision $decision): array {
                $payload = is_array($decision->payload) ? $decision->payload : [];
                $changes = is_array($payload['changes'] ?? null) ? $payload['changes'] : [];

                return [
                    'id' => $decision->id,
                    'decision' => $this->decisionLabel($decision->decision),
                    'decision_code' => $decision->decision,
                    'status' => $this->decisionStatusLabel($decision->status),
                    'status_code' => $decision->status,
                    'supplier' => $decision->supplier_code ?: '-',
                    'title' => $decision->supplier_title ?: ('product_id ' . ($decision->product_id ?: '-')),
                    'product_id' => $decision->product_id,
                    'product_url' => $decision->product_id ? url('/admin/products/' . $decision->product_id) : null,
                    'category_change' => $this->formatDecisionChange(
                        $payload['current_category'] ?? null,
                        $payload['suggested_category'] ?? null,
                        array_key_exists('category_id', $changes),
                    ),
                    'brand_change' => $this->formatDecisionChange(
                        $payload['current_brand'] ?? null,
                        $payload['suggested_brand'] ?? null,
                        array_key_exists('brand_id', $changes),
                    ),
                    'duplicate' => $this->formatDuplicate($decision, $payload),
                    'reason' => $decision->reason ?: '-',
                ];
            });
    }

    public function updatedDecisionsPerPage(): void
    {
        $this->selectedDecisionIds = [];
        $this->resetPage('decisionsPage');
    }

    public function updatedDecisionSearch(): void
    {
        $this->selectedDecisionIds = [];
        $this->resetPage('decisionsPage');
    }

    public function updatedDecisionStatus(): void
    {
        $this->selectedDecisionIds = [];
        $this->resetPage('decisionsPage');
    }

    public function updatedDecisionType(): void
    {
        $this->selectedDecisionIds = [];
        $this->resetPage('decisionsPage');
    }

    public function updatedDecisionChange(): void
    {
        $this->selectedDecisionIds = [];
        $this->resetPage('decisionsPage');
    }

    public function updatedDecisionSupplier(): void
    {
        $this->selectedDecisionIds = [];
        $this->resetPage('decisionsPage');
    }

    public function resetDecisionFilters(): void
    {
        $this->decisionSearch = '';
        $this->decisionStatus = SupplierReviewDecision::STATUS_PENDING;
        $this->decisionType = '';
        $this->decisionChange = '';
        $this->decisionSupplier = '';
        $this->selectedDecisionIds = [];
        $this->resetPage('decisionsPage');
    }

    public function selectVisiblePendingDecisions(): void
    {
        $perPage = in_array($this->decisionsPerPage, [5, 10, 25, 50, 100], true)
            ? $this->decisionsPerPage
            : 10;
        $page = (int) \Illuminate\Pagination\Paginator::resolveCurrentPage('decisionsPage');

        $visibleIds = $this->decisionQuery(forcePending: true)
            ->latest('id')
            ->forPage($page, $perPage)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->selectedDecisionIds = array_values(array_unique([
            ...$this->normalizedSelectedDecisionIds(),
            ...$visibleIds,
        ]));
    }

    public function clearSelectedDecisions(): void
    {
        $this->selectedDecisionIds = [];
    }

    public function applySelectedDecisions(): void
    {
        $ids = $this->pendingSelectedDecisionIds();
        if ($ids === []) {
            $this->notifyNoSelectedPending();
            return;
        }

        try {
            $exitCode = Artisan::call('supplier:apply-review-decisions', [
                '--apply' => true,
                '--id' => $ids,
            ]);

            if ($exitCode !== 0) {
                throw new \RuntimeException(trim(Artisan::output()) ?: 'Команда применения вернула ошибку.');
            }

            $this->selectedDecisionIds = [];
            Notification::make()
                ->success()
                ->title('Выбранные решения применены')
                ->body('Решений: ' . count($ids))
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Не удалось применить выбранные решения')
                ->body($exception->getMessage())
                ->persistent()
                ->send();
        }
    }

    public function deleteSelectedDecisions(): void
    {
        $ids = $this->pendingSelectedDecisionIds();
        if ($ids === []) {
            $this->notifyNoSelectedPending();
            return;
        }

        try {
            $exitCode = Artisan::call('supplier:apply-review-decisions', [
                '--apply' => true,
                '--delete' => $ids,
            ]);

            if ($exitCode !== 0) {
                throw new \RuntimeException(trim(Artisan::output()) ?: 'Команда удаления вернула ошибку.');
            }

            $this->selectedDecisionIds = [];
            Notification::make()
                ->success()
                ->title('Выбранные pending-решения удалены')
                ->body('Решений: ' . count($ids))
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Не удалось удалить выбранные решения')
                ->body($exception->getMessage())
                ->persistent()
                ->send();
        }
    }

    public function recalculateSelectedDecisions(): void
    {
        $ids = $this->pendingSelectedDecisionIds();
        if ($ids === []) {
            $this->notifyNoSelectedPending();
            return;
        }

        $productIds = SupplierReviewDecision::query()
            ->whereIn('id', $ids)
            ->where('decision', SupplierReviewDecision::DECISION_UPDATE_PRODUCT_CATALOG)
            ->whereNotNull('product_id')
            ->pluck('product_id')
            ->map(fn ($productId): int => (int) $productId)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($productIds === []) {
            Notification::make()
                ->warning()
                ->title('Нет выбранных решений для пересчёта')
                ->body('Пересчитывать можно pending-решения типа бренд/категория с product_id.')
                ->send();

            return;
        }

        RunProductCatalogAiReview::dispatch(
            $productIds,
            (int) auth()->id(),
            true,
        );

        $this->selectedDecisionIds = [];
        Notification::make()
            ->success()
            ->title('Выбранные решения поставлены на пересчёт')
            ->body('Товаров: ' . count($productIds))
            ->persistent()
            ->send();
    }

    public function decisionTypeOptions(): array
    {
        return [
            SupplierReviewDecision::DECISION_LINK => $this->decisionLabel(SupplierReviewDecision::DECISION_LINK),
            SupplierReviewDecision::DECISION_UNLINK => $this->decisionLabel(SupplierReviewDecision::DECISION_UNLINK),
            SupplierReviewDecision::DECISION_UPDATE_RETAIL_PRICE => $this->decisionLabel(SupplierReviewDecision::DECISION_UPDATE_RETAIL_PRICE),
            SupplierReviewDecision::DECISION_UPDATE_PRODUCT_CATALOG => $this->decisionLabel(SupplierReviewDecision::DECISION_UPDATE_PRODUCT_CATALOG),
            SupplierReviewDecision::DECISION_IGNORE => $this->decisionLabel(SupplierReviewDecision::DECISION_IGNORE),
        ];
    }

    public function decisionChangeOptions(): array
    {
        return [
            'category' => 'Меняется категория',
            'brand' => 'Меняется бренд',
            'brand_missing' => 'Бренд не найден',
            'duplicate' => 'Есть дубль',
        ];
    }

    public function catalogAuditTypeOptions(): array
    {
        return [
            'suspicious_category' => 'Подозрительная категория',
            'missing_category' => 'Без категории',
            'duplicates' => 'Возможные дубли',
            'missing_brand' => 'Без бренда',
        ];
    }

    public function supplierFilterOptions(): array
    {
        return SupplierReviewDecision::query()
            ->whereNotNull('supplier_code')
            ->where('supplier_code', '!=', '')
            ->distinct()
            ->orderBy('supplier_code')
            ->pluck('supplier_code', 'supplier_code')
            ->all();
    }

    public function applyPendingDecision(int $decisionId): void
    {
        $decision = $this->findPendingDecision($decisionId);

        if (! $decision) {
            $this->notifyDecisionNotFound();
            return;
        }

        try {
            $exitCode = Artisan::call('supplier:apply-review-decisions', [
                '--apply' => true,
                '--id' => [$decision->id],
            ]);

            if ($exitCode !== 0) {
                throw new \RuntimeException(trim(Artisan::output()) ?: 'Команда применения вернула ошибку.');
            }

            Notification::make()
                ->success()
                ->title('Решение применено')
                ->body('ID ' . $decision->id . ': ' . $this->decisionLabel($decision->decision))
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Не удалось применить решение')
                ->body($exception->getMessage())
                ->persistent()
                ->send();
        }
    }

    public function deletePendingDecision(int $decisionId): void
    {
        $decision = $this->findPendingDecision($decisionId);

        if (! $decision) {
            $this->notifyDecisionNotFound();
            return;
        }

        try {
            $exitCode = Artisan::call('supplier:apply-review-decisions', [
                '--apply' => true,
                '--delete' => [$decision->id],
            ]);

            if ($exitCode !== 0) {
                throw new \RuntimeException(trim(Artisan::output()) ?: 'Команда удаления вернула ошибку.');
            }

            Notification::make()
                ->success()
                ->title('Pending-решение удалено')
                ->body('ID ' . $decision->id . ' больше не будет применяться.')
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Не удалось удалить решение')
                ->body($exception->getMessage())
                ->persistent()
                ->send();
        }
    }

    public function supplierSyncSummary(): array
    {
        return [
            'active' => SupplierSync::query()->where('is_active', true)->count(),
            'failed' => SupplierSync::query()->where('last_status', 'failed')->count(),
            'never' => SupplierSync::query()
                ->where(fn ($query) => $query->whereNull('last_run_at')->orWhere('last_status', 'never'))
                ->count(),
        ];
    }

    private function queueCatalogQualityAudit(array $data): void
    {
        $auditType = (string) ($data['audit_type'] ?? 'suspicious_category');
        if (! array_key_exists($auditType, $this->catalogAuditTypeOptions())) {
            $auditType = 'suspicious_category';
        }

        $limit = max(1, min(200, (int) ($data['limit'] ?? 20)));
        $useAi = (bool) ($data['use_ai'] ?? true);

        if ($useAi && ! app(AiContentEnricher::class)->isAvailable()) {
            Notification::make()
                ->danger()
                ->title('AI-провайдер не настроен')
                ->body('Добавьте ANTHROPIC_API_KEY или AI_API_KEY + AI_API_URL + AI_MODEL, либо запустите аудит без AI.')
                ->send();

            return;
        }

        $productIds = $this->catalogAuditProductIds($auditType, $limit);

        if ($productIds === []) {
            Notification::make()
                ->warning()
                ->title('Кандидаты для аудита не найдены')
                ->body('Сценарий: ' . $this->catalogAuditTypeOptions()[$auditType])
                ->send();

            return;
        }

        RunProductCatalogAiReview::dispatch(
            $productIds,
            (int) auth()->id(),
            $useAi,
        );

        Notification::make()
            ->success()
            ->title('AI-аудит каталога поставлен в очередь')
            ->body($this->catalogAuditTypeOptions()[$auditType] . ': товаров в задаче ' . count($productIds) . '. Результат появится в pending-решениях.')
            ->persistent()
            ->send();
    }

    private function queuePendingRecalculation(array $data): void
    {
        $limit = max(1, min(200, (int) ($data['limit'] ?? 50)));
        $useAi = (bool) ($data['use_ai'] ?? true);

        if ($useAi && ! app(AiContentEnricher::class)->isAvailable()) {
            Notification::make()
                ->danger()
                ->title('AI-провайдер не настроен')
                ->body('Добавьте ANTHROPIC_API_KEY или AI_API_KEY + AI_API_URL + AI_MODEL, либо пересчитайте без AI.')
                ->send();

            return;
        }

        $productIds = SupplierReviewDecision::query()
            ->where('status', SupplierReviewDecision::STATUS_PENDING)
            ->where('decision', SupplierReviewDecision::DECISION_UPDATE_PRODUCT_CATALOG)
            ->whereNotNull('product_id')
            ->latest('id')
            ->limit($limit)
            ->pluck('product_id')
            ->map(fn ($productId): int => (int) $productId)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($productIds === []) {
            Notification::make()
                ->warning()
                ->title('Нет pending-решений для пересчёта')
                ->body('Очередь решений по бренду/категории сейчас пустая.')
                ->send();

            return;
        }

        RunProductCatalogAiReview::dispatch(
            $productIds,
            (int) auth()->id(),
            $useAi,
        );

        Notification::make()
            ->success()
            ->title('Пересчёт поставлен в очередь')
            ->body('Решений к пересчёту: ' . count($productIds) . '. Старые подсказки будут обновлены.')
            ->persistent()
            ->send();
    }

    private function catalogAuditProductIds(string $auditType, int $limit): array
    {
        $query = Product::query()
            ->where('is_archived', false)
            ->where('is_active', true);

        match ($auditType) {
            'missing_category' => $query->whereNull('category_id'),
            'missing_brand' => $query->whereNull('brand_id'),
            'duplicates' => $query->where(function ($query): void {
                $query
                    ->whereNotNull('sku')
                    ->where('sku', '!=', '')
                    ->orWhereHas('supplierProducts', fn ($query) => $query
                        ->whereNotNull('supplier_article')
                        ->where('supplier_article', '!=', ''));
            }),
            'suspicious_category' => $query
                ->whereNotNull('category_id')
                ->where(function ($query): void {
                    $query
                        ->whereHas('supplierProducts')
                        ->orWhereNull('brand_id')
                        ->orWhere(fn ($query) => $query
                            ->whereNull('content')
                            ->orWhere('content', '')
                            ->orWhereRaw('CHAR_LENGTH(TRIM(content)) < 80'));
                }),
            default => null,
        };

        return $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function qualityIssueQuery(string $issue)
    {
        $query = Product::query()
            ->where('is_archived', false)
            ->where('is_active', true);

        match ($issue) {
            'without_images' => $query->where(fn ($query) => $query
                ->whereNull('images')
                ->orWhere('images', '')
                ->orWhere('images', '[]')
                ->orWhereRaw('JSON_LENGTH(images) = 0')),
            'without_brand' => $query->whereNull('brand_id'),
            'without_category' => $query->whereNull('category_id'),
            'without_source' => $query->whereDoesntHave('supplierProducts', fn ($query) => $query
                ->whereNotNull('source_url')
                ->where('source_url', '!=', '')),
            default => $query->where(fn ($query) => $query
                ->whereNull('content')
                ->orWhere('content', '')
                ->orWhereRaw('CHAR_LENGTH(TRIM(content)) < 80')),
        };

        return $query;
    }

    private function productQualityProblems(Product $product): array
    {
        $problems = [];

        if (! filled($product->content) || mb_strlen(trim(strip_tags((string) $product->content))) < 80) {
            $problems[] = 'SEO';
        }

        if (! $this->productHasImages($product)) {
            $problems[] = 'Фото';
        }

        if (! $product->brand_id) {
            $problems[] = 'Бренд';
        }

        if (! $product->category_id) {
            $problems[] = 'Категория';
        }

        if ($this->productSourceLabel($product) === '-') {
            $problems[] = 'Источник';
        }

        return $problems;
    }

    private function productHasImages(Product $product): bool
    {
        $images = $product->images;

        if (is_array($images)) {
            return collect($images)->filter(fn ($image): bool => filled($image))->isNotEmpty();
        }

        return filled($images) && $images !== '[]';
    }

    private function productSourceLabel(Product $product): string
    {
        $supplierProduct = $product->supplierProducts
            ->first(fn ($supplierProduct): bool => filled($supplierProduct->source_url));

        if (! $supplierProduct) {
            return '-';
        }

        return $supplierProduct->supplier?->code
            ?: $supplierProduct->supplier?->name
            ?: parse_url((string) $supplierProduct->source_url, PHP_URL_HOST)
            ?: 'source_url';
    }

    private function productsWithoutContent(): int
    {
        return Product::query()
            ->where('is_archived', false)
            ->where(fn ($query) => $query
                ->whereNull('content')
                ->orWhere('content', '')
                ->orWhereRaw('CHAR_LENGTH(TRIM(content)) < 80'))
            ->count();
    }

    private function productsWithoutImages(): int
    {
        return Product::query()
            ->where('is_archived', false)
            ->where(fn ($query) => $query
                ->whereNull('images')
                ->orWhere('images', '')
                ->orWhere('images', '[]')
                ->orWhereRaw('JSON_LENGTH(images) = 0'))
            ->count();
    }

    private function pendingDecisionsCount(): int
    {
        return SupplierReviewDecision::query()
            ->where('status', SupplierReviewDecision::STATUS_PENDING)
            ->count();
    }

    private function decisionQuery(bool $forcePending = false)
    {
        $search = trim($this->decisionSearch);
        $status = $forcePending ? SupplierReviewDecision::STATUS_PENDING : trim($this->decisionStatus);
        $type = trim($this->decisionType);
        $change = trim($this->decisionChange);
        $supplier = trim($this->decisionSupplier);

        return SupplierReviewDecision::query()
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($type !== '', fn ($query) => $query->where('decision', $type))
            ->when($supplier !== '', fn ($query) => $query->where('supplier_code', $supplier))
            ->when($change !== '', function ($query) use ($change): void {
                match ($change) {
                    'category' => $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.changes.category_id')) IS NOT NULL AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.changes.category_id')) != 'null'"),
                    'brand' => $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.changes.brand_id')) IS NOT NULL AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.changes.brand_id')) != 'null'"),
                    'brand_missing' => $query
                        ->whereRaw("(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.current_brand_id')) IS NULL OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.current_brand_id')) = 'null')")
                        ->whereRaw("(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.suggested_brand_id')) IS NULL OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.suggested_brand_id')) = 'null')"),
                    'duplicate' => $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.duplicate_product_id')) IS NOT NULL AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.duplicate_product_id')) != 'null'"),
                    default => null,
                };
            })
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';

                $query->where(function ($query) use ($like, $search): void {
                    $query
                        ->where('supplier_title', 'like', $like)
                        ->orWhere('supplier_article', 'like', $like)
                        ->orWhere('source_url', 'like', $like)
                        ->orWhere('reason', 'like', $like);

                    if (ctype_digit($search)) {
                        $query->orWhere('product_id', (int) $search);
                    }
                });
            });
    }

    private function formatDecisionChange(mixed $current, mixed $suggested, bool $changed): array
    {
        $currentText = filled($current) ? (string) $current : '-';
        $suggestedText = filled($suggested) ? (string) $suggested : '-';

        return [
            'current' => $currentText,
            'suggested' => $suggestedText,
            'suggested_label' => $suggestedText === '-' ? 'Не найдено' : ($changed ? 'Предложено' : 'Без изменения'),
            'changed' => $changed,
            'empty' => $suggestedText === '-',
        ];
    }

    private function formatDuplicate(SupplierReviewDecision $decision, array $payload): array
    {
        if (! filled($payload['duplicate_product_id'] ?? null)) {
            return [
                'exists' => false,
                'id' => null,
                'current_id' => $decision->product_id,
                'current_title' => $decision->supplier_title ?: ('product_id ' . ($decision->product_id ?: '-')),
                'title' => '-',
                'url' => null,
                'reason' => null,
                'pair_label' => null,
                'is_reciprocal' => false,
                'action_hint' => null,
            ];
        }

        $id = (int) $payload['duplicate_product_id'];
        $currentId = (int) $decision->product_id;
        $sku = filled($payload['duplicate_sku'] ?? null) ? (string) $payload['duplicate_sku'] : ('ID ' . $id);
        $name = filled($payload['duplicate_name'] ?? null) ? (string) $payload['duplicate_name'] : null;
        $pairIds = [$currentId, $id];
        sort($pairIds);

        return [
            'exists' => true,
            'id' => $id,
            'current_id' => $currentId,
            'current_title' => $decision->supplier_title ?: ('product_id ' . ($decision->product_id ?: '-')),
            'title' => $name ? ($sku . ' - ' . $name) : $sku,
            'url' => url('/admin/products/' . $id),
            'pair_label' => $pairIds[0] . ' ↔ ' . $pairIds[1],
            'is_reciprocal' => $this->hasReciprocalDuplicateDecision($currentId, $id),
            'reason' => 'Совпало нормализованное название товара.',
            'action_hint' => 'Это сигнал для ручной сверки: открыть обе карточки, выбрать основную, вторую объединить/архивировать или удалить это решение.',
        ];
    }

    private function hasReciprocalDuplicateDecision(int $currentProductId, int $duplicateProductId): bool
    {
        if ($currentProductId <= 0 || $duplicateProductId <= 0) {
            return false;
        }

        return SupplierReviewDecision::query()
            ->where('status', SupplierReviewDecision::STATUS_PENDING)
            ->where('decision', SupplierReviewDecision::DECISION_UPDATE_PRODUCT_CATALOG)
            ->where('product_id', $duplicateProductId)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.duplicate_product_id')) = ?", [(string) $currentProductId])
            ->exists();
    }

    private function normalizedSelectedDecisionIds(): array
    {
        return collect($this->selectedDecisionIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function pendingSelectedDecisionIds(): array
    {
        $ids = $this->normalizedSelectedDecisionIds();
        if ($ids === []) {
            return [];
        }

        return SupplierReviewDecision::query()
            ->whereIn('id', $ids)
            ->where('status', SupplierReviewDecision::STATUS_PENDING)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function findPendingDecision(int $decisionId): ?SupplierReviewDecision
    {
        return SupplierReviewDecision::query()
            ->whereKey($decisionId)
            ->where('status', SupplierReviewDecision::STATUS_PENDING)
            ->first();
    }

    private function notifyDecisionNotFound(): void
    {
        Notification::make()
            ->warning()
            ->title('Pending-решение не найдено')
            ->body('Возможно, его уже применили или удалили.')
            ->send();
    }

    private function notifyNoSelectedPending(): void
    {
        Notification::make()
            ->warning()
            ->title('Нет выбранных pending-решений')
            ->body('Выберите одно или несколько решений со статусом Pending.')
            ->send();
    }

    private function decisionLabel(?string $decision): string
    {
        return match ($decision) {
            SupplierReviewDecision::DECISION_LINK => 'Связать товар',
            SupplierReviewDecision::DECISION_UNLINK => 'Удалить связь',
            SupplierReviewDecision::DECISION_UPDATE_RETAIL_PRICE => 'Обновить розницу',
            SupplierReviewDecision::DECISION_UPDATE_PRODUCT_CATALOG => 'Обновить бренд/категорию',
            SupplierReviewDecision::DECISION_IGNORE => 'Игнорировать',
            default => $decision ?: '-',
        };
    }

    private function decisionStatusLabel(?string $status): string
    {
        return match ($status) {
            SupplierReviewDecision::STATUS_PENDING => 'Ожидает',
            SupplierReviewDecision::STATUS_APPLIED => 'Применено',
            SupplierReviewDecision::STATUS_FAILED => 'Ошибка',
            default => $status ?: '-',
        };
    }
}
