<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\OneCAgentTask;
use App\Models\OneCReference;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The 1C lists the admin chooses from when it makes an order for 1C. The agent reads them through OData
 * («Обновить справочники 1С»), the site keeps a copy: a list 1C did not give (not in the REST composition)
 * keeps its old copy and is named in the task.
 */
class OneCReferences
{
    /** kind => [list name in the task, entity, query, label] */
    public const LISTS = [
        'organization' => ['organizations', 'Catalog_Организации', ['$select' => 'Ref_Key,Description,DeletionMark'], 'Организации'],
        'warehouse' => ['warehouses', 'Catalog_Склады', ['$select' => 'Ref_Key,Description,IsFolder,DeletionMark'], 'Склады'],
        'partner' => ['partners', 'Catalog_Партнеры', ['$select' => 'Ref_Key,Description,Code,DeletionMark'], 'Партнёры'],
        'counterparty' => ['counterparties', 'Catalog_Контрагенты', ['$select' => 'Ref_Key,Description,ИНН,Партнер_Key,DeletionMark'], 'Контрагенты'],
        'agreement' => ['agreements', 'Catalog_СоглашенияСКлиентами', ['$top' => '2000'], 'Соглашения с клиентами'],
        'contract' => ['contracts', 'Catalog_ДоговорыКонтрагентов', ['$select' => 'Ref_Key,Description,Контрагент_Key,Партнер_Key,Организация_Key,DeletionMark'], 'Договоры с контрагентами'],
        'subdivision' => ['subdivisions', 'Catalog_СтруктураПредприятия', ['$select' => 'Ref_Key,Description,DeletionMark'], 'Подразделения'],
        'user' => ['users', 'Catalog_Пользователи', ['$select' => 'Ref_Key,Description,DeletionMark'], 'Пользователи (менеджеры)'],
        'vat_rate' => ['vat_rates', 'Catalog_СтавкиНДС', ['$select' => 'Ref_Key,Description,DeletionMark'], 'Ставки НДС'],
        'delivery_zone' => ['delivery_zones', 'Catalog_ЗоныДоставки', ['$select' => 'Ref_Key,Description,DeletionMark'], 'Зоны доставки'],
        'priority' => ['priorities', 'Catalog_Приоритеты', ['$select' => 'Ref_Key,Description,DeletionMark'], 'Приоритеты'],
    ];

    /** Recent 1C orders: a scheme is made from one of them. */
    public const TEMPLATES = 'order_templates';

    public const ZERO = '00000000-0000-0000-0000-000000000000';

    public function __construct(private readonly OneCAgentTasks $tasks) {}

    /** Days of 1C orders per request: an answer stays far below 8 MB. */
    private const ORDER_DAYS_PER_PART = 10;

    /** @param  int  $orderDays  1C orders of these last days are read too (0 = lists only) */
    public function enqueueSync(?User $user, int $orderDays = 60, bool $lists = true): OneCAgentTask
    {
        $queries = [];
        if ($lists) {
            foreach (self::LISTS as [$name, $entity, $query]) {
                $queries[$name] = ODataReadTaskHandler::path($entity, $query);
            }
            $queries[self::TEMPLATES] = ODataReadTaskHandler::path('Document_ЗаказКлиента', ['$top' => '40', '$orderby' => 'Date desc']);
        }
        $to = now('Europe/Minsk')->addDay()->startOfDay();
        for ($i = 1; $orderDays > 0 && $i <= (int) ceil($orderDays / self::ORDER_DAYS_PER_PART); $i++) {
            $from = $to->copy()->subDays(self::ORDER_DAYS_PER_PART);
            $queries['orders_'.$i] = ODataReadTaskHandler::path('Document_ЗаказКлиента', [
                '$filter' => "Date ge datetime'".$from->format('Y-m-d\TH:i:s')."' and Date lt datetime'".$to->format('Y-m-d\TH:i:s')."'",
                '$orderby' => 'Date']);
            $to = $from;
        }

        return $this->tasks->enqueue(ODataReadTaskHandler::TYPE, null, $lists ? 'Справочники 1С' : 'Заказы 1С за '.$orderDays.' дн.', $user,
            ['queries' => $queries, 'sync' => true]);
    }

