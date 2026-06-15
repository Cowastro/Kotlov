<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $tmfId = $this->ensureBrand('TMF', 'tmf', $now);
        $etnaId = $this->ensureBrand('ЭТНА', 'etna', $now);
        $astonId = $this->ensureBrand('ASTON', 'aston', $now);
        $everestId = $this->ensureBrand('Эверест', 'everest', $now);

        $this->mergeBrandInto(['termofor'], $tmfId, '/brands/tmf', $now);
        $this->mergeBrandInto(['yetna'], $etnaId, '/brands/etna', $now);

        DB::table('products')
            ->where('is_archived', false)
            ->where('name', 'like', '%ASTON%')
            ->update([
                'brand_id' => $astonId,
                'updated_at' => $now,
            ]);

        DB::table('products')
            ->where('is_archived', false)
            ->where(function ($query) {
                $query->where('name', 'like', '%Эверест%')
                    ->orWhere('name', 'like', '%EVEREST%')
                    ->orWhere('name', 'like', '%Everest%');
            })
            ->update([
                'brand_id' => $everestId,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        // Data normalization is intentionally not reversed automatically.
    }

    private function ensureBrand(string $name, string $slug, $now): int
    {
        $brand = DB::table('brands')->where('slug', $slug)->first();
        if ($brand) {
            DB::table('brands')->where('id', $brand->id)->update([
                'name' => $name,
                'h1' => $brand->h1 ?: $name,
                'is_active' => true,
                'updated_at' => $now,
            ]);

            return (int) $brand->id;
        }

        $brand = DB::table('brands')->where('name', $name)->first();
        if ($brand) {
            DB::table('brands')->where('id', $brand->id)->update([
                'slug' => $slug,
                'h1' => $brand->h1 ?: $name,
                'is_active' => true,
                'updated_at' => $now,
            ]);

            return (int) $brand->id;
        }

        return (int) DB::table('brands')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'h1' => $name,
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function mergeBrandInto(array $oldSlugs, int $targetBrandId, string $targetUrl, $now): void
    {
        foreach ($oldSlugs as $oldSlug) {
            $oldBrand = DB::table('brands')->where('slug', $oldSlug)->first();
            if (! $oldBrand || (int) $oldBrand->id === $targetBrandId) {
                continue;
            }

            DB::table('products')
                ->where('brand_id', $oldBrand->id)
                ->update([
                    'brand_id' => $targetBrandId,
                    'updated_at' => $now,
                ]);

            DB::table('brands')->where('id', $oldBrand->id)->update([
                'is_active' => false,
                'updated_at' => $now,
            ]);

            DB::table('redirects')->updateOrInsert(
                ['from_url' => '/brands/' . Str::lower($oldSlug)],
                [
                    'to_url' => $targetUrl,
                    'status_code' => 301,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
};
