<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_documents', function (Blueprint $table): void {
            $table->string('client_legal_name')->nullable()->after('client_company');
        });

        DB::table('commercial_documents')
            ->whereNull('client_legal_name')
            ->whereNotNull('client_company')
            ->update(['client_legal_name' => DB::raw('client_company')]);
    }

    public function down(): void
    {
        Schema::table('commercial_documents', function (Blueprint $table): void {
            $table->dropColumn('client_legal_name');
        });
    }
};
