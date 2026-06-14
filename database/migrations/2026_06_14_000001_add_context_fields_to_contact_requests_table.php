<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->after('admin_notes')->index();
            $table->string('product_name')->nullable()->after('product_id');
            $table->text('product_url')->nullable()->after('product_name');
            $table->string('city')->nullable()->after('product_url');
            $table->string('source')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('contact_requests', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
            $table->dropColumn([
                'product_id',
                'product_name',
                'product_url',
                'city',
                'source',
            ]);
        });
    }
};
