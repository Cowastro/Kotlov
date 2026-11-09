@php($dialogLead = $lead ?? $getRecord())

@livewire(\App\Livewire\ContactRequestDialog::class, ['contactRequestId' => $dialogLead->id], key(($placement ?? 'record').'-dialog-'.$dialogLead->id))
