<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders from the admin to 1C: the 1C lists the agent reads (organizations, partners, agreements…), the order
 * schemes made from a real 1C order («Розница СБГ бай», «Опт СанБизнесГруп») and what 1C says about the order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onec_references', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 32);
            $table->string('ref_key', 36);
            $table->string('description', 500)->nullable();
            $table->string('code', 100)->nullable();
            $table->string('owner_key', 36)->nullable()->index();
            $table->string('inn', 40)->nullable()->index();
            $table->json('data')->nullable();
            $table->boolean('deletion_mark')->default(false);
            $table->boolean('is_folder')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->unique(['kind', 'ref_key']);
            $table->index(['kind', 'description']);
        });

        Schema::create('onec_order_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('customer_mode', 20)->default('retail');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->json('header');
            $table->json('row_defaults')->nullable();
            $table->json('payment_stage')->nullable();
            $table->json('meta')->nullable();
            $table->string('template_number', 50)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('onec_profile_id')->nullable()->after('onec_order_number');
            $table->string('onec_doc_status', 40)->nullable()->after('onec_profile_id');
            $table->boolean('onec_posted')->nullable()->after('onec_doc_status');
            $table->boolean('onec_deleted')->default(false)->after('onec_posted');
            $table->timestamp('onec_checked_at')->nullable()->after('onec_deleted');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['onec_profile_id', 'onec_doc_status', 'onec_posted', 'onec_deleted', 'onec_checked_at']);
        });
        Schema::dropIfExists('onec_order_profiles');
        Schema::dropIfExists('onec_references');
    }
};
