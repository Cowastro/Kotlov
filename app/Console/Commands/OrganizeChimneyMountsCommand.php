<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Split the overloaded «Крепления и монтаж» category into customer-oriented groups. */
class OrganizeChimneyMountsCommand extends Command
{
    protected $signature = 'catalog:organize-chimney-mounts {--apply : Create subcategories and move products}';

    protected $description = 'Create useful chimney mounting subcategories and move every direct product by its purpose';

    private const ROOT = 'krepleniya-dymohod';

    /** @var array<string,array{name:string,pattern:string,sort:int,description:string}> */
    private const CHILDREN = [
        'montazhnye-ploshchadki-dymohoda' => [
            'name' => 'Монтажные площадки',
            'pattern' => '/площадк.*монтаж|монтажн.*площадк|площадк.*сквоз/iu',
            'sort' => 10,
            'description' => 'Монтажные и сквозные площадки для опоры и фиксации элементов дымохода.',
        ],
        'homuty-i-rastyazhki-dymohoda' => [
            'name' => 'Хомуты и растяжки',
            'pattern' => '/хомут|растяжк/iu',
            'sort' => 20,
            'description' => 'Трубные, стяжные и стеновые хомуты, растяжки и комплектующие к ним.',
        ],
        'krepleniya-podvesy-i-shtangi-dymohoda' => [
            'name' => 'Крепления, подвесы и штанги',
            'pattern' => '/креплен|подвес|штанг|кронштейн|консоль|опора/iu',
            'sort' => 30,
            'description' => 'Настенные и универсальные крепления, подвесы, консоли и регулируемые штанги.',
        ],
        'prohody-perekrytiy-i-potolochnye-razdelki' => [
            'name' => 'Проходы перекрытий и потолочные разделки',
            'pattern' => '/проход перекрытия|^ппу(?:\s|\b)|лист потолоч|разделител[ьи] потол|потолочн.*проход|короб потолоч/iu',
            'sort' => 40,
            'description' => 'Потолочно-проходные узлы, разделки и листы для безопасного прохода дымохода через перекрытие.',
        ],
        'krovelnye-prohodki-master-flash' => [
            'name' => 'Кровельные проходки Master Flash',
            'pattern' => '/мастер.?флеш|master\s*flash|кровельн.*(?:уплот|проход)|проходник кровли|лента для примыкания/iu',
            'sort' => 50,
            'description' => 'Эластичные кровельные проходки и материалы примыкания для герметизации выхода дымохода.',
        ],
        'rozety-i-dekorativnye-elementy-dymohoda' => [
            'name' => 'Розеты и декоративные элементы',
            'pattern' => '/розет|кольцо декоратив|декоративн.*кольц|фартук|накладка декоратив/iu',
            'sort' => 60,
            'description' => 'Розеты, декоративные кольца, накладки и фартуки для аккуратного оформления прохода трубы.',
        ],
        'teploizolyatsiya-i-zashchitnye-listy-dymohoda' => [
            'name' => 'Теплоизоляция и защитные листы',
            'pattern' => '/теплоизоля|базальт|лист предтоп|экран защит|огнезащ|фиброцемент/iu',
            'sort' => 70,
            'description' => 'Теплоизоляционные комплекты, базальтовые материалы и защитные листы для монтажа дымохода.',
        ],
        'uplotneniya-vtulki-i-flantsy-dymohoda' => [
            'name' => 'Уплотнения, втулки и фланцы',
            'pattern' => '/уплотнен|втулк|флан|прокладк/iu',
            'sort' => 80,
            'description' => 'Силиконовые уплотнения, втулки, фланцы и прокладки для соединений дымохода.',
        ],
    ];

