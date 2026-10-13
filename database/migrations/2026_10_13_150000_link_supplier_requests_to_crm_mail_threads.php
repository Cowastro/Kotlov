<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_mail_threads', function (Blueprint $table): void {
            $table->foreignId('supplier_order_request_id')
                ->nullable()
                ->after('assigned_user_id')
                ->constrained('supplier_order_requests')
                ->nullOnDelete();
            $table->unique('supplier_order_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('crm_mail_threads', function (Blueprint $table): void {
            $table->dropUnique(['supplier_order_request_id']);
            $table->dropConstrainedForeignId('supplier_order_request_id');
        });
    }
};
