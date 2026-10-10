<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_order_requests', function (Blueprint $table): void {
            $table->foreignId('sent_by')->nullable()->after('sent_at')->constrained('users')->nullOnDelete();
            $table->text('supplier_response_note')->nullable()->after('acknowledged_at');
            $table->timestamp('rejected_at')->nullable()->after('supplier_response_note');
            $table->timestamp('fulfilled_at')->nullable()->after('rejected_at');
            $table->foreignId('status_updated_by')->nullable()->after('fulfilled_at')->constrained('users')->nullOnDelete();
            $table->timestamp('status_updated_at')->nullable()->index()->after('status_updated_by');
        });

        Schema::create('supplier_order_request_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('supplier_order_request_id');
            $table->foreign('supplier_order_request_id', 'supplier_request_history_request_fk')
                ->references('id')
                ->on('supplier_order_requests')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('actor_scope', 24);
            $table->string('status_from', 32)->nullable();
            $table->string('status_to', 32);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['supplier_order_request_id', 'created_at'], 'supplier_request_history_timeline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_order_request_status_histories');

        Schema::table('supplier_order_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('status_updated_by');
            $table->dropConstrainedForeignId('sent_by');
            $table->dropColumn([
                'supplier_response_note',
                'rejected_at',
                'fulfilled_at',
                'status_updated_at',
            ]);
        });
    }
};
