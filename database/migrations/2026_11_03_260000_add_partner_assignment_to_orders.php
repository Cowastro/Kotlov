<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A site order handed by the centre to a partner's sales point (decentralised retail).
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('partner_branch_id')->nullable()->constrained('partner_branches')->nullOnDelete();
            $table->string('partner_status', 20)->nullable()->index();
            $table->timestamp('partner_assigned_at')->nullable();
            $table->timestamp('partner_responded_at')->nullable();
            $table->text('partner_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('partner_branch_id');
            $table->dropColumn(['partner_status', 'partner_assigned_at', 'partner_responded_at', 'partner_note']);
        });
    }
};
