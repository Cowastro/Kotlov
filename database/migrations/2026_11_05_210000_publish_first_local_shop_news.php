<?php

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\City;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $cities = City::whereIn('slug', ['lida', 'borisov'])->where('is_active', true)
            ->where('storefront_enabled', true)
            ->whereHas('partnerBranches', fn ($branch) => $branch->where('is_active', true)
                ->whereHas('client', fn ($client) => $client->where('status', 'approved')))
            ->get()->keyBy('slug');
        if ($cities->isEmpty()) { return; }
        $category = BlogCategory::firstOrCreate(['slug' => 'novosti-magazinov'], [
            'name' => 'Новости магазинов', 'sort_order' => 11, 'is_active' => true,
        ]);

        $articles = [
            'lida' => [
                'slug' => 'pechi-kaminy-dymohody-magazin-lida',
                'title' => 'Печи, камины и дымоходы в Лиде: знакомимся с магазином KOTLOV',
                'excerpt' => 'Что можно посмотреть в торговом зале на улице Калинина, 57, как подготовиться к подбору печи и где уточнить комплектацию дымохода.',
                'cover_image' => 'img/partner-branches/lida/showroom-20261105.webp',
                'content' => <<<'HTML'
<p>У магазина KOTLOV в Лиде появилась отдельная региональная страница. Покупателям теперь проще найти местные контакты, посмотреть фотографии торгового зала и перейти к общему каталогу оборудования.</p>
<h2>Что представлено в торговом зале</h2>
<p>На фотографиях магазина — отопительные печи, камины, банные печи, элементы дымоходов и печное литьё. Образцы помогают оценить размеры, исполнение дверцы и внешний вид оборудования перед выбором.</p>
<p>Посмотреть модели и характеристики можно в разделах <a href="/pechki">«Печи»</a>, <a href="/kaminy">«Камины»</a> и <a href="/dymohody">«Дымоходы»</a>. Каталог шире выставки: наличие конкретного образца и возможность его осмотра уточните до поездки.</p>
<h2>Что подготовить к консультации</h2>
<p>Для подбора печи полезны площадь и высота помещения, сведения об утеплении и предполагаемом режиме отопления. Для дымохода — модель печи или котла, диаметр выхода, примерная высота трассы и фотографии перекрытия или кровли. Эти данные помогут обсудить комплектацию и дальнейшие шаги с продавцом.</p>
<h2>Адрес и время работы в Лиде</h2>
<p><strong>Ул. Калинина, 57</strong>, рядом с Курганом Бессмертия. Магазин работает Пн–Пт 10:00–17:00 с перерывом 13:00–14:00, Сб 10:00–15:00. Воскресенье — выходной.</p>
<p>Телефон: <a href="tel:+375333667609">+375 (33) 366-76-09</a>. <a href="/#city-branch">Все телефоны, карта и фотографии магазина</a>. Наличие, итоговую стоимость, оплату и получение заказа подтвердит продавец — ИП Кузин А. А.</p>
HTML,
            ],
            'borisov' => [
                'slug' => 'magazin-pechkin-borisov-katalog-i-kontakty',
                'title' => 'KOTLOV в Борисове: как выбрать оборудование и обратиться в «Печкiн»',
                'excerpt' => 'Региональный каталог, консультация перед покупкой и график магазина на улице Дёмина, 39, в павильоне 222.',
                'cover_image' => null,
                'content' => <<<'HTML'
<p>Покупателям Борисова доступен региональный сайт KOTLOV с общим каталогом отопительного оборудования и контактами магазина «Печкiн» ООО «СБГ бай». Здесь можно выбрать товар и обратиться к местному продавцу перед покупкой.</p>
<h2>Сначала выберите оборудование в каталоге</h2>
<p>На сайте доступны <a href="/kotly">котлы</a>, <a href="/pechki">печи</a>, <a href="/kaminy">камины</a>, <a href="/dymohody">дымоходы</a> и комплектующие. В карточках можно сравнить характеристики и стоимость. Общий ассортимент маркетплейса не означает, что каждая позиция находится на полке магазина в Борисове.</p>
<h2>Уточните наличие и комплектацию</h2>
<p>Сообщите продавцу название или артикул выбранной модели. Если требуется дымоход, подготовьте модель оборудования, диаметр подключения и фотографии места установки. Продавец уточнит доступность, состав заказа, итоговую стоимость и срок получения. Оплату, доставку и самовывоз согласуйте с магазином до оплаты.</p>
<h2>Когда приезжать в магазин</h2>
<p>Адрес: <strong>г. Борисов, ул. Дёмина, 39, павильон 222</strong>. Вторник–пятница: 09:00–18:00; суббота: 09:00–17:00. Воскресенье и понедельник — выходные.</p>
<p>Телефон магазина: <a href="tel:+375291446314">+375 (29) 144-63-14</a>. <a href="/#city-branch">Карта проезда и фотографии «Печкiна»</a>. Перед поездкой за конкретным товаром позвоните, чтобы подтвердить наличие или согласовать выдачу.</p>
HTML,
            ],
        ];

        foreach ($articles as $slug => $attributes) {
            if (! $cities->has($slug)) { continue; }
            if ($slug === 'borisov') {
                // Use the actual Borisov store photo, never a different shop's interior.
                $branch = \App\Models\PartnerBranch::query()->visibleIn($cities[$slug])->first();
                $attributes['cover_image'] = $branch?->hero_image ?: null;
            }
            BlogPost::firstOrCreate(['slug' => $attributes['slug']], array_merge($attributes, [
                'city_id' => $cities[$slug]->id, 'category_id' => $category->id,
                'is_published' => true, 'published_at' => now(),
                'meta_title' => $attributes['title'].' | KOTLOV',
                'meta_description' => $attributes['excerpt'],
                'tags' => [$cities[$slug]->name, 'Новости магазина'],
            ]));
        }
    }

    public function down(): void
    {
        // Preserve local publications and editorial changes.
    }
};
