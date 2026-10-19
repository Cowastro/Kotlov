<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_mail_threads', function (Blueprint $table): void {
            $table->foreignId('product_id')->nullable()->after('supplier_order_request_id')
                ->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->after('product_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('crm_mail_threads', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropConstrainedForeignId('product_id');
        });
    }
};
