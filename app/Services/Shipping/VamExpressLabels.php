<?php

namespace App\Services\Shipping;

use App\Models\VamExpressShipment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;

class VamExpressLabels
{
    public const FORMATS = ['a4' => 'А4', '78' => 'Лента 78 мм'];

    public function get(VamExpressShipment $shipment, string $format): array
    {
        if (! isset(self::FORMATS[$format])) {
            throw new VamExpressException('Неизвестный формат этикетки.');
        }
        $settings = app(VamExpressSettings::class);
        $credentials = $settings->get();
        $account = hash('sha256', $credentials['username'] ?? '');
        $key = 'vam-label:'.$account.':'.$shipment->remote_id.':'.$format;
        $cached = Cache::get($key);
        if (is_string($cached)) {
            return json_decode(Crypt::decryptString($cached), true, flags: JSON_THROW_ON_ERROR);
        }
        if (! $settings->configured()) {
            throw new VamExpressException('Включите подключение ВАМ Экспресс в настройках доставки.');
        }
        if (Cache::has('vam-label-failure:'.$account)) {
            throw new VamExpressException('Предыдущая попытка не удалась. Повторите через две минуты.');
        }
        $lock = Cache::lock('vam-express-sync', 600);
        if (! $lock->get()) {
            throw new VamExpressException('Сейчас идёт обновление заявок ВАМ Экспресс. Откройте этикетку через минуту.');
        }
        try {
            if (RateLimiter::tooManyAttempts('vam-label-downloads', 6)) {
                throw new VamExpressException('Достигнут лимит загрузки этикеток. Повторите через минуту.');
            }
            RateLimiter::hit('vam-label-downloads', 60);
            $portal = app(VamExpressPortal::class);
            $portal->login($credentials['username'], $credentials['password']);
            $label = ['html' => $portal->label($shipment->remote_id, $format), 'fetched_at' => now()->toIso8601String()];
            Cache::put($key, Crypt::encryptString(json_encode($label, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)), now()->addMinutes(15));

            return $label;
        } catch (\Throwable $error) {
            Cache::put('vam-label-failure:'.$account, true, 120);
            throw new VamExpressException($error instanceof VamExpressException
                ? $error->getMessage() : 'Не удалось получить этикетку ВАМ Экспресс. Повторите позже.');
        } finally {
            $lock->release();
        }
    }
}
