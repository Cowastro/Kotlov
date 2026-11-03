<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FIELDS = ['utm_source', 'utm_medium', 'utm_campaign', 'ref'];

    public function up(): void
    {
        foreach (['installer_applications', 'b2b_clients'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                foreach (self::FIELDS as $field) {
                    $table->string($field, 80)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['installer_applications', 'b2b_clients'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn(self::FIELDS));
        }
    }
};
