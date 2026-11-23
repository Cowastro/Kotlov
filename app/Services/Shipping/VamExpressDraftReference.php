<?php

namespace App\Services\Shipping;

class VamExpressDraftReference
{
    public function parse(string $html): array
    {
        $parser = new VamExpressHtml;
        $hidden = $parser->hidden($html);
        $xpath = $parser->xpath($html);
        $scripts = implode("\n", array_map(fn ($n) => $n->textContent, iterator_to_array($xpath->query('//script[not(@src)]'))));
        if (! preg_match('/var\s+availableUserProfiles\s*=\s*(\[.*?\]);/s', $scripts, $profiles)
            || ! preg_match('/var\s+availableTitles\s*=\s*(\[.*?\]);/s', $scripts, $titles)
            || ! preg_match('/appkey\s*:\s*([\x22\x27])([^\x22\x27]+)\1/', $scripts, $lookup)
            || ! preg_match('/function\s+calculateCost\(\).*?userid\s*:\s*[\x22\x27](\d+)[\x22\x27].*?apikey\s*:\s*([\x22\x27])([^\x22\x27]+)\2/s', $scripts, $calculator)) {
            throw new VamExpressException('Изменилась форма расчёта ВАМ Экспресс. Справочник не обновлён.');
        }
        $goods = json_decode(preg_replace('/,\s*\]/', ']', $titles[1]), true, flags: JSON_THROW_ON_ERROR);
        $profiles = json_decode($profiles[1], true, flags: JSON_THROW_ON_ERROR);
        $options = [];
        foreach ($profiles as $profile) {
            $id = (string) ($profile['data'] ?? '');
            if (! preg_match('/^[1-4]\d{1,12}$/', $id) || ! is_string($profile['value'] ?? null)) {
                throw new VamExpressException('Не удалось проверить тип профиля отправителя.');
            }
            $options[$id] = trim($profile['value']);
        }
        if (! $options || count($options) > 100 || ! is_array($goods) || ! $goods || count($goods) > 5000) {
            throw new VamExpressException('Не удалось проверить справочники ВАМ Экспресс.');
        }
        foreach ($goods as $title) {
            if (! is_string($title) || $title === '' || mb_strlen($title) > 255) {
                throw new VamExpressException('Неверный формат категории груза.');
            }
        }
        $userId = $hidden['ctl00$MainContent$hfUserID'] ?? '';
        $discount = $hidden['ctl00$MainContent$hfUserDiscount'] ?? '';
        $express = $hidden['ctl00$MainContent$hfExpressCost'] ?? '';
        if (! ctype_digit((string) $userId) || ! is_numeric($discount) || ! is_numeric($express)) {
            throw new VamExpressException('Не удалось прочитать условия аккаунта для расчёта.');
        }
        $value = function (string $field) use ($xpath): string {
            $nodes = $xpath->query('//input[@name="ctl00$MainContent$'.$field.'"]');

            return $nodes->length === 1 ? trim($nodes->item(0)->getAttribute('value')) : '';
        };

        return [
            'profiles' => $options, 'goods' => array_values(array_unique($goods)),
            'user_id' => (string) $userId, 'discount' => (string) $discount, 'express_cost' => (string) $express,
            'lookup_key' => $lookup[2], 'calculator_key' => $calculator[3], 'calculator_user_id' => $calculator[1],
            'sender' => ['sender_city_id' => $hidden['ctl00$MainContent$hfSenderCityID'] ?? null,
                'sender_city_label' => $value('tbSenderCity'), 'sender_street' => $value('tbSenderStreetName'),
                'sender_house' => $value('tbSenderStreetNumber'), 'sender_housing' => $value('tbSenderHousing'),
                'sender_apartment' => $value('tbSenderApartmentNumber')],
        ];
    }
}
