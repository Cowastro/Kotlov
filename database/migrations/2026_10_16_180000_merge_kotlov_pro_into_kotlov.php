<?php

use App\Models\Brand;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            // Exact aliases only; KOTLOV GE and unrelated names are out of scope.
            $brands = DB::table('brands')->get()->filter(fn ($brand) => Brand::isKotlovAlias($brand->name) || Brand::isKotlovAlias($brand->slug));
            if ($brands->isEmpty()) {
                return;
            }
            $canonical = $brands->firstWhere('slug', 'kotlov')
                ?? $brands->first(fn ($brand) => mb_strtoupper(trim($brand->name)) === 'KOTLOV')
                ?? $brands->first();
            $duplicateIds = $brands->where('id', '!=', $canonical->id)->pluck('id')->all();
            $products = DB::table('products')->whereIn('brand_id', $brands->pluck('id'))->get(['id', 'brand_id', 'specs']);
            $backupPath = 'private/brand-merges/kotlov-2026-10-16.json';
            if (($duplicateIds || $canonical->slug !== 'kotlov') && ! Storage::disk('local')->exists($backupPath)) {
                $saved = Storage::disk('local')->put($backupPath, json_encode([
                    'brands' => $brands->values(), 'products' => $products,
                    'supplier_links' => DB::table('brand_supplier')->whereIn('brand_id', $brands->pluck('id'))->get(),
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                if (! $saved) {
                    throw new RuntimeException('Cannot back up KOTLOV brand merge.');
                }
            }

            foreach ($brands as $brand) {
                if ($brand->slug !== 'kotlov') {
                    DB::table('redirects')->updateOrInsert(['from_url' => '/brands/'.$brand->slug], [
                        'to_url' => '/brands/kotlov', 'status_code' => 301, 'is_active' => true,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                foreach (DB::table('brand_supplier')->where('brand_id', $brand->id)->get() as $link) {
                    DB::table('brand_supplier')->insertOrIgnore([
                        'brand_id' => $canonical->id, 'supplier_id' => $link->supplier_id,
                        'created_at' => $link->created_at, 'updated_at' => $link->updated_at,
                    ]);
                }
            }
            // Update only the foreign key, keeping every commercial and integration field intact.
            DB::table('products')->whereIn('brand_id', $duplicateIds)->update(['brand_id' => $canonical->id]);
            foreach ($products as $product) {
                $specs = json_decode((string) $product->specs, true);
                if (! is_array($specs)) {
                    continue;
                }
                $before = $specs;
                foreach ($specs as $key => &$spec) {
                    if (is_array($spec)) {
                        $label = mb_strtolower(trim((string) ($spec['key'] ?? $spec['name'] ?? '')));
                        if (in_array($label, ['бренд', 'бренд комплекта', 'brand'], true) && Brand::isKotlovAlias((string) ($spec['value'] ?? ''))) {
                            $spec['value'] = 'KOTLOV';
                        }
                    } elseif (in_array(mb_strtolower((string) $key), ['бренд', 'бренд комплекта', 'brand'], true) && Brand::isKotlovAlias((string) $spec)) {
                        $spec = 'KOTLOV';
                    }
                }
                unset($spec);
                if ($specs !== $before) {
                    DB::table('products')->where('id', $product->id)->update(['specs' => json_encode($specs, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
                }
            }
            DB::table('brand_supplier')->whereIn('brand_id', $duplicateIds)->delete();
            DB::table('brands')->whereIn('id', $duplicateIds)->delete();
            DB::table('brands')->where('id', $canonical->id)->update([
                'name' => 'KOTLOV', 'slug' => 'kotlov', 'h1' => 'Отопительное оборудование KOTLOV',
                'is_active' => true, 'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // Keep the user-confirmed unified brand. The private backup permits manual recovery.
    }
};
