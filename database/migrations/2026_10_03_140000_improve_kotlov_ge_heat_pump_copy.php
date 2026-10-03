<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $brandId = DB::table('brands')->where('name', 'KOTLOV GE')->value('id');
        $categoryId = DB::table('categories')->where('slug', 'teplovyie-nasosyi')->value('id');

        if (! $brandId || ! $categoryId) {
            return;
        }

        DB::table('products')
            ->where('brand_id', $brandId)
            ->where('category_id', $categoryId)
            ->orderBy('id')
            ->get(['id', 'name', 'specs', 'short_description', 'content', 'meta_title', 'meta_description'])
            ->each(function (object $product): void {
                $specs = $this->specs((string) $product->specs);
                $model = $specs['модель'] ?? $this->modelFromName((string) $product->name);
                $power = $specs['мощность'] ?? null;
                $refrigerant = mb_strtoupper(trim((string) ($specs['хладагент'] ?? '')));
                $temperature = trim((string) ($specs['температура воды'] ?? ''));
                $supply = trim((string) ($specs['питание'] ?? ''));
                $isR290 = $refrigerant === 'R290' || str_contains(mb_strtoupper((string) $product->name), 'R290');
                $isR32 = $refrigerant === 'R32' || str_contains(mb_strtoupper((string) $product->name), 'R32');

                $updates = [];

                if ($this->isGeneratedShortCopy((string) $product->short_description)) {
                    $updates['short_description'] = $this->shortDescription(
                        $model,
                        $power,
                        $temperature,
                        $supply,
                        $isR290,
                        $isR32
                    );
                }

                if ($this->isGeneratedLongCopy((string) $product->content)) {
                    $updates['content'] = $this->content(
                        $model,
                        $power,
                        $temperature,
                        $supply,
                        $isR290,
                        $isR32
                    );
                }

                if ($this->isGeneratedMetaTitle((string) $product->meta_title)) {
                    $updates['meta_title'] = $this->metaTitle($model, $power);
                }

                if ($this->isGeneratedMetaDescription((string) $product->meta_description)) {
                    $updates['meta_description'] = $this->metaDescription(
                        $model,
                        $power,
                        $temperature,
                        $supply,
                        $isR290,
                        $isR32
                    );
                }

                if ($updates !== []) {
                    $updates['updated_at'] = now();
                    DB::table('products')->where('id', $product->id)->update($updates);
                }
            });
    }

    public function down(): void
    {
        // Editorial copy may be changed later in the admin panel. Do not overwrite it on rollback.
    }

    private function specs(string $json): array
    {
        $rows = json_decode($json, true);
        if (! is_array($rows)) {
            return [];
        }

        $specs = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = mb_strtolower(trim((string) ($row['key'] ?? $row['name'] ?? '')));
            $value = trim((string) ($row['value'] ?? ''));
            if ($key !== '' && $value !== '') {
                $specs[$key] = $value;
            }
        }

        return $specs;
    }

    private function shortDescription(
        ?string $model,
        ?string $power,
        string $temperature,
        string $supply,
        bool $isR290,
        bool $isR32
    ): string {
        $identity = $this->identity($model, $power);

        if ($isR290) {
            return "Высокотемпературный тепловой насос воздух-вода {$identity} на природном хладагенте R290. Для радиаторов, тёплого пола и горячего водоснабжения: отопление до 75°C, ГВС до 80°C. Инженерный подбор и монтаж по Беларуси.";
        }

        if ($isR32) {
            return "Тепловой насос воздух-вода {$identity} на хладагенте R32 для отопления, охлаждения и горячего водоснабжения. Температура воды — {$temperature}, питание — {$supply}. Подбор по теплопотерям и монтаж по Беларуси.";
        }

        return "Тепловой насос воздух-вода {$identity} для отопления, охлаждения и горячего водоснабжения. Питание — {$supply}. Рабочие режимы, комплектацию и мощность подтверждаем инженерным расчётом объекта.";
    }

    private function content(
        ?string $model,
        ?string $power,
        string $temperature,
        string $supply,
        bool $isR290,
        bool $isR32
    ): string {
        $safeModel = $this->html($this->displayModel($model) ?: 'тепловой насос');
        $safePower = $this->html($power ?: 'уточняется расчётом');
        $safeTemperature = $this->html($temperature ?: 'подбирается под систему');
        $safeSupply = $this->html($supply ?: 'уточняется');

        if ($isR290) {
            return <<<HTML
<p><strong>KOTLOV GE {$safeModel}</strong> — высокотемпературный тепловой насос воздух-вода мощностью <strong>{$safePower}</strong> на природном хладагенте R290. Модель предназначена для отопления, охлаждения и подготовки горячей воды и особенно интересна для объектов, где требуется повышенная температура подачи.</p>

<h2>Главное о модели {$safeModel}</h2>
<ul>
    <li><strong>Мощность:</strong> {$safePower}.</li>
    <li><strong>Температурный режим:</strong> {$safeTemperature}.</li>
    <li><strong>Электропитание:</strong> {$safeSupply}.</li>
    <li><strong>Хладагент:</strong> R290.</li>
    <li><strong>Задачи:</strong> отопление, охлаждение и горячее водоснабжение.</li>
</ul>

<h2>Когда стоит рассматривать серию KOTLOV GE R290</h2>
<p>Высокая температура воды позволяет рассматривать эту модель не только для тёплого пола, но и для <a href="/teplovye-nasosy-dlya-radiatorov">радиаторного отопления</a>, реконструкции существующей котельной и приготовления горячей воды. Фактический режим работы зависит от теплопотерь здания, наружной температуры и параметров отопительных приборов.</p>

<p>Перед заказом инженер KOTLOV проверит теплопотери, требуемую температуру подачи, электрическую мощность, схему ГВС и необходимость резервного источника. После расчёта подготовим спецификацию оборудования и предложим <a href="/montazh-teplovyh-nasosov">монтаж теплового насоса под ключ</a>.</p>

<blockquote>Мощность теплового насоса выбирают по расчётным теплопотерям дома, а не только по площади. Такой подход помогает избежать частых запусков, недостатка тепла и лишней работы электрического догрева.</blockquote>

<p>Подробнее о возможностях серии — в разделе <a href="/teplovye-nasosy-r290">тепловые насосы R290</a>.</p>
HTML;
        }

        if ($isR32) {
            return <<<HTML
<p><strong>KOTLOV GE {$safeModel}</strong> — тепловой насос воздух-вода мощностью <strong>{$safePower}</strong> на хладагенте R32. Он объединяет отопление, охлаждение и подготовку горячей воды в одной инженерной системе.</p>

<h2>Ключевые параметры {$safeModel}</h2>
<ul>
    <li><strong>Мощность:</strong> {$safePower}.</li>
    <li><strong>Температура воды:</strong> {$safeTemperature}.</li>
    <li><strong>Электропитание:</strong> {$safeSupply}.</li>
    <li><strong>Хладагент:</strong> R32.</li>
    <li><strong>Задачи:</strong> отопление, охлаждение и горячее водоснабжение.</li>
</ul>

<h2>Для какой системы подходит</h2>
<p>Серия R32 лучше всего раскрывается в низкотемпературных системах: с <a href="/teplovye-nasosy-dlya-teplogo-pola">водяным тёплым полом</a>, фанкойлами и правильно подобранными радиаторами. Модель можно использовать в новом доме или при модернизации котельной, если фактические теплопотери и требуемая температура подачи соответствуют её возможностям.</p>

<p>Команда KOTLOV рассчитает нагрузку, проверит электропитание, подберёт бойлер ГВС, буферную ёмкость, автоматику и резервный источник. По результатам вы получите понятную спецификацию и предложение на <a href="/montazh-teplovyh-nasosov">монтаж с пусконаладкой</a>.</p>

<blockquote>Не привязываем мощность к площади дома без расчёта: учитываем утепление, остекление, вентиляцию, температуру внутри и реальные параметры системы отопления.</blockquote>

<p>Рекомендации по выбору собраны в материале <a href="/teplovye-nasosy-dlya-doma">«Тепловой насос для дома»</a>.</p>
HTML;
        }

        return <<<HTML
<p><strong>KOTLOV GE {$safeModel}</strong> — тепловой насос воздух-вода мощностью <strong>{$safePower}</strong> для отопления, охлаждения и приготовления горячей воды. Модель работает от сети {$safeSupply}.</p>

<h2>Подбор по параметрам объекта</h2>
<p>Для этой позиции рабочая температура и хладагент требуют подтверждения по актуальной комплектации. Поэтому мы не публикуем неподтверждённые значения: инженер сверит исполнение оборудования и рассчитает систему под конкретный дом.</p>

<p>При подборе учитываем теплопотери, тип отопления, горячее водоснабжение, доступную электрическую мощность и резерв. После проверки подготовим спецификацию оборудования и предложение на <a href="/montazh-teplovyh-nasosov">монтаж теплового насоса</a>.</p>

<blockquote>Окончательный выбор модели выполняется по теплотехническому расчёту, а не только по площади здания.</blockquote>
HTML;
    }

    private function metaTitle(?string $model, ?string $power): string
    {
        return $this->identity($model, $power) . ' — купить | KOTLOV';
    }

    private function metaDescription(
        ?string $model,
        ?string $power,
        string $temperature,
        string $supply,
        bool $isR290,
        bool $isR32
    ): string {
        $identity = $this->identity($model, $power);

        if ($isR290) {
            return "Купить {$identity} на R290 в Беларуси. Отопление до 75°C, ГВС до 80°C. Инженерный расчёт, комплектация и монтаж KOTLOV.BY.";
        }

        if ($isR32) {
            return "Купить {$identity} на R32 в Беларуси. Вода {$temperature}, питание {$supply}. Расчёт системы, комплектация и монтаж KOTLOV.BY.";
        }

        return "Купить {$identity} в Беларуси. Тепловой насос воздух-вода, питание {$supply}. Инженерный подбор, комплектация и монтаж KOTLOV.BY.";
    }

    private function identity(?string $model, ?string $power): string
    {
        $model = $this->displayModel($model);
        $power = trim((string) $power);
        $identity = trim('KOTLOV GE ' . $model);

        if ($power !== '' && mb_stripos($model, $power) === false) {
            $identity .= ' ' . $power;
        }

        return trim($identity);
    }

    private function displayModel(?string $model): string
    {
        return trim((string) preg_replace('/^GE\s+/ui', '', trim((string) $model)));
    }

    private function modelFromName(string $name): ?string
    {
        if (preg_match('/\b(?:NL-)?FLM[\w-]+(?:\/R(?:290|32))?\b/ui', $name, $match)) {
            return $match[0];
        }

        if (preg_match('/\bOlympus\s+R32\b/ui', $name, $match)) {
            return $match[0];
        }

        return null;
    }

    private function html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function isGeneratedShortCopy(string $copy): bool
    {
        $copy = trim($copy);

        return str_starts_with($copy, 'Тепловой насос воздух-вода Тепловой насос KOTLOV GE')
            || str_starts_with($copy, 'Тепловой насос воздух-вода KOTLOV GE')
            || str_starts_with($copy, 'Высокотемпературный тепловой насос воздух-вода KOTLOV GE');
    }

    private function isGeneratedLongCopy(string $copy): bool
    {
        return str_contains($copy, 'Правильный тепловой насос начинается не с названия модели')
            || str_contains($copy, 'Команда KOTLOV рассчитает нагрузку')
            || str_contains($copy, 'Для этой позиции рабочая температура и хладагент требуют подтверждения')
            || str_contains($copy, 'Перед заказом инженер KOTLOV проверит теплопотери');
    }

    private function isGeneratedMetaTitle(string $copy): bool
    {
        $copy = trim($copy);

        return (str_starts_with($copy, 'Тепловой насос KOTLOV GE') && str_contains($copy, 'купить в Беларуси'))
            || (str_starts_with($copy, 'KOTLOV GE') && str_ends_with($copy, '| KOTLOV'));
    }

    private function isGeneratedMetaDescription(string $copy): bool
    {
        $copy = trim($copy);

        return str_starts_with($copy, 'Тепловой насос воздух-вода Тепловой насос KOTLOV GE')
            || str_starts_with($copy, 'Купить KOTLOV GE');
    }
};
