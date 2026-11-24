<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The date the 1C retail price was set: an old one of a good not in stock does not lead the site price. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onec_items', fn (Blueprint $table) => $table->date('price_retail_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('onec_items', fn (Blueprint $table) => $table->dropColumn('price_retail_at'));
    }
};
