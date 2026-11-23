<?php

namespace App\Support;

/** Single-host admission control. Must work without Composer, Laravel or MySQL. */
class HttpCapacity
{
    // Leave eight of the account's 30 DB connections for scheduled jobs.
    private const SLOTS = ['public' => 12, 'admin' => 4, 'interactive' => 4, 'integration' => 2];

    public function __construct(private readonly string $directory) {}

    /** @return resource|null */
    public function acquire(array $server)
    {
        if (! is_dir($this->directory) && ! @mkdir($this->directory, 0770, true) && ! is_dir($this->directory)) {
            return null;
        }

        $pool = $this->pool($server);
        for ($slot = 0; $slot < self::SLOTS[$pool]; $slot++) {
            // Never unlink active slot files: a new inode would allow duplicate locks.
            $lease = @fopen($this->directory.'/'.$pool.'-'.$slot.'.lock', 'c');
            if ($lease === false) {
                continue;
            }
            if (flock($lease, LOCK_EX | LOCK_NB)) {
                return $lease;
            }
            fclose($lease);
        }

        return null;
    }

    /** @param resource|null $lease */
    public static function release($lease): void
    {
        if (is_resource($lease)) {
            flock($lease, LOCK_UN);
            fclose($lease);
        }
    }

    public function pool(array $server): string
    {
        $path = parse_url($server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $host = strtolower(explode(':', $server['HTTP_HOST'] ?? '')[0]);
        if (preg_match('~^/(?:1c/exchange(?:/|$)|webpay/notify$|telegram/webhook$|integrations/)~', $path)) {
            return 'integration';
        }
        if (preg_match('~^/admin(?:/|$)~', $path) || $host === 'admin.kotlov.by') {
            return 'admin';
        }
        if (preg_match('~^/(?:livewire[^/]*(?:/|$)|b2b(?:/|$)|account(?:/|$)|cart(?:/|$)|checkout(?:/|$)|login$|logout$|register$|password(?:/|$))~', $path)) {
            return 'interactive';
        }

        return 'public';
    }

    /** @return array{type:string,body:string} */
    public function busyResponse(array $server): array
    {
        $message = 'Сейчас много посетителей. Пожалуйста, повторите через несколько секунд.';
        if (str_contains(strtolower($server['HTTP_ACCEPT'] ?? ''), 'json')
            || strtolower($server['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
            return ['type' => 'application/json; charset=utf-8', 'body' => json_encode(['message' => $message], JSON_UNESCAPED_UNICODE)];
        }

        return ['type' => 'text/html; charset=utf-8', 'body' => '<!doctype html><html lang="ru"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Попробуйте ещё раз — KOTLOV</title><body style="font:16px/1.5 system-ui;max-width:480px;margin:15vh auto;padding:24px"><h1 style="font-size:24px">Попробуйте ещё раз</h1><p>'.$message.'</p><button onclick="location.reload()" style="padding:12px 20px;font:inherit;cursor:pointer">Повторить</button></body></html>'];
    }
}
