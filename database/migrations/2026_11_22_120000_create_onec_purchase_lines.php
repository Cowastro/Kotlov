<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase prices from 1C: the lines of posted «Приобретение товаров и услуг», and on a card the price of its last
 * 1C purchase (without and with VAT) — the cost the site compares retail and wholesale prices with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onec_purchase_lines', function (Blueprint $table): void {
            $table->id();
            $table->string('doc_key', 36);
            $table->unsignedInteger('line_number');
            $table->string('doc_number', 50)->nullable();
            $table->dateTime('doc_date')->index();
            $table->string('item_key', 36)->index();
            $table->string('characteristic_key', 36)->nullable();
            $table->decimal('quantity', 14, 3);
            $table->decimal('price_net', 14, 4);
            $table->decimal('price_gross', 14, 4);
            $table->string('currency_key', 36)->nullable();
            $table->string('partner_key', 36)->nullable();
            $table->string('organization_key', 36)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->unique(['doc_key', 'line_number']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('onec_cost_byn', 12, 2)->nullable()->after('manual_purchase_price_byn');
            $table->decimal('onec_cost_vat_byn', 12, 2)->nullable()->after('onec_cost_byn');
            $table->date('onec_cost_date')->nullable()->after('onec_cost_vat_byn');
            $table->string('onec_cost_doc', 50)->nullable()->after('onec_cost_date');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['onec_cost_byn', 'onec_cost_vat_byn', 'onec_cost_date', 'onec_cost_doc']);
        });
        Schema::dropIfExists('onec_purchase_lines');
    }
};
