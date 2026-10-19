<?php

namespace App\Services\Marketplaces;

use App\Models\DealByOrderInbox;
use App\Models\MarketplaceSyncCursor;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class DealByOrderCapture
{
    private const CHANNEL = 'deal_by';

    private const STREAM = 'orders';

    private const PAGE_SIZE = 100;

    public function __construct(private readonly DealByClient $client) {}

    /**
     * @return array{received:int,created:int,updated:int,unchanged:int,from:string,to:string}
     */
    public function capture(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        try {
            return Cache::lock('deal-by:order-capture', 300)->block(
                1,
                fn (): array => $this->captureLocked($from, $to),
            );
        } catch (LockTimeoutException) {
            throw new RuntimeException('Другой процесс уже загружает заказы Deal.by.');
        }
    }

    /**
     * @return array{received:int,created:int,updated:int,unchanged:int,from:string,to:string}
     */
    private function captureLocked(?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        $now = CarbonImmutable::now('UTC');
        $cursor = MarketplaceSyncCursor::query()->firstOrCreate([
            'channel' => self::CHANNEL,
            'stream' => self::STREAM,
        ]);
        $cursor->forceFill(['last_started_at' => $now, 'last_error' => null])->save();

        $overlapMinutes = max(1, (int) config('services.deal_by.orders_overlap_minutes', 15));
        $lookbackDays = max(1, (int) config('services.deal_by.orders_initial_lookback_days', 30));
        $effectiveFrom = $from
            ?? ($cursor->cursor_at
                ? CarbonImmutable::instance($cursor->cursor_at)->utc()->subMinutes($overlapMinutes)
                : $now->subDays($lookbackDays));
        $effectiveTo = ($to ?? $now)->utc();

        if ($effectiveFrom->greaterThanOrEqualTo($effectiveTo)) {
            throw new RuntimeException('Начало периода Deal.by должно быть раньше его окончания.');
        }

        $metrics = ['received' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0];

        try {
            $lastId = null;
            for ($page = 1; $page <= 1000; $page++) {
                $payload = $this->client->listOrders(self::PAGE_SIZE, $lastId, [
                    'last_modified_from' => $effectiveFrom->format('Y-m-d\TH:i:s\Z'),
                    'last_modified_to' => $effectiveTo->format('Y-m-d\TH:i:s\Z'),
                    'sort_dir' => 'asc',
                ]);
                if (! isset($payload['orders']) || ! is_array($payload['orders'])) {
                    throw new RuntimeException('Deal.by вернул ответ без списка заказов; курсор не изменён.');
                }

                $orders = collect($payload['orders'])->values();
                if ($orders->contains(fn (mixed $order): bool => ! is_array($order) || ! filled($order['id'] ?? null))) {
                    throw new RuntimeException('Deal.by вернул заказ без идентификатора; курсор не изменён.');
                }

                foreach ($orders as $order) {
                    $result = $this->store($order, $now);
                    $metrics['received']++;
                    $metrics[$result]++;
                }

                if (count($payload['orders']) < self::PAGE_SIZE) {
                    break;
                }

                $nextLastId = (int) data_get($orders->last(), 'id');
                if ($nextLastId <= 0 || $nextLastId === $lastId) {
                    throw new RuntimeException('Deal.by вернул некорректный курсор пагинации заказов.');
                }
                $lastId = $nextLastId;

                if ($page === 1000) {
                    throw new RuntimeException('Превышен безопасный предел в 100 000 заказов за один запуск.');
                }
            }

            $cursor->forceFill([
                'cursor_at' => $effectiveTo,
                'last_succeeded_at' => CarbonImmutable::now('UTC'),
                'last_failed_at' => null,
                'last_error' => null,
                'metadata' => $metrics,
            ])->save();
        } catch (Throwable $exception) {
            $cursor->forceFill([
                'last_failed_at' => CarbonImmutable::now('UTC'),
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();

            throw $exception;
        }

        return [
            ...$metrics,
            'from' => $effectiveFrom->toIso8601String(),
            'to' => $effectiveTo->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function store(array $payload, CarbonInterface $seenAt): string
    {
        $remoteOrderId = trim((string) $payload['id']);
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $hash = hash('sha256', $encoded);

        return DB::transaction(function () use ($payload, $seenAt, $remoteOrderId, $hash): string {
            $inbox = DealByOrderInbox::query()
                ->where('remote_order_id', $remoteOrderId)
                ->lockForUpdate()
                ->first();

            if (! $inbox) {
                DealByOrderInbox::query()->create([
                    ...$this->attributes($payload, $seenAt),
                    'remote_order_id' => $remoteOrderId,
                    'first_seen_at' => $seenAt,
                    'payload_hash' => $hash,
                    'payload' => $payload,
                    'processing_status' => DealByOrderInbox::STATUS_PENDING,
                ]);

                return 'created';
            }

            if (hash_equals($inbox->payload_hash, $hash)) {
                $inbox->forceFill(['last_seen_at' => $seenAt])->save();

                return 'unchanged';
            }

            $inbox->forceFill([
                ...$this->attributes($payload, $seenAt),
                'payload_hash' => $hash,
                'payload' => $payload,
                'processing_status' => $inbox->order_id
                    ? $inbox->processing_status
                    : DealByOrderInbox::STATUS_PENDING,
                'last_error' => null,
            ])->save();

            return 'updated';
        });
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function attributes(array $payload, CarbonInterface $seenAt): array
    {
        return [
            'remote_status' => filled($payload['status'] ?? null) ? (string) $payload['status'] : null,
            'remote_status_name' => filled($payload['status_name'] ?? null) ? (string) $payload['status_name'] : null,
            'remote_created_at' => $this->parseDate($payload['date_created'] ?? null),
            'remote_modified_at' => $this->parseDate($payload['date_modified'] ?? null),
            'last_seen_at' => $seenAt,
        ];
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }
}
