<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_documents', function (Blueprint $table) {
            $table->boolean('ai_assisted')->default(false)->after('status')->index();
        });

        // The first production offer was created through the AI quick-offer flow
        // before this provenance flag existed. Preserve that known history.
        DB::table('commercial_documents')
            ->where('id', 1)
            ->whereNotNull('crm_mail_thread_id')
            ->update(['ai_assisted' => true]);
    }

    public function down(): void
    {
        Schema::table('commercial_documents', function (Blueprint $table) {
            $table->dropColumn('ai_assisted');
        });
    }
};
