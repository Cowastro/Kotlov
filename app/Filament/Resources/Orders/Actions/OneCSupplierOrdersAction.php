<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Filament\Resources\OneCOrderProfiles\OneCOrderProfileResource;
use App\Models\OneCOrderProfile;
use App\Models\Order;
use App\Services\Integrations\OneCBridge\OneCOrderExport;
use App\Services\Integrations\OneCBridge\OneCReferences;
use App\Services\Integrations\OneCBridge\OneCSupplierOrderExport;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Support\HtmlString;

/**
 * «Заказы поставщикам в 1С»: the lines of the order bought from suppliers, one «Заказ поставщику» per supplier.
 * The supplier and its contract are found in 1C by УНП (or chosen), the lines go at their purchase prices.
 */
class OneCSupplierOrdersAction
{
    public static function make(): Action
    {
        return Action::make('onecSupplierOrders')
            ->label(fn (Order $record): string => self::label($record))
            ->icon('heroicon-o-truck')
            ->color(fn (Order $record): string => self::pending($record) > 0 ? 'warning' : 'success')
            ->visible(fn (Order $record): bool => (bool) auth()->user()?->isManager()
                && (filled($record->onec_order_guid) || app(\App\Services\Orders\OrderDeliveryPreparation::class)->blocker($record) === null)
                && app(OneCSupplierOrderExport::class)->groups($record) !== [])
            ->modalHeading('Заказы поставщикам в 1С')
            ->modalDescription(new HtmlString('По одному непроведённому «Заказу поставщику» на каждого поставщика: позиции этого заказа с маршрутом «Закупить у поставщика». '
                .'Поставщика и договор сайт находит в 1С по УНП, цены — входные цены позиций. Остальное — из схемы, сделанной по образцу заказа поставщику из 1С.'))
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Отправить в 1С')
            ->modalSubmitAction(fn (Order $record, Action $action) => self::pending($record) > 0 ? $action : false)
            ->fillForm(fn (Order $record): array => self::defaults($record))
            ->schema(fn (Order $record): array => self::schema($record))
            ->action(function (Order $record, array $data): void {
                $export = app(OneCSupplierOrderExport::class);
                $sent = [];
                $errors = [];
                foreach ($export->groups($record) as $supplierId => $group) {
                    $key = 's'.$supplierId;
                    if (OneCSupplierOrderExport::sent($group['record']) || ! ($data['send'][$key] ?? false)) {
                        continue;
                    }
                    try {
                        $export->enqueue($record, $supplierId, ['profile_id' => $data['profile_id'] ?? null, 'counterparty' => $data['counterparty'][$key] ?? null,
                            'contract' => $data['contract'][$key] ?? null, 'prices_with_vat' => (bool) ($data['prices_with_vat'] ?? true)], auth()->user());
                        $sent[] = $group['supplier']->name;
                    } catch (DomainException $e) {
                        $errors[] = $e->getMessage();
                    }
                }
                if ($sent !== []) {
                    Notification::make()->success()->title('В очереди агента 1С: '.implode(', ', $sent))
                        ->body('Агент создаст непроведённые «Заказы поставщику» в течение 5 минут.')->send();
                }
                if ($errors !== []) {
                    Notification::make()->danger()->title('Не отправлено')->body(implode("\n", $errors))->persistent()->send();
                }
            });
    }

    private static function pending(Order $order): int
    {
        return collect(app(OneCSupplierOrderExport::class)->groups($order))->reject(fn (array $g): bool => OneCSupplierOrderExport::sent($g['record']))->count();
    }

    private static function label(Order $order): string
    {
        $groups = app(OneCSupplierOrderExport::class)->groups($order);
        $done = collect($groups)->filter(fn (array $g): bool => OneCSupplierOrderExport::sent($g['record']))->count();

        return 'Поставщикам в 1С: '.$done.' из '.count($groups);
    }

    /** @return array<string, mixed> */
    private static function defaults(Order $order): array
    {
        $export = app(OneCSupplierOrderExport::class);
        $profileId = array_key_first($export->profiles());
        $org = $profileId ? strtolower((string) (OneCOrderProfile::query()->find($profileId)?->header['Организация_Key'] ?? '')) : null;
        $data = ['profile_id' => $profileId, 'prices_with_vat' => true, 'send' => [], 'counterparty' => [], 'contract' => []];
        foreach ($export->groups($order) as $supplierId => $group) {
            $key = 's'.$supplierId;
            $data['send'][$key] = ! OneCSupplierOrderExport::sent($group['record']);
            $data['counterparty'][$key] = $group['counterparty']?->ref_key;
            $data['contract'][$key] = array_key_first(app(OneCOrderExport::class)->contracts($group['counterparty']?->ref_key, $org ?: null));
        }

        return $data;
    }

