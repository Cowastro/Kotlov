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

        $products = Product::query()
            ->whereIn('category_id', $categoryIds)
            ->orderable()
            ->withSupplierChannels()
            ->with(['category', 'brand'])
            ->when($preferredBrandId, fn ($query) => $query->orderByRaw(
                'CASE WHEN brand_id = ? THEN 0 ELSE 1 END',
                [$preferredBrandId]
            ))
            ->orderByDesc('in_stock')
            ->orderBy('sort_order')
            ->limit(400)
            ->get();

        $catalog = $products->map(function (Product $product): array {
            $text = $product->name.' '.collect((array) $product->specs)->flatten()->implode(' ');
            preg_match('/(?:Ø|диаметр[^0-9]{0,12})?(80|100|110|115|120|130|140|150|160|180|200|250|300)\s*(?:мм)?/iu', $text, $diameter);
            preg_match('/(?:AISI\s*)?(304|316|321|430)/iu', $text, $steel);
            $slug = (string) $product->category?->slug;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'image' => $product->image_url,
                'url' => '/'.$slug.'/'.$product->slug,
                'diameter' => $diameter[1] ?? null,
                'steel' => $steel[1] ?? null,
                'system' => str_contains($slug, 'sendvich') || str_contains(mb_strtolower($text), 'сэндвич') ? 'sandwich' : 'mono',
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
        return match (true) {
            str_contains($slug, 'truby') => 'pipe',
            str_contains($slug, 'troyniki') => 'tee',
            str_contains($slug, 'kolena') => 'elbow',
            str_contains($slug, 'kondensat') => 'condensate',
            str_contains($slug, 'krepleniya') => 'mount',
            str_contains($slug, 'zonty') || str_contains($slug, 'zaglushki') => 'cap',
            str_contains($slug, 'perehody') => 'adapter',
            str_contains(mb_strtolower($name), 'труба') => 'pipe',
            str_contains(mb_strtolower($name), 'тройник') => 'tee',
            str_contains(mb_strtolower($name), 'колен') || str_contains(mb_strtolower($name), 'отвод') => 'elbow',
            default => 'other',
        };
    }

    private function diameter(Product $product): ?string
    {
        $text = $product->name.' '.collect((array) $product->specs)->flatten()->implode(' ');
        preg_match('/(?:Ø|диаметр[^0-9]{0,12})?(80|100|110|115|120|130|140|150|160|180|200|250|300)\s*(?:мм)?/iu', $text, $match);

        return $match[1] ?? null;
    }
}
