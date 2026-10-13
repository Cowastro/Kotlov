<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->boolean('is_crm_user')->default(false)->after('role')->index();
            $table->string('department')->nullable()->after('job_title');
        });

        DB::table('users')->select(['id', 'name', 'role'])->orderBy('id')->chunkById(200, function ($users): void {
            foreach ($users as $user) {
                $parts = preg_split('/\s+/u', trim((string) $user->name), 2) ?: [];

                DB::table('users')->where('id', $user->id)->update([
                    'first_name' => $parts[0] ?? null,
                    'last_name' => $parts[1] ?? null,
                    'is_crm_user' => in_array($user->role, ['admin', 'manager', 'sales_manager'], true),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_crm_user']);
            $table->dropColumn(['first_name', 'last_name', 'is_crm_user', 'department']);
        });
    }
};
