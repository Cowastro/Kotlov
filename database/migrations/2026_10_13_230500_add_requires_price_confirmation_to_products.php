<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'requires_price_confirmation')) {
                $table->boolean('requires_price_confirmation')
                    ->default(false)
                    ->after('availability_status')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'requires_price_confirmation')) {
                $table->dropColumn('requires_price_confirmation');
            }
        });
    }
};
