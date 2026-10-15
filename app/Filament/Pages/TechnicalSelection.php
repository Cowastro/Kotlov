<?php

namespace App\Filament\Pages;

use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Models\Product;
use App\Services\Market\MarketWebResearchService;
use App\Services\TechnicalProductSelection;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Throwable;

class TechnicalSelection extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'AI-подбор';

    protected static ?string $title = 'AI-подбор по задаче';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.technical-selection';

    public string $prompt = '';

    #[Locked]
    public array $result = [];

    #[Locked]
    public array $externalResult = [];

    public array $selected = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isManager() === true;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Каталог';
    }

    public function search(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validate(['prompt' => 'required|string|min:5|max:6000']);
        if (! $this->allowRequest()) {
            return;
        }
        $this->result = $this->externalResult = $this->selected = [];
        try {
            $this->result = app(TechnicalProductSelection::class)->search($this->prompt);
        } catch (Throwable $e) {
            report($e);
            Notification::make()->warning()->title('Подбор не выполнен')
                ->body($e::class === \RuntimeException::class ? $e->getMessage() : 'Сервис временно недоступен. Повторите позже.')->send();
        }
    }

    public function searchOutside(): void
    {
        abort_unless(static::canAccess(), 403);
        if ($this->result === [] || ! $this->allowRequest()) {
            return;
        }
        $this->externalResult = [];
        try {
            $this->externalResult = app(MarketWebResearchService::class)->researchQuery($this->result['request']);
        } catch (Throwable $e) {
            report($e);
            Notification::make()->warning()->title('Поиск временно недоступен')->send();
        }
    }

    public function createOffer(): mixed
    {
        abort_unless(static::canAccess() && CommercialDocumentResource::canCreate(), 403);
        $allowed = collect($this->result['matches'] ?? [])->pluck('product_id')->filter()->all();
        $ids = Product::query()->whereIn('id', array_intersect($allowed, array_map('intval', $this->selected)))
            ->where('is_active', true)->where('is_archived', false)->pluck('id')->all();
        if ($ids === []) {
            Notification::make()->warning()->title('Выберите товары из результатов подбора')->send();

            return null;
        }
        session()->put('commercial_document_product_ids', $ids);
        session()->put('commercial_document_product_quantities', array_fill_keys($ids, 1));
        session()->forget(['commercial_document_prefill', 'commercial_document_create_mail_draft']);

        return redirect(CommercialDocumentResource::getUrl('create'));
    }

    private function allowRequest(): bool
    {
        $key = 'technical-selection:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 6)) {
            Notification::make()->warning()->title('Слишком много запросов. Повторите через минуту.')->send();

            return false;
        }
        RateLimiter::hit($key, 60);

        return true;
    }
}
