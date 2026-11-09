<?php

namespace App\Livewire;

use App\Filament\Resources\ContactRequests\ContactRequestResource;
use App\Models\ContactRequest;
use App\Models\SupportConversation;
use Illuminate\Support\Facades\DB;
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
        if ($this->lead->isSiteChat() && preg_match('/^site:(\d+)$/', (string) $this->lead->last_external_event_id, $match)) {
            $this->lead->siteChat?->messages()
                ->where('sender_type', 'visitor')
                ->where('id', '<=', (int) $match[1])
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }
        $this->lead->clearInboxAttention();
        unset($this->lead);
    }

    public function sendReply(LeadInbox $inbox): void
    {
        $lead = $this->lead;
        ContactRequestResource::authorizeEdit($lead);
        $this->draft = trim($this->draft);
        $this->validate(['draft' => ['required', 'string', $lead->isSiteChat() ? 'max:2000' : 'max:1000']], [], ['draft' => 'ответ']);

        if ($lead->isSiteChat()) {
            $sent = $this->updateSiteChat(function (SupportConversation $chat): bool {
                if ($chat->status === 'closed') {
                    return false;
                }
                $now = now();
                $chat->messages()->where('sender_type', 'visitor')->whereNull('read_at')->update(['read_at' => $now]);
                $chat->messages()->create([
                    'sender_type' => 'manager',
                    'sender_id' => auth()->id(),
                    'body' => $this->draft,
                ]);
                $chat->update([
                    'status' => 'answered',
                    'assigned_user_id' => auth()->id(),
                    'last_message_at' => $now,
                    'last_manager_message_at' => $now,
                ]);

                return true;
            });
            if ($sent) {
                $this->draft = '';
                $this->historyPage = 1;
            } else {
                $this->addError('draft', 'Диалог закрыт или недоступен. Текст ответа сохранён.');
            }

            return;
        }

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

    public function closeSiteChat(): void
    {
        $this->updateSiteChat(fn (SupportConversation $chat): bool => $chat->update(['status' => 'closed']));
    }

    public function reopenSiteChat(): void
    {
        $this->updateSiteChat(fn (SupportConversation $chat): bool => $chat->status === 'closed' && $chat->update(['status' => 'new']));
    }

    private function updateSiteChat(callable $update): bool
    {
        $lead = $this->lead;
        ContactRequestResource::authorizeEdit($lead);
        abort_unless($lead->isSiteChat(), 404);

        $result = DB::transaction(function () use ($lead, $update): bool {
            $chat = $lead->siteChat()->lockForUpdate()->first();
            $active = ContactRequest::query()->whereKey($lead->id)->whereNull('archived_at')->lockForUpdate()->first();

            return $chat && $active && $update($chat);
        }, 3);

        unset($this->lead);

        return $result;
    }

    public function render(): View
    {
        $lead = $this->lead;
        $canEdit = ContactRequestResource::canEdit($lead);
        $chat = $lead->isSiteChat() ? $lead->siteChat : null;
        $messages = $chat
            ? $chat->messages()->with('sender')->reorder('id', 'desc')
            : $lead->leadMessages()->with('user')->latest('id');

        return view('livewire.contact-request-dialog', [
            'lead' => $lead,
            'chat' => $chat,
            'history' => $messages->simplePaginate(30, page: $this->historyPage),
            'canEdit' => $canEdit,
            'canReply' => $canEdit && ($lead->isSiteChat()
                ? $chat && $chat->status !== 'closed' && ! $lead->archived_at
                : app(LeadInbox::class)->replyChannel($lead) !== null),
        ]);
    }
}
