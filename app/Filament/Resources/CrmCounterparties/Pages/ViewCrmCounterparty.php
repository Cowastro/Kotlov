<?php

namespace App\Filament\Resources\CrmCounterparties\Pages;

use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Filament\Resources\CrmCounterparties\CrmCounterpartyResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCrmCounterparty extends ViewRecord
{
    protected static string $resource = CrmCounterpartyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_commercial_document')
                ->label('Создать КП')
                ->icon('heroicon-o-document-plus')
                ->color('success')
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
                    ]);

                    $this->redirect(CommercialDocumentResource::getUrl('create'));
                }),
            EditAction::make(),
        ];
    }
}
