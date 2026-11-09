<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_requests', function (Blueprint $table): void {
            $table->string('phone')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('contact_requests')->whereNull('phone')->update(['phone' => 'Не указан']);

        Schema::table('contact_requests', function (Blueprint $table): void {
            $table->string('phone')->nullable(false)->change();
        });
    }
};
