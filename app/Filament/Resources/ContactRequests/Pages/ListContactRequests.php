<?php

namespace App\Filament\Resources\ContactRequests\Pages;

use App\Filament\Pages\LeadChannelSettings;
use App\Filament\Resources\ContactRequests\ContactRequestResource;
use App\Models\ContactRequest;
use Filament\Actions\Action;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListContactRequests extends ListRecords
{
    protected static string $resource = ContactRequestResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('channelSettings')
                ->label('Каналы обращений')
                ->icon('heroicon-o-cog-6-tooth')
                ->visible(fn (): bool => LeadChannelSettings::canAccess())
                ->url(fn (): string => LeadChannelSettings::getUrl()),
        ];
    }

    public function getTabs(): array
    {
        if (! ContactRequest::inboxStateAvailable()) {
            return [];
        }

        return [
            'attention' => Tab::make('Требуют реакции')
                ->modifyQueryUsing(fn (Builder $query) => $query->needsAttention())
                ->excludeQueryWhenResolvingRecord()
                ->badge(ContactRequest::query()->needsAttention()->count() ?: null)
                ->badgeColor('danger'),
            'active' => Tab::make('Активные')
                ->modifyQueryUsing(fn (Builder $query) => $query->activeInbox())
                ->excludeQueryWhenResolvingRecord(),
            'archive' => Tab::make('Архив')
                ->modifyQueryUsing(fn (Builder $query) => $query->archivedInbox())
                ->excludeQueryWhenResolvingRecord(),
        ];
    }
}
