<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cards a manager creates from a supplier price row while writing a КП stay
 * hidden until a content manager checks them: created_via marks them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'created_via')) {
                $table->string('created_via', 40)->nullable()->index();
            }
            if (! Schema::hasColumn('products', 'created_by_user_id')) {
                $table->unsignedBigInteger('created_by_user_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['created_via']);
            $table->dropColumn(['created_via', 'created_by_user_id']);
        });
    }
};
