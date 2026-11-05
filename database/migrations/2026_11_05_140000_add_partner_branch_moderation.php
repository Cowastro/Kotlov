<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_branches', function (Blueprint $table): void {
            $table->json('pending_changes')->nullable();
            $table->timestamp('changes_submitted_at')->nullable();
            $table->text('changes_rejection_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('partner_branches', fn (Blueprint $table) => $table->dropColumn(['pending_changes', 'changes_submitted_at', 'changes_rejection_reason']));
    }
};
