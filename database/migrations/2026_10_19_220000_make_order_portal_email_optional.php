<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_portal_links', function (Blueprint $table): void {
            $table->string('access_email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_portal_links', function (Blueprint $table): void {
            $table->string('access_email')->nullable(false)->change();
        });
    }
};