    /** Products that belong to an existing sibling category, not to mounting. */
    private const SIBLINGS = [
        'teplosyomniki' => '/радиатор\s+darco/iu',
        'perehody-adaptery-dymohod' => '/адаптер/iu',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $root = Category::query()->where('slug', self::ROOT)->first();
        if (! $root) {
            $this->line('audit.chimney_mounts.missing_root=1');
            return self::FAILURE;
        }

        $products = Product::query()->where('category_id', $root->id)->where('is_archived', false)
            ->orderBy('id')->get(['id', 'name', 'slug', 'category_id']);
        $assignments = [];
        $counts = array_fill_keys(array_merge(array_keys(self::CHILDREN), array_keys(self::SIBLINGS)), 0);
        $unmatched = [];

        foreach ($products as $product) {
            $target = $this->targetFor((string) $product->name);
            if (! $target) {
                $unmatched[] = $product;
                $this->line("audit.chimney_mounts.unmatched={$product->id}");
                continue;
            }
            $assignments[] = [$product, $target];
            $counts[$target]++;
        }

        $missingSiblings = collect(array_keys(self::SIBLINGS))
            ->reject(fn (string $slug) => Category::query()->where('slug', $slug)->exists())->values();

        $this->line('metric.chimney_mounts.direct='.$products->count());
        $this->line('metric.chimney_mounts.classified='.count($assignments));
        $this->line('metric.chimney_mounts.unmatched='.count($unmatched));
        foreach ($counts as $slug => $count) {
            $this->line('group.chimney_mounts.'.str_replace('-', '_', $slug).'='.$count);
        }
        foreach ($missingSiblings as $slug) {
            $this->line("audit.chimney_mounts.missing_target={$slug}");
        }

        if ($unmatched !== [] || $missingSiblings->isNotEmpty()) {
            $this->error('Распределение остановлено: есть нераспознанные товары или отсутствующие целевые категории.');
            return self::FAILURE;
        }
        if (! $apply) {
            $this->warn('Предпросмотр: база не изменена. Для записи добавьте --apply.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($root, $assignments): void {
            $targets = [];
            foreach (self::CHILDREN as $slug => $definition) {
                $targets[$slug] = Category::query()->updateOrCreate(
                    ['slug' => $slug],
                    [
                        'parent_id' => $root->id,
                        'name' => $definition['name'],
                        'h1' => $definition['name'],
                        'sort_order' => $definition['sort'],
                        'is_active' => true,
                        'image' => 'img/popular/chimney.jpg',
                        'content' => '<p>'.$definition['description'].'</p>',
                        'meta_title' => $definition['name'].' — купить в %city% | KOTLOV',
                        'meta_description' => $definition['description'].' Цены, наличие и доставка по Беларуси.',
                    ],
                );
            }
            foreach (array_keys(self::SIBLINGS) as $slug) {
                $targets[$slug] = Category::query()->where('slug', $slug)->firstOrFail();
            }

            foreach ($assignments as [$product, $targetSlug]) {
                $oldPath = '/'.self::ROOT.'/'.$product->slug;
                $newPath = '/'.$targetSlug.'/'.$product->slug;
                $product->forceFill(['category_id' => $targets[$targetSlug]->id])->save();
                DB::table('redirects')->updateOrInsert(
                    ['from_url' => $oldPath],
                    ['to_url' => $newPath, 'status_code' => 301, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        });

        $this->line('metric.chimney_mounts.moved='.count($assignments));
        return self::SUCCESS;
    }

    private function targetFor(string $name): ?string
    {
        foreach (self::SIBLINGS as $slug => $pattern) {
            if (preg_match($pattern, $name)) {
                return $slug;
            }
        }
        // Ceiling passes must be checked before general mounting words such as
        // «лист» or «комплект», while platforms precede generic «крепление».
        $priority = [
            'krovelnye-prohodki-master-flash',
            'prohody-perekrytiy-i-potolochnye-razdelki',
            'montazhnye-ploshchadki-dymohoda',
            'homuty-i-rastyazhki-dymohoda',
            'krepleniya-podvesy-i-shtangi-dymohoda',
            'rozety-i-dekorativnye-elementy-dymohoda',
            'teploizolyatsiya-i-zashchitnye-listy-dymohoda',
            'uplotneniya-vtulki-i-flantsy-dymohoda',
        ];
        foreach ($priority as $slug) {
            if (preg_match(self::CHILDREN[$slug]['pattern'], $name)) {
                return $slug;
            }
        }
        return null;
    }
}
