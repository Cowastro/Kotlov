<div @if ($visible) wire:poll.10s="pollInbox" @endif>
    @if ($visible)
        <div aria-live="polite">
            <x-filament::button tag="a" size="sm" :color="$count ? 'danger' : 'gray'" icon="heroicon-o-inbox"
                :href="\App\Filament\Resources\ContactRequests\ContactRequestResource::getUrl('index', ['tab' => 'attention'], panel: 'admin')">
                {{ $count ? 'Входящие · '.$count : 'Входящие' }}
            </x-filament::button>
        </div>
    @endif
</div>
