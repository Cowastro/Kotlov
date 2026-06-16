<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_review_decisions', function (Blueprint $table) {
            $table->id();
            $table->string('decision_key', 64)->unique();
            $table->string('supplier_code')->nullable()->index();
            $table->string('report_file')->index();
            $table->string('report_row')->nullable();
            $table->string('decision', 64)->index();
            $table->string('status', 32)->default('pending')->index();
            $table->foreignId('supplier_product_id')->nullable()->index()->constrained('supplier_products')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->index()->constrained('products')->nullOnDelete();
            $table->string('supplier_title')->nullable();
            $table->string('supplier_article')->nullable();
            $table->text('source_url')->nullable();
            $table->text('reason')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('applied_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_review_decisions');
    }
};
