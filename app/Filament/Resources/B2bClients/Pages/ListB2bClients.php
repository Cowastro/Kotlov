<?php

namespace App\Filament\Resources\B2bClients\Pages;

use App\Filament\Resources\B2bClients\B2bClientResource;
use App\Models\B2bClient;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListB2bClients extends ListRecords
{
    protected static string $resource = B2bClientResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getTabs(): array
    {
        $count = fn (string $status): int => B2bClient::query()->where('status', $status)->count();

        return [
            'pending' => Tab::make('Новые заявки')
                ->icon('heroicon-o-inbox')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'pending'))
                ->badge($count('pending')),
            'approved' => Tab::make('Активные партнёры')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'approved'))
                ->badge($count('approved')),
            'paused' => Tab::make('Приостановлены')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'paused'))
                ->badge($count('paused')),
            'rejected' => Tab::make('Отклонены')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'rejected'))
                ->badge($count('rejected')),
            'all' => Tab::make('Все')
                ->badge(B2bClient::query()->count()),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Добавить B2B-клиента')];
    }
}
