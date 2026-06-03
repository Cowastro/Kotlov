<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL не позволяет ALTER COLUMN напрямую с ENUM → string через Blueprint change().
        // Используем raw ALTER TABLE для надёжности.
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_type VARCHAR(100) NOT NULL DEFAULT 'cash'");
        DB::statement("ALTER TABLE orders MODIFY COLUMN delivery_type VARCHAR(100) NOT NULL DEFAULT 'courier'");
    }

    public function down(): void
    {
        // Возврат к ENUM возможен только если в БД нет значений вне старого списка.
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_type ENUM('cash','card','invoice') NOT NULL DEFAULT 'cash'");
        DB::statement("ALTER TABLE orders MODIFY COLUMN delivery_type ENUM('pickup','courier','transport') NOT NULL DEFAULT 'courier'");
    }
};
