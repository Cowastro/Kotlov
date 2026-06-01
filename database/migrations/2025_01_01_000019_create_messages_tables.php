<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Треды переписки
        Schema::create('message_threads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable()->index();      // привязка к заказу
            $table->unsignedBigInteger('product_id')->nullable()->index();    // привязка к товару
            $table->unsignedBigInteger('client_id')->index();                 // покупатель
            $table->unsignedBigInteger('participant_id')->index();            // поставщик или монтажник
            $table->string('subject')->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('participant_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Сообщения в треде
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('thread_id')->index();
            $table->unsignedBigInteger('sender_id')->index();
            $table->text('body');
            $table->json('attachments')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('thread_id')->references('id')->on('message_threads')->onDelete('cascade');
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('message_threads');
    }
};
