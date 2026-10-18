<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_reminders', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('note')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable()->index();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['assigned_user_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_reminders');
    }
};
