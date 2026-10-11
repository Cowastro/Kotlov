<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LEGACY_SLUG = 'dymohody-nerzhaveyushchie';

    public function up(): void
    {
        $legacyId = DB::table('categories')->where('slug', self::LEGACY_SLUG)->value('id');

        if (! $legacyId) {
            return;
        }

        $targets = [
            'coaxial' => DB::table('categories')->where('slug', 'koaxial-dymoxod')->value('id'),
            'mono_pipes' => DB::table('categories')->where('slug', 'truby-mono')->value('id'),
            'gas_boilers' => DB::table('categories')->where('slug', 'gazovye')->value('id'),
            'other' => DB::table('categories')->where('slug', 'prochie-dymohod')->value('id'),
        ];

        DB::transaction(function () use ($legacyId, $targets): void {
            $this->moveProducts(
                $legacyId,
                $targets['coaxial'],
                'koaxial-dymoxod',
                fn ($query) => $query
                    ->whereIn('sku', ['KOTLOV-006110'])
                    ->orWhere('name', 'like', '%коаксиал%')
                    ->orWhere('name', 'like', '%Коаксиал%')
            );

            $this->moveProducts(
                $legacyId,
                $targets['mono_pipes'],
                'truby-mono',
                fn ($query) => $query
                    ->whereIn('sku', ['KOTLOV-006527'])
                    ->orWhere(fn ($inner) => $inner
                        ->where('name', 'like', '%Blist%')
                        ->where(fn ($name) => $name
                            ->where('name', 'like', '%Труб%')
                            ->orWhere('name', 'like', '%труб%')))
            );

            $this->moveProducts(
                $legacyId,
                $targets['gas_boilers'],
                'gazovye',
                fn ($query) => $query
                    ->where('name', 'like', '%Ariston CARES%')
                    ->orWhere('name', 'like', '%газов%кот%')
                    ->orWhere('name', 'like', '%Газов%кот%')
            );

            // No product may remain attached to the retired duplicate. This
            // fallback preserves visibility while keeping the tree coherent.
            $this->moveProducts(
                $legacyId,
                $targets['other'],
                'prochie-dymohod',
                fn ($query) => $query
            );

            DB::table('categories')->where('id', $legacyId)->update([
                'is_active' => 0,
                'updated_at' => now(),
            ]);

            DB::table('redirects')->updateOrInsert(
                ['from_url' => '/dymohody-nerzhaveyushchie'],
                [
                    'to_url' => '/dymohody',
                    'status_code' => 301,
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // This stale redirect hid the real, separate coaxial category.
            DB::table('redirects')->where('from_url', '/koaxial-dymoxod')->update([
                'is_active' => 0,
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('categories')->where('slug', self::LEGACY_SLUG)->update([
            'is_active' => 1,
            'updated_at' => now(),
        ]);

        DB::table('redirects')->where('from_url', '/dymohody-nerzhaveyushchie')->delete();

        DB::table('redirects')->where('from_url', '/koaxial-dymoxod')->update([
            'is_active' => 1,
            'updated_at' => now(),
        ]);
    }

    private function moveProducts(int $legacyId, ?int $targetId, string $targetSlug, callable $scope): void
    {
        if (! $targetId) {
            return;
        }

        $products = DB::table('products')
            ->where('category_id', $legacyId)
            ->where(fn ($query) => $scope($query))
            ->get(['id', 'slug']);

        foreach ($products as $product) {
            if ($product->slug) {
                DB::table('redirects')->updateOrInsert(
                    ['from_url' => '/'.self::LEGACY_SLUG.'/'.$product->slug],
                    [
                        'to_url' => '/'.$targetSlug.'/'.$product->slug,
                        'status_code' => 301,
                        'is_active' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        DB::table('products')->whereIn('id', $products->pluck('id'))->update([
            'category_id' => $targetId,
            'updated_at' => now(),
        ]);
    }
};
