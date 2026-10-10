<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->boolean('automatic_order_transfer_enabled')->default(false)->index()->after('settlement_notes');
            $table->timestamp('automatic_order_transfer_approved_at')->nullable()->after('automatic_order_transfer_enabled');
            $table->foreignId('automatic_order_transfer_approved_by')->nullable()
                ->after('automatic_order_transfer_approved_at')->constrained('users')->nullOnDelete();
            $table->text('automatic_order_transfer_note')->nullable()->after('automatic_order_transfer_approved_by');
        });

        Schema::table('supplier_order_requests', function (Blueprint $table): void {
            $table->string('transfer_mode', 24)->default('manual')->index()->after('status');
            $table->uuid('automatic_transfer_run_uuid')->nullable()->index()->after('transfer_mode');
        });

        Schema::table('supplier_order_request_status_histories', function (Blueprint $table): void {
            $table->uuid('run_uuid')->nullable()->index()->after('actor_scope');
        });

        Schema::create('supplier_auto_transfer_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('enabled');
            $table->json('readiness_snapshot');
            $table->text('note')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->index(['supplier_id', 'decided_at'], 'supplier_auto_transfer_decision_timeline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_auto_transfer_decisions');

        Schema::table('supplier_order_request_status_histories', function (Blueprint $table): void {
            $table->dropColumn('run_uuid');
        });

        Schema::table('supplier_order_requests', function (Blueprint $table): void {
            $table->dropColumn(['transfer_mode', 'automatic_transfer_run_uuid']);
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('automatic_order_transfer_approved_by');
            $table->dropIndex(['automatic_order_transfer_enabled']);
            $table->dropColumn([
                'automatic_order_transfer_enabled',
                'automatic_order_transfer_approved_at',
                'automatic_order_transfer_note',
            ]);
        });
    }
};
