<?php

namespace App\Filament\Resources\CrmCounterparties\Pages;

use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Filament\Resources\CrmCounterparties\CrmCounterpartyResource;
use App\Models\CrmCounterparty;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCrmCounterparty extends ViewRecord
{
    protected static string $resource = CrmCounterpartyResource::class;

    protected string $view = 'filament.resources.crm-counterparties.pages.view-crm-counterparty';

    public function getTitle(): string
    {
        return $this->record->name;
    }

    protected function resolveRecord(int|string $key): CrmCounterparty
    {
        /** @var CrmCounterparty $record */
        $record = parent::resolveRecord($key);

        return $record->load([
            'responsibleUser',
            'b2bClient.user',
            'contactPoints',
            'contacts.contactPoints',
            'commercialDocuments.manager',
            'orders',
            'externalIdentities',
        ])->loadCount([
            'contacts',
            'calls',
            'mailThreads',
            'commercialDocuments',
            'orders',
            'externalIdentities',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CrmCounterpartyResource::createB2bClientAction(),
            CrmCounterpartyResource::openB2bClientAction(),
            Action::make('create_commercial_document')
                ->label('Создать КП')
                ->icon('heroicon-o-document-plus')
                ->color('success')
                ->visible(fn (): bool => ! $this->record->trashed())
                ->action(function (): void {
                    $counterparty = $this->record->load(['contacts.contactPoints', 'contactPoints']);
                    $contact = $counterparty->contacts->firstWhere('is_primary', true) ?? $counterparty->contacts->first();
                    $points = $contact?->contactPoints->isNotEmpty() ? $contact->contactPoints : $counterparty->contactPoints;

                    session()->put('commercial_document_prefill', [
                        'type' => 'quote',
                        'crm_counterparty_id' => $counterparty->id,
                        'crm_contact_id' => $contact?->id,
                        'client_company' => $counterparty->type === 'organization' ? ($counterparty->public_name ?: $counterparty->name) : null,
                        'client_legal_name' => $counterparty->type === 'organization' ? ($counterparty->legal_name ?: $counterparty->name) : null,
                        'client_name' => $contact?->full_name ?: ($counterparty->type === 'person' ? $counterparty->name : null),
                        'client_email' => $points->firstWhere('type', 'email')?->value,
                        'client_phone' => $points->firstWhere('type', 'phone')?->value,
                        'client_unp' => $counterparty->unp,
                        'client_address' => $counterparty->legal_address ?: ($counterparty->actual_address ?: $counterparty->postal_address),
                        'client_default_discount_percent' => (float) $counterparty->commercial_discount_percent,
                        'payment_terms' => $counterparty->commercial_payment_terms ?: 'Безналичный расчёт по выставленному счёту.',
                        'delivery_terms' => $counterparty->commercial_delivery_terms ?: 'Срок и способ поставки согласовываются с менеджером.',
                        'currency' => $counterparty->commercial_currency ?: 'BYN',
                    ]);

                    $this->redirect(CommercialDocumentResource::getUrl('create'));
                }),
            EditAction::make()->visible(fn (): bool => ! $this->record->trashed()),
            Action::make('deleteSpam')
                ->label('Удалить как спам')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === 'lead' && ! $this->record->trashed())
                ->action(function (): void {
                    $this->record->delete();
                    $this->redirect(CrmCounterpartyResource::getUrl('index'));
                }),
            Action::make('restore')
                ->label('Восстановить')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->trashed())
                ->action(fn () => $this->record->restore()),
        ];
    }
}
