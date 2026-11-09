<?php

namespace App\Livewire;

use App\Filament\Resources\ContactRequests\ContactRequestResource;
use App\Models\ContactRequest;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ContactRequestIndicator extends Component
{
    #[Locked]
    public ?string $alertCursor = null;

    #[Locked]
    public array $shownAtCursor = [];

    public function mount(): void
    {
        if (! $this->canSeeInbox()) {
            return;
        }

        $this->alertCursor = now()->toDateTimeString();
        $this->shownAtCursor = $this->alerts()->where('created_at', $this->alertCursor)->pluck('id')->all();
    }

    public function pollInbox(): void
    {
        if (! $this->canSeeInbox() || $this->alertCursor === null) {
            return;
        }

        $alerts = $this->alerts()->unread()
            ->where(fn (Builder $query) => $query->where('created_at', '>', $this->alertCursor)
                ->orWhere(fn (Builder $sameSecond) => $sameSecond->where('created_at', $this->alertCursor)
                    ->whereNotIn('id', $this->shownAtCursor)))
            ->reorder('created_at')->orderBy('id')->limit(25)->get();

        if ($alerts->isEmpty()) {
            return;
        }

        $cursor = $alerts->last()->created_at->toDateTimeString();
        $this->shownAtCursor = array_merge(
            $cursor === $this->alertCursor ? $this->shownAtCursor : [],
            $alerts->filter(fn (DatabaseNotification $alert): bool => $alert->created_at->toDateTimeString() === $cursor)->modelKeys(),
        );
        $this->alertCursor = $cursor;

        if ($alerts->count() > 3) {
            Notification::make()
                ->title('Новые обращения и сообщения')
                ->icon('heroicon-o-inbox')
                ->iconColor('warning')
                ->duration(10000)
                ->actions([
                    Action::make('openInbox')->label('Открыть входящие')
                        ->url(ContactRequestResource::getUrl('index', ['tab' => 'attention'], panel: 'admin')),
                ])
                ->send();

            return;
        }

        foreach ($alerts as $alert) {
            // The toast needs a separate ID so closing it cannot remove the bell entry.
            $toast = Notification::fromArray($alert->data)->duration(10000);
            foreach ($toast->getActions() as $action) {
                if ($action instanceof Action) {
                    $action->markAsRead(false);
                }
            }
            $toast->send();
        }
    }

    public function render(): View
    {
        $visible = $this->canSeeInbox();

        return view('livewire.contact-request-indicator', [
            'visible' => $visible,
            'count' => $visible ? ContactRequest::query()->needsAttention()->count() : 0,
        ]);
    }

    private function canSeeInbox(): bool
    {
        return auth()->check() && ContactRequestResource::canViewAny() && ContactRequest::inboxStateAvailable();
    }

    private function alerts(): MorphMany
    {
        return auth()->user()->notifications()->where('data->viewData->inboxAlert', true);
    }
}
