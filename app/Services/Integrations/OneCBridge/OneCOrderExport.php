<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\OneCAgentTask;
use App\Models\OneCOrderProfile;
use App\Models\OneCReference;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Integrations\IntegrationProductMatchDecision;
use DomainException;
use Illuminate\Support\Str;

/**
 * «Создать заказ в 1С»: the manager checks a form filled from the order and the scheme (organization, customer,
 * agreement, warehouse, delivery…) and the agent creates an UNPOSTED «Заказ клиента».
 *
 * 1C items are never created from here: each line must point at exactly one existing 1C item (the link of the
 * card with the 1C nomenclature), otherwise the order does not go — a new item would be a duplicate in 1C.
 */
class OneCOrderExport
{
    public function __construct(
        private readonly OneCReferences $refs,
        private readonly OneCOrderProfiles $profiles,
        private readonly OneCAgentTasks $tasks,
    ) {}

    /**
     * Each line with its 1C item, or why it has none.
     *
     * @return list<array{item: OrderItem, name: string, qty: float, gross: float, key: ?string, characteristic: ?string, onec_code: ?string, onec_name: ?string, problem: ?string}>
     */
    public function lines(Order $order): array
    {
        $order->loadMissing('items.integrationProduct');
        $source = IntegrationSource::query()->where('code', 'onec')->first();
        $linked = $source ? IntegrationProduct::query()->where('integration_source_id', $source->id)
            ->whereIn('product_id', $order->items->pluck('product_id')->filter()->unique())
            ->where(fn ($q) => $q->whereNull('match_status')->orWhere('match_status', '!=', 'ignored'))
            ->get(['id', 'product_id', 'external_id', 'external_code', 'name'])->groupBy('product_id') : collect();

        $lines = [];
        foreach ($order->items as $item) {
            $row = null;
            $problem = null;
            $own = $item->integrationProduct;
            if (! $source) {
                $problem = 'нет подключения центральной 1С';
            } elseif ($own && (int) $own->integration_source_id === (int) $source->id) {
                $own->match_status === 'ignored' ? $problem = 'товар 1С отмечен «не для сайта»' : $row = $own;
            } elseif (! $item->product_id) {
                $problem = 'позиция без карточки — не с чем связать в 1С';
            } else {
                $candidates = collect($linked->get($item->product_id, []))->unique('external_id')->values();
                match ($candidates->count()) {
                    0 => $problem = 'карточка не связана с товаром 1С',
                    1 => $row = $candidates->first(),
                    default => $problem = 'карточка связана с '.$candidates->count().' товарами 1С: '
                        .$candidates->map(fn ($c): string => $c->external_code ?: $c->name)->implode(', ').' — оставьте один',
                };
            }
            [$key, $characteristic] = array_pad(explode('#', (string) $row?->external_id, 2), 2, null);
            if ($row && ! preg_match('/^[0-9a-f-]{36}$/i', (string) $key)) {
                $problem = 'код товара 1С не похож на ключ 1С';
                $row = null;
            }
            $qty = (float) $item->quantity;
            $gross = (float) $item->total;
            if ($item->price_tax_mode === IntegrationSource::PRICE_TAX_EXCLUSIVE) {
                $gross = round($gross * 1.2, 2);
            }
            $lines[] = ['item' => $item, 'name' => (string) $item->product_name, 'qty' => $qty, 'gross' => $gross,
                'key' => $row ? strtolower((string) $key) : null,
                'characteristic' => $row && $characteristic && preg_match('/^[0-9a-f-]{36}$/i', $characteristic) ? strtolower($characteristic) : null,
                'onec_code' => $row?->external_code, 'onec_name' => $row?->name, 'problem' => $qty > 0 ? $problem : 'количество 0'];
        }

        return $lines;
    }

    /** @return array<int, string> free 1C items (not linked to a card, not «не для сайта») for a search */
    public function searchItems(string $text, int $limit = 30): array
    {
        $source = IntegrationSource::query()->where('code', 'onec')->first();
        $text = trim($text);
        if (! $source || mb_strlen($text) < 2) {
            return [];
        }

        return IntegrationProduct::query()->where('integration_source_id', $source->id)->whereNull('product_id')
            ->where(fn ($q) => $q->whereNull('match_status')->orWhere('match_status', '!=', 'ignored'))
            ->where(fn ($q) => $q->where('name', 'like', '%'.$text.'%')->orWhere('external_code', 'like', $text.'%')->orWhere('external_sku', 'like', $text.'%'))
            ->orderBy('name')->limit($limit)->get(['id', 'external_code', 'name', 'stock_quantity'])
            ->mapWithKeys(fn (IntegrationProduct $p): array => [$p->id => trim(($p->external_code ? $p->external_code.' · ' : '').$p->name
                .' · остаток '.rtrim(rtrim(number_format((float) $p->stock_quantity, 3, '.', ''), '0'), '.'))])->all();
    }

