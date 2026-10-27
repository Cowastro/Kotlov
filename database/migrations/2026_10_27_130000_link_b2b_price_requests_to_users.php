<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_requests', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->unsignedInteger('requested_quantity')->nullable()->after('product_id');
            $table->text('client_response')->nullable()->after('requested_quantity');
            $table->index(['user_id', 'source', 'product_id'], 'contact_requests_b2b_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('contact_requests', function (Blueprint $table): void {
            $table->dropIndex('contact_requests_b2b_lookup');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['requested_quantity', 'client_response']);
        });
    }
};
