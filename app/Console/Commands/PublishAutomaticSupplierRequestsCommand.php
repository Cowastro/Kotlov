<?php

namespace App\Console\Commands;

use App\Models\SupplierOrderRequest;
use App\Services\Orders\SupplierOrderRequestWorkflow;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PublishAutomaticSupplierRequestsCommand extends Command
{
    protected $signature = 'orders:auto-publish-supplier-requests
        {--apply : Передать разрешённые черновики в кабинеты поставщиков}';

    protected $description = 'Проверить или передать черновики только поставщикам с одобренной автоматической передачей';

    public function handle(SupplierOrderRequestWorkflow $workflow): int
    {
        $runUuid = (string) Str::uuid();
        $apply = (bool) $this->option('apply');
        $candidates = SupplierOrderRequest::query()
            ->where('status', 'draft')
            ->whereHas('supplier', fn ($query) => $query
                ->where('is_active', true)
                ->where('automatic_order_transfer_enabled', true))
            ->with('supplier')
            ->oldest('created_at')
            ->limit((int) config('shop.supplier_auto_transfer.batch_size', 100))
            ->get();

        $published = 0;
        $skipped = 0;
        $failed = 0;

        if ($apply) {
            foreach ($candidates as $request) {
                try {
                    $workflow->publishAutomatically($request, $runUuid);
                    $published++;
                } catch (ValidationException) {
                    $skipped++;
                } catch (Throwable $exception) {
                    report($exception);
                    $failed++;
                }
            }
        }

        $this->components->info(json_encode([
            'run_uuid' => $runUuid,
            'mode' => $apply ? 'apply' : 'dry-run',
            'candidates' => $candidates->count(),
            'published' => $published,
            'skipped' => $skipped,
            'failed' => $failed,
        ], JSON_UNESCAPED_UNICODE));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
