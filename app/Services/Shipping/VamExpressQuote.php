<?php

namespace App\Services\Shipping;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class VamExpressQuote
{
    public static function inputs(array $payload): array
    {
        return ['profile_id' => (string) ($payload['profile_id'] ?? ''), 'city_id' => (string) ($payload['city_id'] ?? ''),
            'express' => (bool) ($payload['express'] ?? false),
            'goods' => array_values(array_map(fn ($g) => ['description' => (string) ($g['description'] ?? ''),
                'number' => (int) ($g['number'] ?? 0), 'declared_price' => number_format((float) ($g['declared_price'] ?? 0), 2, '.', '')], $payload['goods'] ?? []))];
    }

    public static function fingerprint(array $payload): string
    {
        return hash('sha256', json_encode(self::inputs($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function estimate(array $payload): array
    {
        Validator::make($payload, ['profile_id' => 'required', 'city_id' => 'required|integer|min:1', 'goods' => 'required|array|min:1|max:20',
            'goods.*.description' => 'required|string|max:255', 'goods.*.number' => 'required|integer|min:1|max:10000',
            'goods.*.declared_price' => 'required|numeric|decimal:0,2|min:0|max:1000000'], [], [
                'profile_id' => 'Профиль отправителя', 'city_id' => 'Населённый пункт', 'goods.*.description' => 'Категория груза',
                'goods.*.number' => 'Количество по тарифу',
            ])->validate();
        $input = self::inputs($payload);
        $reference = app(VamExpressReference::class);
        $context = $reference->context();
        if (! isset($context['profiles'][$input['profile_id']]) || ! ($city = $reference->resolveCity($input['city_id'], (string) ($payload['city_label'] ?? '')))) {
            throw new VamExpressException('Выберите профиль и населённый пункт из справочника перевозчика. Если город сохранён давно, найдите его заново.');
        }
        foreach ($input['goods'] as $good) {
            if (! in_array($good['description'], $context['goods'], true)) {
                throw new VamExpressException('Выберите точную категорию каждого груза из справочника ВАМ Экспресс.');
            }
        }
        $assessedCents = array_sum(array_map(fn ($g) => (int) round((float) $g['declared_price'] * 100) * $g['number'], $input['goods']));
        $type = substr($input['profile_id'], 0, 1);
        if ($type === '3' && $assessedCents <= 0) {
            throw new VamExpressException('Для профиля «Договор поручения» укажите оценочную стоимость груза.');
        }
        $account = hash('sha256', json_encode([$context['user_id'], $context['discount'], $context['express_cost']]));
        $key = 'vam-quote:'.$account.':'.self::fingerprint($payload);
        if (is_array($cached = Cache::get($key))) {
            return $cached;
        }
        if (RateLimiter::tooManyAttempts('vam-quotes', 10)) {
            throw new VamExpressException('Достигнут лимит расчётов. Повторите через минуту.');
        }
        $lock = Cache::lock('vam-express-sync', 600);
        if (! $lock->get()) {
            throw new VamExpressException('Идёт обмен ВАМ Экспресс. Рассчитайте доставку через минуту.');
        }
        try {
            RateLimiter::hit('vam-quotes', 60);
            $api = ['city_id' => $input['city_id'], 'profile_type' => $type, 'user_discount' => $context['discount'],
                'user_id' => $context['user_id'], 'goods' => array_map(fn ($g) => ['description' => $g['description'], 'number' => (string) $g['number']], $input['goods']),
                'express_cost' => $input['express'] ? $context['express_cost'] : 0];
            if ($type === '3') {
                $api['assessed_cost'] = number_format($assessedCents / 100, 2, '.', '');
            }
            $raw = app(VamExpressPortal::class)->estimate($context, $api);
            $previous = libxml_use_internal_errors(true);
            try {
                $xml = preg_match('/<!DOCTYPE|<!ENTITY/i', $raw) ? false : simplexml_load_string($raw, \SimpleXMLElement::class, LIBXML_NONET);
                $value = $xml && $xml->getName() === 'string' ? trim((string) $xml) : '';
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            $errors = ['OutOfTitles' => 'Категория груза не найдена у перевозчика.', 'OutOfCity' => 'Выберите город из списка.',
                'ProfileNotSelected' => 'Выберите профиль.', 'DeviationIsUnknown' => 'Доплату за маршрут нужно уточнить у ВАМ Экспресс.',
                'TitleNotAvaliable' => 'Эта категория недоступна для вашего аккаунта.',
                'InternetMarketAssessedCostException' => 'Укажите оценочную стоимость для профиля поручения.'];
            $value = str_replace(',', '.', $value);
            if (! preg_match('/^\d{1,7}(?:\.\d{1,8})?$/D', $value)) {
                throw new VamExpressException($errors[$value] ?? 'Перевозчик не вернул стоимость. Проверьте профиль, город и грузы.');
            }
            $result = ['amount' => number_format(round((float) $value, 2), 2, '.', ''), 'currency' => 'BYN',
                'fingerprint' => self::fingerprint($payload), 'calculated_at' => now()->toIso8601String(),
                'city' => $city['label'], 'days' => $city['days'], 'terms' => $city['terms'],
                'profile' => $context['profiles'][$input['profile_id']], 'source' => 'ВАМ Экспресс'];
            Cache::put($key, $result, now()->addMinutes(15));

            return $result;
        } finally {
            $lock->release();
        }
    }
}
