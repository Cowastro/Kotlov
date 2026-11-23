<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onec_items', function (Blueprint $table): void {
            $table->unsignedInteger('site_link_count')->default(0);
            $table->unsignedInteger('characteristic_count')->default(0);
            $table->boolean('link_conflict')->default(false);
        });
        Schema::create('onec_item_link_changes', function (Blueprint $table): void {
            $table->id();
            $table->string('external_id')->index();
            $table->unsignedBigInteger('integration_product_id')->nullable();
            $table->unsignedBigInteger('old_product_id')->nullable();
            $table->unsignedBigInteger('new_product_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onec_item_link_changes');
        Schema::table('onec_items', fn (Blueprint $table) => $table->dropColumn(['site_link_count', 'characteristic_count', 'link_conflict']));
    }
};
