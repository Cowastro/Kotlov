<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Brand;
use App\Models\Product;
use App\Services\ChimneyBundlePromotion;
use Illuminate\Http\Request;

class ChimneySelectorController extends Controller
{
    public function __invoke(Request $request, ChimneyBundlePromotion $promotion)
    {
        $categoryIds = Category::query()
            ->whereIn('slug', (array) config('shop.chimney_bundle_discount.chimney_category_slugs', []))
            ->pluck('id');
        $preferredBrandSlug = (string) config('shop.chimney_bundle_discount.preferred_brand_slug', 'teplov-i-suhov');
        $preferredBrandId = Brand::query()->where('slug', $preferredBrandSlug)->value('id');

        $catalogQuery = Product::query()
            ->whereIn('category_id', $categoryIds)
            ->orderable()
            ->withSupplierChannels()
            ->with(['category', 'brand']);

        $products = (clone $catalogQuery)
            ->when($preferredBrandId, fn ($query) => $query->orderByRaw(
                'CASE WHEN brand_id = ? THEN 0 ELSE 1 END',
                [$preferredBrandId]
            ))
            ->orderByDesc('in_stock')
            ->orderBy('sort_order')
            ->limit(700)
            ->get();

        // Keep the common half- and one-metre pipe sections in the selector even
        // when they fall outside the general catalogue limit.
        $pipeSections = (clone $catalogQuery)
            ->when($preferredBrandId, fn ($query) => $query->where('brand_id', $preferredBrandId))
            ->where(function ($query) {
                $query->where('name', 'like', '%L500%')
                    ->orWhere('name', 'like', '%L1000%');
            })
            ->limit(300)
            ->get();
        $products = $products->merge($pipeSections)->unique('id')->values();

        $catalog = $products->map(function (Product $product): array {
            $text = $product->name.' '.json_encode((array) $product->specs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $diameter = $this->diameterFromText($text);
            preg_match('/(?:D|Ø)?\s*(80|100|110|115|120|130|140|150|160|180|200|250|300)\s*[\/]\s*(120|130|150|160|180|200|210|220|230|250|260|280|300|310|350)/iu', $text, $diameterPair);
            preg_match('/(?:AISI\s*)?(304|316|321|430)/iu', $text, $steel);
            preg_match('/(?:304|316|321|430)\s*[-,]\s*(0[.,][5-9]|1[.,]0)/iu', $text, $thickness);
            preg_match('/\bL\s*(250|500|750|1000)\b/iu', $text, $length);
            $slug = (string) $product->category?->slug;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'image' => $product->image_url,
                'url' => '/'.$slug.'/'.$product->slug,
                'diameter' => $diameter,
                'outer_diameter' => $diameterPair[2] ?? null,
                'diameter_pair' => isset($diameterPair[1], $diameterPair[2]) ? [$diameterPair[1], $diameterPair[2]] : null,
                'steel' => $steel[1] ?? null,
                'thickness' => isset($thickness[1]) ? str_replace(',', '.', $thickness[1]) : null,
                'length_mm' => isset($length[1]) ? (int) $length[1] : null,
                'system' => str_contains($slug, 'sendvich')
                    || str_contains(mb_strtolower($text), 'сэндвич')
                    || str_contains(mb_strtolower($text), 'термо')
                    || (bool) preg_match('/D?\s*\d{2,3}\s*[\/]\s*\d{2,3}/iu', $text)
                        ? 'sandwich'
                        : 'mono',
                'kind' => $this->kind($slug, $product->name),
                'preferred' => $product->brand?->slug === config('shop.chimney_bundle_discount.preferred_brand_slug'),
            ];
        })->values();

        $equipment = null;
        if ($request->integer('equipment')) {
            $equipment = Product::query()->with('category')->find($request->integer('equipment'));
        }
        if ($equipment && ! $promotion->productIsEligibleEquipment($equipment)) {
            $equipment = null;
        }
        $defaultDiameter = $equipment ? $this->diameter($equipment) : null;
        $cartPromotion = $promotion->quote(session('cart', []));
        $discountUnlocked = $equipment !== null || $cartPromotion['equipment_product_ids'] !== [];
        $equipmentInCart = $equipment !== null && isset(session('cart', [])[$equipment->id]);

        return view('pages.chimney-selector', [
            'catalog' => $catalog,
            'equipment' => $equipment,
            'defaultDiameter' => $defaultDiameter,
            'discountPercent' => (float) config('shop.chimney_bundle_discount.percent', 10),
            'discountUnlocked' => $discountUnlocked,
            'equipmentInCart' => $equipmentInCart,
            'preferredBrandName' => 'Теплов и Сухов',
        ]);
    }

    private function kind(string $slug, string $name): string
    {
        $normalizedName = mb_strtolower($name);

        return match (true) {
            str_contains($slug, 'shibery') || str_contains($normalizedName, 'шибер') => 'damper',
            str_contains($slug, 'zonty') || str_contains($normalizedName, 'зонт') || str_contains($normalizedName, 'дефлектор') => 'umbrella',
            str_contains($normalizedName, 'ппу') || str_contains($normalizedName, 'проход через') || str_contains($normalizedName, 'проходной узел') => 'ceiling_pass',
            str_contains($normalizedName, 'хомут') => 'clamp',
            str_contains($normalizedName, 'моно/термо') || str_contains($normalizedName, 'моно термо') || str_contains($normalizedName, 'пмт') => 'transition',
            str_contains($slug, 'truby') => 'pipe',
            str_contains($slug, 'troyniki') => 'tee',
            str_contains($slug, 'kolena') => 'elbow',
            str_contains($slug, 'kondensat') => 'condensate',
            str_contains($slug, 'krepleniya') => 'mount',
            str_contains($slug, 'perehody') => 'adapter',
            str_contains($normalizedName, 'труба') => 'pipe',
            str_contains($normalizedName, 'тройник') => 'tee',
            str_contains($normalizedName, 'колен') || str_contains($normalizedName, 'отвод') => 'elbow',
            default => 'other',
        };
    }

    private function diameter(Product $product): ?string
    {
        $text = $product->name.' '.json_encode((array) $product->specs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->diameterFromText($text);
    }

    private function diameterFromText(string $text): ?string
    {
        if (preg_match('/(?:D|Ø|ф)\s*(80|100|110|115|120|130|140|150|160|180|200|250|300)\b/iu', $text, $match)) {
            return $match[1];
        }
        if (preg_match('/диаметр[^0-9]{0,20}(80|100|110|115|120|130|140|150|160|180|200|250|300)\b/iu', $text, $match)) {
            return $match[1];
        }

        return null;
    }
}
