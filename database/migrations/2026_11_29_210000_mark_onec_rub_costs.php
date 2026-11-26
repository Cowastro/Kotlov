<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onec_items', fn (Blueprint $table) => $table->boolean('cost_from_rub')->default(false));
    }

    public function down(): void
    {
        Schema::table('onec_items', fn (Blueprint $table) => $table->dropColumn('cost_from_rub'));
    }
};
