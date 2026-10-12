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
            $employees = $client->employees($connection);
            foreach ($employees as $employee) {
                $externalId = (string) ($employee['employeeId'] ?? '');
                if ($externalId === '') {
                    continue;
                }

                $fullNumber = PhoneNumber::normalize($employee['fullNumber'] ?? null);
                $email = $employee['email'] ?? null;
                $user = $email ? User::query()->where('email', $email)->first() : null;

                TelephonyEmployee::query()->updateOrCreate(
                    ['provider' => 'a1', 'external_id' => $externalId],
                    [
                        'user_id' => $user?->id,
                        'name' => trim(($employee['name'] ?? '').' '.($employee['surname'] ?? '')) ?: 'Сотрудник A1',
                        'email' => $email,
                        'group_name' => $employee['groupName'] ?? null,
                        'full_number' => $fullNumber,
                        'short_number' => PhoneNumber::normalize($employee['shortNumber'] ?? null),
                        'is_active' => true,
                        'last_synced_at' => now(),
                    ],
                );
            }

            $minutes = max(5, (int) ($this->option('minutes') ?: config('telephony.history_lookback_minutes', 15)));
            $start = ($connection->history_synced_until ?? now()->subMinutes($minutes))->copy()->subMinutes(2);
            $end = now();
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
