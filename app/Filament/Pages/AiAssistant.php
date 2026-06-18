<?php

namespace App\Filament\Pages;

use App\Jobs\RunProductCatalogAiReview;
use App\Models\Product;
use App\Models\SupplierReviewDecision;
use App\Models\SupplierSync;
use App\Services\AiContentEnricher;
use BackedEnum;
use Filament\Actions\Action;
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
            Action::make('audit_missing_brand')
                ->label('AI-аудит без бренда')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->form([
                    TextInput::make('limit')
                        ->label('Сколько товаров проверить')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->default(20)
                        ->required(),
                    Toggle::make('use_ai')
                        ->label('Использовать DeepSeek/AI')
                        ->helperText('Если выключить, будут использованы только локальные правила.')
                        ->default(true),
                ])
                ->requiresConfirmation()
                ->modalHeading('Запустить AI-аудит товаров без бренда')
                ->modalDescription('Товары не изменятся. Будут созданы pending-решения для проверки.')
                ->action(fn (array $data) => $this->queueMissingBrandAudit($data)),
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

    public function pendingDecisions()
    {
        return SupplierReviewDecision::query()
            ->where('status', SupplierReviewDecision::STATUS_PENDING)
            ->latest('id')
            ->paginate(10, ['*'], 'decisionsPage')
            ->through(fn (SupplierReviewDecision $decision): array => [
                'id' => $decision->id,
                'decision' => $this->decisionLabel($decision->decision),
                'supplier' => $decision->supplier_code ?: '-',
                'title' => $decision->supplier_title ?: ('product_id ' . ($decision->product_id ?: '-')),
                'product_id' => $decision->product_id,
                'product_url' => $decision->product_id ? url('/admin/products/' . $decision->product_id) : null,
                'reason' => $decision->reason ?: '-',
            ]);
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

    private function queueMissingBrandAudit(array $data): void
    {
        $limit = max(1, min(100, (int) ($data['limit'] ?? 20)));
        $useAi = (bool) ($data['use_ai'] ?? true);

        if ($useAi && ! app(AiContentEnricher::class)->isAvailable()) {
            Notification::make()
                ->danger()
                ->title('AI-провайдер не настроен')
                ->body('Добавьте ANTHROPIC_API_KEY или AI_API_KEY + AI_API_URL + AI_MODEL, либо запустите аудит без AI.')
                ->send();

            return;
        }

        $productIds = Product::query()
            ->where('is_archived', false)
            ->whereNull('brand_id')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all();

        if ($productIds === []) {
            Notification::make()
                ->warning()
                ->title('Товары без бренда не найдены')
                ->body('Очередь для этого сценария сейчас пустая.')
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
            ->title('AI-аудит поставлен в очередь')
            ->body('Товаров в задаче: ' . count($productIds) . '. Результат появится в pending-решениях.')
            ->persistent()
            ->send();
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
}
