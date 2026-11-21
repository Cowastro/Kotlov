<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Models\Order;
use App\Services\Integrations\OneCBridge\OneCOrderMatcher;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * «Связать с заказом 1С»: a site order a manager already entered in 1C by hand gets the link to that 1C order and is
 * never sent to 1C again. The site suggests 1C orders by phone, sum, items and company; the manager chooses.
 */
class OneCOrderLinkAction
{
    public static function make(): Action
    {
        return Action::make('onecLink')
            ->label(fn (Order $record): string => 'Уже есть в 1С?'.(($n = count(app(OneCOrderMatcher::class)->candidates($record, 3))) > 0 ? ' ('.$n.')' : ''))
            ->icon('heroicon-o-link')
            ->color(fn (Order $record): string => app(OneCOrderMatcher::class)->candidates($record, 1) !== [] ? 'warning' : 'gray')
            ->visible(fn (Order $record): bool => (bool) auth()->user()?->isManager() && (blank($record->onec_order_guid) || $record->onec_deleted))
            ->modalHeading('Связать с заказом, который уже заведён в 1С')
            ->modalDescription(new HtmlString('Связанный заказ сайта <b>не передаётся</b> в 1С повторно, его статус и «проведён» берутся из 1С. Заказы 1С сайт читает каждый час (последние 3 дня) и каждое утро (60 дней).'))
            ->modalSubmitActionLabel('Связать')
            ->schema(fn (Order $record): array => [
                Radio::make('candidate')->label('Похожие заказы 1С')
                    ->options(collect(app(OneCOrderMatcher::class)->candidates($record))->mapWithKeys(fn (array $c): array => [$c['ref']->ref_key => $c['label']])->all())
                    ->visible(fn (): bool => app(OneCOrderMatcher::class)->candidates($record, 1) !== []),
                Select::make('manual')->label('Или найдите заказ 1С по номеру')->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => app(OneCOrderMatcher::class)->search($search))
                    ->helperText('Например: МПУТ-000756 или 002330'),
            ])
            ->action(function (Order $record, array $data): void {
                $key = $data['manual'] ?? null ?: ($data['candidate'] ?? null);
                if (! $key) {
                    Notification::make()->warning()->title('Выберите заказ 1С')->send();

                    return;
                }
                try {
                    app(OneCOrderMatcher::class)->link($record, $key, auth()->user());
                    Notification::make()->success()->title('Заказ связан с 1С: '.$record->fresh()->onec_order_number)->send();
                } catch (DomainException $e) {
                    Notification::make()->danger()->title('Не связан')->body($e->getMessage())->send();
                }
            });
    }
}