    private static function schema(Order $order): array
    {
        $export = app(OneCSupplierOrderExport::class);
        $refs = app(OneCReferences::class);
        $profiles = $export->profiles();
        $fields = [
            Select::make('profile_id')->label('Схема заказа поставщику')->options($profiles)->required()
                ->helperText(new HtmlString($profiles === [] ? '<span style="color:#b91c1c">Схемы нет: создайте её в <a href="'.e(OneCOrderProfileResource::getUrl('index'))
                    .'" style="text-decoration:underline">Схемы заказов 1С</a> по образцу заказа поставщику (тип «Заказ поставщику»).</span>' : 'Организация, склад, НДС, оплата — из схемы.')),
            Toggle::make('prices_with_vat')->label('Входные цены позиций — с НДС')
                ->helperText('Обычно цены поставщиков в Беларуси с НДС. Если выключить, НДС добавится к цене.'),
        ];
        foreach ($export->groups($order) as $supplierId => $group) {
            $key = 's'.$supplierId;
            $sent = OneCSupplierOrderExport::sent($group['record']);
            $rows = collect($group['lines'])->map(fn (array $l): string => '<tr><td style="padding:3px 8px">'.e($l['name']).'</td><td style="padding:3px 8px">'
                .($l['key'] ? '<span style="color:#16a34a">✓ '.e((string) $l['onec_code']).'</span>' : '<span style="color:#b91c1c">'.e((string) $l['problem']).'</span>')
                .'</td><td style="padding:3px 8px;text-align:right">'.e((string) $l['qty']).'</td><td style="padding:3px 8px;text-align:right">'
                .e($l['purchase_price'] !== null ? number_format((float) $l['purchase_price'], 2, ',', ' ') : '—').'</td></tr>')->implode('');
            $state = $sent
                ? '<p style="margin:6px 0 0;color:#16a34a">В 1С: '.e($group['record']->onec_order_number ?: 'в очереди агента')
                    .($group['record']->onec_order_number ? ($group['record']->onec_posted ? ' · проведён' : ' · не проведён') : '').'</p>'
                : '';
            $children = [Placeholder::make('lines_'.$key)->hiddenLabel()->content(new HtmlString(
                '<table style="width:100%;font-size:13px"><tr style="color:#6b7280;text-align:left"><th style="padding:3px 8px">Позиция</th><th style="padding:3px 8px">Товар 1С</th>'
                .'<th style="padding:3px 8px;text-align:right">Кол-во</th><th style="padding:3px 8px;text-align:right">Вход</th></tr>'.$rows.'</table>'.$state))];
            if (! $sent) {
                $children[] = Toggle::make('send.'.$key)->label('Отправить заказ этому поставщику');
                $children[] = Select::make('counterparty.'.$key)->label('Поставщик в 1С (контрагент)')->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => $refs->search('counterparty', $search))
                    ->getOptionLabelUsing(fn ($value): ?string => $refs->label('counterparty', $value))->live()
                    ->helperText($group['counterparty'] ? 'Найден по УНП '.$group['tax_id'] : ($group['tax_id'] !== '' ? 'По УНП '.$group['tax_id'].' в 1С не найден — выберите' : 'У поставщика на сайте нет УНП — выберите'));
                $children[] = Select::make('contract.'.$key)->label('Договор')
                    ->options(function (Get $get, ?string $state) use ($key, $refs): array {
                        $org = strtolower((string) (OneCOrderProfile::query()->find($get('profile_id'))?->header['Организация_Key'] ?? ''));

                        return app(OneCOrderExport::class)->contracts($get('counterparty.'.$key), $org ?: null)
                            + ($state ? [$state => (string) $refs->label('contract', $state)] : []);
                    });
            }
            $fields[] = Section::make($group['supplier']->name.' · '.number_format($group['total'], 2, ',', ' ').' BYN')->compact()->schema($children);
        }

        return $fields;
    }
}
