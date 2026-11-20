<?php

namespace App\Services\Integrations;

use App\Models\IntegrationSource;
use App\Models\OneCAgentTask;
use App\Models\OneCPriceBatch;
use App\Models\User;
use App\Services\Integrations\OneCBridge\OneCAgentTasks;
use App\Services\Integrations\OneCBridge\PriceBatchTaskHandler;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Batches of prices for 1C: built from the goods in stock without a price, edited and approved by a manager,
 * handed to the agent on the 1C server one at a time, closed with the number of the 1C document it made.
 */
class OneCPriceBatches
{
    private const LOADED = ['ok', 'computed', 'raised'];

    private const GUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(
        private readonly PricelessStockPriceProposal $proposal,
        private readonly OneCAgentTasks $tasks,
    ) {}

    public function buildFromPriceless(?User $user): OneCPriceBatch
    {
        $source = IntegrationSource::query()->where('code', 'onec')->firstOrFail();
        $rows = $this->proposal->propose($source)->filter(fn (array $r): bool => in_array($r['verdict'], self::LOADED, true));

        return DB::transaction(function () use ($rows, $user): OneCPriceBatch {
            $batch = OneCPriceBatch::query()->create(['status' => OneCPriceBatch::DRAFT, 'created_by' => $user?->id,
                'comment' => 'Товары с остатком без цены в 1С']);
            foreach ($rows as $r) {
                [$item, $characteristic] = array_pad(explode('#', (string) $r['row']->external_id, 2), 2, null);
                if (! preg_match(self::GUID, (string) $item)) {
                    continue;
                }
                $batch->items()->create([
                    'integration_product_id' => $r['row']->id,
                    'item_key' => strtolower($item),
                    'characteristic_key' => $characteristic && preg_match(self::GUID, $characteristic) ? strtolower($characteristic) : null,
                    'code' => $r['row']->external_code,
                    'name' => mb_substr((string) $r['row']->name, 0, 250),
                    'retail' => $r['retail'],
                    'wholesale' => $r['wholesale'],
                    'retail_site' => $r['retail_site'],
                    'purchase' => $r['purchase'],
                    'verdict' => $r['verdict'],
                    'note' => mb_substr($r['note'], 0, 250) ?: null,
                ]);
            }

            return $batch->loadCount('items');
        });
    }

    public function approve(OneCPriceBatch $batch, ?User $user): void
    {
        DB::transaction(function () use ($batch, $user): void {
            $locked = OneCPriceBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($locked->status !== OneCPriceBatch::DRAFT) {
                throw new DomainException('Отправить можно только черновик.');
            }
            $bad = $locked->items()->where(fn ($q) => $q->where('retail', '<=', 0)->orWhere('wholesale', '<=', 0)
                ->orWhereColumn('wholesale', '>', 'retail'))->count();
            if ($locked->items()->count() === 0) {
                throw new DomainException('В пакете нет товаров.');
            }
            if ($bad > 0) {
                throw new DomainException("У {$bad} товаров цена не задана или опт выше розницы.");
            }
            $locked->forceFill(['status' => OneCPriceBatch::APPROVED, 'approved_by' => $user?->id, 'approved_at' => now(), 'error' => null])->save();
            $this->tasks->enqueue(PriceBatchTaskHandler::TYPE, $locked, 'Цены: пакет №'.$locked->id.', товаров '.$locked->items()->count(), $user);
        });
    }

    /** @return array<string, mixed> what the agent writes into 1C: one row per item and 1C price type */
    public function payload(OneCPriceBatch $batch): array
    {
        $config = config('onec.price_documents');
        $vat = (float) $config['vat_rate'];
        $rows = [];
        foreach ($batch->items()->orderBy('id')->get() as $item) {
            foreach ($config['types'] as [$kind, $withVat, $typeKey, $formula]) {
                $withVatPrice = (float) $item->{$kind};
                $rows[] = [
                    'item_key' => $item->item_key,
                    'characteristic_key' => $item->characteristic_key ?: '00000000-0000-0000-0000-000000000000',
                    'price_type_key' => $typeKey,
                    'price' => round($withVat ? $withVatPrice : $withVatPrice / $vat, 2),
                    'manual' => $formula === '',
                    'formula' => $formula,
                ];
            }
        }

        return [
            'batch_id' => $batch->id,
            'comment' => 'Цены с сайта KOTLOV, пакет №'.$batch->id,
            'currency_key' => $config['currency_key'],
            'price_type_keys' => array_values(array_map(fn (array $t): string => $t[2], $config['types'])),
            'rows' => $rows,
        ];
    }

    /** @param array{ok?: bool, document_number?: string, document_key?: string, error?: string} $result */
    public function finish(OneCPriceBatch $batch, array $result): OneCPriceBatch
    {
        if ($batch->status !== OneCPriceBatch::SENDING) {
            throw new DomainException('Пакет не ждёт ответа агента.');
        }
        $ok = (bool) ($result['ok'] ?? false);
        $batch->forceFill([
            'status' => $ok ? OneCPriceBatch::CREATED : OneCPriceBatch::FAILED,
            'finished_at' => now(),
            'onec_document_number' => $ok ? mb_substr((string) ($result['document_number'] ?? ''), 0, 60) ?: null : null,
            'onec_document_key' => $ok && preg_match(self::GUID, (string) ($result['document_key'] ?? '')) ? strtolower($result['document_key']) : null,
            'error' => $ok ? null : mb_substr((string) ($result['error'] ?? 'Агент не сообщил причину'), 0, 2000),
        ])->save();

        return $batch;
    }

    /** A failed batch goes back to the agent's queue as it is, or (failed or still waiting) to a draft to be corrected. */
    public function retry(OneCPriceBatch $batch, bool $toDraft = false): void
    {
        if (! in_array($batch->status, [OneCPriceBatch::FAILED, OneCPriceBatch::APPROVED], true)) {
            throw new DomainException('Повторить можно только пакет с ошибкой.');
        }
        $task = $this->tasks->activeFor($batch, PriceBatchTaskHandler::TYPE);
        if ($toDraft) {
            if ($task) {
                $this->tasks->cancel($task);
            }
            $batch->forceFill(['status' => OneCPriceBatch::DRAFT, 'picked_at' => null, 'finished_at' => null])->save();

            return;
        }
        if ($batch->status !== OneCPriceBatch::FAILED) {
            throw new DomainException('Пакет и так ждёт агента 1С.');
        }
        $failed = OneCAgentTask::query()->whereMorphedTo('subject', $batch)->where('type', PriceBatchTaskHandler::TYPE)
            ->where('status', OneCAgentTask::FAILED)->latest('id')->first();
        if ($failed) {
            $this->tasks->retry($failed);
        } else {
            $batch->forceFill(['status' => OneCPriceBatch::APPROVED, 'picked_at' => null, 'finished_at' => null, 'error' => null])->save();
            $this->tasks->enqueue(PriceBatchTaskHandler::TYPE, $batch, 'Цены: пакет №'.$batch->id, null);
        }
        $batch->refresh();
    }
}
