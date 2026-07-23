<?php

namespace App\Filament\Resources\ContactRequests\Pages;

use App\Filament\Resources\ContactRequests\ContactRequestResource;
use App\Models\ContactRequest;
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

    public function getTabs(): array
    {
        $colors = [
            'new' => 'info',
            'read' => 'warning',
            'done' => 'success',
        ];

        $tabs = [
            'all' => Tab::make('Все')
                ->badge(ContactRequest::count()),
        ];

        foreach (ContactRequest::$statuses as $status => $label) {
            $count = ContactRequest::where('status', $status)->count();

            $tabs[$status] = Tab::make($label)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status))
                ->badge($count ?: null)
                ->badgeColor($colors[$status] ?? 'gray');
        }

        return $tabs;
    }
}
