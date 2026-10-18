<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2b_clients', function (Blueprint $table): void {
            $table->foreignId('counterparty_id')
                ->nullable()
                ->unique()
                ->after('user_id')
                ->constrained('crm_counterparties')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('b2b_clients', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('counterparty_id');
        });
    }
};
