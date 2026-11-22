<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\OneCReference;
use App\Models\Order;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Finds the 1C order a manager already entered by hand for a site order (phone in the delivery text, sum, the same
 * 1C items, the company, the dates), so the order is linked instead of being sent to 1C a second time.
 * It only suggests: the manager links.
 */
class OneCOrderMatcher
{
    /** From this score the order is shown as «найден в 1С» (e.g. phone and sum, or sum and the same items). */
    public const FOUND = 55;

    /** Site order statuses that are not looked for in 1C any more. */
    private const CLOSED = ['cancelled'];

    public function __construct(
        private readonly OneCOrderExport $export,
        private readonly OneCReferences $refs,
    ) {}

    /** @return list<array{ref: OneCReference, score: int, reasons: list<string>, label: string}> best first */
    public function candidates(Order $order, int $limit = 8): array
    {
        // The order page asks several times per request (label, colour, form); the matcher lives one request.
        return array_slice($this->cache[$order->id.'|'.$order->updated_at] ??= $this->find($order), 0, $limit);
    }

    /** @var array<string, list<array<string, mixed>>> */
    private array $cache = [];

    private function find(Order $order): array
    {
        $created = $order->created_at ?? now();
        $taken = Order::query()->whereNotNull('onec_order_guid')->whereKeyNot($order->id)->pluck('onec_order_guid')->map(fn ($k) => strtolower($k))->flip();
        $phone = substr(preg_replace('/\D+/', '', (string) $order->customer_phone), -9);
        $surname = mb_strtolower(trim((string) explode(' ', trim((string) $order->customer_name))[0]));
        $keys = collect($this->export->lines($order))->pluck('key')->filter()->unique()->values();
        $total = round((float) $order->total, 2);
        $goods = round((float) $order->items->sum('total'), 2);
        $taxId = $this->export->companyTaxId($order);
        $company = $taxId !== '' ? $this->export->counterpartyByTaxId($taxId)?->ref_key : null;
        $clientNumber = OneCOrderExport::clientNumber($order);

        $found = [];
        $rows = OneCReference::query()->where('kind', 'order')->where('deletion_mark', false)
            ->whereBetween('code', [$created->copy()->subDays(3)->format('Y-m-d\TH:i:s'), $created->copy()->addDays(21)->format('Y-m-d\TH:i:s')])
            ->get();
        foreach ($rows as $ref) {
            if ($taken->has($ref->ref_key)) {
                continue;
            }
            $d = $ref->data ?? [];
            $score = 0;
            $reasons = [];
            if (($d['client_number'] ?? null) === $clientNumber || str_contains((string) ($d['text'] ?? ''), 'kotlov.by №'.mb_strtolower((string) $order->number))) {
                $score += 100;
                $reasons[] = 'номер заказа сайта';
            }
            if (strlen($phone) === 9 && str_contains(str_replace(' ', '', (string) ($d['digits'] ?? '')), $phone)) {
                $score += 45;
                $reasons[] = 'телефон';
            }
            $sum = round((float) ($d['sum'] ?? 0), 2);
            if ($sum > 0 && (abs($sum - $total) < 0.02 || abs($sum - $goods) < 0.02)) {
                $score += 30;
                $reasons[] = 'сумма';
            }
            $theirs = collect($d['items'] ?? [])->pluck(0)->filter()->unique();
            if ($keys->isNotEmpty() && $theirs->isNotEmpty()) {
                $same = $keys->intersect($theirs)->count();
                if ($same > 0) {
                    $score += (int) round(35 * $same / max($keys->count(), $theirs->count()));
                    $reasons[] = 'товары '.$same.' из '.$keys->count();
                }
            }
            if ($company && ($d['counterparty'] ?? null) === $company) {
                $score += 40;
                $reasons[] = 'компания по УНП';
            }
            if (mb_strlen($surname) >= 3 && str_contains((string) ($d['text'] ?? ''), $surname)) {
                $score += 10;
                $reasons[] = 'фамилия';
            }
            if ($score >= 30) {
                $found[] = ['ref' => $ref, 'score' => $score, 'reasons' => $reasons, 'label' => $this->label($ref).' — совпадает: '.implode(', ', $reasons)];
            }
        }

        return collect($found)->sortByDesc('score')->take(8)->values()->all();
    }

    /** The best 1C order when it is a strong match. */
    public function found(Order $order): ?array
    {
        $best = $this->candidates($order, 1)[0] ?? null;

        return $best && $best['score'] >= self::FOUND ? $best : null;
    }

