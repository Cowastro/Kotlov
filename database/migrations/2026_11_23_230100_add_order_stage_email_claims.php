<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_stage_email_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32);
            $table->timestamp('created_at');
            $table->unique(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_stage_email_claims');
    }
};
