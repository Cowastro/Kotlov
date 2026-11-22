<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Filament\Resources\OneCAgentTasks\OneCAgentTaskResource;
use App\Filament\Resources\OneCOrderProfiles\OneCOrderProfileResource;
use App\Models\OneCAgentTask;
use App\Models\OneCOrderProfile;
use App\Models\Order;
use App\Services\Integrations\OneCBridge\OneCAgentTasks;
use App\Services\Integrations\OneCBridge\OneCItemCreator;
use App\Services\Integrations\OneCBridge\OneCOrderExport;
use App\Services\Integrations\OneCBridge\OneCOrderMatcher;
use App\Services\Integrations\OneCBridge\OneCOrderProfiles;
use App\Services\Integrations\OneCBridge\OneCReferences;
use App\Services\Integrations\OneCBridge\OrderTaskHandler;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Illuminate\Support\HtmlString;

/**
 * «Создать заказ в 1С»: the form is filled from the order and the 1C scheme («Розница СБГ бай» by default, a company
 * order takes the company scheme and finds the customer by УНП), the manager checks or changes anything, links the
 * lines that have no 1C item yet, and the agent creates an unposted «Заказ клиента».
 */
class OneCOrderExportAction
{
    /** Form key => 1C field (Livewire keeps form keys in Latin). */
    public const KEYS = [
        'org' => 'Организация_Key',
        'warehouse' => 'Склад_Key',
        'partner' => 'Партнер_Key',
        'counterparty' => 'Контрагент_Key',
        'agreement' => 'Соглашение_Key',
        'contract' => 'Договор_Key',
        'subdivision' => 'Подразделение_Key',
        'manager' => 'Менеджер_Key',
        'zone' => 'ЗонаДоставки_Key',
        'priority' => 'Приоритет_Key',
    ];

