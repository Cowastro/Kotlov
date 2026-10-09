<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('integration_categories')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('external_id');
            $table->string('parent_external_id')->nullable()->index();
            $table->string('name');
            $table->string('path', 1024);
            $table->json('payload')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['integration_source_id', 'external_id'], 'integration_category_external_unique');
        });

        Schema::table('integration_products', function (Blueprint $table) {
            $table->foreignId('integration_category_id')
                ->nullable()
                ->after('integration_source_id')
                ->constrained('integration_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('integration_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('integration_category_id');
        });

        Schema::dropIfExists('integration_categories');
    }
};
