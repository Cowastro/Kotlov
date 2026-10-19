<?php

namespace App\Services\Suppliers;

use App\Models\Product;
use Illuminate\Support\Str;

class SantehskladCatalogContent
{
    public function shouldEnrich(Product $product): bool
    {
        $content = trim(strip_tags((string) $product->content));

        return mb_strlen($content) < 500
            || str_contains($content, 'Цена привязана к прайсу поставщика «Сантехсклад»');
    }

    /** @param array<string,mixed> $row @param array<string,mixed> $payload */
    public function enrich(Product $product, array $row, array $payload): bool
    {
        if (! $this->shouldEnrich($product)) {
            return false;
        }

        $profile = $this->profile($row);
        $name = trim((string) $product->name);
        $power = $this->power($row);
        $source = $this->source($row);
        $features = array_values(array_unique(array_filter(array_merge(
            $profile['features'],
            $this->rowFeatures($row)
        ))));

        $short = $name.'. '.$profile['short'];
        $content = '<p><strong>'.e($name).'</strong> — '.e($profile['intro']).'</p>'
            .'<h2>Назначение и особенности</h2><ul>'
            .collect($features)->map(fn (string $feature): string => '<li>'.e($feature).'</li>')->implode('')
            .'</ul><h2>Подбор и заказ</h2><p>'.e($this->selectionText($profile['kind'], $power)).'</p>'
            .'<p>Цена сформирована по прайсу официального поставщика от '
            .e(date('d.m.Y', strtotime((string) $payload['source_date'])))
            .'. Перед заказом менеджер KOTLOV.BY подтвердит наличие, срок поставки и совместимость выбранного исполнения.</p>';

        $specs = $this->mergeSpecs($product->specs ?: [], array_merge(
            $this->baseSpecs($row, $profile),
            $profile['specs']
        ));
        $serviceInfo = is_array($product->service_info) ? $product->service_info : [];
        $serviceInfo['santehsklad_content'] = [
            'source_date' => $payload['source_date'],
            'source_type' => 'supplier_pricelist_and_verified_catalogue',
            'enriched_at' => now()->toIso8601String(),
        ];

        $changes = [
            'short_description' => Str::limit($short, 260, ''),
            'content' => $content,
            'specs' => $specs,
            'service_info' => $serviceInfo,
            'meta_title' => Str::limit($name.' — купить в Минске | KOTLOV.BY', 70, ''),
            'meta_description' => Str::limit($name.'. '.$profile['meta'].' Цена поставщика, подбор и заказ с подтверждением наличия в KOTLOV.BY.', 170, ''),
        ];

        if (blank($product->content_source_url) && $source['url']) {
            $changes['content_source_url'] = $source['url'];
        }
        if ($product->imagePaths() === [] && $source['image']) {
            $changes['images'] = [$source['image']];
        }
        if (blank($product->warranty) && $profile['warranty']) {
            $changes['warranty'] = $profile['warranty'];
        }

        $product->forceFill($changes)->save();

        return true;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function profile(array $row): array
    {
        $identity = (string) $row['identity'];
        $name = mb_strtolower((string) $row['name']);

        if (str_starts_with($identity, 'pramen_green_')) {
            return $this->boilerProfile('твердотопливный котёл для автономного водяного отопления', 'дрова и древесные брикеты', [
                'Компактная серия PRAMEN Green для частных домов, дач и небольших коммерческих объектов.',
                'Механическая загрузка топлива не требует сложной автоматики.',
            ]);
        }
        if (str_starts_with($identity, 'standart_pro_')) {
            return $this->boilerProfile('усиленный твердотопливный котёл для водяной системы отопления', 'дрова, уголь и топливные брикеты', [
                'Серия Standart Pro рассчитана на системы с повышенными требованиями к ресурсу теплообменника.',
                'Водонаполненный колосник участвует в теплообмене и помогает эффективнее использовать топливо.',
            ], '60 месяцев');
        }
        if (str_starts_with($identity, 'standart_')) {
            return $this->boilerProfile('классический твердотопливный котёл для автономной системы отопления', 'дрова, уголь и топливные брикеты', [
                'Серия Standart подходит для домов и хозяйственных объектов с водяным отоплением.',
                'Чугунная колосниковая решётка рассчитана на регулярную ручную загрузку топлива.',
            ], '48 месяцев');
        }
        if (str_starts_with($identity, 'universal_')) {
            return $this->boilerProfile('универсальный твердотопливный котёл длительного горения', 'дрова, уголь и топливные брикеты', [
                'Увеличенная камера сгорания позволяет загружать больше топлива и реже обслуживать котёл.',
                'Водонаполненный колосник повышает площадь теплообмена.',
            ], '60 месяцев');
        }
        if (str_starts_with($identity, 'max_')) {
            return $this->boilerProfile('мощный водогрейный твердотопливный котёл для крупных объектов', 'дрова, уголь и топливные брикеты', [
                'Серия Max предназначена для больших домов, производственных, складских и коммерческих помещений.',
                'Усиленный теплообменник рассчитан на эксплуатацию в котельных повышенной мощности.',
            ], '48 месяцев');
        }
        if (str_starts_with($identity, 'pellet_universal_')) {
            return $this->pelletProfile('пеллетный котёл с возможностью гибкой организации отопления', [
                'Серия Pellet Universal сочетает автоматическое пеллетное отопление и развитую водяную часть котла.',
                'Увеличенная камера сгорания упрощает обслуживание и расширяет сценарии эксплуатации.',
            ], '60 месяцев');
        }
        if (str_starts_with($identity, 'pellet_max_')) {
            return $this->pelletProfile('пеллетный котёл большой мощности для коммерческих и производственных объектов', [
                'Серия Pellet Max рассчитана на крупные системы отопления и продолжительную сезонную работу.',
                'Конструкция допускает основную работу на пеллетах и резервное ручное топливо согласно комплектации.',
            ], '48 месяцев');
        }
        if (str_starts_with($identity, 'pellet_eco_')) {
            return $this->pelletProfile('автоматический пеллетный котёл для котельных средней и большой мощности', [
                'Серия Pellet Eco ориентирована на автоматическую работу именно на древесных пеллетах.',
                'Подходит для объектов, где важны стабильная подача топлива и сокращение ручного обслуживания.',
            ]);
        }
        if (str_starts_with($identity, 'pellet_standart_praktik_')) {
            return $this->pelletProfile('готовый комплект котла, пеллетной горелки Praktik и топливного бункера', [
                'Комплект уже объединяет основные узлы автоматической пеллетной котельной.',
                'Объём бункера подобран производителем под мощность комплекта.',
            ]);
        }
        if (str_starts_with($identity, 'pellet_standart_')) {
            return $this->pelletProfile('пеллетный теплообменник для сборки автоматической котельной', [
                'Серия Pellet Standart предназначена для работы с совместимой пеллетной горелкой и автоматикой.',
                'Чугунный колосник сохраняет возможность эксплуатации в предусмотренном производителем режиме.',
            ]);
        }
        if (str_starts_with($identity, 'promka_')) {
            $glass = str_contains($identity, 'no_glass') ? 'глухой дверцей' : 'дверцей со стеклом';

            return [
                'kind' => 'stove',
                'short' => 'Стальная отопительная печь с варочной поверхностью и '.$glass.'.',
                'intro' => 'стальная отопительная печь длительного горения с варочной поверхностью и '.$glass.'. Она подходит для обогрева дачи, мастерской, гаража или небольшого дома.',
                'meta' => 'Отопительная печь длительного горения с варочной поверхностью.',
                'features' => [
                    'Варочная поверхность позволяет разогревать воду и готовить пищу.',
                    'Режим длительного горения помогает поддерживать тепло между загрузками дров.',
                    'Выбранное исполнение: '.($glass === 'дверцей со стеклом' ? 'со стеклом для обзора пламени.' : 'без стекла — практичный вариант для технических помещений.'),
                ],
                'specs' => [['key' => 'Топливо', 'value' => 'Дрова']],
                'warranty' => '36 месяцев',
            ];
        }
        if (str_starts_with($identity, 'madera_')) {
            return [
                'kind' => 'stove',
                'short' => 'Отопительно-варочная печь для сезонного и постоянного использования.',
                'intro' => 'отопительно-варочная печь для обогрева помещения и приготовления пищи. Переключение режимов «зима/лето» помогает использовать печь по сезону.',
                'meta' => 'Отопительно-варочная печь с режимами зима/лето.',
                'features' => [
                    'Варочная поверхность для приготовления пищи и нагрева воды.',
                    'Режимы «зима/лето» для отопительного сезона и тёплого времени года.',
                    'Автономная работа на твёрдом топливе без газовой магистрали.',
                ],
                'specs' => [['key' => 'Топливо', 'value' => 'Дрова']],
                'warranty' => '48 месяцев',
            ];
        }
        if (str_contains($name, 'бункер')) {
            return [
                'kind' => 'bunker',
                'short' => 'Бункер для хранения и равномерной подачи пеллет в автоматическую систему отопления.',
                'intro' => 'топливный бункер для запаса древесных пеллет рядом с котлом и их подачи к горелке через совместимый шнек.',
                'meta' => 'Бункер для пеллет, совместимый с оборудованием SAKOVICH.',
                'features' => [
                    'Увеличивает автономность пеллетной котельной между загрузками топлива.',
                    'Предназначен для сухого хранения древесных пеллет.',
                    'Подбирается по объёму, мощности котла и расположению оборудования в котельной.',
                ],
                'specs' => [['key' => 'Назначение', 'value' => 'Хранение пеллет']],
                'warranty' => '12 месяцев',
            ];
        }
        if (preg_match('/(?:kipi|rotary|\d+\s*[-–]\s*\d+\s*(?:kw|квт))/ui', $name.' '.($row['group'] ?? ''))) {
            return [
                'kind' => 'burner',
                'short' => 'Поворотная самоочищающаяся пеллетная горелка с подающим устройством и автоматикой PLUM ecoMAX 361.',
                'intro' => 'поворотная пеллетная горелка KIPI Rotary с автоматической очисткой жаровой трубы. Комплект предназначен для автоматизации совместимого отопительного котла.',
                'meta' => 'Самоочищающаяся пеллетная горелка KIPI Rotary с автоматикой PLUM.',
                'features' => [
                    'Поворотная камера сгорания автоматически удаляет золу и спёкшиеся остатки.',
                    'В комплект входят подающее устройство и автоматика PLUM ecoMAX 361.',
                    'Перед установкой необходимо проверить геометрию дверцы, глубину топки и требуемую мощность котла.',
                ],
                'specs' => [
                    ['key' => 'Топливо', 'value' => 'Древесные пеллеты'],
                    ['key' => 'Тип очистки', 'value' => 'Автоматическая, поворотная камера'],
                    ['key' => 'Автоматика', 'value' => 'PLUM ecoMAX 361'],
                ],
                'warranty' => '12 месяцев',
            ];
        }
        if (str_contains($name, 'вентилятор')) {
            return [
                'kind' => 'fan',
                'short' => 'Наддувный вентилятор для управляемой подачи воздуха в твердотопливный котёл.',
                'intro' => 'вентилятор наддува для подачи воздуха в камеру сгорания твердотопливного котла по команде совместимого контроллера.',
                'meta' => 'Вентилятор наддува для твердотопливного котла.',
                'features' => [
                    'Помогает контролировать интенсивность горения и температуру теплоносителя.',
                    'Работает совместно с подходящим контроллером котла.',
                    'Подбирается по производительности, мощности котла и присоединительным размерам.',
                ],
                'specs' => [['key' => 'Назначение', 'value' => 'Наддув воздуха в котёл']],
                'warranty' => '12 месяцев',
            ];
        }
        if (str_contains($name, 'контроллер')) {
            return [
                'kind' => 'controller',
                'short' => 'Контроллер для автоматического управления котлом и подключённым оборудованием.',
                'intro' => 'электронный контроллер для управления работой твердотопливного котла, вентилятора и указанных в комплектации насосов.',
                'meta' => 'Автоматика управления твердотопливным котлом.',
                'features' => [
                    'Автоматически поддерживает заданную температуру теплоносителя.',
                    'Управляет вентилятором и насосными контурами согласно указанной комплектации.',
                    'Совместимость с датчиками, приводами и Wi‑Fi определяется по конкретной модификации.',
                ],
                'specs' => [['key' => 'Тип', 'value' => 'Контроллер котла']],
                'warranty' => '12 месяцев',
            ];
        }

        return [
            'kind' => 'accessory',
            'short' => 'Комплектующая для котлов, горелок и систем автоматизации отопления.',
            'intro' => 'комплектующая для обслуживания, ремонта или расширения функций совместимого отопительного оборудования.',
            'meta' => 'Комплектующая для отопительного оборудования.',
            'features' => [
                'Назначение и совместимость определяются моделью оборудования.',
                'Оригинальная позиция из актуального прайса поставщика.',
                'Перед заказом менеджер сверит артикул и требуемую модификацию.',
            ],
            'specs' => [['key' => 'Тип', 'value' => 'Комплектующая']],
            'warranty' => '12 месяцев',
        ];
    }

    /** @return array<string,mixed> */
    private function boilerProfile(string $intro, string $fuel, array $features, ?string $warranty = null): array
    {
        return [
            'kind' => 'boiler',
            'short' => ucfirst($intro).' с ручной загрузкой топлива.',
            'intro' => $intro.'. Модель применяется как источник тепла в закрытых и открытых системах отопления при соблюдении требований производителя.',
            'meta' => ucfirst($intro).' с ручной загрузкой топлива.',
            'features' => array_merge($features, [
                'Работает автономно и подходит для объектов без газовой магистрали.',
                'Мощность необходимо выбирать по теплопотерям здания, а не только по площади.',
            ]),
            'specs' => [
                ['key' => 'Тип котла', 'value' => 'Твердотопливный'],
                ['key' => 'Топливо', 'value' => $fuel],
                ['key' => 'Загрузка топлива', 'value' => 'Ручная'],
            ],
            'warranty' => $warranty,
        ];
    }

    /** @return array<string,mixed> */
    private function pelletProfile(string $intro, array $features, ?string $warranty = null): array
    {
        return [
            'kind' => 'pellet_boiler',
            'short' => ucfirst($intro).'.',
            'intro' => $intro.'. Оборудование предназначено для водяной системы отопления и подбирается вместе с горелкой, автоматикой и бункером.',
            'meta' => ucfirst($intro).'.',
            'features' => array_merge($features, [
                'Автоматика управляет подачей топлива и поддержанием заданной температуры.',
                'Производительность горелки и объём бункера должны соответствовать мощности котла.',
            ]),
            'specs' => [
                ['key' => 'Тип котла', 'value' => 'Пеллетный'],
                ['key' => 'Основное топливо', 'value' => 'Древесные пеллеты'],
            ],
            'warranty' => $warranty,
        ];
    }

    /** @param array<string,mixed> $row @param array<string,mixed> $profile */
    private function baseSpecs(array $row, array $profile): array
    {
        $specs = [];
        if ($power = $this->power($row)) {
            $specs[] = ['key' => 'Номинальная мощность', 'value' => $power];
        }
        if ($row['variant'] && trim((string) $row['variant']) !== trim((string) $row['name'])) {
            $specs[] = ['key' => 'Исполнение', 'value' => trim((string) $row['variant'])];
        }

        return $specs;
    }

    /** @param array<int,array<string,mixed>> $current @param array<int,array<string,mixed>> $extra */
    private function mergeSpecs(array $current, array $extra): array
    {
        $merged = [];
        foreach (array_merge($current, $extra) as $spec) {
            if (! is_array($spec) || blank($spec['key'] ?? null) || blank($spec['value'] ?? null)) {
                continue;
            }
            $key = mb_strtolower(trim((string) $spec['key']));
            $merged[$key] = ['key' => trim((string) $spec['key']), 'value' => trim((string) $spec['value'])];
        }

        return array_values($merged);
    }

    /** @param array<string,mixed> $row */
    private function rowFeatures(array $row): array
    {
        $variant = trim((string) ($row['variant'] ?? ''));
        if ($variant === '' || $variant === trim((string) $row['name'])) {
            return [];
        }

        return ['Исполнение по прайсу производителя: '.rtrim($variant, '.').'.'];
    }

    private function selectionText(string $kind, ?string $power): string
    {
        return match ($kind) {
            'boiler', 'pellet_boiler' => 'Модель'.($power ? ' мощностью '.$power : '').' выбирают по расчётным теплопотерям здания, параметрам системы отопления, дымохода и доступному топливу. Менеджер поможет проверить конфигурацию котельной и подобрать совместимые комплектующие.',
            'stove' => 'При подборе учитывают объём и утепление помещения, режим эксплуатации, диаметр и высоту дымохода, а также безопасные расстояния до горючих конструкций.',
            'burner' => 'Диапазон мощности горелки должен перекрывать рабочую потребность котла. До заказа необходимо проверить размеры топки, посадочный фланец и возможность безопасного монтажа.',
            'bunker' => 'Бункер выбирают по требуемой автономности, свободному месту в котельной и совместимости со шнеком подачи топлива.',
            default => 'Для точного подбора сообщите менеджеру модель основного оборудования и его заводской артикул. Это позволит исключить ошибку совместимости.',
        };
    }

    /** @param array<string,mixed> $row */
    private function power(array $row): ?string
    {
        $power = trim((string) ($row['power'] ?? ''));
        if ($power === '' && preg_match('/(\d+(?:[.,]\d+)?\s*[-–]\s*\d+(?:[.,]\d+)?)\s*(?:kw|квт)/ui', (string) $row['name'], $match)) {
            $power = $match[1];
        }

        return $power !== '' ? str_replace('.', ',', $power).' кВт' : null;
    }

    /** @param array<string,mixed> $row @return array{url:?string,image:?string} */
    private function source(array $row): array
    {
        $identity = (string) $row['identity'];
        $power = (string) ($row['power'] ?? '');
        $base = 'https://domtepla.by';
        $url = null;
        $image = null;

        if (str_starts_with($identity, 'pramen_green_')) {
            $url = $base.'/kotly-i-chasti-kotlov/tverdotoplivnye_kotly/tverdotoplivnye-kotly-na-drovah/tverdotoplivnyj-kotel-pramen-green-'.str_replace('.', '-', $power);
            $image = $power === '12.5'
                ? $base.'/image/cache/catalog/2026/04-26/pramen/12%20%D0%BA%D0%92%D1%82%20%D1%84%D0%BE%D1%82%D0%BE/1%2012%20%D0%BA%D0%B2%D1%82-800x800.jpg'
                : $base.'/image/cache/catalog/2026/04-26/pramen/16-20%20%D0%BA%D0%92%D1%82%20%D1%84%D0%BE%D1%82%D0%BE/1%2020%20%D0%9A%D0%B2%D1%82-800x800.jpg';
        } elseif (str_starts_with($identity, 'standart_pro_')) {
            $url = $base.'/kotly-i-chasti-kotlov/tverdotoplivnye_kotly/tverdotoplivnye-kotly-na-drovah/kotel-standart-pro-'.$power.($power === '21' ? 'kvt' : '-kvt');
            $image = $power === '14'
                ? $base.'/image/cache/catalog/kotly/standartpro/standart-standart%20pro%2012%20%D0%BF%D1%80%D0%BE%D0%B7%D1%80%D0%B0%D1%87%D0%BD%D1%8B%D0%B9%20-800x800.png'
                : $base.'/image/cache/catalog/kotly/standartpro/standart-550x700.png-800x800.png';
        } elseif (str_starts_with($identity, 'standart_')) {
            $url = $base.'/kotly-i-chasti-kotlov/tverdotoplivnye_kotly/tverdotoplivnye-kotly-na-drovah/kotel-standart-'.$power.'-kvt';
            $image = $power === '12'
                ? $base.'/image/cache/catalog/kotly/standart/standart-800x800.jpg'
                : $base.'/image/cache/catalog/kotly/standartpro/standart-550x700.png-800x800.png';
        } elseif (str_starts_with($identity, 'max_') && $power !== '300') {
            $slug = in_array($power, ['99', '125'], true) ? 'kotel-sakovich-max-'.$power.'kvt' : 'tverdotoplivnyj-kotel-sakovich-max-'.$power.'kvt';
            $url = $base.'/kotly-i-chasti-kotlov/tverdotoplivnye_kotly/promyshlennye-vodogrejnye-kotly/'.$slug;
            $image = $base.'/image/cache/catalog/kotly/max/max-800x800.png';
        } elseif (str_starts_with($identity, 'pellet_universal_')) {
            $url = $base.'/kotly-i-chasti-kotlov/tverdotoplivnye_kotly/pelletnye_kotly/kotyol-pellet-universal-'.$power.'-kvt';
            $image = $base.'/image/cache/catalog/kotly/pelletuniversal/pelletuniversal-800x800.jpg';
        } elseif (str_starts_with($identity, 'pellet_max_')) {
            $slug = in_array($power, ['50', '99'], true) ? 'kotel-sakovich-pellet-max-'.$power.'kvt' : 'pelletnyj-kotel-sakovich-pellet-max-'.$power.'kvt';
            $url = $base.'/kotly-i-chasti-kotlov/tverdotoplivnye_kotly/pelletnye_kotly/'.$slug;
            $image = $base.'/image/cache/catalog/kotly/pelletmax50/pelletmax-800x800.jpg';
        } elseif (str_starts_with($identity, 'pellet_eco_')) {
            $url = $base.'/kotly-i-chasti-kotlov/tverdotoplivnye_kotly/pelletnye_kotly/pelletnyj-kotel-sakovich-eco-'.$power.'-kvt';
        } elseif (str_starts_with($identity, 'pellet_standart_praktik_')) {
            $suffix = $power === '10' ? '10kvt' : $power.'-kvt';
            $url = $base.'/kotly-i-chasti-kotlov/tverdotoplivnye_kotly/pelletnye_kotly/pelletnyj-kotel-sakovich-standart-pellet-'.$suffix;
            $image = $base.'/image/cache/catalog/2025/03-25/kotel-standart/stansdart-pellet-800x800.jpg';
        } elseif (str_starts_with($identity, 'promka_')) {
            preg_match('/promka_(\d+)/', $identity, $match);
            $url = $base.'/kaminy-i-pechi/otopitelnye-pechi/pech-sakovich-promka-'.($match[1] ?? $power).'-kvt';
            $image = $base.'/image/cache/catalog/kamin/pechi/promka/mainfoto_promka-800x800.jpg';
        } elseif (str_starts_with($identity, 'madera_')) {
            $url = $base.'/kotly-i-chasti-kotlov/tverdotoplivnye_kotly/tverdotoplivnye-kotly-na-drovah/kotel-madera-16-kvt';
            $image = $base.'/image/cache/catalog/kotly/madera/main_madera3-800x800.jpg';
        } elseif (str_contains(mb_strtolower((string) $row['name']), 'econet 300')) {
            $url = $base.'/gorelki/komplektuyushie_dlya_gorelok/internet-modul-econet300';
            $image = $base.'/image/cache/catalog/product/gorelki/komplektuyuschie/econet/econet1-800x800.jpg';
        } elseif (preg_match('/vsk-(120|140|160)/ui', (string) $row['name'], $match)) {
            $url = $base.'/avtomatika-i-ventilyatory/ventilyatory-dlya-tverdotoplivnyh-kotlov/ventilyator-nadduva-vsk-'.$match[1];
            $image = match ($match[1]) {
                '120' => $base.'/image/cache/catalog/kotly/aksesyary/vent/vsk120/vsk120-01-800x800.jpg',
                '140' => $base.'/image/cache/catalog/kotly/aksesyary/vent/wks160/vsk1203-800x800.jpg',
                default => $base.'/image/cache/catalog/kotly/aksesyary/vent/wks160/vsk160-01-800x800.jpg',
            };
        } elseif (str_contains(mb_strtolower((string) $row['name']), 'klimat pro max')) {
            $url = $base.'/avtomatika-i-ventilyatory/avtomatika-dlya-tverdotoplivnyh-kotlov/kontroller-klimat-pro-max-ventilyator-nasos';
            $image = $base.'/image/cache/catalog/2025/10-25/proMAx/1-800x800.jpg';
        }

        return compact('url', 'image');
    }
}
