<?php

namespace App\Console\Commands;

use App\Models\TelephonyConnection;
use App\Models\TelephonyEmployee;
use App\Models\User;
use App\Services\Telephony\A1HistoryReconciler;
use App\Services\Telephony\A1OpenApiClient;
use App\Services\Telephony\PhoneNumber;
use Illuminate\Console\Command;
use Throwable;

class SyncA1Telephony extends Command
{
    protected $signature = 'telephony:sync-a1 {--minutes= : Глубина сверки истории}';

    protected $description = 'Сверить сотрудников и историю звонков с Open API ВАТС A1';

    public function handle(A1OpenApiClient $client, A1HistoryReconciler $reconciler): int
    {
        $connection = TelephonyConnection::query()->where('provider', 'a1')->where('is_enabled', true)->first();

        if (! $connection) {
            $this->components->info('Интеграция A1 выключена — запросы не выполнялись.');

            return self::SUCCESS;
        }

        try {
            $staffUsers = User::query()
                ->where('is_active', true)
                ->whereIn('role', ['admin', 'manager', 'sales_manager'])
                ->get(['id', 'email', 'phone']);

            $employees = $client->employees($connection);
            foreach ($employees as $employee) {
                $externalId = (string) ($employee['employeeId'] ?? '');
                if ($externalId === '') {
                    continue;
                }

                $fullNumber = PhoneNumber::normalize($employee['fullNumber'] ?? null);
                $email = $employee['email'] ?? null;
                $telephonyEmployee = TelephonyEmployee::query()->firstOrNew([
                    'provider' => 'a1',
                    'external_id' => $externalId,
                ]);

                // A1 can expose the shared company email for several employees.
                // Prefer a unique phone match and preserve an explicit manual link
                // before using email for a previously unknown employee.
                $phoneUser = $fullNumber
                    ? $staffUsers->first(fn (User $candidate): bool => PhoneNumber::normalize($candidate->phone) === $fullNumber)
                    : null;
                $linkedUser = $telephonyEmployee->user_id
                    ? $staffUsers->firstWhere('id', $telephonyEmployee->user_id)
                    : null;
                $normalizedEmail = mb_strtolower(trim((string) $email));
                $emailUser = $normalizedEmail !== ''
                    ? $staffUsers->first(fn (User $candidate): bool => mb_strtolower(trim((string) $candidate->email)) === $normalizedEmail)
                    : null;
                $user = $phoneUser ?? $linkedUser ?? $emailUser;

                $telephonyEmployee->fill([
                    'user_id' => $user?->id,
                    'name' => trim(($employee['name'] ?? '').' '.($employee['surname'] ?? '')) ?: 'Сотрудник A1',
                    'email' => $email,
                    'group_name' => $employee['groupName'] ?? null,
                    'full_number' => $fullNumber,
                    'short_number' => PhoneNumber::normalize($employee['shortNumber'] ?? null),
                    'is_active' => true,
                    'last_synced_at' => now(),
                ])->save();
            }

            $end = now();
            $requestedMinutes = $this->option('minutes');
            $minutes = max(5, (int) ($requestedMinutes ?: config('telephony.history_lookback_minutes', 15)));
            $start = $requestedMinutes !== null
                ? $end->copy()->subMinutes($minutes)
                : ($connection->history_synced_until ?? $end->copy()->subMinutes($minutes))->copy()->subMinutes(2);
            $history = $client->callHistory($connection, $start->timestamp, $end->timestamp);

            foreach ($history as $row) {
                $reconciler->reconcile($row);
            }

            $connection->update([
                'api_last_success_at' => now(),
                'history_synced_until' => $end,
                'last_error' => null,
            ]);

            $this->components->info('A1: сотрудников '.count($employees).', звонков сверено '.count($history).'.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $connection->update(['last_error' => mb_substr($exception->getMessage(), 0, 2000)]);
            report($exception);
            $this->components->error('Ошибка A1: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
