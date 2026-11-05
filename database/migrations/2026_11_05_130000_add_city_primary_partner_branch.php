<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table): void {
            $table->foreignId('primary_partner_branch_id')->nullable()->constrained('partner_branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cities', fn (Blueprint $table) => $table->dropConstrainedForeignId('primary_partner_branch_id'));
    }
};
