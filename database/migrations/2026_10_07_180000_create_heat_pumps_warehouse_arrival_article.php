<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'postuplenie-teplovyh-nasosov-kotlov-na-sklad';

    private const CATEGORY_SLUG = 'novosti-kompanii';

    public function up(): void
    {
        $now = now();

        DB::table('blog_categories')->updateOrInsert(
            ['slug' => self::CATEGORY_SLUG],
            [
                'name' => 'Новости компании',
                'sort_order' => 5,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $categoryId = DB::table('blog_categories')
            ->where('slug', self::CATEGORY_SLUG)
            ->value('id');

        DB::table('blog_posts')->updateOrInsert(
            ['slug' => self::SLUG],
            [
                'category_id' => $categoryId,
                'author_id' => null,
                'title' => 'Тепловые насосы KOTLOV поступили на склад: от частного дома до промышленного объекта',
                'excerpt' => 'На склад KOTLOV поступили моноблочные тепловые насосы на R32 и R290: однофазные решения для домов и трёхфазные модели для крупных объектов. Показываем реальную поставку и объясняем, как подобрать оборудование под проект.',
                'content' => $this->content(),
                'cover_image' => 'img/blog/works/heat-pumps-arrival-warehouse-cover.webp',
                'images' => json_encode([
                    'img/blog/works/heat-pumps-arrival-warehouse-cover.webp',
                    'img/blog/works/heat-pumps-arrival-industrial-unit.webp',
                    'img/blog/works/heat-pumps-arrival-storage.webp',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'tags' => json_encode([
                    'KOTLOV',
                    'тепловые насосы',
                    'поступление на склад',
                    'R290',
                    'R32',
                    'промышленное отопление',
                    'отопление дома',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'is_published' => true,
                'published_at' => '2026-10-07 12:00:00',
                'meta_title' => 'Тепловые насосы KOTLOV поступили на склад | R32 и R290',
                'meta_description' => 'Новое поступление тепловых насосов KOTLOV на склад: модели на R32 и R290 для частных домов, коммерческих и промышленных объектов. Фото поставки и помощь с подбором.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('blog_posts')->where('slug', self::SLUG)->delete();
    }

    private function content(): string
    {
        return <<<'HTML'
<p class="text text-body-1">На склад KOTLOV поступили тепловые насосы для разных задач: от отопления частного дома до работы на крупном промышленном объекте. Публикуем реальные фотографии разгрузки и хранения оборудования — техника уже здесь, а не только в каталоге или на рендерах.</p>

<p class="text text-body-1">В поставке представлены моноблочные модели на хладагентах R32 и R290, с однофазным и трёхфазным подключением. Это позволяет подбирать систему под доступную электрическую мощность, температурный режим отопления и требования конкретного объекта.</p>

<blockquote>
    Первая позиция, NL-DCB30K, поступила под промышленный объект. Для такого оборудования особенно важны инженерный расчёт, проверка электроснабжения и согласование схемы подключения до начала монтажа.
</blockquote>

<div class="blog-image-2">
    <img loading="eager" width="1600" height="900" src="/img/blog/works/heat-pumps-arrival-warehouse-cover.webp" alt="Поступление тепловых насосов KOTLOV на склад">
</div>

<h2>Какие модели поступили</h2>

<div class="kotlov-article-table">
    <table>
        <thead>
            <tr>
                <th>Модель</th>
                <th>Электропитание</th>
                <th>Хладагент</th>
                <th>Назначение</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>NL-DCB30K</td>
                <td>400 В, 3 фазы</td>
                <td>R32</td>
                <td>Промышленный объект</td>
            </tr>
            <tr>
                <td>NL-FLM30-100II/R290</td>
                <td>400 В, 3 фазы</td>
                <td>R290</td>
                <td>Подбор по параметрам проекта</td>
            </tr>
            <tr>
                <td>NL-FLM30-130II/R290</td>
                <td>400 В, 3 фазы</td>
                <td>R290</td>
                <td>Подбор по параметрам проекта</td>
            </tr>
            <tr>
                <td>NL-FLM50-160II/R290</td>
                <td>400 В, 3 фазы</td>
                <td>R290</td>
                <td>Подбор по параметрам проекта</td>
            </tr>
            <tr>
                <td>NL-FLM25Y/32</td>
                <td>230 В, 1 фаза</td>
                <td>R32</td>
                <td>Подбор по параметрам проекта</td>
            </tr>
        </tbody>
    </table>
</div>

<p class="text text-body-1">Все перечисленные позиции выполнены в моноблочном формате. Актуальную доступность конкретной модели и срок подготовки к отгрузке уточняет менеджер: оборудование распределяется под проекты и заявки.</p>

<div class="tf-grid-layout sm-col-2 gap-30">
    <div class="blog-image-2">
        <img loading="lazy" width="1536" height="1024" src="/img/blog/works/heat-pumps-arrival-industrial-unit.webp" alt="Крупный тепловой насос KOTLOV для промышленного объекта в транспортной упаковке">
    </div>
    <div class="blog-image-2">
        <img loading="lazy" width="1536" height="1024" src="/img/blog/works/heat-pumps-arrival-storage.webp" alt="Тепловые насосы KOTLOV после поступления на склад">
    </div>
</div>

<h2>Почему наличие на складе важно для проекта</h2>

<p class="text text-body-1">Тепловой насос нельзя правильно выбрать только по площади здания или цифре в названии модели. Нужно учитывать теплопотери, тип отопительных приборов, необходимую температуру подачи, потребность в горячей воде и доступную электрическую мощность.</p>

<ul>
    <li><strong>Для частного дома</strong> проверяем утепление, тёплый пол или радиаторы, режим горячего водоснабжения и доступность однофазного либо трёхфазного подключения.</li>
    <li><strong>Для коммерческого объекта</strong> учитываем график нагрузки, резервирование, автоматику и возможность каскадной работы.</li>
    <li><strong>Для промышленного проекта</strong> расчёт начинаем с требуемой тепловой мощности, схемы теплоснабжения и параметров электросети.</li>
</ul>

<p class="text text-body-1">Поступление оборудования на склад сокращает путь от расчёта до комплектации объекта: специалисты KOTLOV могут сверить проектные требования с реальной моделью, подготовить схему и согласовать монтаж.</p>

<h2>R32 или R290: что выбрать</h2>

<p class="text text-body-1">Выбор хладагента связан не с одним рекламным преимуществом, а со всей системой отопления. Модели на R32 подходят для многих стандартных задач, а решения на R290 особенно интересны для проектов, где требуется более высокая температура воды. Окончательный выбор делается после расчёта и проверки отопительных приборов.</p>

<p class="text text-body-1">Подробно различия и сценарии применения разобраны в материале <a href="/blog/teplovye-nasosy-ge-r290-vysokotemperaturnye" class="link text-decoration-underline">о высокотемпературных тепловых насосах R290</a>. Если вы только начинаете выбирать систему, используйте <a href="/blog/kak-vybrat-teplovoy-nasos" class="link text-decoration-underline">пошаговый гид по подбору теплового насоса</a>.</p>

<div class="kotlov-article-note">
    <strong>Нужен расчёт под ваш объект?</strong>
    Перейдите в <a href="/teplovyie-nasosyi" class="link text-decoration-underline">каталог тепловых насосов KOTLOV</a> или оставьте заявку на <a href="/montazh-teplovyh-nasosov" class="link text-decoration-underline">подбор и монтаж под ключ</a>. Для точного расчёта подготовьте площадь и назначение объекта, данные по утеплению, тип отопления и доступную электрическую мощность.
</div>
HTML;
    }
};
