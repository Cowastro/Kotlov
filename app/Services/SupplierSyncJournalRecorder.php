<?php

namespace App\Services;

use App\Models\SupplierSyncRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Input\InputInterface;

class SupplierSyncJournalRecorder
{
    /** @var array<int, array{command:string, run_id:int, started_at:float}> */
    private array $runs = [];

    public function start(?string $command, InputInterface $input): void
    {
        if (! $this->shouldTrack($command) || ! $this->tablesExist()) {
            return;
        }

        $run = SupplierSyncRun::query()->create([
            'command' => $command,
            'status' => 'running',
            'started_at' => now(),
            'context' => $this->safeContext($input),
        ]);

        $this->runs[] = [
            'command' => $command,
            'run_id' => (int) $run->id,
            'started_at' => microtime(true),
        ];
    }

    public function finish(?string $command, int $exitCode): void
    {
        if (! $this->shouldTrack($command) || ! $this->tablesExist()) {
            return;
        }

        $position = $this->findRunPosition((string) $command);
        if ($position === null) {
            return;
        }

        $active = $this->runs[$position];
        array_splice($this->runs, $position, 1);

        try {
            $summary = $this->captureChanges((int) $active['run_id']);

            SupplierSyncRun::query()->whereKey($active['run_id'])->update([
                'status' => $exitCode === 0 ? 'success' : 'failed',
                'finished_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $active['started_at']) * 1000),
                'exit_code' => $exitCode,
                'changes_count' => $summary['changes'],
                'price_changes_count' => $summary['prices'],
                'stock_changes_count' => $summary['stock'],
                'supplier_names' => $summary['suppliers'],
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
            SupplierSyncRun::query()->whereKey($active['run_id'])->update([
                'status' => 'journal_error',
                'finished_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $active['started_at']) * 1000),
                'exit_code' => $exitCode,
                'updated_at' => now(),
            ]);
        }
    }

    public function establishBaseline(bool $reset = false): int
    {
        if (! $this->tablesExist()) {
            return 0;
        }

        if ($reset) {
            DB::table('supplier_sync_states')->truncate();
        }

        $states = $this->currentStates();
        $now = now();

        foreach (array_chunk(array_values($states), 500) as $chunk) {
            DB::table('supplier_sync_states')->upsert(
                array_map(fn (array $state): array => $this->statePayload($state, $now), $chunk),
                ['supplier_product_id'],
                [
                    'supplier_id', 'product_id', 'supplier_name', 'supplier_article',
                    'product_sku', 'product_name', 'supplier_price', 'retail_price',
                    'in_stock', 'stock_quantity', 'stock_status',
                    'availability_status', 'observed_at', 'updated_at',
                ]
            );
        }

        return count($states);
    }

    /** @return array{changes:int, prices:int, stock:int, suppliers:?string} */
    private function captureChanges(int $runId): array
    {
        $current = $this->currentStates();
        $previous = DB::table('supplier_sync_states')
            ->get()
            ->keyBy('supplier_product_id');
        $now = now();
        $changes = [];
        $changedStates = [];
        $priceCount = 0;
        $stockCount = 0;
        $supplierNames = [];

        foreach ($current as $supplierProductId => $state) {
            $before = $previous->get($supplierProductId);
            $flags = $before ? $this->changeFlags($before, $state) : ['link_created'];

            if ($flags === []) {
                continue;
            }

            $priceChanged = (bool) array_intersect($flags, ['supplier_price', 'retail_price']);
            $stockChanged = (bool) array_intersect($flags, [
                'in_stock', 'stock_quantity', 'stock_status', 'availability_status',
            ]);
            $priceCount += $priceChanged ? 1 : 0;
            $stockCount += $stockChanged ? 1 : 0;
            $supplierNames[$state['supplier_name'] ?: 'Без поставщика'] = true;

            $changes[] = $this->changePayload($runId, $before, $state, $flags, $now);
            $changedStates[] = $this->statePayload($state, $now);
        }

        $removedIds = $previous->keys()->diff(array_keys($current));
        foreach ($removedIds as $supplierProductId) {
            $before = $previous->get($supplierProductId);
            $supplierNames[$before->supplier_name ?: 'Без поставщика'] = true;
            $changes[] = $this->changePayload($runId, $before, null, ['link_removed'], $now);
        }

        foreach (array_chunk($changes, 500) as $chunk) {
            DB::table('supplier_sync_changes')->insert($chunk);
        }

        foreach (array_chunk($changedStates, 500) as $chunk) {
            DB::table('supplier_sync_states')->upsert(
                $chunk,
                ['supplier_product_id'],
                [
                    'supplier_id', 'product_id', 'supplier_name', 'supplier_article',
                    'product_sku', 'product_name', 'supplier_price', 'retail_price',
                    'in_stock', 'stock_quantity', 'stock_status',
                    'availability_status', 'observed_at', 'updated_at',
                ]
            );
        }

        if ($removedIds->isNotEmpty()) {
            DB::table('supplier_sync_states')->whereIn('supplier_product_id', $removedIds)->delete();
        }

        return [
            'changes' => count($changes),
            'prices' => $priceCount,
            'stock' => $stockCount,
            'suppliers' => $supplierNames === []
                ? null
                : mb_substr(implode(', ', array_keys($supplierNames)), 0, 255),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function currentStates(): array
    {
        return DB::table('supplier_products as sp')
            ->leftJoin('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->leftJoin('products as p', 'p.id', '=', 'sp.product_id')
            ->select([
                'sp.id as supplier_product_id', 'sp.supplier_id', 'sp.product_id',
                's.name as supplier_name', 'sp.supplier_article', 'sp.price_byn as supplier_price',
                'sp.in_stock', 'sp.stock_quantity', 'sp.stock_status',
                'p.sku as product_sku', 'p.name as product_name', 'p.price as retail_price',
                'p.availability_status',
            ])
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (int) $row->supplier_product_id => [
                    'supplier_product_id' => (int) $row->supplier_product_id,
                    'supplier_id' => $row->supplier_id !== null ? (int) $row->supplier_id : null,
                    'product_id' => $row->product_id !== null ? (int) $row->product_id : null,
                    'supplier_name' => $row->supplier_name,
                    'supplier_article' => $row->supplier_article,
                    'product_sku' => $row->product_sku,
                    'product_name' => $row->product_name,
                    'supplier_price' => $this->money($row->supplier_price),
                    'retail_price' => $this->money($row->retail_price),
                    'in_stock' => $row->in_stock === null ? null : (bool) $row->in_stock,
                    'stock_quantity' => $row->stock_quantity === null ? null : (int) $row->stock_quantity,
                    'stock_status' => $row->stock_status,
                    'availability_status' => $row->availability_status,
                ],
            ])
            ->all();
    }

    /** @return array<int, string> */
    private function changeFlags(object $before, array $after): array
    {
        $flags = [];
        $comparisons = [
            'supplier_price' => [$this->money($before->supplier_price), $after['supplier_price']],
            'retail_price' => [$this->money($before->retail_price), $after['retail_price']],
            'in_stock' => [$before->in_stock === null ? null : (bool) $before->in_stock, $after['in_stock']],
            'stock_quantity' => [$before->stock_quantity === null ? null : (int) $before->stock_quantity, $after['stock_quantity']],
            'stock_status' => [$before->stock_status, $after['stock_status']],
            'availability_status' => [$before->availability_status, $after['availability_status']],
            'product_link' => [$before->product_id === null ? null : (int) $before->product_id, $after['product_id']],
        ];

        foreach ($comparisons as $field => [$old, $new]) {
            if ($old !== $new) {
                $flags[] = $field;
            }
        }

        return $flags;
    }

    private function statePayload(array $state, $now): array
    {
        return [
            ...$state,
            'observed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function changePayload(int $runId, ?object $before, ?array $after, array $flags, $now): array
    {
        return [
            'supplier_sync_run_id' => $runId,
            'supplier_id' => $after['supplier_id'] ?? $before?->supplier_id,
            'supplier_product_id' => $after['supplier_product_id'] ?? $before?->supplier_product_id,
            'product_id' => $after['product_id'] ?? $before?->product_id,
            'supplier_name' => $after['supplier_name'] ?? $before?->supplier_name,
            'supplier_article' => $after['supplier_article'] ?? $before?->supplier_article,
            'product_sku' => $after['product_sku'] ?? $before?->product_sku,
            'product_name' => $after['product_name'] ?? $before?->product_name,
            'change_flags' => json_encode($flags, JSON_UNESCAPED_UNICODE),
            'supplier_price_before' => $before?->supplier_price,
            'supplier_price_after' => $after['supplier_price'] ?? null,
            'retail_price_before' => $before?->retail_price,
            'retail_price_after' => $after['retail_price'] ?? null,
            'in_stock_before' => $before?->in_stock,
            'in_stock_after' => $after['in_stock'] ?? null,
            'stock_quantity_before' => $before?->stock_quantity,
            'stock_quantity_after' => $after['stock_quantity'] ?? null,
            'stock_status_before' => $before?->stock_status,
            'stock_status_after' => $after['stock_status'] ?? null,
            'availability_before' => $before?->availability_status,
            'availability_after' => $after['availability_status'] ?? null,
            'created_at' => $now,
        ];
    }

    private function money(mixed $value): ?string
    {
        return $value === null ? null : number_format((float) $value, 2, '.', '');
    }

    private function shouldTrack(?string $command): bool
    {
        return is_string($command) && str_starts_with($command, 'supplier:sync-');
    }

    private function tablesExist(): bool
    {
        return Schema::hasTable('supplier_sync_runs')
            && Schema::hasTable('supplier_sync_changes')
            && Schema::hasTable('supplier_sync_states');
    }

    private function findRunPosition(string $command): ?int
    {
        for ($i = count($this->runs) - 1; $i >= 0; $i--) {
            if ($this->runs[$i]['command'] === $command) {
                return $i;
            }
        }

        return null;
    }

    private function safeContext(InputInterface $input): array
    {
        $safe = [];
        foreach (['sheet', 'brand', 'only-existing', 'only-linked', 'sync-retail-prices'] as $name) {
            if ($input->hasOption($name)) {
                $value = $input->getOption($name);
                if ($value !== null && $value !== false && $value !== '') {
                    $safe[$name] = $value;
                }
            }
        }

        return $safe;
    }
}
