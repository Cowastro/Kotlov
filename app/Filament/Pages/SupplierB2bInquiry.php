<?php

namespace App\Filament\Pages;

use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\CrmMail\CrmMailSender;
use App\Services\Market\MarketResearchSupplierInquiry;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Throwable;

class SupplierB2bInquiry extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static ?string $navigationLabel = 'B2B-заявка поставщику';

    protected static ?string $title = 'B2B-заявка поставщику';

    protected static ?string $slug = 'supplier-b2b-inquiries';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.supplier-b2b-inquiry';

    public static function canAccess(): bool
    {
        return auth()->user()?->isManager() === true;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Каталог';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_b2b_inquiry')
                ->label('Создать B2B-заявку')
                ->icon(Heroicon::OutlinedPlus)
                ->color('primary')
                ->modalWidth('4xl')
                ->modalHeading('Запросить партнёрскую цену и наличие')
                ->modalDescription('Выберите товар и поставщика из базы либо укажите произвольную компанию и email.')
                ->modalSubmitActionLabel('Создать и отправить запрос')
                ->fillForm(fn (): array => $this->defaultFormData())
                ->schema([
                    Select::make('product_id')
                        ->label('Товар')
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => Product::query()
                            ->where('is_archived', false)
                            ->where(function (Builder $query) use ($search): void {
                                $query->where('name', 'like', '%'.$search.'%')
                                    ->orWhere('sku', 'like', '%'.$search.'%');
                            })
                            ->orderBy('name')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (Product $product): array => [
                                $product->id => $this->productLabel($product),
                            ])->all())
                        ->getOptionLabelUsing(fn (mixed $value): ?string => $this->productOptionLabel($value))
                        ->live()
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            $product = Product::query()->find($state);
                            $service = app(MarketResearchSupplierInquiry::class);
                            $set('subject', $service->subjectForProduct($product));
                            $set('body', $service->bodyForProduct($product));
                        }),
                    Select::make('supplier_id')
                        ->label('Поставщик из базы')
                        ->options(fn (): array => app(MarketResearchSupplierInquiry::class)->supplierOptions())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->placeholder('Необязательно')
                        ->helperText('Если нужной компании нет, оставьте поле пустым и укажите её данные ниже.')
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            $supplier = Supplier::query()->with('crmCounterparty.contactPoints')->find($state);
                            $set('recipient', $supplier?->contactEmailAddresses()[0] ?? '');
                            $set('participant_name', $supplier?->name ?? '');
                        }),
                    Select::make('mailbox_id')
                        ->label('Отправить из ящика')
                        ->options(fn (): array => CrmMailbox::query()->where('is_enabled', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->required(),
                    TextInput::make('recipient')
                        ->label('Email поставщика')
                        ->email()
                        ->required(),
                    TextInput::make('participant_name')
                        ->label('Компания или контакт')
                        ->placeholder('Необязательно')
                        ->maxLength(255),
                    TagsInput::make('cc')
                        ->label('Копия (CC)')
                        ->placeholder('copy@example.com')
                        ->helperText('После каждого адреса нажмите Enter.'),
                    Toggle::make('attach_russian_representative')
                        ->label('Приложить реквизиты ООО «МПГ» (РФ)')
                        ->helperText('Для российского поставщика к письму добавится PDF с реквизитами представительства для выставления счёта и поставки в РФ.')
                        ->default(false),
                    TextInput::make('subject')
                        ->label('Тема')
                        ->required()
                        ->maxLength(255),
                    Textarea::make('body')
                        ->label('Текст запроса')
                        ->rows(12)
                        ->required()
                        ->maxLength(30000),
                    Toggle::make('send_now')
                        ->label('Отправить сразу')
                        ->helperText('Если выключить, письмо сохранится в почте как черновик для проверки.')
                        ->default(true),
                ])
                ->action(function (array $data) {
                    try {
                        $product = Product::query()->findOrFail((int) $data['product_id']);
                        $supplier = filled($data['supplier_id'] ?? null)
                            ? Supplier::query()->find((int) $data['supplier_id'])
                            : null;
                        $mailbox = CrmMailbox::query()->findOrFail((int) $data['mailbox_id']);
                        $user = auth()->user();
                        $thread = app(MarketResearchSupplierInquiry::class)->createStandaloneDraft(
                            $product,
                            $supplier,
                            $mailbox,
                            $user,
                            (string) $data['recipient'],
                            (array) ($data['cc'] ?? []),
                            $data['participant_name'] ?? null,
                            (string) $data['subject'],
                            (string) $data['body'],
                            (bool) ($data['attach_russian_representative'] ?? false),
                        );

                        if ((bool) ($data['send_now'] ?? false)) {
                            app(CrmMailSender::class)->sendDraft($thread, $user);
                            Notification::make()->success()->title('B2B-заявка отправлена')->body($supplier?->name ?: (string) $data['recipient'])->send();
                        } else {
                            Notification::make()->success()->title('Черновик B2B-заявки создан')->body($supplier?->name ?: (string) $data['recipient'])->send();
                        }

                        return redirect(CrmMailThreadResource::getUrl('view', ['record' => $thread]));
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()->danger()->title('Не удалось подготовить B2B-заявку')->body($exception->getMessage())->send();

                        return null;
                    }
                }),
        ];
    }

    /** @return array<string, mixed> */
    private function defaultFormData(): array
    {
        $mailbox = CrmMailbox::query()->where('is_enabled', true)->first() ?? CrmMailbox::query()->first();
        $service = app(MarketResearchSupplierInquiry::class);

        return [
            'product_id' => null,
            'supplier_id' => null,
            'mailbox_id' => $mailbox?->id,
            'recipient' => '',
            'participant_name' => '',
            'cc' => [],
            'attach_russian_representative' => false,
            'subject' => $service->subjectForProduct(null),
            'body' => $service->bodyForProduct(null),
            'send_now' => true,
        ];
    }

    private function productOptionLabel(mixed $value): ?string
    {
        $product = Product::query()->find($value);

        return $product ? $this->productLabel($product) : null;
    }

    private function productLabel(Product $product): string
    {
        return $product->name.(filled($product->sku) ? ' · '.$product->sku : '');
    }

    #[Computed]
    public function recentInquiries()
    {
        return CrmMailMessage::query()
            ->with('thread')
            ->whereIn('metadata->source', ['supplier_b2b_inquiry', 'market_research_b2b_inquiry'])
            ->latest('id')
            ->limit(30)
            ->get();
    }

    public function threadUrl(int $threadId): string
    {
        return CrmMailThreadResource::getUrl('view', ['record' => $threadId]);
    }
}
