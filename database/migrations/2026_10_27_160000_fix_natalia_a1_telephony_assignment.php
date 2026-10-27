<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NATALIA_PHONE = '375291562102';

    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('telephony_employees')) {
            return;
        }

        $natalia = DB::table('users')
            ->select(['id', 'phone'])
            ->where('is_active', true)
            ->whereIn('role', ['admin', 'manager', 'sales_manager'])
            ->get()
            ->first(fn (object $user): bool => $this->normalize($user->phone) === self::NATALIA_PHONE);

        if (! $natalia) {
            return;
        }

        DB::table('telephony_employees')
            ->where('provider', 'a1')
            ->get(['id', 'full_number'])
            ->filter(fn (object $employee): bool => $this->normalize($employee->full_number) === self::NATALIA_PHONE)
            ->each(fn (object $employee) => DB::table('telephony_employees')
                ->where('id', $employee->id)
                ->update(['user_id' => $natalia->id, 'updated_at' => now()]));

        if (! Schema::hasTable('telephony_calls')) {
            return;
        }

        DB::table('telephony_calls')
            ->where('provider', 'a1')
            ->select(['id', 'employee_phone'])
            ->orderBy('id')
            ->chunkById(200, function ($calls) use ($natalia): void {
                $ids = $calls
                    ->filter(fn (object $call): bool => $this->normalize($call->employee_phone) === self::NATALIA_PHONE)
                    ->pluck('id');

                if ($ids->isNotEmpty()) {
                    DB::table('telephony_calls')
                        ->whereIn('id', $ids)
                        ->update(['assigned_user_id' => $natalia->id, 'updated_at' => now()]);
                }
            });
    }

    public function down(): void
    {
        // This operational data correction is intentionally irreversible.
    }

    private function normalize(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?? '';
    }
};
