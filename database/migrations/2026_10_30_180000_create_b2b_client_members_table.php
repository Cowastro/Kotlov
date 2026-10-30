<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('b2b_client_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('role', 30)->default('buyer');
            $table->boolean('is_active')->default(true);
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->index(['b2b_client_id', 'is_active']);
        });

        $now = now();
        DB::table('b2b_clients')
            ->select(['id', 'user_id'])
            ->orderBy('id')
            ->each(function (object $client) use ($now): void {
                DB::table('b2b_client_members')->insertOrIgnore([
                    'b2b_client_id' => $client->id,
                    'user_id' => $client->user_id,
                    'role' => 'owner',
                    'is_active' => true,
                    'joined_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_client_members');
    }
};
