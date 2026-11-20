<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** The key of the agent on the 1C server: only its SHA-256 is stored, the key itself is shown once. */
class OneCAgentToken extends Model
{
    protected $table = 'onec_agent_tokens';

    protected $fillable = ['name', 'token_hash', 'created_by', 'last_seen_at', 'revoked_at'];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /** @return array{0: self, 1: string} the record and the plain key to show once */
    public static function issue(string $name, ?int $userId): array
    {
        $plain = 'k1c_'.Str::random(48);
        $token = self::query()->create(['name' => $name, 'token_hash' => hash('sha256', $plain), 'created_by' => $userId]);

        return [$token, $plain];
    }

    public static function findActive(?string $plain): ?self
    {
        if (! is_string($plain) || ! str_starts_with($plain, 'k1c_')) {
            return null;
        }

        return self::query()->where('token_hash', hash('sha256', $plain))->whereNull('revoked_at')->first();
    }
}
