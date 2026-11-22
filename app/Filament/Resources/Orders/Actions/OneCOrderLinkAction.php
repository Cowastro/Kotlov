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
 * «Найден в 1С: МПУТ-000758»: the order a manager already entered in 1C by hand. The site found it by phone, sum,
 * items, company; the manager links it (it is never sent to 1C again) or, when it is a different order, creates a new
 * one. $nested: the same window opened from «Создать заказ в 1С» («Уже есть в 1С — связать») to link by number.
 */
class OneCOrderLinkAction
{
    public static function make(string $name = 'onecLink', bool $nested = false): Action
    {
        return Action::make($name)
            ->label(fn (Order $record): string => $nested ? 'Уже есть в 1С — связать'
                : 'Найден в 1С: '.(app(OneCOrderMatcher::class)->found($record)['ref']->description ?? ''))
            ->icon('heroicon-o-link')
            ->color($nested ? 'gray' : 'warning')
            ->visible(fn (Order $record): bool => OneCOrderExportAction::canManage() && ($nested || OneCOrderExportAction::foundShown($record)))
            ->modalHeading(fn (Order $record): string => $nested ? 'Связать с заказом, заведённым в 1С' : 'Этот заказ уже есть в 1С?')
            ->modalDescription(fn (Order $record): HtmlString => self::description($record, $nested))
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Да, это он — связать')
            ->extraModalFooterActions(fn (): array => $nested ? [] : [OneCOrderExportAction::make('onecCreateNew', nested: true)])
            ->fillForm(fn (Order $record): array => ['candidate' => app(OneCOrderMatcher::class)->found($record)['ref']->ref_key ?? null])
            ->schema(fn (Order $record): array => [
                Radio::make('candidate')->label('Заказы 1С, похожие на этот')
                    ->options(collect(app(OneCOrderMatcher::class)->candidates($record))->mapWithKeys(fn (array $c): array => [$c['ref']->ref_key => $c['label']])->all())
                    ->visible(fn (): bool => app(OneCOrderMatcher::class)->candidates($record, 1) !== []),
                Select::make('manual')->label('Другой заказ 1С — найти по номеру')->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => app(OneCOrderMatcher::class)->search($search))
                    ->getOptionLabelUsing(fn ($value): ?string => app(OneCOrderMatcher::class)->labelFor((string) $value))
                    ->helperText('Например: МПУТ-000756 или 002330. Выбранный здесь заказ важнее отмеченного выше.'),
            ])
            ->action(function (Order $record, array $data, Action $action) use ($nested): void {
                $key = ($data['manual'] ?? null) ?: ($data['candidate'] ?? null);
                if (! $key) {
                    Notification::make()->warning()->title('Выберите заказ 1С')->send();

                    return;
                }
                try {
                    app(OneCOrderMatcher::class)->link($record, $key, auth()->user());
                    Notification::make()->success()->title('Заказ связан с 1С: '.$record->fresh()->onec_order_number)
                        ->body('В 1С повторно не передаётся, статус и «проведён» сайт берёт из 1С.')->send();
                    if ($nested) {
                        $action->cancelParentActions();
                    }
                } catch (DomainException $e) {
                    Notification::make()->danger()->title('Не связан')->body($e->getMessage())->send();
                }
            });
    }

    private static function description(Order $order, bool $nested): HtmlString
    {
        $best = $nested ? null : app(OneCOrderMatcher::class)->found($order);
        $intro = $best
            ? '<div style="padding:10px 12px;border-radius:8px;background:#fffbeb;color:#92400e;margin-bottom:8px"><div style="font-size:18px;font-weight:700">'
                .e($best['ref']->description).'</div>'.e(app(OneCOrderMatcher::class)->label($best['ref']))
                .'<br>Совпадает: <b>'.e(implode(', ', $best['reasons'])).'</b></div>'
            : '';

        return new HtmlString($intro.'Если это тот же заказ — нажмите «Да, это он — связать»: заказ сайта больше не уйдёт в 1С, статус и «проведён» сайт будет брать из 1С. '
            .($nested ? '' : 'Если это другой заказ — «Это другой заказ — создать новый в 1С».')
            .'<br><span style="color:#6b7280">Заказы 1С сайт читает каждый час (последние 3 дня) и каждое утро (60 дней).</span>');
    }
}
