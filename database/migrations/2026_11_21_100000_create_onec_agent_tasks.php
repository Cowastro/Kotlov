<?php

use App\Models\OneCPriceBatch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «Мост 1С»: one queue of tasks for the agent on the 1C server (price batches first, orders and the rest later).
 * The agent takes one task at a time, does it in 1C and reports the result; the site keeps the history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onec_agent_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->index();
            $table->nullableMorphs('subject');
            $table->string('title');
            $table->string('status', 20)->default('queued')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('picked_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('summary')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::table('onec_agent_tokens', function (Blueprint $table) {
            $table->string('agent_version', 20)->nullable()->after('last_seen_at');
        });

        // Price batches already waiting for the agent go into the queue as they are.
        $now = now();
        foreach (DB::table('onec_price_batches')->whereIn('status', [OneCPriceBatch::APPROVED, OneCPriceBatch::SENDING])->orderBy('id')->get() as $batch) {
            DB::table('onec_agent_tasks')->insert([
                'type' => 'price_batch', 'subject_type' => OneCPriceBatch::class, 'subject_id' => $batch->id,
                'title' => 'Цены: пакет №'.$batch->id, 'status' => $batch->status === OneCPriceBatch::SENDING ? 'sending' : 'queued',
                'picked_at' => $batch->picked_at, 'created_by' => $batch->approved_by, 'created_at' => $batch->approved_at ?? $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('onec_agent_tokens', function (Blueprint $table) {
            $table->dropColumn('agent_version');
        });
        Schema::dropIfExists('onec_agent_tasks');
    }
};