    public function itemLabel(?int $id): ?string
    {
        $p = $id ? IntegrationProduct::query()->find($id) : null;

        return $p ? trim(($p->external_code ? $p->external_code.' · ' : '').$p->name) : null;
    }

    /** Links the card of an order line with a free 1C item, as «Привязать» in the 1C catalog does. */
    public function link(OrderItem $item, int $integrationProductId): void
    {
        $row = IntegrationProduct::query()->find($integrationProductId);
        if (! $item->product_id || ! $row || ! app(IntegrationProductMatchDecision::class)->linkSelectedProduct($row, (int) $item->product_id)) {
            throw new DomainException('«'.$item->product_name.'»: товар 1С уже привязан к другой карточке или отмечен «не для сайта» — выберите другой.');
        }
    }

    public function companyTaxId(Order $order): string
    {
        $snapshot = is_array($order->b2b_commercial_terms_snapshot) ? $order->b2b_commercial_terms_snapshot : [];

        return trim((string) ($order->company_unp ?: ($snapshot['company_unp'] ?? null) ?: ($order->user?->isB2B() ? $order->user?->company_inn : null)));
    }

    /** @return array<string, mixed> the form, filled from the order and the scheme */
    public function defaults(Order $order, ?OneCOrderProfile $profile = null): array
    {
        $taxId = $this->companyTaxId($order);
        $profile ??= $this->profiles->forOrder($taxId !== '');
        if (! $profile) {
            return ['profile_id' => null, 'h' => []];
        }
        $h = [];
        foreach (array_keys(OneCOrderProfiles::FIELDS) as $field) {
            $value = strtolower((string) ($profile->header[$field] ?? ''));
            $h[$field] = $value !== '' && $value !== OneCReferences::ZERO ? $value : null;
        }
        if ($profile->customer_mode === OneCOrderProfile::COMPANY) {
            $h['Партнер_Key'] = $h['Контрагент_Key'] = $h['Договор_Key'] = null;
            $counterparty = $taxId !== '' ? $this->counterpartyByTaxId($taxId) : null;
            if ($counterparty) {
                $h['Контрагент_Key'] = $counterparty->ref_key;
                $h['Партнер_Key'] = $counterparty->owner_key;
                $h['Договор_Key'] = array_key_first($this->contracts($counterparty->ref_key, $h['Организация_Key']));
            }
        }
        if ($order->manager) {
            $h['Менеджер_Key'] = OneCReference::query()->where('kind', 'user')->where('deletion_mark', false)
                ->where('description', $order->manager->name)->value('ref_key') ?? $h['Менеджер_Key'];
        }

        $pickup = $order->delivery_type === 'pickup';
        $address = $pickup ? '' : collect([$order->delivery_region, $order->delivery_city, $order->delivery_address])
            ->map(fn ($v): string => trim((string) $v))->filter()->unique()->implode(', ');
        $total = number_format((float) $order->total, 2, ',', '');
        $info = collect([
            $pickup ? 'Самовывоз' : $address,
            'К оплате '.$total.'р.',
            $pickup ? null : 'Согласовать доставку по звонку',
            'Клиент: '.trim((string) ($order->company_name ?: $order->customer_name)),
            $order->customer_phone ? 'Телефон: '.$order->customer_phone : null,
        ])->filter()->implode("\n");

        return [
            'profile_id' => $profile->id,
            'h' => $h,
            'status' => $profile->header['Статус'] ?? 'НеСогласован',
            'delivery' => $pickup ? 'Самовывоз' : ($profile->header['СпособДоставки'] ?? null),
            'address' => $address,
            'info' => $info,
            'comment' => trim('Заказ с сайта kotlov.by №'.$order->number.($order->comment ? '. Комментарий клиента: '.$order->comment : '')),
        ];
    }

    public function counterpartyByTaxId(string $taxId): ?OneCReference
    {
        $found = OneCReference::query()->where('kind', 'counterparty')->where('deletion_mark', false)->where('inn', trim($taxId))->get();

        return $found->count() === 1 ? $found->first() : null;
    }

