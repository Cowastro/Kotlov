<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Starting partner levels agreed on 03.11.2026. Inserted once into an empty
 * table; after that the levels are edited in the admin only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('partner_program_levels')->exists()) {
            return;
        }

        $now = now();
        $levels = [
            [
                'rank' => 1,
                'name' => 'Партнёр',
                'rules' => [],
                'benefits' => [
                    'wholesale_prices' => 'Уровень цен «Опт-1» на весь B2B-каталог.',
                    'directory_priority' => 'Профиль монтажника в каталоге kotlov.by.',
                ],
            ],
            [
                'rank' => 2,
                'name' => 'Профессионал',
                'rules' => [['metric' => 'revenue', 'months' => 6, 'target' => 15000]],
                'benefits' => [
                    'wholesale_prices' => 'Уровень цен «Опт-2».',
                    'payment_deferment' => 'Можно запросить отсрочку платежа — лимит согласует менеджер.',
                    'directory_priority' => 'Повышенный приоритет: выше в списке монтажников своего города.',
                    'site_leads' => 'Заявки клиентов на монтаж в вашем регионе.',
                ],
            ],
            [
                'rank' => 3,
                'name' => 'Дилер',
                'rules' => [['metric' => 'revenue', 'months' => 12, 'target' => 60000]],
                'benefits' => [
                    'wholesale_prices' => 'Уровень цен «Опт-3».',
                    'payment_deferment' => 'Увеличенная отсрочка и кредитный лимит по договору.',
                    'directory_priority' => 'Первые места в списке монтажников своего региона.',
                    'site_leads' => 'Первая очередь на заявки клиентов с сайта.',
                    'volume_bonus' => 'Бонус по итогам квартала — условия в договоре.',
                ],
            ],
        ];

        foreach ($levels as $level) {
            DB::table('partner_program_levels')->insert([
                'rank' => $level['rank'],
                'name' => $level['name'],
                'is_active' => true,
                'rules' => json_encode($level['rules'], JSON_UNESCAPED_UNICODE),
                'benefits' => json_encode($level['benefits'], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Levels may have been edited and assigned to partners; nothing is removed.
    }
};
