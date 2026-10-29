<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('b2b_price_levels', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('rank')->default(1);
            $table->decimal('sale_price_discount_percent', 7, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();
        });

        $now = now();
        DB::table('b2b_price_levels')->insert([
            [
                'code' => 'partner',
                'name' => 'Опт-1 · Партнёр',
                'description' => 'Базовый уровень онлайн-склада для нового подтверждённого B2B-клиента.',
                'rank' => 1,
                'sale_price_discount_percent' => 0,
                'is_active' => true,
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'professional',
                'name' => 'Опт-2 · Профессионал',
                'description' => 'Уровень для регулярных покупателей и монтажных организаций.',
                'rank' => 2,
                'sale_price_discount_percent' => 0,
                'is_active' => true,
                'sort_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'dealer',
                'name' => 'Опт-3 · Дилер',
                'description' => 'Индивидуальные условия для постоянных клиентов с подтверждённым оборотом.',
                'rank' => 3,
                'sale_price_discount_percent' => 0,
                'is_active' => true,
                'sort_order' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::table('b2b_clients', function (Blueprint $table): void {
            $table->foreignId('b2b_price_level_id')
                ->nullable()
                ->after('partner_type')
                ->constrained('b2b_price_levels')
                ->nullOnDelete();
            $table->string('service_mode', 40)->default('online_warehouse')->after('b2b_price_level_id');
            $table->decimal('minimum_order_amount', 12, 2)->nullable()->after('delivery_terms');
            $table->unsignedSmallInteger('payment_deferment_days')->nullable()->after('minimum_order_amount');
            $table->decimal('credit_limit', 14, 2)->nullable()->after('payment_deferment_days');
            $table->date('commercial_terms_valid_until')->nullable()->after('credit_limit');
            $table->json('allowed_integration_warehouse_ids')->nullable()->after('allowed_integration_source_ids');
            $table->boolean('specification_service_enabled')->default(true)->after('allowed_integration_warehouse_ids');
        });

        $partnerLevelId = DB::table('b2b_price_levels')->where('code', 'partner')->value('id');
        DB::table('b2b_clients')
            ->where('status', 'approved')
            ->whereNull('b2b_price_level_id')
            ->update(['b2b_price_level_id' => $partnerLevelId]);

        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('b2b_base_price', 12, 2)->nullable()->after('price_tax_mode');
            $table->unsignedBigInteger('b2b_price_level_id')->nullable()->after('b2b_base_price');
            $table->string('b2b_price_level_code', 40)->nullable()->after('b2b_price_level_id');
            $table->string('b2b_price_level_name')->nullable()->after('b2b_price_level_code');
            $table->decimal('b2b_discount_percent', 7, 2)->nullable()->after('b2b_price_level_name');
            $table->timestamp('b2b_priced_at')->nullable()->after('b2b_discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn([
                'b2b_base_price', 'b2b_price_level_id', 'b2b_price_level_code',
                'b2b_price_level_name', 'b2b_discount_percent', 'b2b_priced_at',
            ]);
        });

        Schema::table('b2b_clients', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('b2b_price_level_id');
            $table->dropColumn([
                'service_mode', 'minimum_order_amount', 'payment_deferment_days',
                'credit_limit', 'commercial_terms_valid_until',
                'allowed_integration_warehouse_ids', 'specification_service_enabled',
            ]);
        });

        Schema::dropIfExists('b2b_price_levels');
    }
};
