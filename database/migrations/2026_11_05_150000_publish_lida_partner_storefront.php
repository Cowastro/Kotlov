<?php

use App\Models\B2bClient;
use App\Models\City;
use App\Models\PartnerBranch;
use App\Services\CityStorefrontManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            // Public contacts verified against https://kotlovlida.by/contacts on 2026-11-05.
            $client = B2bClient::whereKey(11)->where('unp', '591355217')->lockForUpdate()->first();
            if (! $client) { return; }
            if ($client->status !== 'approved') {
                throw new RuntimeException('The Lida partner must be approved before publication.');
            }
            $city = City::where('slug', 'lida')->lockForUpdate()->firstOrFail();
            $branches = PartnerBranch::where('city_id', $city->id)->where('b2b_client_id', $client->id)->lockForUpdate()->get();
            if ($branches->count() > 1 || $branches->contains(fn ($branch) => filled($branch->pending_changes))) {
                throw new RuntimeException('Review existing Lida branches before importing the store.');
            }
            $photos = collect(['facade', 'showroom', 'stoves', 'sauna', 'chimneys'])
                ->map(fn ($name) => 'partner-branches/lida/'.$name.'-20261105.webp')->all();
            foreach ($photos as $path) {
                $source = public_path('img/'.$path);
                if (! is_file($source) || ! Storage::disk('public')->put($path, file_get_contents($source))) {
                    throw new RuntimeException('Unable to publish Lida store photographs.');
                }
            }
            $branch = $branches->first() ?? new PartnerBranch(['city_id' => $city->id, 'b2b_client_id' => $client->id]);
            $branch->fill([
                'name' => 'Магазин KOTLOV в Лиде (ИП Кузин А. А.)',
                'address' => 'г. Лида, ул. Калинина, 57',
                'phones' => ['+375 (33) 366-76-09', '+375 (29) 928-37-66', '+375 (15) 460-90-00'],
                'email' => 'kotlovlida@gmail.com',
                'working_hours' => 'Пн–Пт 10:00–17:00, перерыв 13:00–14:00; Сб 10:00–15:00; Вс — выходной',
                'map_url' => 'https://yandex.by/maps/?text='.rawurlencode('Лида, улица Калинина, 57'),
                'storefront_title' => 'KOTLOV в Лиде',
                'storefront_intro' => 'Котлы, печи, камины и дымоходы в Лиде. Поможем подобрать оборудование, покажем образцы в магазине и уточним наличие.',
                'storefront_promo' => 'Подбор котлов, печей, каминов и комплектующих для дымохода. Магазин расположен рядом с Курганом Бессмертия. Самовывоз и условия доставки уточняйте по телефону.',
                'hero_image' => $photos[0],
                'gallery' => $photos,
                'seo_title' => 'Котлы, печи и камины в Лиде — магазин KOTLOV',
                'seo_description' => 'Магазин KOTLOV в Лиде: котлы, печи, камины и дымоходы. Ул. Калинина, 57. Подбор оборудования, контакты, график работы и общий каталог KOTLOV.',
                'is_active' => true,
                'sort_order' => 10,
            ])->save();
            app(CityStorefrontManager::class)->update($city, ['storefront_enabled' => true, 'primary_partner_branch_id' => $branch->id]);
            $client->auditLogs()->create(['event' => 'branch_published', 'metadata' => ['branch_id' => $branch->id, 'source' => 'kotlovlida.by/contacts', 'photos' => count($photos)]]);
        });
    }

    public function down(): void
    {
        // Preserve published store data and subsequent partner edits.
    }
};
