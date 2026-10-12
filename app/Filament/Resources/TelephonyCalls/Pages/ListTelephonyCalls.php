<?php

namespace App\Filament\Resources\TelephonyCalls\Pages;

use App\Filament\Resources\TelephonyCalls\TelephonyCallResource;
use App\Filament\Widgets\TelephonyOverview;
use App\Models\TelephonyCall;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListTelephonyCalls extends ListRecords
{
    protected static string $resource = TelephonyCallResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderWidgets(): array
    {
        return [TelephonyOverview::class];
    }

    public function getSubheading(): ?string
    {
        return 'Входящие и исходящие звонки, пропущенные лиды, ответственные, клиенты и связанные заказы.';
    }

    public function getTabs(): array
    {
        return [
            'attention' => Tab::make('Нужна реакция')->icon('heroicon-o-exclamation-triangle')
                ->modifyQueryUsing(fn (Builder $query) => $query->needsAttention())
                ->badge(TelephonyCall::query()->needsAttention()->count() ?: null)->badgeColor('danger'),
            'missed' => Tab::make('Пропущенные')->icon('heroicon-o-phone-x-mark')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'missed'))
                ->badge(TelephonyCall::query()->where('status', 'missed')->count() ?: null)->badgeColor('danger'),
            'mine' => Tab::make('Мои')->icon('heroicon-o-user')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('assigned_user_id', auth()->id()))
                ->badge(TelephonyCall::query()->where('assigned_user_id', auth()->id())->count() ?: null),
            'all' => Tab::make('Все')->badge(TelephonyCall::query()->count()),
        ];
    }
}