    /** @return array<string, string> the contracts of a counterparty with an organization */
    public function contracts(?string $counterparty, ?string $organization): array
    {
        if (! $counterparty) {
            return [];
        }

        return OneCReference::query()->where('kind', 'contract')->where('deletion_mark', false)->get()
            ->filter(fn (OneCReference $r): bool => strtolower((string) ($r->data['Контрагент_Key'] ?? '')) === $counterparty
                && (! $organization || strtolower((string) ($r->data['Организация_Key'] ?? '')) === $organization))
            ->sortBy('description')->pluck('description', 'ref_key')->all();
    }

    /** @return list<string> what stops the order */
    public function problems(Order $order, ?OneCOrderProfile $profile, array $form): array
    {
        $problems = [];
        if (! $profile) {
            return ['Нет схемы заказа 1С: создайте её в «Мост 1С» → «Схемы заказов» по образцу заказа из 1С'];
        }
        foreach ($this->lines($order) as $line) {
            if ($line['problem']) {
                $problems[] = '«'.$line['name'].'»: '.$line['problem'];
            }
        }
        if ($this->lines($order) === []) {
            $problems[] = 'В заказе нет позиций';
        }
        foreach (['Организация_Key', 'Склад_Key', 'Партнер_Key', 'Контрагент_Key', 'Соглашение_Key'] as $field) {
            if (blank($form['h'][$field] ?? null)) {
                $problems[] = 'Не выбрано: '.OneCOrderProfiles::FIELDS[$field][0]
                    .($profile->customer_mode === OneCOrderProfile::COMPANY && in_array($field, ['Партнер_Key', 'Контрагент_Key'], true)
                        ? ' (компании с УНП '.($this->companyTaxId($order) ?: '—').' нет в 1С или их несколько)' : '');
            }
        }
        if ($order->onec_order_guid && ! $order->onec_deleted) {
            $problems[] = 'Заказ уже есть в 1С: '.($order->onec_order_number ?: $order->onec_order_guid);
        }

        return $problems;
    }

    /**
     * Simpler and simpler orders for a 1C that refuses one «ПриЗаписи» without saying why (OData gives no reason):
     * 1 — no text in «Адрес доставки» (the address stays in the delivery info), 2 — no payment stage (1C takes it from
     * the agreement), 3 — no service fields of the header and the lines, 4 — only the main fields. The variant that
     * passes is kept in the scheme for the next orders.
     */
    public const MAX_VARIANT = 4;

    private const SERVICE_FIELDS = ['СкидкиРассчитаны', 'Согласован', 'СуммаАвансаДоОбеспечения', 'СуммаПредоплатыДоОтгрузки', 'ДатаСогласования',
        'МаксимальныйКодСтроки', 'АдресДоставкиЗначение', 'АдресДоставкиЗначенияПолей', 'ВремяДоставкиС', 'ВремяДоставкиПо'];

    private const MAIN_FIELDS = ['Date', 'Организация_Key', 'Партнер_Key', 'Контрагент_Key', 'Соглашение_Key', 'Договор_Key', 'Склад_Key', 'Валюта_Key',
        'Подразделение_Key', 'Менеджер_Key', 'НалогообложениеНДС', 'ХозяйственнаяОперация', 'ПорядокРасчетов', 'ЦенаВключаетНДС', 'Статус',
        'СпособДоставки', 'ДополнительнаяИнформацияПоДоставке', 'ЖелаемаяДатаОтгрузки', 'ДатаОтгрузки', 'НомерПоДаннымКлиента', 'Комментарий',
        'СуммаДокумента', 'Товары'];

    private const MAIN_ROW_FIELDS = ['LineNumber', 'Номенклатура_Key', 'Характеристика_Key', 'КоличествоУпаковок', 'Количество', 'Цена', 'Сумма',
        'СтавкаНДС_Key', 'СуммаНДС', 'СуммаСНДС', 'Склад_Key'];

