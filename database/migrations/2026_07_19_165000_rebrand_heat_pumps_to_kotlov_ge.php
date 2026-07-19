<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const CATEGORY_SLUG = 'teplovyie-nasosyi';

    public function up(): void
    {
        $now = now();

        $brandId = DB::table('brands')->where('slug', 'kotlov-ge')->value('id');

        if (! $brandId) {
            $brandId = DB::table('brands')->insertGetId([
                'name' => 'KOTLOV GE',
                'slug' => 'kotlov-ge',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('brands')->where('id', $brandId)->update([
                'name' => 'KOTLOV GE',
                'is_active' => true,
                'updated_at' => $now,
            ]);
        }

        $categoryId = DB::table('categories')->where('slug', self::CATEGORY_SLUG)->value('id');

        if (! $categoryId) {
            return;
        }

        $products = [
            'teplovoy-nasos-vozduh-voda-b3sd' => [
                'name' => 'Тепловой насос KOTLOV GE FLM30-R32 10 кВт',
                'model' => 'FLM30-R32',
                'line' => 'R32',
                'power' => '10 кВт',
                'powerRange' => '6–10 кВт',
                'waterTemp' => 'до 60°C',
                'refrigerant' => 'R32',
                'voltage' => '220 В',
            ],
            'teplovoy-nasos-vozduh-voda-b5sd' => [
                'name' => 'Тепловой насос KOTLOV GE FLM40-R32 16 кВт',
                'model' => 'FLM40-R32',
                'line' => 'R32',
                'power' => '16 кВт',
                'powerRange' => '16–20 кВт',
                'waterTemp' => 'до 60°C',
                'refrigerant' => 'R32',
                'voltage' => '380 В',
            ],
            'teplovoy-nasos-centrometal-4-kvt' => [
                'name' => 'Тепловой насос KOTLOV GE 4 кВт',
                'model' => 'GE 4 кВт',
                'line' => 'воздух-вода',
                'power' => '4 кВт',
                'powerRange' => 'до 5 кВт',
                'waterTemp' => 'уточняйте',
                'refrigerant' => 'уточняйте',
                'voltage' => '220 В',
            ],
            'teplovoy-nasos-centrometal-6-kvt' => [
                'name' => 'Тепловой насос KOTLOV GE 6 кВт',
                'model' => 'GE 6 кВт',
                'line' => 'воздух-вода',
                'power' => '6 кВт',
                'powerRange' => '6–10 кВт',
                'waterTemp' => 'уточняйте',
                'refrigerant' => 'уточняйте',
                'voltage' => '220 В',
            ],
            'teplovoy-nasos-centrometal-8-kvt' => [
                'name' => 'Тепловой насос KOTLOV GE 8 кВт',
                'model' => 'GE 8 кВт',
                'line' => 'воздух-вода',
                'power' => '8 кВт',
                'powerRange' => '6–10 кВт',
                'waterTemp' => 'уточняйте',
                'refrigerant' => 'уточняйте',
                'voltage' => '220 В',
            ],
            'teplovoy-nasos-centrometal-12-kvt' => [
                'name' => 'Тепловой насос KOTLOV GE NL-FLM50-190II/R290 19 кВт',
                'model' => 'NL-FLM50-190II/R290',
                'line' => 'R290 высокотемпературный',
                'power' => '19 кВт',
                'powerRange' => '16–20 кВт',
                'waterTemp' => 'ГВС до 80°C, отопление до 75°C',
                'refrigerant' => 'R290',
                'voltage' => '380 В',
            ],
            'teplovoy-nasos-centrometal-12-kvt-380v' => [
                'name' => 'Тепловой насос KOTLOV GE NL-FLM50-160II/R290 16 кВт',
                'model' => 'NL-FLM50-160II/R290',
                'line' => 'R290 высокотемпературный',
                'power' => '16 кВт',
                'powerRange' => '16–20 кВт',
                'waterTemp' => 'ГВС до 80°C, отопление до 75°C',
                'refrigerant' => 'R290',
                'voltage' => '380 В',
            ],
            'teplovoy-nasos-centrometal-14-kvt' => [
                'name' => 'Тепловой насос KOTLOV GE NL-FLM30-130II/R290 12,8 кВт',
                'model' => 'NL-FLM30-130II/R290',
                'line' => 'R290 высокотемпературный',
                'power' => '12,8 кВт',
                'powerRange' => '11–15 кВт',
                'waterTemp' => 'ГВС до 80°C, отопление до 75°C',
                'refrigerant' => 'R290',
                'voltage' => '220 В',
            ],
            'teplovoy-nasos-centrometal-16-kvt' => [
                'name' => 'Тепловой насос KOTLOV GE NL-FLM30-100II/R290 10 кВт',
                'model' => 'NL-FLM30-100II/R290',
                'line' => 'R290 высокотемпературный',
                'power' => '10 кВт',
                'powerRange' => '6–10 кВт',
                'waterTemp' => 'ГВС до 80°C, отопление до 75°C',
                'refrigerant' => 'R290',
                'voltage' => '220 В',
            ],
            'hotta-teplovoy' => [
                'name' => 'Тепловой насос KOTLOV GE Olympus R32 12,5 кВт',
                'model' => 'Olympus R32',
                'line' => 'R32',
                'power' => '12,5 кВт',
                'powerRange' => '11–15 кВт',
                'waterTemp' => 'до 60°C',
                'refrigerant' => 'R32',
                'voltage' => '220 В',
            ],
            'teplovoy-nasos-flamingo-flm30100-r290' => [
                'name' => 'Тепловой насос KOTLOV GE NL-FLM30-100II/R290 10 кВт',
                'model' => 'NL-FLM30-100II/R290',
                'line' => 'R290 высокотемпературный',
                'power' => '10 кВт',
                'powerRange' => '6–10 кВт',
                'waterTemp' => 'ГВС до 80°C, отопление до 75°C',
                'refrigerant' => 'R290',
                'voltage' => '220 В',
            ],
            'teplovoy-nasos-hotta-flm80-r32-30-kvt' => [
                'name' => 'Тепловой насос KOTLOV GE FLM80-R32 30 кВт',
                'model' => 'FLM80-R32',
                'line' => 'R32',
                'power' => '30 кВт',
                'powerRange' => '26–30 кВт',
                'waterTemp' => 'до 60°C',
                'refrigerant' => 'R32',
                'voltage' => '380 В',
            ],
            'teplovoy-nasos-hotta-flm60-r32-23-kvt' => [
                'name' => 'Тепловой насос KOTLOV GE FLM60-R32 23 кВт',
                'model' => 'FLM60-R32',
                'line' => 'R32',
                'power' => '23 кВт',
                'powerRange' => '21–25 кВт',
                'waterTemp' => 'до 60°C',
                'refrigerant' => 'R32',
                'voltage' => '220 В',
            ],
        ];

        foreach ($products as $slug => $data) {
            $product = DB::table('products')->where('slug', $slug)->where('category_id', $categoryId)->first();

            if (! $product) {
                continue;
            }

            DB::table('products')->where('id', $product->id)->update([
                'brand_id' => $brandId,
                'name' => $data['name'],
                'h1' => $data['name'],
                'content' => $this->content($data),
                'short_description' => $this->shortDescription($data),
                'specs' => $this->specs($data),
                'meta_title' => $data['name'] . ' купить в Беларуси',
                'meta_keywords' => implode(', ', [
                    $data['name'],
                    'KOTLOV GE',
                    'тепловой насос воздух-вода',
                    'тепловой насос ' . $data['power'],
                    'тепловой насос ' . $data['refrigerant'],
                ]),
                'meta_description' => Str::limit(strip_tags($this->shortDescription($data)), 245, ''),
                'updated_at' => $now,
            ]);
        }

        $this->updateBlogMentions($now);
    }

    public function down(): void
    {
        //
    }

    private function shortDescription(array $data): string
    {
        return "Тепловой насос воздух-вода {$data['name']} для отопления, охлаждения и горячего водоснабжения. Бренд KOTLOV GE, модель {$data['model']}, мощность {$data['power']}, хладагент {$data['refrigerant']}, температура воды {$data['waterTemp']}.";
    }

    private function specs(array $data): string
    {
        return json_encode([
            ['key' => 'Бренд', 'value' => 'KOTLOV GE'],
            ['key' => 'Производственная платформа', 'value' => 'NULITE'],
            ['key' => 'Модель', 'value' => $data['model']],
            ['key' => 'Тип', 'value' => 'тепловой насос воздух-вода'],
            ['key' => 'Серия', 'value' => $data['line']],
            ['key' => 'Мощность', 'value' => $data['power']],
            ['key' => 'Диапазон мощности для фильтра', 'value' => $data['powerRange']],
            ['key' => 'Хладагент', 'value' => $data['refrigerant']],
            ['key' => 'Температура воды', 'value' => $data['waterTemp']],
            ['key' => 'Питание', 'value' => $data['voltage']],
            ['key' => 'Назначение', 'value' => 'отопление, охлаждение, горячее водоснабжение'],
        ], JSON_UNESCAPED_UNICODE);
    }

    private function content(array $data): string
    {
        $r290 = str_contains($data['refrigerant'], 'R290');

        $temperatureText = $r290
            ? 'Серия R290 относится к высокотемпературным решениям: по линейке KOTLOV GE такие насосы могут готовить горячую воду до 80°C и работать на отопление до 75°C. Это важно для домов, где есть радиаторы, бойлер ГВС или требуется больший температурный запас.'
            : 'Серия R32 подходит для современных систем отопления с тёплыми полами, фанкойлами, низкотемпературными радиаторами и подготовкой горячей воды. Это хороший вариант для нового дома или модернизации котельной.';

        return <<<HTML
<p><strong>{$data['name']}</strong> — тепловой насос воздух-вода бренда <strong>KOTLOV GE</strong>. Линейка выпускается на производственной платформе NULITE и адаптируется под задачи отопления частных домов и коммерческих объектов в Беларуси.</p>

<p>Модель {$data['model']} рассчитана на отопление, охлаждение и подготовку горячей воды. Подбор теплового насоса нельзя делать только по площади дома: важно учитывать теплопотери, утепление, радиаторы или тёплый пол, нужную температуру подачи, электрическую мощность объекта и резервный источник тепла.</p>

<h2>Что важно знать о KOTLOV GE {$data['model']}</h2>

<ul>
    <li><strong>Мощность:</strong> {$data['power']}.</li>
    <li><strong>Хладагент:</strong> {$data['refrigerant']}.</li>
    <li><strong>Температура воды:</strong> {$data['waterTemp']}.</li>
    <li><strong>Назначение:</strong> отопление, охлаждение и ГВС.</li>
    <li><strong>Поставка и подбор:</strong> KOTLOV.BY.</li>
</ul>

<p>{$temperatureText}</p>

<blockquote>
    Правильный тепловой насос начинается не с названия модели, а с расчёта дома. Если подобрать мощность, температуру подачи и гидравлическую схему правильно, система работает тише, экономичнее и без лишнего электрического догрева.
</blockquote>

<h2>Для каких объектов подходит</h2>

<p>{$data['name']} можно рассматривать для частного дома, коттеджа, небольшого коммерческого объекта или гибридной котельной, где тепловой насос работает вместе с резервным котлом. Специалисты KOTLOV.BY помогут проверить параметры объекта и подобрать комплектацию: буферную ёмкость, бойлер ГВС, насосные группы, автоматику и резерв.</p>
HTML;
    }

    private function updateBlogMentions($now): void
    {
        $replacements = [
            'Hotta Flamingo FLM80-R32 30 кВт' => 'KOTLOV GE FLM80-R32 30 кВт',
            'Hotta FLM80-R32' => 'KOTLOV GE FLM80-R32',
            'Hotta Flamingo' => 'KOTLOV GE',
            'Hotta 30 кВт' => 'KOTLOV GE 30 кВт',
            'GE R290' => 'KOTLOV GE R290',
            'тепловые насосы GE' => 'тепловые насосы KOTLOV GE',
            'тепловой насос GE' => 'тепловой насос KOTLOV GE',
        ];

        $posts = DB::table('blog_posts')
            ->where(function ($query) {
                $query->where('content', 'like', '%Hotta%')
                    ->orWhere('content', 'like', '%GE R290%')
                    ->orWhere('title', 'like', '%Hotta%')
                    ->orWhere('title', 'like', '%GE R290%')
                    ->orWhere('excerpt', 'like', '%Hotta%')
                    ->orWhere('excerpt', 'like', '%GE R290%');
            })
            ->get();

        foreach ($posts as $post) {
            $updates = [];

            foreach (['title', 'excerpt', 'content', 'meta_title', 'meta_description', 'meta_keywords'] as $field) {
                $value = $post->{$field} ?? null;

                if ($value === null) {
                    continue;
                }

                $updates[$field] = str_replace(array_keys($replacements), array_values($replacements), $value);
            }

            if ($updates) {
                $updates['updated_at'] = $now;
                DB::table('blog_posts')->where('id', $post->id)->update($updates);
            }
        }
    }
};
