<?php

use App\Models\OneCPriceBatch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Price batches finished before «Мост 1С» existed go into its history (the pilot batch №1 among them). */
return new class extends Migration
{
    public function up(): void
    {
        $batches = DB::table('onec_price_batches')->whereIn('status', [OneCPriceBatch::CREATED, OneCPriceBatch::FAILED])
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('onec_agent_tasks')
                ->whereColumn('onec_agent_tasks.subject_id', 'onec_price_batches.id')
                ->where('onec_agent_tasks.subject_type', OneCPriceBatch::class))
            ->orderBy('id')->get();
        foreach ($batches as $batch) {
            $done = $batch->status === OneCPriceBatch::CREATED;
            $items = DB::table('onec_price_batch_items')->where('onec_price_batch_id', $batch->id)->count();
            DB::table('onec_agent_tasks')->insert([
                'type' => 'price_batch', 'subject_type' => OneCPriceBatch::class, 'subject_id' => $batch->id,
                'title' => 'Цены: пакет №'.$batch->id.', товаров '.$items, 'status' => $done ? 'done' : 'failed', 'attempts' => 1,
                'created_by' => $batch->approved_by, 'picked_at' => $batch->picked_at, 'finished_at' => $batch->finished_at,
                'summary' => $done ? 'Документ '.($batch->onec_document_number ?: 'создан').', не проведён' : null,
                'result' => $batch->onec_document_number ? json_encode(['document_number' => $batch->onec_document_number,
                    'document_key' => (string) $batch->onec_document_key], JSON_UNESCAPED_UNICODE) : null,
                'error' => $done ? null : $batch->error,
                'created_at' => $batch->approved_at ?? $batch->created_at, 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void {}
};
