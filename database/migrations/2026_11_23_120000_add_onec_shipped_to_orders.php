<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What 1C has shipped of the order (sales in «Расчёты с клиентами»): shipped and paid is «Готов к закрытию». */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->decimal('onec_shipped', 12, 2)->nullable());
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('onec_shipped'));
    }
};
