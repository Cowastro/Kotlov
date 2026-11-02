<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2b_clients', function (Blueprint $table): void {
            $table->string('default_delivery_city')->nullable()->after('contact_phone');
            $table->text('default_delivery_address')->nullable()->after('default_delivery_city');
        });
    }

    public function down(): void
    {
        Schema::table('b2b_clients', function (Blueprint $table): void {
            $table->dropColumn(['default_delivery_city', 'default_delivery_address']);
        });
    }
};
