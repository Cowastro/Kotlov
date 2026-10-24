<?php

namespace App\Filament\Resources\ChimneyCalculationRequests\Tables;

use App\Models\ChimneyCalculationRequest;
use App\Support\AdminPhoneLinks;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ChimneyCalculationRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('number')->label('Расчёт')->searchable()->sortable()->weight('bold'),
            TextColumn::make('customer_name')->label('Клиент')->searchable(),
            TextColumn::make('customer_phone')->label('Телефон')->searchable()->copyable()
                ->formatStateUsing(fn (?string $state): ?HtmlString => AdminPhoneLinks::render($state))->html(),
            TextColumn::make('status')->label('Статус')->badge()
                ->formatStateUsing(fn ($state) => ChimneyCalculationRequest::STATUSES[$state] ?? $state)
                ->color(fn ($state) => match ($state) {
                    'pending' => 'warning', 'converted' => 'success', 'cancelled' => 'danger', default => 'gray'
                }),
            TextColumn::make('total')->label('Итого')->money('BYN')->sortable(),
            TextColumn::make('discount_percent')->label('Скидка')->suffix('%')->toggleable(),
            TextColumn::make('order.number')->label('Заказ')->placeholder('—')->url(fn ($record) => $record->order_id ? url('/admin/orders/'.$record->order_id) : null),
            TextColumn::make('created_at')->label('Получен')->dateTime('d.m.Y H:i', 'Europe/Minsk')->sortable(),
        ])->defaultSort('created_at', 'desc')
            ->filters([SelectFilter::make('status')->label('Статус')->options(ChimneyCalculationRequest::STATUSES)])
            ->recordActions([ViewAction::make()]);
    }
}
