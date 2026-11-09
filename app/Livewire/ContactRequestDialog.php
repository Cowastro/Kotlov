<?php

namespace App\Livewire;

use App\Filament\Resources\ContactRequests\ContactRequestResource;
use App\Models\ContactRequest;
use App\Services\LeadInbox;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ContactRequestDialog extends Component
{
    #[Locked]
    public int $contactRequestId;

    #[Locked]
    public int $historyPage = 1;

    #[Locked]
    public string $sendKey;

    public string $draft = '';

    public function mount(): void
    {
        $this->sendKey = (string) Str::uuid();
    }

    #[Computed]
    public function lead(): ContactRequest
    {
        $lead = ContactRequest::query()->findOrFail($this->contactRequestId);
        ContactRequestResource::authorizeView($lead);

        return $lead;
    }

    public function olderMessages(): void
    {
        $this->historyPage++;
    }

    public function newerMessages(): void
    {
        $this->historyPage = max(1, $this->historyPage - 1);
    }

    public function markSeen(): void
    {
        ContactRequestResource::authorizeEdit($this->lead);
        $this->lead->clearInboxAttention();
        unset($this->lead);
    }

    public function sendReply(LeadInbox $inbox): void
    {
        $lead = $this->lead;
        ContactRequestResource::authorizeEdit($lead);
        $this->draft = trim($this->draft);
        $this->validate(['draft' => ['required', 'string', 'max:1000']], [], ['draft' => 'ответ']);

        $message = $inbox->reply($lead, auth()->id(), $this->draft, $this->sendKey);
        if (! $message) {
            $this->addError('draft', 'Этот канал сейчас недоступен для ответов.');

            return;
        }

        $this->historyPage = 1;
        $this->sendKey = (string) Str::uuid();
        if ($message->state === 'failed') {
            $this->addError('draft', 'Канал отклонил ответ. Текст остался в поле.');
        } else {
            $this->draft = '';
            if ($message->state === 'sent') {
                $lead->clearInboxAttention();
            }
        }
        unset($this->lead);
    }

    public function render(): View
    {
        $lead = $this->lead;
        $canEdit = ContactRequestResource::canEdit($lead);

        return view('livewire.contact-request-dialog', [
            'lead' => $lead,
            'history' => $lead->leadMessages()->with('user')->latest('id')->simplePaginate(30, page: $this->historyPage),
            'canEdit' => $canEdit,
            'canReply' => $canEdit && app(LeadInbox::class)->replyChannel($lead) !== null,
        ]);
    }
}