    /**
     * The one 1C button of an order: «1С: МПУТ-…» once the order is in 1C, «Ждёт агента 1С» while it goes there,
     * «Создать заказ в 1С» when 1C has nothing like it (a likely 1C order shows «Найден в 1С» instead, see
     * OneCOrderLinkAction). $nested: the same form opened from the «Найден в 1С» window for a different order.
     */
    public static function make(string $name = 'onecOrder', bool $nested = false): Action
    {
        return Action::make($name)
            ->label(fn (Order $record): string => $nested ? 'Это другой заказ — создать новый в 1С' : self::label($record))
            ->icon(fn (Order $record): string => self::exported($record) ? 'heroicon-o-check-badge' : 'heroicon-o-arrow-up-tray')
            ->color(fn (Order $record): string => match (true) {
                $nested => 'gray',
                self::exported($record) => $record->onec_posted ? 'success' : 'info',
                self::lastTask($record)?->status === OneCAgentTask::FAILED => 'danger',
                default => 'primary',
            })
            ->visible(fn (Order $record): bool => self::canManage() && ($nested || ! self::foundShown($record)))
            ->modalHeading(fn (Order $record): string => self::exported($record) ? 'Заказ в 1С' : 'Создать заказ клиента в 1С')
            ->modalDescription(fn (Order $record): HtmlString => self::state($record))
            ->modalWidth(fn (Order $record): Width => self::exported($record) || self::waiting($record) ? Width::Large : Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Отправить в 1С')
            ->modalSubmitAction(fn (Order $record, Action $action) => self::exported($record) || self::waiting($record) ? false : $action)
            ->modalCancelActionLabel(fn (Order $record): string => self::exported($record) || self::waiting($record) ? 'Закрыть' : 'Отмена')
            ->extraModalFooterActions(fn (Order $record): array => $nested ? [] : self::footer($record))
            ->fillForm(fn (Order $record): array => self::formState(app(OneCOrderExport::class)->defaults($record)))
            ->schema(fn (Order $record): array => self::exported($record) || self::waiting($record) ? [] : self::schema($record))
            ->action(function (Order $record, array $data, Action $action) use ($nested): void {
                $export = app(OneCOrderExport::class);
                try {
                    foreach ((array) ($data['links'] ?? []) as $itemId => $integrationProductId) {
                        $item = $record->items->firstWhere('id', (int) $itemId);
                        if ($item && $integrationProductId) {
                            $export->link($item, (int) $integrationProductId);
                        }
                    }
                    // Lines 1C has no item for: the items are created first, the order goes after them.
                    $create = collect((array) ($data['create'] ?? []))->filter()->keys();
                    if ($create->isNotEmpty()) {
                        $creator = app(OneCItemCreator::class);
                        foreach ($create as $itemId) {
                            $item = $record->items->firstWhere('id', (int) $itemId);
                            if ($item?->product && blank($data['links'][$itemId] ?? null)) {
                                $creator->request($item->product, (string) $creator->defaultTemplate($item->product), null, auth()->user());
                            }
                        }
                        Notification::make()->success()->title('Товары создаются в 1С: '.$create->count())
                            ->body('Агент проверит, нет ли их в 1С, и создаст в течение 5 минут — карточки привяжутся сами. Затем отправьте заказ ещё раз.')
                            ->persistent()->send();

                        return;
                    }
                    $record->unsetRelation('items');
                    $task = $export->enqueue($record->fresh(), self::toForm($data), auth()->user());
                    Notification::make()->success()->title('Заказ в очереди агента 1С (задача №'.$task->id.')')
                        ->body('Агент создаст непроведённый «Заказ клиента» в течение 5 минут. Номер 1С появится на кнопке.')->send();
                    if ($nested) {
                        $action->cancelParentActions();
                    }
                } catch (DomainException $e) {
                    Notification::make()->danger()->title('Заказ не отправлен')->body($e->getMessage())->persistent()->send();
                }
            });
    }

    public static function canManage(): bool
    {
        return (bool) auth()->user()?->isManager();
    }

    public static function exported(Order $order): bool
    {
        return filled($order->onec_order_guid) && ! $order->onec_deleted;
    }

    /** 1C has a likely copy of the order: the page shows «Найден в 1С» instead of «Создать заказ в 1С». */
    public static function foundShown(Order $order): bool
    {
        return ! self::exported($order) && ! self::waiting($order) && app(OneCOrderMatcher::class)->found($order) !== null;
    }

    private static function lastTask(Order $order): ?OneCAgentTask
    {
        return OneCAgentTask::query()->where('type', OrderTaskHandler::TYPE)->whereMorphedTo('subject', $order)->latest('id')->first();
    }

    public static function waiting(Order $order): bool
    {
        return app(OneCAgentTasks::class)->activeFor($order, OrderTaskHandler::TYPE) !== null;
    }

    private static function label(Order $order): string
    {
        return match (true) {
            self::exported($order) => '1С: '.($order->onec_order_number ?: 'заказ создан').($order->onec_posted ? ' · проведён' : ''),
            self::waiting($order) => 'Ждёт агента 1С',
            self::lastTask($order)?->status === OneCAgentTask::FAILED => 'В 1С: ошибка, повторить',
            default => 'Создать заказ в 1С',
        };
    }

    /** @return list<Action> */
    private static function footer(Order $order): array
    {
        if (self::exported($order)) {
            return $order->onec_profile_id ? [] : [
                Action::make('onecUnlink')->label('Отвязать')->color('danger')->link()
                    ->requiresConfirmation()
                    ->modalHeading('Снять связь с заказом 1С '.$order->onec_order_number.'?')
                    ->modalDescription('Делайте это, только если заказ связали не с тем заказом 1С. Сам заказ в 1С не меняется.')
                    ->action(function (Order $record, Action $action): void {
                        try {
                            app(OneCOrderMatcher::class)->unlink($record, auth()->user());
                            Notification::make()->success()->title('Связь с 1С снята')->send();
                            $action->cancelParentActions();
                        } catch (DomainException $e) {
                            Notification::make()->danger()->title('Связь не снята')->body($e->getMessage())->send();
                        }
                    }),
            ];
        }

        return self::waiting($order) ? [] : [OneCOrderLinkAction::make('onecLinkManual', nested: true)];
    }

    private static function state(Order $order): HtmlString
    {
        $task = self::lastTask($order);
        $link = $task ? ' <a href="'.e(OneCAgentTaskResource::getUrl('view', ['record' => $task])).'" style="text-decoration:underline">задача №'.$task->id.'</a>' : '';
        if (self::exported($order)) {
            return new HtmlString(self::card($order).'<div style="margin-top:8px;color:#6b7280">'
                .($order->onec_profile_id ? 'Создан с сайта по схеме «'.e((string) OneCOrderProfile::query()->whereKey($order->onec_profile_id)->value('name')).'».'.$link
                    : 'Связан с заказом, заведённым в 1С вручную.')
                .' Статус и «проведён» сайт сверяет с 1С каждые 30 минут. Дальше заказ ведут в 1С.</div>');
        }
        if (self::waiting($order)) {
            return new HtmlString('Заказ в очереди: агент на сервере 1С создаст его в течение 5 минут.'.$link);
        }
        $failed = $task?->status === OneCAgentTask::FAILED
            ? '<div style="margin-bottom:6px;color:#b91c1c">Прошлая попытка: '.e(mb_substr((string) $task->error, 0, 400)).$link.'</div>' : '';

        return new HtmlString($failed.'Заказ создаётся в 1С <b>непроведённым</b>: проверьте и проведите его в 1С. Товары в 1С не создаются — каждая строка должна быть связана с существующей номенклатурой 1С. '
            .'Если менеджер уже завёл этот заказ в 1С — нажмите внизу «Уже есть в 1С — связать».');
    }

    /** «МПУТ-000758 · не проведён · к обеспечению» as a small card. */
    public static function card(Order $order): string
    {
        $state = $order->onec_posted ? '<span style="color:#16a34a">проведён</span>' : '<span style="color:#d97706">не проведён</span>';
        $status = OneCOrderProfiles::STATUSES[$order->onec_doc_status] ?? $order->onec_doc_status;

        return '<div style="padding:10px 12px;border-radius:8px;border:1px solid rgba(127,127,127,.3)"><div style="font-size:18px;font-weight:700">'
            .e((string) $order->onec_order_number).'</div><div>'.$state.($status ? ' · '.e(mb_strtolower((string) $status)) : '')
            .($order->onec_checked_at ? ' · сверено '.e($order->onec_checked_at->timezone('Europe/Minsk')->format('d.m H:i')) : '').'</div></div>';
    }

    /** @return array<string, mixed> */
    private static function formState(array $defaults): array
    {
        $h = [];
        foreach (self::KEYS as $alias => $field) {
            $h[$alias] = $defaults['h'][$field] ?? null;
        }

        return array_merge($defaults, ['h' => $h, 'links' => []]);
    }

    /** @return array<string, mixed> */
    private static function toForm(array $data): array
    {
        $h = [];
        foreach (self::KEYS as $alias => $field) {
            $h[$field] = $data['h'][$alias] ?? null;
        }

        return array_merge($data, ['h' => $h]);
    }

    private static function schema(Order $order): array
    {
        $refs = app(OneCReferences::class);
        $export = app(OneCOrderExport::class);
        $profiles = OneCOrderProfile::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('sort')->orderBy('id')->pluck('name', 'id')->all();
        $select = fn (string $alias, string $kind): Select => Select::make('h.'.$alias)
            ->label(OneCOrderProfiles::FIELDS[self::KEYS[$alias]][0])
            ->options(fn (?string $state): array => $refs->options($kind, current: $state))->searchable();
        $searchable = fn (string $alias, string $kind): Select => Select::make('h.'.$alias)
            ->label(OneCOrderProfiles::FIELDS[self::KEYS[$alias]][0])
            ->searchable()->live()
            ->getSearchResultsUsing(fn (string $search): array => $refs->search($kind, $search))
            ->getOptionLabelUsing(fn ($value): ?string => $refs->label($kind, $value));

        $linkFields = [];
        foreach ($export->lines($order) as $line) {
            if ($line['key'] === null && $line['item']->product_id) {
                $linkFields[] = Select::make('links.'.$line['item']->id)
                    ->label('«'.$line['name'].'» — товар 1С')
                    ->placeholder('Найдите по названию или коду 1С')
                    ->helperText($line['problem'])
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => $export->searchItems($search))
                    ->getOptionLabelUsing(fn ($value): ?string => $export->itemLabel((int) $value));
                $template = app(OneCItemCreator::class)->defaultTemplate($line['item']->product);
                $linkFields[] = Toggle::make('create.'.$line['item']->id)
                    ->label('Нет в 1С — создать товар «'.$line['name'].'» в 1С')
                    ->helperText($template ? 'По образцу '.app(OneCItemCreator::class)->templateLabel($template).'. Другой образец — кнопка «Создать в 1С» в карточке товара.'
                        : 'Нет образца в этой категории — создайте товар из карточки товара (кнопка «Создать в 1С»).')
                    ->disabled($template === null);
            }
        }

        return [
            Select::make('profile_id')->label('Схема заказа 1С')->options($profiles)->required()->live()
                ->helperText(new HtmlString('Схемы делаются по образцу заказа из 1С: <a href="'.e(OneCOrderProfileResource::getUrl('index')).'" style="text-decoration:underline">Схемы заказов 1С</a>'))
                ->afterStateUpdated(function (?string $state, Set $set) use ($order): void {
                    $defaults = self::formState(app(OneCOrderExport::class)->defaults($order, OneCOrderProfile::query()->find($state)));
                    foreach (['h', 'status', 'delivery', 'address', 'info', 'comment'] as $key) {
                        $set($key, $defaults[$key] ?? null);
                    }
                }),
            Section::make('Организация и покупатель')->columns(2)->schema([
                $select('org', 'organization')->live(),
                $select('warehouse', 'warehouse'),
                $searchable('partner', 'partner'),
                $searchable('counterparty', 'counterparty')
                    ->afterStateUpdated(function (?string $state, Set $set, Get $get) use ($export, $refs): void {
                        $owner = $refs->find('counterparty', $state)?->owner_key;
                        if ($owner) {
                            $set('h.partner', $owner);
                        }
                        $set('h.contract', array_key_first($export->contracts($state, $get('h.org'))));
                    }),
                Select::make('h.agreement')->label('Соглашение')->searchable()
                    ->options(fn (Get $get, ?string $state): array => $refs->options('agreement', $get('h.partner'), current: $state)),
                Select::make('h.contract')->label('Договор')->searchable()
                    ->options(fn (Get $get, ?string $state): array => $export->contracts($get('h.counterparty'), $get('h.org'))
                        + ($state ? [$state => (string) $refs->label('contract', $state)] : [])),
                $select('subdivision', 'subdivision'),
                $select('manager', 'user'),
                Select::make('status')->label('Статус в 1С')->options(fn (?string $state): array => OneCOrderProfiles::STATUSES + ($state ? [$state => $state] : [])),
                $select('priority', 'priority'),
            ]),
            Section::make('Доставка')->columns(2)->schema([
                Select::make('delivery')->label('Способ доставки')->options(fn (?string $state): array => OneCOrderProfiles::DELIVERY + ($state ? [$state => $state] : [])),
                $select('zone', 'delivery_zone'),
                TextInput::make('address')->label('Адрес доставки')->columnSpanFull(),
                Textarea::make('info')->label('Дополнительная информация по доставке')->rows(5)->columnSpanFull(),
                Textarea::make('comment')->label('Комментарий')->rows(2)->columnSpanFull(),
            ]),
            Section::make('Товары и связь с номенклатурой 1С')->schema([
                Placeholder::make('lines')->hiddenLabel()->content(fn (): HtmlString => self::linesTable($order)),
                Grid::make(1)->schema($linkFields)->visible($linkFields !== []),
            ]),
        ];
    }

