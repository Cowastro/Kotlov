<?php

namespace App\Console\Commands;

use App\Enums\ClientType;
use App\Models\InstallerProfile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class EnsureInstallerAccountCommand extends Command
{
    protected $signature = 'installer:ensure-account
                            {profile : ID или slug профиля монтажника}
                            {--send-reset : Отправить письмо для установки пароля}';

    protected $description = 'Проверить и восстановить связь профиля монтажника с активной учетной записью';

    public function handle(): int
    {
        $profileKey = (string) $this->argument('profile');
        $profile = InstallerProfile::query()
            ->where(fn ($query) => $query
                ->where('slug', $profileKey)
                ->when(ctype_digit($profileKey), fn ($query) => $query->orWhereKey((int) $profileKey)))
            ->first();

        if (! $profile) {
            $this->error("Профиль {$profileKey} не найден.");

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($profile) {
            $user = $profile->user;

            if (! $user && $profile->email) {
                $user = User::query()->where('email', $profile->email)->first();
            }

            if (! $user && $profile->phone) {
                $user = User::query()->where('phone', $profile->phone)->first();
            }

            if (! $user) {
                if (! $profile->email) {
                    return null;
                }

                $user = User::query()->create([
                    'name' => $profile->contact_name ?: $profile->company_name ?: "Монтажник #{$profile->id}",
                    'email' => $profile->email,
                    'password' => Str::random(48),
                ]);
            }

            $user->update([
                'name' => $user->name ?: ($profile->contact_name ?: $profile->company_name),
                'role' => 'installer',
                'client_type' => ClientType::Installer,
                'phone' => $user->phone ?: $profile->phone,
                'company_name' => $user->company_name ?: $profile->company_name,
                'is_active' => true,
                'b2b_approved' => true,
            ]);

            if ($profile->user_id !== $user->id) {
                $profile->update(['user_id' => $user->id]);
            }

            return $user->refresh();
        });

        if (! $user) {
            $this->error('У профиля нет связанного пользователя и email для создания учетной записи.');

            return self::FAILURE;
        }

        $this->table(['Профиль', 'Пользователь', 'Email', 'Роль', 'Активен'], [[
            "#{$profile->id} {$profile->slug}",
            "#{$user->id} {$user->name}",
            $user->email,
            $user->role,
            $user->is_active ? 'да' : 'нет',
        ]]);

        if (! $this->option('send-reset')) {
            $this->info('Связь и права проверены. Письмо для установки пароля не отправлялось.');

            return self::SUCCESS;
        }

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            $this->error(__($status));

            return self::FAILURE;
        }

        $this->info("Письмо для установки пароля отправлено на {$user->email}.");

        return self::SUCCESS;
    }
}