    /** @return array<string, mixed> the «Заказ клиента» for OData */
    public function body(Order $order, OneCOrderProfile $profile, array $form, ?int $variant = null): array
    {
        $variant ??= (int) ($profile->meta['variant'] ?? 0);
        $meta = $profile->meta ?? [];
        $has = fn (string $key): bool => in_array($key, (array) ($meta['keys'] ?? []), true);
        $now = now('Europe/Minsk')->format('Y-m-d\TH:i:s');
        // A scheme made before these fields were left out of samples: payment id and reserve designation belong to the sample order.
        $doc = collect($profile->header)->except(['ИдентификаторПлатежа', 'МаксимальныйКодСтроки', 'Назначение_Key'])->all();
        foreach (array_keys(OneCOrderProfiles::FIELDS) as $field) {
            if (array_key_exists($field, $form['h'] ?? []) || array_key_exists($field, $doc)) {
                $doc[$field] = ($form['h'][$field] ?? null) ?: OneCReferences::ZERO;
            }
        }
        $doc['Date'] = $now;
        if ($has('Статус') && filled($form['status'] ?? null)) {
            $doc['Статус'] = $form['status'];
        }
        if ($has('СпособДоставки') && filled($form['delivery'] ?? null)) {
            $doc['СпособДоставки'] = $form['delivery'];
        }
        foreach (array_intersect((array) ($meta['address'] ?? []), [OneCOrderProfiles::ADDRESS_FIELD]) as $key) {
            $doc[$key] = (string) ($form['address'] ?? '');
        }
        foreach ((array) ($meta['info'] ?? []) as $key) {
            $doc[$key] = (string) ($form['info'] ?? '');
        }
        if (in_array('ДатаСогласования', (array) ($meta['dates'] ?? []), true)) {
            $doc['ДатаСогласования'] = $now;
        }
        // The shipping date: the planned delivery of the site order, otherwise tomorrow. 1C needs it on the lines
        // («Отгрузить») and lists orders by it, an empty one hides the order among old ones.
        $ship = self::shipDate($order);
        foreach (['ЖелаемаяДатаОтгрузки', 'ДатаОтгрузки'] as $key) {
            if ($has($key)) {
                $doc[$key] = $ship;
            }
        }
        if ($has('НомерПоДаннымКлиента')) {
            $doc['НомерПоДаннымКлиента'] = self::clientNumber($order);
        }
        $doc['Комментарий'] = (string) ($form['comment'] ?? '');

        $withVat = (bool) ($doc['ЦенаВключаетНДС'] ?? true);
        $vatKey = $profile->row_defaults['СтавкаНДС_Key'] ?? null;
        $rate = $this->refs->vatRate($vatKey);
        $rowKeys = (array) ($meta['row_keys'] ?? []);
        $rows = [];
        $total = 0.0;
        $totalVat = 0.0;
        foreach ($this->lines($order) as $i => $line) {
            $n = $i + 1;
            $gross = round($line['gross'], 2);
            $vat = round($gross * $rate / (1 + $rate), 2);
            $sum = $withVat ? $gross : round($gross - $vat, 2);
            $row = array_merge((array) $profile->row_defaults, [
                'LineNumber' => (string) $n,
                'Номенклатура_Key' => $line['key'],
                'Характеристика_Key' => $line['characteristic'] ?: OneCReferences::ZERO,
                'КоличествоУпаковок' => $line['qty'],
                'Количество' => $line['qty'],
                'Цена' => round($sum / max($line['qty'], 0.001), 2),
                'Сумма' => $sum,
                'СуммаНДС' => $vat,
                'СуммаСНДС' => $gross,
                'Склад_Key' => $doc['Склад_Key'] ?? OneCReferences::ZERO,
            ]);
            foreach (['КодСтроки' => $n, 'КлючСвязи' => $n, 'ИдентификаторСтроки' => (string) Str::uuid(), 'ДатаОтгрузки' => $ship] as $key => $value) {
                if (in_array($key, $rowKeys, true)) {
                    $row[$key] = $value;
                }
            }
            $rows[] = $row;
            $total += $sum;
            $totalVat += $vat;
        }
        $doc['Товары'] = $rows;
        if ($has('МаксимальныйКодСтроки')) {
            $doc['МаксимальныйКодСтроки'] = count($rows);
        }
        $grossTotal = round($withVat ? $total : $total + $totalVat, 2);
        $doc['СуммаДокумента'] = $grossTotal;

        if ($profile->payment_stage) {
            $stage = array_merge($profile->payment_stage, ['LineNumber' => '1']);
            $keys = (array) ($meta['payment_keys'] ?? []);
            foreach (['ДатаПлатежа' => $now, 'ПроцентПлатежа' => 100, 'СуммаПлатежа' => $grossTotal, 'СуммаНДС' => round($totalVat, 2)] as $key => $value) {
                if (in_array($key, $keys, true)) {
                    $stage[$key] = $value;
                }
            }
            $doc['ЭтапыГрафикаОплаты'] = [$stage];
        }

        return self::simplify($doc, $variant);
    }

    /** @param array<string, mixed> $doc */
    public static function simplify(array $doc, int $variant): array
    {
        if ($variant >= 1 && array_key_exists(OneCOrderProfiles::ADDRESS_FIELD, $doc)) {
            $doc[OneCOrderProfiles::ADDRESS_FIELD] = '';
        }
        if ($variant >= 2) {
            unset($doc['ЭтапыГрафикаОплаты']);
        }
        if ($variant >= 3) {
            $doc = collect($doc)->except(self::SERVICE_FIELDS)->all();
            $doc['Товары'] = array_map(fn (array $row): array => collect($row)->only(self::MAIN_ROW_FIELDS)->all(), $doc['Товары'] ?? []);
        }
        if ($variant >= 4) {
            $doc = collect($doc)->only(self::MAIN_FIELDS)->all();
        }

        return $doc;
    }

