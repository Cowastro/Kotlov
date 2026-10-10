<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `users` MODIFY `role` ENUM('admin', 'manager', 'sales_manager', 'supplier', 'installer', 'client') NOT NULL DEFAULT 'client'");

            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['admin', 'manager', 'sales_manager', 'supplier', 'installer', 'client'])
                ->default('client')
                ->change();
        });
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::table('users')
                ->whereIn('role', ['manager', 'sales_manager'])
                ->update(['role' => 'client']);

            DB::statement("ALTER TABLE `users` MODIFY `role` ENUM('admin', 'supplier', 'installer', 'client') NOT NULL DEFAULT 'client'");

            return;
        }

        DB::table('users')
            ->whereIn('role', ['manager', 'sales_manager'])
            ->update(['role' => 'client']);

        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['admin', 'supplier', 'installer', 'client'])
                ->default('client')
                ->change();
        });
    }
};
