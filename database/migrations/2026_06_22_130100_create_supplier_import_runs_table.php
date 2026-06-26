<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_import_runs')) {
            return;
        }

        Schema::create('supplier_import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('supplier_sources')->cascadeOnDelete();
            $table->string('status', 32)->default('pending')->index();
            $table->string('file_path')->nullable();
            $table->string('file_hash', 64)->nullable()->index();
            $table->json('stats')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['source_id', 'status']);
            $table->index(['source_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_import_runs');
    }
};
