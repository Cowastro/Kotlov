<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('client_type')->default('retail')->after('role');
            $table->boolean('b2b_approved')->default(false)->after('client_type');
            $table->string('company_name')->nullable()->after('b2b_approved');
            $table->string('company_inn')->nullable()->after('company_name');
            $table->text('b2b_comment')->nullable()->after('company_inn');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'client_type',
                'b2b_approved',
                'company_name',
                'company_inn',
                'b2b_comment',
            ]);
        });
    }
};