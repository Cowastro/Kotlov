<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerProgramLevel extends Model
{
    public const METRICS = [
        'revenue' => 'Оплаченный оборот, BYN',
        'orders' => 'Оплаченные заказы, шт.',
    ];

    public const BENEFITS = [
        'wholesale_prices' => 'Оптовые цены',
        'payment_deferment' => 'Отсрочка платежа',
        'directory_priority' => 'Приоритет в каталоге монтажников',
        'site_leads' => 'Заявки клиентов с сайта',
        'volume_bonus' => 'Бонус за объём',
    ];

    protected $fillable = ['rank', 'name', 'is_active', 'benefits', 'rules'];

    protected $casts = ['rules' => 'array', 'benefits' => 'array', 'is_active' => 'boolean', 'rank' => 'integer'];

    public function benefitsFor(string $partnerType): array
    {
        $labels = self::BENEFITS;
        if ($partnerType !== 'installer') {
            unset($labels['directory_priority'], $labels['site_leads']);
        }

        $benefits = [];
        foreach ($labels as $key => $label) {
            $terms = $this->benefits[$key] ?? null;
            if (filled($terms)) {
                $benefits[$label] = $terms;
            }
        }

        return $benefits;
    }
}
