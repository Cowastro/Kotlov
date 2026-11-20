<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What 1C says about the document of a price batch now: posted by the accountant, marked for deletion. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onec_price_batches', function (Blueprint $table) {
            $table->boolean('onec_posted')->nullable()->after('onec_document_key');
            $table->boolean('onec_deleted')->default(false)->after('onec_posted');
            $table->timestamp('onec_checked_at')->nullable()->after('onec_deleted');
        });
    }

    public function down(): void
    {
        Schema::table('onec_price_batches', function (Blueprint $table) {
            $table->dropColumn(['onec_posted', 'onec_deleted', 'onec_checked_at']);
        });
    }
};
