<?php

namespace App\Console\Commands;

use App\Support\Catalog\TskPumpClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OrganizePumpAccessoriesCommand extends Command
{
    protected $signature = 'catalog:organize-pump-accessories
        {--apply : Create the category and move confidently identified accessories}';

    protected $description = 'Move pump accessories out of the borehole-pump category.';

    private const SOURCE_SLUG = 'skvajinnye-nasosy';
    private const TARGET_SLUG = 'komplektuyushchie-dlya-nasosov';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $source = DB::table('categories')->where('slug', self::SOURCE_SLUG)->first();
        $parent = DB::table('categories')->where('slug', 'vodosnabzhenie')->first();
        $target = DB::table('categories')->where('slug', self::TARGET_SLUG)->first();

        if (! $source || ! $parent) {
            $this->error('Required source or parent category was not found.');
            return self::FAILURE;
        }

        $rows = DB::table('products')
            ->where('category_id', $source->id)
            ->where('is_archived', false)
            ->orderBy('id')
            ->get(['id', 'name', 'slug']);

        $candidates = $rows->filter(fn ($product) =>
            TskPumpClassifier::categorySlug((string) $product->name) === self::TARGET_SLUG
        )->values();

        $stats = [
            'inspected' => $rows->count(),
            'candidates' => $candidates->count(),
            'category_exists' => $target ? 1 : 0,
            'category_created' => 0,
            'moved' => 0,
            'parent_meta_updated' => 0,
            'errors' => 0,
        ];

        foreach ($candidates as $product) {
            $this->line(sprintf(
                'audit.pump_accessories.product=%d:%s',
                (int) $product->id,
                preg_replace('/[^a-z0-9._-]/', '', (string) $product->slug),
            ));
        }

        if ($apply) {
            DB::transaction(function () use ($parent, &$target, $candidates, &$stats): void {
                DB::table('categories')->where('id', $parent->id)->update([
                    'name' => 'Водоснабжение',
                    'h1' => 'Насосы и оборудование для водоснабжения',
                    'meta_title' => 'Насосы и оборудование для водоснабжения купить в Минске | KOTLOV.BY',
                    'meta_description' => 'Насосы, насосные станции и комплектующие для систем водоснабжения. Подбор, заказ и доставка по Беларуси.',
                    'updated_at' => now(),
                ]);
                $stats['parent_meta_updated'] = 1;

                if (! $target) {
                    $targetId = DB::table('categories')->insertGetId([
                        'parent_id' => $parent->id,
                        'name' => 'Комплектующие для насосов',
                        'slug' => self::TARGET_SLUG,
                        'h1' => 'Комплектующие для насосов',
                        'content' => '<p>Автоматика, защита, контрольно-измерительные приборы, рукава, соединения и монтажные принадлежности для насосных систем.</p>',
                        'meta_title' => 'Комплектующие для насосов купить в Минске | KOTLOV.BY',
                        'meta_description' => 'Комплектующие для насосов: блоки управления, реле, манометры, рукава, муфты и монтажные принадлежности. Доставка по Беларуси.',
                        'sort_order' => 70,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $target = (object) ['id' => $targetId];
                    $stats['category_created'] = 1;
                }

                if ($candidates->isNotEmpty()) {
                    $stats['moved'] = DB::table('products')
                        ->whereIn('id', $candidates->pluck('id')->all())
                        ->update(['category_id' => $target->id, 'updated_at' => now()]);
                }
            });
        }

        foreach ($stats as $name => $value) {
            $this->line("metric.pump_accessories.{$name}={$value}");
        }

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