    /** 1C orders kept to find the site orders a manager already entered in 1C by hand. */
    public function importOrders(OneCAgentTask $task): int
    {
        $n = 0;
        $now = now();
        foreach (array_keys((array) data_get($task->payload, 'queries', [])) as $name) {
            if (! str_starts_with($name, 'orders_')) {
                continue;
            }
            $part = ODataReadTaskHandler::part($task, $name);
            if ($part['code'] !== 200) {
                continue;
            }
            $rows = [];
            foreach ((array) data_get($part['json'], 'value', []) as $order) {
                $key = strtolower((string) ($order['Ref_Key'] ?? ''));
                if (! preg_match('/^[0-9a-f-]{36}$/', $key)) {
                    continue;
                }
                $texts = collect($order)->filter(fn ($v, $k): bool => is_string($v) && ! str_ends_with((string) $k, '_Key')
                    && ! str_ends_with((string) $k, '_Type') && ! str_contains((string) $k, '@') && ! preg_match('/^[0-9a-f-]{36}$/i', $v))
                    ->except(['Number', 'Date', 'DataVersion'])->implode(' ');
                $items = collect((array) ($order['Товары'] ?? []))->map(fn ($r): array => [strtolower((string) ($r['Номенклатура_Key'] ?? '')),
                    (float) ($r['Количество'] ?? 0), (float) ($r['СуммаСНДС'] ?? $r['Сумма'] ?? 0)])->values()->all();
                $partner = strtolower((string) ($order['Партнер_Key'] ?? ''));
                $rows[] = ['kind' => 'order', 'ref_key' => $key, 'description' => mb_substr((string) ($order['Number'] ?? ''), 0, 500),
                    'code' => substr((string) ($order['Date'] ?? ''), 0, 19) ?: null,
                    'owner_key' => $partner !== '' && $partner !== self::ZERO ? $partner : null, 'inn' => null,
                    'data' => json_encode([
                        'sum' => (float) ($order['СуммаДокумента'] ?? 0), 'status' => $order['Статус'] ?? null,
                        'posted' => (bool) ($order['Posted'] ?? false), 'org' => strtolower((string) ($order['Организация_Key'] ?? '')),
                        'counterparty' => strtolower((string) ($order['Контрагент_Key'] ?? '')),
                        'client_number' => $order['НомерПоДаннымКлиента'] ?? null,
                        'text' => mb_substr(mb_strtolower($texts), 0, 2000), 'digits' => mb_substr(preg_replace('/\D+/', ' ', $texts), 0, 1000),
                        'items' => $items,
                    ], JSON_UNESCAPED_UNICODE),
                    'deletion_mark' => (bool) ($order['DeletionMark'] ?? false), 'is_folder' => false, 'synced_at' => $now];
            }
            foreach (array_chunk($rows, 300) as $chunk) {
                OneCReference::query()->upsert($chunk, ['kind', 'ref_key'], ['description', 'code', 'owner_key', 'data', 'deletion_mark', 'synced_at']);
            }
            $n += count($rows);
        }

        return $n;
    }

    /** Takes every list 1C gave; @return array<string, int> rows per list name */
    public function import(OneCAgentTask $task): array
    {
        $counts = [];
        foreach (self::LISTS as $kind => [$name]) {
            $part = ODataReadTaskHandler::part($task, $name);
            if ($part['code'] !== 200 || ! is_array(data_get($part['json'], 'value'))) {
                continue;
            }
            $now = now();
            $rows = [];
            foreach ($part['json']['value'] as $row) {
                $key = strtolower((string) ($row['Ref_Key'] ?? ''));
                if (! preg_match('/^[0-9a-f-]{36}$/', $key)) {
                    continue;
                }
                $owner = (string) ($row['Партнер_Key'] ?? $row['Контрагент_Key'] ?? '');
                $rows[$key] = [
                    'kind' => $kind, 'ref_key' => $key,
                    'description' => mb_substr(trim((string) ($row['Description'] ?? '')), 0, 500),
                    'code' => mb_substr(trim((string) ($row['Code'] ?? '')), 0, 100) ?: null,
                    'owner_key' => $owner !== '' && $owner !== self::ZERO ? strtolower($owner) : null,
                    'inn' => trim((string) ($row['ИНН'] ?? '')) ?: null,
                    'data' => json_encode($this->scalars($row), JSON_UNESCAPED_UNICODE),
                    'deletion_mark' => (bool) ($row['DeletionMark'] ?? false),
                    'is_folder' => (bool) ($row['IsFolder'] ?? false),
                    'synced_at' => $now,
                ];
            }
            DB::transaction(function () use ($kind, $rows, $now): void {
                foreach (array_chunk(array_values($rows), 500) as $chunk) {
                    OneCReference::query()->upsert($chunk, ['kind', 'ref_key'],
                        ['description', 'code', 'owner_key', 'inn', 'data', 'deletion_mark', 'is_folder', 'synced_at']);
                }
                OneCReference::query()->where('kind', $kind)->where('synced_at', '<', $now)->delete();
            });
            $counts[$name] = count($rows);
        }

        return $counts;
    }

