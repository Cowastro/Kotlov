<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The 1C nomenclature on the site: every item and group of «Номенклатура», with the site card it is linked to. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onec_items', function (Blueprint $table): void {
            $table->id();
            $table->string('ref_key', 36)->unique();
            $table->string('code', 50)->nullable()->index();
            $table->string('description', 500)->nullable()->index();
            $table->string('article', 100)->nullable()->index();
            $table->string('parent_key', 36)->nullable()->index();
            $table->boolean('is_folder')->default(false)->index();
            $table->boolean('deletion_mark')->default(false);
            $table->string('kind_key', 36)->nullable();
            $table->string('unit_key', 36)->nullable();
            $table->string('path', 1000)->nullable();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->boolean('created_from_site')->default(false);
            $table->unsignedSmallInteger('same_name_count')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onec_items');
    }
};
