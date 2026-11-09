<?php

namespace App\Filament\Pages;

use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Models\Product;
use App\Services\CommercialDocuments\RequestLineSelection;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

/**
 * «Подбор по заявке»: a customer's purchase request (scan, PDF, photo or pasted text) → a catalogue product per
 * line (in stock first, then cheaper) → the quote form filled with these products and quantities at retail price.
 */
class RequestSelection extends Page
{
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static ?string $navigationLabel = 'Подбор по заявке';

    protected static ?string $title = 'Подбор по заявке клиента';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.request-selection';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $requestFile = null;

    public string $text = '';

    #[Locked]
    public string $method = '';

    /** Lines with the AI choice; the manager changes product_id and quantity. */
    #[Locked]
    public array $lines = [];

    /** line index => chosen product id (0 = skip the line). */
    public array $choice = [];

    /** line index => quantity. */
    public array $quantity = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isManager() === true;
    }

    /** Opened from «КП и счета» (the list and the create form), not a menu item of its own. */
    protected static bool $shouldRegisterNavigation = false;

    public function getBreadcrumbs(): array
    {
        return [\App\Filament\Resources\CommercialDocuments\CommercialDocumentResource::getUrl('index') => 'КП и счета', 'Подбор по заявке'];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Продажи';
    }

    public function select(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validate([
            'requestFile' => 'nullable|file|max:15360|mimes:pdf,jpg,jpeg,png,webp',
            'text' => 'nullable|string|max:12000',
        ]);
        if (! $this->requestFile && mb_strlen(trim($this->text)) < 5) {
            Notification::make()->warning()->title('Загрузите файл заявки или вставьте её текст')->send();

            return;
        }
        if (! $this->allowRequest()) {
            return;
        }

        try {
            $service = app(RequestLineSelection::class);
            $recognized = $this->requestFile
                ? $service->recognize($this->requestFile->getRealPath(), $this->requestFile->getClientOriginalExtension())
                : $service->recognize(null, null, $this->text);
            $this->method = $recognized['method'];
            $this->lines = $service->select($recognized['lines']);
            $this->choice = collect($this->lines)->map(fn (array $line): int => (int) ($line['product_id'] ?? 0))->all();
            $this->quantity = collect($this->lines)->map(fn (array $line): float => (float) $line['quantity'])->all();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->warning()->title('Подбор не выполнен')
                ->body($exception instanceof RuntimeException ? $exception->getMessage() : 'Сервис временно недоступен. Повторите позже.')
                ->send();
        }
    }

    public function createOffer(): mixed
    {
        abort_unless(static::canAccess() && CommercialDocumentResource::canCreate(), 403);
        $quantities = [];
        foreach ($this->lines as $index => $line) {
            $productId = (int) ($this->choice[$index] ?? 0);
            // Only a product that was offered for this line can reach the quote.
            if ($productId <= 0 || ! isset($line['candidates'][$productId])) {
                continue;
            }
            $quantities[$productId] = ($quantities[$productId] ?? 0) + max(0.001, (float) ($this->quantity[$index] ?? $line['quantity']));
        }
        $ids = Product::query()->whereIn('id', array_keys($quantities))
            ->where('is_active', true)->where('is_archived', false)->pluck('id')->all();
        if ($ids === []) {
            Notification::make()->warning()->title('Нет выбранных товаров для КП')->send();

            return null;
        }

        session()->put('commercial_document_product_ids', $ids);
        session()->put('commercial_document_product_quantities', array_intersect_key($quantities, array_flip($ids)));
        session()->forget(['commercial_document_prefill', 'commercial_document_create_mail_draft']);

        return redirect(CommercialDocumentResource::getUrl('create'));
    }

    public function startNew(): void
    {
        $this->reset(['requestFile', 'text', 'method', 'lines', 'choice', 'quantity']);
    }

    public function total(): float
    {
        $sum = 0.0;
        foreach ($this->lines as $index => $line) {
            $product = $line['candidates'][(int) ($this->choice[$index] ?? 0)] ?? null;
            if ($product) {
                $sum += $product['price'] * (float) ($this->quantity[$index] ?? 0);
            }
        }

        return round($sum, 2);
    }

    private function allowRequest(): bool
    {
        $key = 'request-selection:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 6)) {
            Notification::make()->warning()->title('Слишком много запросов. Повторите через минуту.')->send();

            return false;
        }
        RateLimiter::hit($key, 60);

        return true;
    }
}