    /** The plain fields of a 1C row (keys of related rows, flags), without tables and links. */
    private function scalars(array $row): array
    {
        return collect($row)->reject(fn ($v, $k): bool => is_array($v) || str_contains((string) $k, '@')
            || in_array($k, ['Ref_Key', 'Description', 'Code', 'DataVersion', 'DeletionMark', 'IsFolder', 'Predefined', 'PredefinedDataName'], true))
            ->map(fn ($v) => is_string($v) ? mb_substr($v, 0, 200) : $v)->all();
    }

    /**
     * @param  ?string  $current  the value already chosen stays in the list even when 1C did not give its list
     * @return array<string, string> ref_key => description, for a select
     */
    public function options(string $kind, ?string $owner = null, bool $withFolders = false, ?string $current = null): array
    {
        $options = $this->listed($kind, $owner, $withFolders);
        if ($current && ! isset($options[$current])) {
            $options[$current] = (string) $this->label($kind, $current);
        }

        return $options;
    }

    /** @return array<string, string> */
    private function listed(string $kind, ?string $owner, bool $withFolders): array
    {
        return OneCReference::query()->where('kind', $kind)->where('deletion_mark', false)
            ->when(! $withFolders, fn ($q) => $q->where('is_folder', false))
            ->when($owner, fn ($q) => $q->where(fn ($q) => $q->where('owner_key', $owner)->orWhereNull('owner_key')))
            ->orderBy('description')->limit(500)->pluck('description', 'ref_key')->all();
    }

    /** @return array<string, string> */
    public function search(string $kind, string $text, int $limit = 50): array
    {
        $text = trim($text);

        return OneCReference::query()->where('kind', $kind)->where('deletion_mark', false)->where('is_folder', false)
            ->where(fn ($q) => $q->where('description', 'like', '%'.$text.'%')->orWhere('inn', 'like', $text.'%')->orWhere('code', $text))
            ->orderBy('description')->limit($limit)->get()
            ->mapWithKeys(fn (OneCReference $r): array => [$r->ref_key => $r->description.($r->inn ? ' · УНП '.$r->inn : '')])->all();
    }

    public function label(string $kind, ?string $key): ?string
    {
        if (! $key || $key === self::ZERO) {
            return null;
        }

        return OneCReference::query()->where('kind', $kind)->where('ref_key', strtolower($key))->value('description') ?? 'не найдено ('.substr($key, 0, 8).'…)';
    }

    public function find(string $kind, ?string $key): ?OneCReference
    {
        return $key ? OneCReference::query()->where('kind', $kind)->where('ref_key', strtolower($key))->first() : null;
    }

    /** VAT share of a 1C rate: «20%» → 0.2, «Без НДС» → 0. */
    public function vatRate(?string $key): float
    {
        $name = (string) $this->label('vat_rate', $key);

        return preg_match('/(\d+(?:[.,]\d+)?)\s*%/u', $name, $m) ? (float) str_replace(',', '.', $m[1]) / 100 : (str_contains(mb_strtolower($name), 'без') ? 0.0 : 0.2);
    }

    /** The task with the freshest 1C lists. */
    public function lastSync(): ?OneCAgentTask
    {
        // The hourly read of recent 1C orders is a sync too, but without the lists and the order samples.
        return OneCAgentTask::query()->where('type', ODataReadTaskHandler::TYPE)->where('status', OneCAgentTask::DONE)
            ->where('payload->sync', true)->whereNotNull('payload->queries->'.self::TEMPLATES)->latest('id')->first();
    }

    /** @return list<array<string, mixed>> recent 1C orders as 1C gave them */
    public function templates(): array
    {
        $task = $this->lastSync();
        $part = $task ? ODataReadTaskHandler::part($task, self::TEMPLATES) : null;

        return $part && $part['code'] === 200 ? array_values((array) data_get($part['json'], 'value', [])) : [];
    }

    /** «МПУТ-000756 от 20.11.2026 · СБГ бай ООО · Розничный покупатель · 440,99» */
    public function templateLabel(array $order): string
    {
        $date = substr((string) ($order['Date'] ?? ''), 0, 10);
        $sum = number_format((float) ($order['СуммаДокумента'] ?? 0), 2, ',', ' ');

        return trim(($order['Number'] ?? '?').' от '.($date ? date('d.m.Y', strtotime($date)) : '?')
            .' · '.($this->label('organization', $order['Организация_Key'] ?? null) ?? '—')
            .' · '.($this->label('partner', $order['Партнер_Key'] ?? null) ?? '—').' · '.$sum);
    }

    /** @return array<string, int> kind => rows on the site */
    public function counts(): array
    {
        return OneCReference::query()->selectRaw('kind, count(*) as n')->groupBy('kind')->pluck('n', 'kind')->map(fn ($n): int => (int) $n)->all();
    }
}
