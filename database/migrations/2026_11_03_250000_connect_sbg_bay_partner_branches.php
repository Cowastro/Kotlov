<?php

use App\Models\B2bClient;
use App\Models\City;
use App\Models\PartnerBranch;
use App\Models\User;
use App\Services\B2bClientWorkflow;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

/**
 * First partner of the branch network: ООО «СБГ бай» (sbg.by, УНП 193265863) with
 * its retail points in Минск and Борисов, as published on sbg.by/contacts (03.11.2026).
 * Login access is sent from the admin later; the account gets a random password.
 */
return new class extends Migration
{
    private const UNP = '193265863';

    public function up(): void
    {
        // Only a real catalogue with cities gets the partner; fresh test databases stay empty.
        if (! City::query()->where('slug', 'minsk')->exists()) {
            return;
        }

        $client = B2bClient::query()->where('unp', self::UNP)->first();

        if (! $client) {
            $user = User::query()->firstOrCreate(['email' => 'sbg.by.info@yandex.by'], [
                'name' => 'ООО «СБГ бай»',
                'password' => Str::random(40),
                'phone' => '+375 (29) 377-70-92',
                'client_type' => 'wholesale',
                'company_name' => 'ООО «СБГ бай»',
                'company_inn' => self::UNP,
                'is_active' => true,
            ]);
            $client = B2bClient::query()->firstOrCreate(['user_id' => $user->id], [
                'status' => 'pending',
                'partner_type' => 'wholesale',
                'company_name' => 'ООО «СБГ бай»',
                'unp' => self::UNP,
                'contact_name' => 'ООО «СБГ бай»',
                'contact_email' => 'sbg.by.info@yandex.by',
                'contact_phone' => '+375 (29) 377-70-92',
                'default_delivery_city' => 'Минск',
                'default_delivery_address' => 'г. Минск, ул. Селицкого, 39Б, каб. 23',
                'application_comment' => 'Партнёр сети филиалов KOTLOV (sbg.by).',
            ]);
        }

        if ($client->status !== 'approved') {
            $client = app(B2bClientWorkflow::class)->approve($client);
        }

        foreach ([
            ['minsk', 'ООО «СБГ бай»', 'г. Минск, ул. Селицкого, 39Б, каб. 23 (2-й этаж)', ['+375 (29) 377-70-92', '+375 (33) 377-70-93'], 'sbg.by.info@yandex.by', 'Пн–Чт 9:00–18:00, Пт 9:00–17:00', 10],
            ['borisov', 'Магазин «Печкiн» (ООО «СБГ бай»)', 'г. Борисов, ул. Демина, 39, пав. 222', ['+375 (29) 144-63-14'], 'sbgby@tut.by', null, 10],
        ] as [$slug, $name, $address, $phones, $email, $hours, $sort]) {
            $city = City::query()->where('slug', $slug)->first();
            if (! $city) {
                continue;
            }

            PartnerBranch::query()->firstOrCreate(
                ['b2b_client_id' => $client->id, 'city_id' => $city->id],
                ['name' => $name, 'address' => $address, 'phones' => $phones, 'email' => $email,
                    'working_hours' => $hours, 'is_active' => true, 'sort_order' => $sort],
            );
        }
    }

    public function down(): void
    {
        // Partner data may already be edited in the admin; nothing is removed.
    }
};
