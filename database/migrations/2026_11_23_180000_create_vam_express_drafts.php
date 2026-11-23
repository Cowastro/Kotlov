<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vam_express_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->string('creation_key', 64)->nullable()->unique();
            $table->unsignedInteger('version')->default(1);
            $table->longText('payload');
            $table->longText('quote')->nullable();
            $table->decimal('quoted_amount', 14, 2)->nullable();
            $table->timestamp('quoted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vam_express_drafts');
    }
};
