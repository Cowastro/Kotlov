<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_import_preview_items')) {
            $this->repairPartiallyCreatedTable();
            return;
        }

        Schema::create('supplier_import_preview_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('supplier_import_runs')->cascadeOnDelete();
            $table->unsignedInteger('row_number')->nullable();
            $table->json('raw_data')->nullable();
            $table->string('parsed_article')->nullable();
            $table->string('parsed_article_strict')->nullable();
            $table->string('parsed_article_compact')->nullable();
            $table->string('parsed_name')->nullable();
            $table->decimal('parsed_price_byn', 12, 2)->nullable();
            $table->boolean('parsed_in_stock')->nullable();
            $table->string('match_method', 32)->default('none')->index();
            $table->unsignedBigInteger('matched_product_id')->nullable();
            $table->unsignedBigInteger('matched_supplier_product_id')->nullable();
            $table->string('status', 32)->default('new_product')->index();
            $table->text('status_detail')->nullable();
            $table->string('action', 32)->nullable()->index();
            $table->string('override_action', 32)->nullable()->index();
            $table->timestamp('applied_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['run_id', 'status']);
            $table->index(['run_id', 'match_method']);
            $table->index(['run_id', 'row_number']);
            $table->index('parsed_article_strict');
            $table->index('parsed_article_compact');

            $table->foreign('matched_product_id', 'sipi_product_fk')
                ->references('id')
                ->on('products')
                ->nullOnDelete();
            $table->foreign('matched_supplier_product_id', 'sipi_supplier_product_fk')
                ->references('id')
                ->on('supplier_products')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_import_preview_items');
    }

    private function repairPartiallyCreatedTable(): void
    {
        $this->addIndexIfMissing('sipi_matched_product_idx', ['matched_product_id']);
        $this->addIndexIfMissing('sipi_matched_supplier_product_idx', ['matched_supplier_product_id']);
        $this->addIndexIfMissing('sipi_run_status_idx', ['run_id', 'status']);
        $this->addIndexIfMissing('sipi_run_match_method_idx', ['run_id', 'match_method']);
        $this->addIndexIfMissing('sipi_run_row_number_idx', ['run_id', 'row_number']);
        $this->addIndexIfMissing('sipi_article_strict_idx', ['parsed_article_strict']);
        $this->addIndexIfMissing('sipi_article_compact_idx', ['parsed_article_compact']);

        $this->addForeignIfMissing(
            'sipi_product_fk',
            'matched_product_id',
            'products',
        );
        $this->addForeignIfMissing(
            'sipi_supplier_product_fk',
            'matched_supplier_product_id',
            'supplier_products',
        );
    }

    private function addIndexIfMissing(string $name, array $columns): void
    {
        try {
            Schema::table('supplier_import_preview_items', function (Blueprint $table) use ($name, $columns) {
                $table->index($columns, $name);
            });
        } catch (\Throwable) {
            // Index already exists on this database.
        }
    }

    private function addForeignIfMissing(string $name, string $column, string $targetTable): void
    {
        try {
            Schema::table('supplier_import_preview_items', function (Blueprint $table) use ($name, $column, $targetTable) {
                $table->foreign($column, $name)
                    ->references('id')
                    ->on($targetTable)
                    ->nullOnDelete();
            });
        } catch (\Throwable) {
            // Foreign key already exists on this database.
        }
    }
};