    /**
     * Remembers on each recent site order not yet in 1C the 1C order it was most likely entered as, for the order
     * list and its filter. Runs after every read of 1C orders.
     */
    public function refreshSuggestions(int $days = 60): int
    {
        $found = 0;
        Order::query()->whereNull('onec_order_guid')->whereNotIn('status', self::CLOSED)->whereNull('archived_at')
            ->where('created_at', '>', now()->subDays($days))->with('items.integrationProduct')
            ->chunkById(100, function ($orders) use (&$found): void {
                foreach ($orders as $order) {
                    $best = $this->found($order);
                    $found += $best ? 1 : 0;
                    $values = ['onec_match_guid' => $best['ref']->ref_key ?? null, 'onec_match_number' => $best['ref']->description ?? null,
                        'onec_match_reasons' => $best ? mb_substr(implode(', ', $best['reasons']), 0, 255) : null];
                    if ($values['onec_match_guid'] !== $order->onec_match_guid || $values['onec_match_reasons'] !== $order->onec_match_reasons) {
                        Order::query()->whereKey($order->id)->update($values);
                    }
                }
            });

        return $found;
    }

    /** «МПУТ-000756 от 20.11.2026 · Розничный покупатель · 440,99 · к выполнению» */
    public function label(OneCReference $ref): string
    {
        $d = $ref->data ?? [];

        return $ref->description.' от '.($ref->code ? date('d.m.Y', strtotime($ref->code)) : '?')
            .' · '.($this->refs->label('partner', $ref->owner_key) ?? '—')
            .' · '.number_format((float) ($d['sum'] ?? 0), 2, ',', ' ')
            .(isset($d['status']) ? ' · '.mb_strtolower(OneCOrderProfiles::STATUSES[$d['status']] ?? (string) $d['status']) : '');
    }

    public function labelFor(string $refKey): ?string
    {
        $ref = OneCReference::query()->where('kind', 'order')->where('ref_key', strtolower($refKey))->first();

        return $ref ? $this->label($ref) : null;
    }

    /** @return array<string, string> 1C orders by number, for a manual search */
    public function search(string $text): array
    {
        $text = trim($text);
        if (mb_strlen($text) < 2) {
            return [];
        }

        return OneCReference::query()->where('kind', 'order')->where('deletion_mark', false)
            ->where('description', 'like', '%'.$text.'%')->orderByDesc('code')->limit(30)->get()
            ->mapWithKeys(fn (OneCReference $r): array => [$r->ref_key => $this->label($r)])->all();
    }

    /** The site order is the 1C order: it never goes to 1C again, its status comes from 1C. */
    public function link(Order $order, string $refKey, ?User $user): void
    {
        DB::transaction(function () use ($order, $refKey, $user): void {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $ref = OneCReference::query()->where('kind', 'order')->where('ref_key', strtolower($refKey))->first();
            if (! $ref) {
                throw new DomainException('Заказ 1С не найден среди прочитанных: обновите заказы 1С в «Мост 1С».');
            }
            if ($order->onec_order_guid && ! $order->onec_deleted) {
                throw new DomainException('Заказ сайта уже связан с заказом 1С '.($order->onec_order_number ?: $order->onec_order_guid).'.');
            }
            $other = Order::query()->where('onec_order_guid', $ref->ref_key)->whereKeyNot($order->id)->value('number');
            if ($other) {
                throw new DomainException('Заказ 1С '.$ref->description.' уже связан с заказом сайта №'.$other.'.');
            }
            if (app(OneCAgentTasks::class)->activeFor($order, OrderTaskHandler::TYPE)) {
                throw new DomainException('Заказ уже в очереди на создание в 1С — отмените задачу в «Мост 1С».');
            }
            $d = $ref->data ?? [];
            $order->forceFill(['onec_order_guid' => $ref->ref_key, 'onec_order_number' => $ref->description, 'onec_exported_at' => now(),
                'onec_doc_status' => $d['status'] ?? null, 'onec_posted' => (bool) ($d['posted'] ?? false), 'onec_deleted' => false,
                'onec_checked_at' => now(), 'onec_profile_id' => null, 'onec_match_guid' => null, 'onec_match_number' => null, 'onec_match_reasons' => null])->save();
            $order->managerComments()->create(['user_id' => $user?->id, 'author_name' => 'Мост 1С',
                'body' => 'Связан с заказом 1С '.$ref->description.' (заведён в 1С вручную) — повторно в 1С не передаётся.']);
        });
        app(OneCOrderStages::class)->apply($order, upTo: 'processing');
    }

    /** A wrong link is undone; an order the site created in 1C stays (it would be sent a second time). */
    public function unlink(Order $order, ?User $user): void
    {
        DB::transaction(function () use ($order, $user): void {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (! $order->onec_order_guid) {
                return;
            }
            if ($order->onec_profile_id) {
                throw new DomainException('Этот заказ создан в 1С с сайта — его отвязка привела бы к повторной отправке. Пометьте заказ 1С на удаление в 1С.');
            }
            $number = $order->onec_order_number;
            $order->forceFill(['onec_order_guid' => null, 'onec_order_number' => null, 'onec_exported_at' => null, 'onec_doc_status' => null,
                'onec_posted' => null, 'onec_deleted' => false, 'onec_checked_at' => null])->save();
            $order->managerComments()->create(['user_id' => $user?->id, 'author_name' => 'Мост 1С',
                'body' => 'Связь с заказом 1С '.$number.' снята.']);
        });
    }
}