    public static function shipDate(Order $order): string
    {
        return ($order->planned_delivery_date ?: now('Europe/Minsk')->addDay())->format('Y-m-d').'T00:00:00';
    }

    /** «Номер заказа по данным клиента»: the agent looks for it before creating, so an order never goes twice. */
    public static function clientNumber(Order $order): string
    {
        return 'kotlov-'.preg_replace('/[^A-Za-z0-9-]/', '', (string) ($order->number ?: $order->id));
    }

    public function enqueue(Order $order, array $form, ?User $user): OneCAgentTask
    {
        $profile = OneCOrderProfile::query()->where('document', OneCOrderProfile::CUSTOMER_ORDER)->where('is_active', true)->find($form['profile_id'] ?? null);
        $problems = $this->problems($order, $profile, $form);
        if ($problems !== []) {
            throw new DomainException(implode('; ', array_slice($problems, 0, 6)).(count($problems) > 6 ? '; и ещё '.(count($problems) - 6) : ''));
        }
        $body = $this->body($order, $profile, $form);
        $find = in_array('НомерПоДаннымКлиента', (array) ($profile->meta['keys'] ?? []), true)
            ? ODataReadTaskHandler::path('Document_ЗаказКлиента', ['$filter' => "НомерПоДаннымКлиента eq '".self::clientNumber($order)."' and DeletionMark eq false",
                '$select' => 'Ref_Key,Number', '$top' => '1'])
            : null;

        return $this->tasks->enqueue(OrderTaskHandler::TYPE, $order, 'Заказ №'.$order->number.' → 1С ('.$profile->name.')', $user,
            ['profile_id' => $profile->id, 'body' => $body, 'find' => $find, 'form' => $form, 'variant' => (int) ($profile->meta['variant'] ?? 0),
                'load' => (bool) ($profile->meta['load_mode'] ?? false)]);
    }

    /**
     * The same order again as a draft, right after 1C refused it «ПриЗаписи» (the agent takes it in the same run):
     * 1C saves it without its write handlers, its logic and checks run when a manager posts it in 1C.
     */
    public function retryAsDraft(OneCAgentTask $failed): ?OneCAgentTask
    {
        $order = $failed->subject;
        $profile = OneCOrderProfile::query()->find(data_get($failed->payload, 'profile_id'));
        $form = data_get($failed->payload, 'form');
        if (! $order instanceof Order || ! $profile || ! is_array($form) || data_get($failed->payload, 'load')) {
            return null;
        }

        return OneCAgentTask::query()->create(['type' => OrderTaskHandler::TYPE, 'subject_type' => $order->getMorphClass(), 'subject_id' => $order->id,
            'title' => mb_substr('Заказ №'.$order->number.' → 1С ('.$profile->name.', черновиком)', 0, 250),
            'payload' => array_merge((array) $failed->payload, ['body' => $this->body($order->fresh(), $profile, $form, 0), 'variant' => 0, 'load' => true]),
            'status' => OneCAgentTask::QUEUED, 'created_by' => $failed->created_by]);
    }

    /** The same order, one step simpler (kept for a 1C that refuses even a draft). */
    public function retrySimpler(OneCAgentTask $failed): ?OneCAgentTask
    {
        $order = $failed->subject;
        $variant = (int) data_get($failed->payload, 'variant', 0) + 1;
        $profile = OneCOrderProfile::query()->find(data_get($failed->payload, 'profile_id'));
        $form = data_get($failed->payload, 'form');
        if (! $order instanceof Order || ! $profile || ! is_array($form) || $variant > self::MAX_VARIANT) {
            return null;
        }

        return OneCAgentTask::query()->create(['type' => OrderTaskHandler::TYPE, 'subject_type' => $order->getMorphClass(), 'subject_id' => $order->id,
            'title' => mb_substr('Заказ №'.$order->number.' → 1С ('.$profile->name.', упрощённый вариант '.$variant.')', 0, 250),
            'payload' => array_merge((array) $failed->payload, ['body' => $this->body($order->fresh(), $profile, $form, $variant), 'variant' => $variant]),
            'status' => OneCAgentTask::QUEUED, 'created_by' => $failed->created_by]);
    }
}
