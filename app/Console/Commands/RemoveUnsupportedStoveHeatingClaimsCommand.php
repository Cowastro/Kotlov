<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemoveUnsupportedStoveHeatingClaimsCommand extends Command
{
    protected $signature = 'catalog:remove-unsupported-stove-heating-claims
        {--apply : Persist the exact reviewed text replacements; the default is a dry run}';

    protected $description = 'Remove unsupported heating-area and room-volume claims from selected stove descriptions';

    /**
     * Exact reviewed replacements. They deliberately do not add a new area or
     * volume: the manufacturers do not publish one for these models.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const REPLACEMENTS = [
        'pech-otopitelnaya-meta-bel-yamal' => [
            'content' => [
                'помещениях площадью до 120 м³' => 'небольших помещениях',
            ],
        ],
        'pech-kamin-meta-bel-rona-aot-60' => [
            'content' => [
                'помещения до 60 м²' => 'жилые помещения',
            ],
        ],
        'pec-kamin-meta-bel-narva-7m' => [
            'content' => [
                'помещение площадью до 150–180 м³' => 'жилое помещение',
            ],
        ],
        'pec-kamin-meta-bel-moskva-9' => [
            'content' => [
                'помещения до 150-200 м³' => 'жилые помещения',
            ],
        ],
        'blist-pec-modena-seraia' => [
            'short_description' => [
                'для обогрева до 150 м³' => 'для отопления дома',
                'предназначена для обогрева помещений объёмом до 150 м³' => 'предназначена для отопления жилых помещений',
            ],
            'content' => [
                'обогреть помещение объемом до 150 м³' => 'обогреть жилое помещение',
                'обогрева помещений объёмом до 150 м³' => 'обогрева жилых помещений',
                'для обогрева до 150 м³' => 'для отопления дома',
            ],
        ],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $products = Product::query()
            ->whereIn('slug', array_keys(self::REPLACEMENTS))
            ->get()
            ->keyBy('slug');

        $missingProducts = array_values(array_diff(array_keys(self::REPLACEMENTS), $products->keys()->all()));
        $plans = collect();

        foreach (self::REPLACEMENTS as $slug => $fields) {
            /** @var Product|null $product */
            $product = $products->get($slug);
            if (! $product) {
                continue;
            }

            $updates = [];
            $replacementsMade = 0;

            foreach ($fields as $field => $replacements) {
                [$cleaned, $count] = $this->cleanField((string) ($product->{$field} ?? ''), $replacements);
                if ($count > 0) {
                    $updates[$field] = $cleaned;
                    $replacementsMade += $count;
                }
            }

            $plans->push(compact('product', 'updates', 'replacementsMade'));
        }

        $this->table(
            ['ID', 'Product', 'Exact claims removed', 'State'],
            $plans->map(fn (array $plan) => [
                $plan['product']->id,
                $plan['product']->name,
                $plan['replacementsMade'],
                $plan['replacementsMade'] > 0 ? 'will update' : 'already clean',
            ])->all(),
        );

        if ($missingProducts !== []) {
            foreach ($missingProducts as $slug) {
                $this->error("Missing product: {$slug}");
            }

            return self::FAILURE;
        }

        if ($apply) {
            DB::transaction(function () use ($plans): void {
                foreach ($plans as $plan) {
                    if ($plan['updates'] !== []) {
                        $plan['product']->update($plan['updates']);
                    }
                }
            });
        }

        $this->line('Mode: '.($apply ? 'APPLY' : 'DRY RUN'));
        $this->line(sprintf(
            'Reviewed products: %d; products changed: %d; exact claims removed: %d.',
            $plans->count(),
            $plans->where('replacementsMade', '>', 0)->count(),
            $plans->sum('replacementsMade'),
        ));
        $this->line('No heating area or room volume was inferred from stove power.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $replacements
     * @return array{0: string, 1: int}
     */
    private function cleanField(string $value, array $replacements): array
    {
        $count = 0;

        foreach ($replacements as $search => $replacement) {
            $value = str_replace($search, $replacement, $value, $fieldCount);
            $count += $fieldCount;
        }

        return [$value, $count];
    }
}
