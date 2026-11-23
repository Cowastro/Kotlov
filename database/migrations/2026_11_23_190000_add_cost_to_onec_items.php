<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cost of a 1C item from the posted purchases: the last one, or the 3-month average when the last has no price. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onec_items', function (Blueprint $table): void {
            $table->decimal('cost_net', 14, 2)->nullable();
            $table->decimal('cost_gross', 14, 2)->nullable();
            $table->date('cost_date')->nullable();
            $table->string('cost_doc', 50)->nullable();
            $table->string('cost_kind', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('onec_items', function (Blueprint $table): void {
            $table->dropColumn(['cost_net', 'cost_gross', 'cost_date', 'cost_doc', 'cost_kind']);
        });
    }
};
