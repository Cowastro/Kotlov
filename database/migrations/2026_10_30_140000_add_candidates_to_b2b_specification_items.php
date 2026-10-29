<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2b_specification_items', function (Blueprint $table): void {
            $table->json('candidates')->nullable()->after('match_reason');
            $table->foreignId('reviewed_by')->nullable()->after('candidates')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('b2b_specification_items', function (Blueprint $table): void {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['candidates', 'reviewed_by', 'reviewed_at']);
        });
    }
};
