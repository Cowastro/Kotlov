<?php

namespace App\Services\Suppliers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class EurotermPortalCredentials
{
    private const PATH = 'suppliers/euroterm/credentials.enc';

    public function get(): array
    {
        if (Storage::disk('local')->exists(self::PATH)) {
            return json_decode(Crypt::decryptString(Storage::disk('local')->get(self::PATH)), true, flags: JSON_THROW_ON_ERROR);
        }

        return ['username' => (string) config('services.euroterm.username'), 'password' => (string) config('services.euroterm.password')];
    }

    public function save(string $username, ?string $password): void
    {
        $old = $this->get();
        $this->write([
            'username' => trim($username), 'password' => filled($password) ? $password : ($old['password'] ?? ''),
            'requested_at' => now()->toIso8601String(), 'last_attempt_at' => $old['last_attempt_at'] ?? null,
        ]);
    }

    public function due(): bool
    {
        $data = $this->get();

        return filled($data['username'] ?? null) && filled($data['password'] ?? null)
            && (filled($data['requested_at'] ?? null) || empty($data['last_attempt_at'])
                || Carbon::parse($data['last_attempt_at'])->lt(now()->subHours(8)));
    }

    public function markAttempt(): void
    {
        $this->write(array_replace($this->get(), ['requested_at' => null, 'last_attempt_at' => now()->toIso8601String()]));
    }

    private function write(array $data): void
    {
        if (! Storage::disk('local')->put(self::PATH, Crypt::encryptString(json_encode($data, JSON_THROW_ON_ERROR)))) {
            throw new \RuntimeException('Не удалось сохранить настройки входа Евротерма.');
        }
        @chmod(Storage::disk('local')->path(self::PATH), 0600);
    }
}
