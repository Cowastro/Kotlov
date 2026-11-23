<?php

namespace App\Services\Shipping;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;

class VamExpressReference
{
    private function accountKey(): string
    {
        return hash('sha256', app(VamExpressSettings::class)->get()['username'] ?? '');
    }

    public function context(): array
    {
        if (! app(VamExpressSettings::class)->configured()) {
            throw new VamExpressException('Сначала включите подключение ВАМ Экспресс.');
        }
        $key = 'vam-reference:'.$this->accountKey();
        if ($cached = Cache::get($key)) {
            return json_decode(Crypt::decryptString($cached), true, flags: JSON_THROW_ON_ERROR);
        }
        $lock = Cache::lock('vam-express-sync', 600);
        if (! $lock->get()) {
            throw new VamExpressException('Идёт обмен ВАМ Экспресс. Откройте форму через минуту.');
        }
        try {
            if (RateLimiter::tooManyAttempts('vam-reference-load', 1)) {
                throw new VamExpressException('Справочник временно недоступен. Повторите через минуту.');
            }
            RateLimiter::hit('vam-reference-load', 60);
            $credentials = app(VamExpressSettings::class)->get();
            $portal = app(VamExpressPortal::class);
            $portal->login($credentials['username'], $credentials['password']);
            $context = $portal->draftReference();
            Cache::put($key, Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)), now()->addHours(24));

            return $context;
        } catch (VamExpressException $e) {
            throw $e;
        } catch (\Throwable) {
            throw new VamExpressException('Не удалось загрузить справочник ВАМ Экспресс.');
        } finally {
            $lock->release();
        }
    }

    public function profiles(): array
    {
        return $this->context()['profiles'];
    }

    public function goods(): array
    {
        return array_combine($goods = $this->context()['goods'], $goods);
    }

    public function sender(): array
    {
        return $this->context()['sender'];
    }

    public function cities(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2 || mb_strlen($query) > 80) {
            return [];
        }
        $key = 'vam-cities:'.$this->accountKey().':'.hash('sha256', mb_strtolower($query));
        if (is_array($cached = Cache::get($key))) {
            return $cached;
        }
        $context = $this->context();
        if (RateLimiter::tooManyAttempts('vam-city-search', 12)) {
            throw new VamExpressException('Слишком много поисков города. Повторите через минуту.');
        }
        $lock = Cache::lock('vam-express-sync', 600);
        if (! $lock->get()) {
            throw new VamExpressException('Идёт обновление ВАМ Экспресс. Повторите поиск через минуту.');
        }
        try {
            RateLimiter::hit('vam-city-search', 60);
            $raw = app(VamExpressPortal::class)->searchCities($query, $context['lookup_key']);
            $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($data['suggestions'] ?? null) || count($data['suggestions']) > 500) {
                throw new VamExpressException('Не удалось прочитать населённые пункты перевозчика.');
            }
            $result = [];
            foreach ($data['suggestions'] as $city) {
                $id = (string) ($city['data'] ?? '');
                if (! ctype_digit($id) || ! is_string($city['value'] ?? null)) {
                    continue;
                }
                $result[$id] = strip_tags($city['value']);
                Cache::put('vam-city:'.$this->accountKey().':'.$id, ['id' => $id, 'label' => $result[$id],
                    'days' => strip_tags($city['deliverydate'] ?? ''), 'terms' => strip_tags($city['deliveryterms'] ?? '')], now()->addDays(7));
            }
            Cache::put($key, $result, now()->addMinutes(30));

            return $result;
        } catch (VamExpressException $e) {
            throw $e;
        } catch (\Throwable) {
            throw new VamExpressException('Не удалось выполнить поиск города ВАМ Экспресс.');
        } finally {
            $lock->release();
        }
    }

    public function city(?string $id): ?array
    {
        return $id && ctype_digit($id) ? Cache::get('vam-city:'.$this->accountKey().':'.$id) : null;
    }
}
