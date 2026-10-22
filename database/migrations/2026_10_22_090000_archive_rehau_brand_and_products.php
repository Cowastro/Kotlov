<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const EXPLICIT_PATHS = [
        '/teplyj-pol/komplekt-dlya-ustanovki-napolnogo-otopleniya',
        '/teplyj-pol/termoregulyator-rehau-solelec-optima',
        '/teplyj-pol/yelektricheskij-greyushhij-mat-rehau-solelec-tm-v-128005x16m',
        '/teplyj-pol/yelektricheskij-greyushhij-mat-rehau-solelec-tm-v-16005x2m',
        '/teplyj-pol/yelektricheskij-greyushhij-mat-rehau-solelec-tm-v-16005x4m',
        '/truby-i-fitingi/d-20-16-20',
        '/truby-i-fitingi/montazhnye-gilzy-rautitan-px-d16',
        '/truby-i-fitingi/montazhnye-gilzy-rautitan-px-d20',
        '/truby-i-fitingi/mufta-soedinitelnaya-ravnoproxodnaya-d16',
        '/truby-i-fitingi/mufta-soedinitelnaya-ravnoproxodnaya-d20',
        '/truby-i-fitingi/otopitelnya-truba-dlya-teplogo-pola-rautherm-s-d-17x20buxta-120m',
        '/truby-i-fitingi/rautitan-d20',
        '/truby-i-fitingi/rautitan-pink',
        '/truby-i-fitingi/rautoll-m1',
        '/truby-i-fitingi/trojnik-ravnoproxodnyj-rautitan-d16',
        '/truby-i-fitingi/trojnik-s-umenshennym-bokovym-proxodom-rautitan-px-d20x16x20',
        '/truby-i-fitingi/trubka-g-obraznaya-dlya-podklyucheniya-radiatora-rautitan-d16250',
        '/truby-i-fitingi/trubka-g-obraznaya-dlya-podklyucheniya-radiatora-rautitan-d16500',
        '/truby-i-fitingi/trubka-g-obraznaya-dlya-podklyucheniya-radiatora-rautitan-d20250',
        '/truby-i-fitingi/ugolnik-rautitan-px-90-d16',
        '/truby-i-fitingi/ugolnik-rautitan-px-90-d20',
        '/truby-i-fitingi/blok-sharovyx-kranov-pryamoj-rehau-g12-g34',
        '/truby-i-fitingi/komplekt-dvux-rezbozazhimnyx-soedinenij-dlya-metaltrubok-g-34-15',
        '/truby-i-fitingi/kryuki-s-dyubelem',
        '/truby-i-fitingi/rautitan-pink-d16',
        '/truby-i-fitingi/rautitan-px-perexodnaya-d20-16',
        '/truby-i-fitingi/rautitan-stabil-d162',
        '/truby-i-fitingi/rezbozazhimnoe-soedinenie-16xg34',
        '/truby-i-fitingi/rezbozazhimnoe-soedinenie-20xg34',
        '/truby-i-fitingi/trubka-g-obraznaya-dlya-podklyucheniya-radiatora-rautitan-d20500',
        '/truby-i-fitingi/rautitan-flex-d16',
        '/truby-i-fitingi/rautitan-flex-d20',
        '/truby-i-fitingi/rautitan-flex-d25',
        '/truby-i-fitingi/rautitan-px-d25',
        '/truby-i-fitingi/rautitan-stabil-d20',
        '/truby-i-fitingi/rautitan-stabil-d25',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $brands = DB::table('brands')
                ->whereRaw("LOWER(TRIM(name)) = 'rehau'")
                ->orWhereRaw("LOWER(TRIM(slug)) = 'rehau'")
                ->get(['id', 'slug']);

            $explicitSlugs = collect(self::EXPLICIT_PATHS)
                ->map(fn (string $path): string => basename($path))
                ->all();

            $productQuery = DB::table('products')->where(function ($query) use ($brands, $explicitSlugs): void {
                if ($brands->isNotEmpty()) {
                    $query->whereIn('brand_id', $brands->pluck('id'));
                }

                $method = $brands->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                $query->{$method}('slug', $explicitSlugs)
                    ->orWhere(function ($names): void {
                        foreach (['rehau', 'rautitan', 'rautherm', 'rautoll', 'solelec'] as $term) {
                            $names->orWhereRaw('LOWER(name) LIKE ?', ['%'.$term.'%'])
                                ->orWhereRaw('LOWER(slug) LIKE ?', ['%'.$term.'%']);
                        }
                    });
            });

            $products = (clone $productQuery)->get(['id', 'category_id', 'slug']);

            if (Schema::hasTable('redirects')) {
                foreach (self::EXPLICIT_PATHS as $path) {
                    $this->redirect($path, str_starts_with($path, '/teplyj-pol/') ? '/teplyj-pol' : '/truby-i-fitingi', $now);
                }

                $categorySlugs = DB::table('categories')
                    ->whereIn('id', $products->pluck('category_id')->filter()->unique())
                    ->pluck('slug', 'id');

                foreach ($products as $product) {
                    $categorySlug = (string) ($categorySlugs[$product->category_id] ?? '');
                    if ($categorySlug === '') {
                        continue;
                    }

                    $target = in_array($categorySlug, ['teplyj-pol', 'truby-i-fitingi'], true)
                        ? '/'.$categorySlug
                        : '/catalog';
                    $this->redirect('/'.$categorySlug.'/'.$product->slug, $target, $now);
                }

                foreach ($brands as $brand) {
                    $this->redirect('/brands/'.$brand->slug, '/truby-i-fitingi', $now);
                }
                $this->redirect('/brands/rehau', '/truby-i-fitingi', $now);
            }

            if ($products->isNotEmpty()) {
                DB::table('products')->whereIn('id', $products->pluck('id'))->update([
                    'is_active' => false,
                    'is_archived' => true,
                    'is_featured' => false,
                    'is_new' => false,
                    'is_sale' => false,
                    'in_stock' => false,
                    'stock_qty' => 0,
                    'availability_status' => 'out_of_stock',
                    'updated_at' => $now,
                ]);
            }

            if ($brands->isNotEmpty()) {
                DB::table('brands')->whereIn('id', $brands->pluck('id'))->update([
                    'is_active' => false,
                    'is_featured' => false,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Legal/compliance removals are intentionally not republished by rollback.
    }

    private function redirect(string $from, string $to, mixed $now): void
    {
        DB::table('redirects')->updateOrInsert(
            ['from_url' => $from],
            [
                'to_url' => $to,
                'status_code' => 301,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }
};
