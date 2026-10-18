<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('b2b_clients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->string('partner_type', 24)->default('wholesale')->index();
            $table->string('company_name');
            $table->string('unp', 50)->nullable()->index();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('payment_terms')->nullable();
            $table->text('delivery_terms')->nullable();
            $table->text('application_comment')->nullable();
            $table->text('manager_notes')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('users')
            ->where(function ($query): void {
                $query->whereIn('client_type', ['wholesale', 'installer'])
                    ->orWhere('b2b_approved', true);
            })
            ->orderBy('id')
            ->each(function ($user) use ($now): void {
                DB::table('b2b_clients')->insert([
                    'user_id' => $user->id,
                    'status' => $user->b2b_approved ? 'approved' : 'pending',
                    'partner_type' => in_array($user->client_type, ['wholesale', 'installer'], true)
                        ? $user->client_type
                        : 'wholesale',
                    'company_name' => $user->company_name ?: $user->name,
                    'unp' => $user->company_inn,
                    'contact_name' => $user->name,
                    'contact_email' => $user->email,
                    'contact_phone' => $user->phone,
                    'reviewed_at' => $user->b2b_approved ? $now : null,
                    'application_comment' => $user->b2b_comment,
                    'created_at' => $user->created_at ?: $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_clients');
    }
};
