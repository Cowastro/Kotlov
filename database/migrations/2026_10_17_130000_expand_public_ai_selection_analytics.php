<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_ai_selection_runs', function (Blueprint $table): void {
            $table->foreignId('catalog_category_id')->nullable()->after('category')->constrained('categories')->nullOnDelete();
            $table->foreignId('contact_request_id')->nullable()->after('catalog_category_id')->constrained('contact_requests')->nullOnDelete();
            $table->foreignId('selected_product_id')->nullable()->after('contact_request_id')->constrained('products')->nullOnDelete();
            $table->text('page_url')->nullable()->after('intent');
            $table->unsignedSmallInteger('result_count')->default(0)->after('result_product_ids');
            $table->string('current_step', 30)->default('results')->after('status')->index();
            $table->timestamp('recommended_at')->nullable()->after('current_step');
            $table->timestamp('lead_prompted_at')->nullable()->after('recommended_at');
            $table->timestamp('contact_submitted_at')->nullable()->after('lead_prompted_at');
            $table->timestamp('product_opened_at')->nullable()->after('contact_submitted_at');
            $table->timestamp('abandoned_at')->nullable()->after('product_opened_at');
        });
    }

    public function down(): void
    {
        Schema::table('public_ai_selection_runs', function (Blueprint $table): void {
            $table->dropIndex(['current_step']);
            $table->dropConstrainedForeignId('selected_product_id');
            $table->dropConstrainedForeignId('contact_request_id');
            $table->dropConstrainedForeignId('catalog_category_id');
            $table->dropColumn([
                'page_url', 'result_count', 'current_step', 'recommended_at', 'lead_prompted_at',
                'contact_submitted_at', 'product_opened_at', 'abandoned_at',
            ]);
        });
    }
};
