<?php

namespace App\Filament\Resources\Products\Actions;

use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Models\CrmMailThread;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class CreateCommercialDocumentAction
{
    public static function make(): Action
    {
        return Action::make('create_commercial_document')
            ->label('Создать КП')
            ->icon('heroicon-o-document-plus')
            ->color('success')
            ->visible(fn (): bool => auth()->user() instanceof User
                && auth()->user()->isManager()
                && CommercialDocumentResource::canCreate())
            ->fillForm(fn (): array => self::initialFormData())
            ->form([
                TextInput::make('quantity')
                    ->label('Количество товара')
                    ->numeric()
                    ->minValue(0.001)
                    ->step(0.001)
                    ->default(1)
                    ->required(),
                Toggle::make('link_to_thread')
                    ->label('Привязать к запросу / сделке')
                    ->helperText('Выключите, если КП создаётся без входящего запроса. Клиента и реквизиты можно заполнить вручную на следующем шаге.')
                    ->live(),
                Select::make('crm_mail_thread_id')
                    ->label('Сделка / переписка с клиентом')
                    ->placeholder('Выберите запрос или недавний лид')
                    ->helperText('Последняя активная сделка предложена автоматически. Проверьте клиента или выберите другую — КП сохранится в выбранной сделке.')
                    ->searchable()
                    ->live()
                    ->required(fn (Get $get): bool => (bool) $get('link_to_thread'))
                    ->visible(fn (Get $get): bool => (bool) $get('link_to_thread'))
                    ->options(fn (): array => self::recentThreadOptions())
                    ->getSearchResultsUsing(fn (string $search): array => self::searchThreadOptions($search))
                    ->getOptionLabelUsing(fn ($value): ?string => self::threadOptionLabel(
                        CrmMailThread::query()->with('counterparty')->find($value),
                    )),
                Toggle::make('create_mail_draft')
                    ->label('После сохранения подготовить письмо с PDF')
                    ->helperText('Черновик ответа появится в выбранной переписке. Перед отправкой его можно проверить и изменить.')
                    ->default(true)
                    ->visible(fn (Get $get): bool => (bool) $get('link_to_thread') && filled($get('crm_mail_thread_id'))),
            ])
            ->modalHeading(fn (Product $record): string => 'Создать КП · '.$record->name)
            ->modalDescription('Товар и его текущая цена будут добавлены в коммерческое предложение. На следующем шаге можно проверить цену, скидку, реквизиты и условия поставки.')
            ->modalSubmitActionLabel('Перейти к оформлению КП')
            ->action(function (Product $record, array $data) {
                abort_unless(auth()->user() instanceof User && auth()->user()->isManager(), 403);

                $linkToThread = (bool) ($data['link_to_thread'] ?? filled($data['crm_mail_thread_id'] ?? null));
                $thread = $linkToThread && filled($data['crm_mail_thread_id'] ?? null)
                    ? CrmMailThread::query()
                        ->with(['counterparty.contacts.contactPoints', 'counterparty.contactPoints', 'contact.contactPoints'])
                        ->findOrFail((int) $data['crm_mail_thread_id'])
                    : null;

                session()->put('commercial_document_product_ids', [$record->getKey()]);
                session()->put('commercial_document_product_quantities', [
                    $record->getKey() => max(0.001, (float) ($data['quantity'] ?? 1)),
                ]);

                if ($thread) {
                    session()->put('commercial_document_prefill', self::prefillFromThread($thread));
                    session()->put('commercial_document_create_mail_draft', (bool) ($data['create_mail_draft'] ?? false));
                } else {
                    session()->forget(['commercial_document_prefill', 'commercial_document_create_mail_draft']);
                }

                return redirect(CommercialDocumentResource::getUrl('create'));
            });
    }

    /** @return array<string, mixed> */
    public static function initialFormData(): array
    {
        $threadId = self::suggestedThreadId();

        return [
            'quantity' => 1,
            'link_to_thread' => filled($threadId),
            'crm_mail_thread_id' => $threadId,
            'create_mail_draft' => true,
        ];
    }

    public static function suggestedThreadId(): ?int
    {
        return self::threadQuery()
            ->potentialDeals()
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->value('id');
    }

    /** @return array<int, string> */
    public static function recentThreadOptions(): array
    {
        return self::threadQuery()
            ->orderByRaw("case when sales_stage in ('new_inquiry', 'qualification', 'proposal_sent', 'negotiation') then 0 else 1 end")
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->mapWithKeys(fn (CrmMailThread $thread): array => [$thread->id => self::threadOptionLabel($thread)])
            ->all();
    }

    /** @return array<int, string> */
    public static function searchThreadOptions(string $search): array
    {
        $search = trim($search);

        return self::threadQuery()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('subject', 'like', "%{$search}%")
                        ->orWhere('participant_name', 'like', "%{$search}%")
                        ->orWhere('participant_email', 'like', "%{$search}%")
                        ->orWhereHas('counterparty', fn (Builder $counterparties): Builder => $counterparties
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('public_name', 'like', "%{$search}%")
                            ->orWhere('legal_name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (CrmMailThread $thread): array => [$thread->id => self::threadOptionLabel($thread)])
            ->all();
    }

    public static function threadOptionLabel(?CrmMailThread $thread): ?string
    {
        if (! $thread) {
            return null;
        }

        $client = $thread->counterparty?->public_name
            ?: $thread->counterparty?->name
            ?: $thread->participant_name
            ?: $thread->participant_email
            ?: 'Клиент не определён';
        $subject = trim((string) $thread->subject) ?: 'Без темы';
        $stage = CrmMailThread::SALES_STAGES[$thread->sales_stage] ?? CrmMailThread::STATUSES[$thread->status] ?? 'Переписка';
        $date = $thread->last_message_at?->format('d.m.Y');

        return collect([$client, $subject, $stage, $date])->filter()->join(' · ');
    }

    /** @return array<string, mixed> */
    public static function prefillFromThread(CrmMailThread $thread): array
    {
        $counterparty = $thread->counterparty;
        $contact = $thread->contact
            ?: $counterparty?->contacts->firstWhere('is_primary', true)
            ?: $counterparty?->contacts->first();
        $points = $contact?->contactPoints->isNotEmpty()
            ? $contact->contactPoints
            : ($counterparty?->contactPoints ?? new Collection);
        $threadEmail = filter_var(trim((string) $thread->participant_email), FILTER_VALIDATE_EMAIL)
            ? trim((string) $thread->participant_email)
            : null;

        return [
            'type' => 'quote',
            'crm_mail_thread_id' => $thread->id,
            'crm_counterparty_id' => $counterparty?->id,
            'crm_contact_id' => $contact?->id,
            'client_company' => $counterparty?->type === 'organization'
                ? ($counterparty->public_name ?: $counterparty->name)
                : null,
            'client_legal_name' => $counterparty?->type === 'organization'
                ? ($counterparty->legal_name ?: $counterparty->name)
                : null,
            'client_name' => $contact?->full_name
                ?: ($counterparty?->type === 'person' ? $counterparty->name : $thread->participant_name),
            'client_email' => $threadEmail ?: $points->firstWhere('type', 'email')?->value,
            'client_phone' => $points->firstWhere('type', 'phone')?->value,
            'client_unp' => $counterparty?->unp,
            'client_address' => $counterparty?->legal_address
                ?: ($counterparty?->actual_address ?: $counterparty?->postal_address),
            'client_default_discount_percent' => (float) ($counterparty?->commercial_discount_percent ?? 0),
            'payment_terms' => $counterparty?->commercial_payment_terms ?: 'Безналичный расчёт по выставленному счёту.',
            'delivery_terms' => $counterparty?->commercial_delivery_terms ?: 'Срок и способ поставки согласовываются с менеджером.',
            'currency' => $counterparty?->commercial_currency ?: 'BYN',
        ];
    }

    private static function threadQuery(): Builder
    {
        return CrmMailThread::query()
            ->with('counterparty')
            ->where('status', '!=', 'spam');
    }
}
