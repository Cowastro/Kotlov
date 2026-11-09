<?php

namespace App\Livewire;

use App\Filament\Resources\ContactRequests\ContactRequestResource;
use App\Models\ContactRequest;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ContactRequestIndicator extends Component
{
    public function render(): View
    {
        $visible = ContactRequestResource::canViewAny() && ContactRequest::inboxStateAvailable();

        return view('livewire.contact-request-indicator', [
            'visible' => $visible,
            'count' => $visible ? ContactRequest::query()->needsAttention()->count() : 0,
        ]);
    }
}
