<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personal and department mailboxes: a mailbox with assigned employees is
 * visible only to them and to administrators. A mailbox without assignees
 * stays shared by every manager (info@, order@).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_mailbox_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mailbox_id')->constrained('crm_mailboxes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['mailbox_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_mailbox_user');
    }
};