    private static function linesTable(Order $order): HtmlString
    {
        $e = fn ($v): string => e((string) $v);
        $rows = collect(app(OneCOrderExport::class)->lines($order))->map(fn (array $l): string => '<tr>'
            .'<td style="padding:4px 8px">'.$e($l['name']).'</td>'
            .'<td style="padding:4px 8px">'.($l['key']
                ? '<span style="color:#15803d">✓</span> '.$e(trim(($l['onec_code'] ? $l['onec_code'].' · ' : '').$l['onec_name']))
                : '<span style="color:#b91c1c">'.$e($l['problem']).'</span>').'</td>'
            .'<td style="padding:4px 8px;text-align:right">'.$e(rtrim(rtrim(number_format($l['qty'], 3, '.', ''), '0'), '.')).'</td>'
            .'<td style="padding:4px 8px;text-align:right;white-space:nowrap">'.$e(number_format($l['gross'], 2, ',', ' ')).'</td></tr>')->implode('');

        return new HtmlString('<table style="width:100%;border-collapse:collapse;font-size:13px"><thead><tr style="text-align:left;color:#6b7280">'
            .'<th style="padding:4px 8px">Позиция на сайте</th><th style="padding:4px 8px">Номенклатура 1С</th><th style="padding:4px 8px;text-align:right">Кол-во</th>'
            .'<th style="padding:4px 8px;text-align:right">Сумма с НДС</th></tr></thead><tbody>'.$rows.'</tbody></table>'
            .((float) $order->delivery_price > 0 ? '<p style="margin:6px 8px 0;color:#92400e">Доставка '.$e(number_format((float) $order->delivery_price, 2, ',', ' '))
                .' р. не входит в товары заказа 1С — она указана в «К оплате».</p>' : ''));
    }
}
