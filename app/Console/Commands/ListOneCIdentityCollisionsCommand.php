<?php

namespace App\Console\Commands;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Services\Integrations\IntegrationIdentityCollisionFinder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * The «Возможные дубли» tab of integration issues as safe lines: 1C rows that carry the same article or
 * barcode under different 1C ids — with their code, name, link status, stock, price and site card.
 */
class ListOneCIdentityCollisionsCommand extends Command
{
    protected $signature = 'integration:identity-collisions {--source=onec : Integration source code}';

    protected $description = 'List 1C rows that share an article or barcode under different 1C ids';

    public function handle(IntegrationIdentityCollisionFinder $finder): int
    {
        $source = IntegrationSource::query()->where('code', $this->option('source'))->first();
        if (! $source) {
            $this->line('metric.onec_collisions.source=0');

            return self::FAILURE;
        }

        $groups = $finder->find($source->id);
        $rows = IntegrationProduct::query()->whereIn('id', collect($groups)->flatMap(fn (array $g) => array_column($g['products'], 'id')))
            ->get()->keyBy('id');
        $stats = ['groups' => count($groups), 'rows' => 0, 'with_characteristic' => 0, 'linked_same_card' => 0, 'linked_different_cards' => 0, 'stocked_rows' => 0];

        foreach ($groups as $index => $group) {
            $products = collect($group['products'])->map(fn (array $p) => $rows->get($p['id']))->filter();
            $cards = $products->pluck('product_id')->filter()->unique();
            $characteristic = $products->contains(fn (IntegrationProduct $p): bool => str_contains((string) $p->external_id, '#'));
            $stats['with_characteristic'] += (int) $characteristic;
            $stats['linked_same_card'] += (int) ($cards->count() === 1 && $products->every(fn ($p) => $p->product_id));
            $stats['linked_different_cards'] += (int) ($cards->count() > 1);
            $identity = collect($group['identities'])->map(fn (array $i): string => $i['kind'].'~'.(Str::slug($i['value']) ?: 'none'))->implode(',');
            $this->line('audit.onec_collisions.group='.($index + 1).':'.$identity.':rows='.$products->count().':cards='.$cards->count().':char='.(int) $characteristic);

            foreach ($products as $product) {
                $stats['rows']++;
                $stats['stocked_rows'] += (int) ((float) $product->stock_quantity > 0);
                $this->line(sprintf('audit.onec_collisions.row=%d:%d:%s:%s:%s:%d:%d:%d',
                    $index + 1, $product->id, Str::slug((string) $product->external_code) ?: 'none',
                    Str::slug(Str::limit((string) $product->name, 70, '')) ?: 'none', $product->match_status ?: 'none',
                    (int) round((float) $product->stock_quantity), (int) round((float) $product->normalizedPriceByn()), (int) $product->product_id));
            }
        }

        foreach ($stats as $key => $value) {
            $this->line("metric.onec_collisions.{$key}={$value}");
        }

        return self::SUCCESS;
    }
}
