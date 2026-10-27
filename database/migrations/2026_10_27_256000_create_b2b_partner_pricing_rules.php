<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->boolean('b2b_partner_enabled')->default(false)->after('is_active');
            $table->string('b2b_client_warehouse_label')->nullable()->after('b2b_partner_enabled');
            $table->unsignedSmallInteger('b2b_stock_max_age_hours')->default(24)->after('b2b_client_warehouse_label');
            $table->unsignedSmallInteger('b2b_delivery_days_min')->default(1)->after('b2b_stock_max_age_hours');
            $table->unsignedSmallInteger('b2b_delivery_days_max')->default(2)->after('b2b_delivery_days_min');
            $table->integer('b2b_priority')->default(100)->after('b2b_delivery_days_max');
            $table->boolean('b2b_require_positive_stock')->default(true)->after('b2b_priority');
        });

        Schema::table('supplier_products', function (Blueprint $table): void {
            $table->boolean('b2b_excluded')->default(false)->after('match_confidence');
            $table->string('b2b_exclusion_reason')->nullable()->after('b2b_excluded');
        });

        Schema::create('b2b_pricing_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('scope_type', 24)->default('supplier');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('calculation_type', 24)->default('target_margin');
            $table->decimal('value', 12, 4);
            $table->decimal('minimum_profit_byn', 12, 2)->default(0);
            $table->decimal('rounding_step_byn', 12, 2)->default(1);
            $table->decimal('minimum_retail_discount_percent', 8, 4)->default(0);
            $table->integer('priority')->default(100);
            $table->string('status', 16)->default('draft');
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
            $table->index(['supplier_id', 'scope_type', 'scope_id'], 'b2b_pricing_rule_scope_index');
        });

        $eurotermId = DB::table('suppliers')->where('code', 'euroterm')->value('id');
        if (! $eurotermId) {
            return;
        }

        DB::table('suppliers')->where('id', $eurotermId)->update([
            'b2b_partner_enabled' => false,
            'b2b_client_warehouse_label' => 'Партнёрский склад',
            'b2b_stock_max_age_hours' => 24,
            'b2b_delivery_days_min' => 1,
            'b2b_delivery_days_max' => 2,
            'b2b_priority' => 100,
            'b2b_require_positive_stock' => true,
            'updated_at' => now(),
        ]);

        DB::table('b2b_pricing_rules')->insert([
            'supplier_id' => $eurotermId,
            'name' => 'Евротерм · базовая маржа 18%',
            'scope_type' => 'supplier',
            'scope_id' => null,
            'calculation_type' => 'target_margin',
            'value' => 18,
            'minimum_profit_byn' => 0,
            'rounding_step_byn' => 1,
            'minimum_retail_discount_percent' => 0,
            'priority' => 100,
            'status' => 'draft',
            'notes' => 'Пилот: только предпросмотр. Клиентам цены не публикуются.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_pricing_rules');

        Schema::table('supplier_products', function (Blueprint $table): void {
            $table->dropColumn(['b2b_excluded', 'b2b_exclusion_reason']);
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn([
                'b2b_partner_enabled', 'b2b_client_warehouse_label', 'b2b_stock_max_age_hours',
                'b2b_delivery_days_min', 'b2b_delivery_days_max', 'b2b_priority', 'b2b_require_positive_stock',
            ]);
        });
    }
};
