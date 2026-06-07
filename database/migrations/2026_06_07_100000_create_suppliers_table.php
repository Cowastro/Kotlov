<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();          // teplov, darco, ferrum
            $table->string('name');                     // Теплов и Сухов
            $table->string('currency', 3)->default('BYN'); // BYN, RUB, EUR, USD
            $table->decimal('currency_rate', 10, 4)->default(1.0000); // курс к BYN
            $table->string('contact')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Заполнить известными поставщиками
        $now = now();
        DB::table('suppliers')->insert([
            ['code' => 'teplov',   'name' => 'Теплов и Сухов', 'currency' => 'BYN', 'currency_rate' => 1.0000, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'darco',    'name' => 'Darco',           'currency' => 'EUR', 'currency_rate' => 1.0000, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'ferrum',   'name' => 'Ferrum',          'currency' => 'RUB', 'currency_rate' => 1.0000, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'ferolajf', 'name' => 'Феролайф',        'currency' => 'BYN', 'currency_rate' => 1.0000, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'schiedel', 'name' => 'Schiedel',        'currency' => 'EUR', 'currency_rate' => 1.0000, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
