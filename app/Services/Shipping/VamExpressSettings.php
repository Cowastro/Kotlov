<?php

namespace App\Services\Shipping;

use App\Models\VamExpressShipment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class VamExpressSettings
{
    private const PATH = 'shipping/vamexpress/connection.enc';

    public function get(): array
    {
        $disk = Storage::disk('local');

        return $disk->exists(self::PATH)
            ? json_decode(Crypt::decryptString($disk->get(self::PATH)), true, flags: JSON_THROW_ON_ERROR)
            : [];
    }

    public function save(string $username, ?string $password, bool $enabled): void
    {
        Cache::lock('vam-express-settings', 10)->block(3, function () use ($username, $password, $enabled): void {
            $old = $this->get();
            $username = trim($username);
            if (filled($old['username'] ?? null) && $username !== $old['username'] && VamExpressShipment::exists()) {
                throw new VamExpressException('Уже загружены заявки этого аккаунта. Для подключения другого аккаунта обратитесь к администратору интеграции.');
            }
            $password = filled($password) ? $password : ($old['password'] ?? '');
            if ($username === '' || $password === '') {
                throw new VamExpressException('Введите логин и пароль от ВАМ Экспресс.');
            }
            $this->write(array_replace($old, compact('username', 'password', 'enabled'), ['requested_at' => now()->toIso8601String()]));
        });
    }

    public function requestSync(?string $id = null): void
    {
        Cache::lock('vam-express-settings', 10)->block(3, function () use ($id): void {
            $data = $this->get();
            if (! $this->configured()) {
                throw new VamExpressException('Сначала сохраните данные входа и включите синхронизацию.');
            }
            if ($id !== null && ! preg_match('/^(?:[a-f0-9]{7}|[a-f0-9]{32})$/i', $id)) {
                throw new VamExpressException('ID должен содержать 7 или 32 шестнадцатеричных символа.');
            }
            $ids = array_values(array_unique(array_merge($data['requested_ids'] ?? [], $id ? [strtolower($id)] : [])));
            if (count($ids) > 10) {
                throw new VamExpressException('Уже ожидают проверки 10 ID. Дождитесь окончания загрузки.');
            }
            $this->write(array_replace($data, ['requested_at' => now()->toIso8601String(), 'requested_ids' => $ids]));
        });
    }

    public function configured(): bool
    {
        $data = $this->get();

        return ($data['enabled'] ?? false) && filled($data['username'] ?? null) && filled($data['password'] ?? null);
    }

    public function due(): bool
    {
        $data = $this->get();
        if (! $this->configured()) {
            return false;
        }
        $attempt = isset($data['last_attempt_at']) ? Carbon::parse($data['last_attempt_at']) : null;

        return (! $attempt || $attempt->lte(now()->subMinutes(2)))
            && (filled($data['requested_at'] ?? null) || ! $attempt || $attempt->lte(now()->subMinutes(30)));
    }

    public function markAttempt(): array
    {
        return Cache::lock('vam-express-settings', 10)->block(3, function (): array {
            $data = $this->get();
            $this->write(array_replace($data, ['last_attempt_at' => now()->toIso8601String(), 'requested_at' => null]));

            return $data['requested_ids'] ?? [];
        });
    }

    public function finish(bool $success, string $message, array $completedIds = []): void
    {
        Cache::lock('vam-express-settings', 10)->block(3, function () use ($success, $message, $completedIds): void {
            $data = $this->get();
            if ($success) {
                $data['requested_ids'] = array_values(array_diff($data['requested_ids'] ?? [], $completedIds));
            }
            $this->write(array_replace($data, ['message' => $message, 'last_success_at' => $success ? now()->toIso8601String() : ($data['last_success_at'] ?? null)]));
        });
    }

    private function write(array $data): void
    {
        $disk = Storage::disk('local');
        if (! $disk->put(self::PATH, Crypt::encryptString(json_encode($data, JSON_THROW_ON_ERROR)))) {
            throw new VamExpressException('Не удалось сохранить настройки ВАМ Экспресс.');
        }
        @chmod($disk->path(self::PATH), 0600);
    }
}
