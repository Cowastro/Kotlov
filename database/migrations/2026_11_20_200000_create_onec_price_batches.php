<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prices the site sends to 1C: a manager prepares a batch (wholesale and retail with VAT per 1C item), approves
 * it, and the agent on the 1C server picks it up and creates an unposted «Установка цен номенклатуры».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onec_price_batches', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('draft')->index();
            $table->string('comment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('picked_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('onec_document_number')->nullable();
            $table->string('onec_document_key', 36)->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('onec_price_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onec_price_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_key', 36);
            $table->string('characteristic_key', 36)->nullable();
            $table->string('code')->nullable();
            $table->string('name');
            $table->decimal('retail', 12, 2);
            $table->decimal('wholesale', 12, 2);
            $table->decimal('retail_site', 12, 2)->nullable();
            $table->decimal('purchase', 12, 2)->nullable();
            $table->string('verdict', 20)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['onec_price_batch_id', 'item_key'], 'onec_price_items_batch_item');
        });

        Schema::create('onec_agent_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onec_agent_tokens');
        Schema::dropIfExists('onec_price_batch_items');
        Schema::dropIfExists('onec_price_batches');
    }
};
