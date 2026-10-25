<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\ChimneyCalculationRequest;
use App\Models\Product;
use App\Services\ChimneyBundlePromotion;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChimneySelectorController extends Controller
{
    public function __invoke(Request $request, ChimneyBundlePromotion $promotion)
    {
        $managerMode = $request->boolean('manager_mode');
        $managerCalculation = null;
        if ($managerMode) {
            abort_unless($request->user()?->isManager() === true, 403);
            if ($request->integer('calculation')) {
                $managerCalculation = ChimneyCalculationRequest::query()->findOrFail($request->integer('calculation'));
            }
        }

        $categoryIds = Category::query()
            ->whereIn('slug', (array) config('shop.chimney_bundle_discount.chimney_category_slugs', []))
            ->pluck('id');
        $preferredBrandSlug = (string) config('shop.chimney_bundle_discount.preferred_brand_slug', 'teplov-i-suhov');
        $preferredBrandId = Brand::query()->where('slug', $preferredBrandSlug)->value('id');
        $darcoBrandId = Brand::query()->where('slug', 'darco')->value('id');

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
        $darcoSections = $darcoBrandId
            ? (clone $catalogQuery)->where('brand_id', $darcoBrandId)
                ->limit(500)
                ->get()
            : collect();
        $mcBlackSections = $preferredBrandId
            ? Product::query()->where('brand_id', $preferredBrandId)
                ->orderable()
                ->withSupplierChannels()
                ->with(['category', 'brand'])
                ->where('name', 'like', '%MC%Black%')
                ->limit(500)
                ->get()
            : collect();
        $preferredAccessories = $preferredBrandId
            ? Product::query()->where('brand_id', $preferredBrandId)
                ->orderable()
                ->withSupplierChannels()
                ->with(['category', 'brand'])
                ->where(function ($query) {
                    $query->where('name', 'like', '%теплообмен%')
                        ->orWhere('name', 'like', '%Теплообмен%')
                        ->orWhere('name', 'like', '%регистр%')
                        ->orWhere('name', 'like', '%Регистр%')
                        ->orWhere('name', 'like', '%теплосъ%')
                        ->orWhere('name', 'like', '%Теплосъ%')
                        ->orWhere('name', 'like', '%бак печной%')
                        ->orWhere('name', 'like', '%Бак печной%')
                        ->orWhere('name', 'like', '%бак на трубе%')
                        ->orWhere('name', 'like', '%Бак на трубе%')
                        ->orWhere('name', 'like', '%бак навес%')
                        ->orWhere('name', 'like', '%Бак навес%')
                        ->orWhere('name', 'like', '%бак самовар%')
                        ->orWhere('name', 'like', '%Бак самовар%');
                })
                ->limit(200)
                ->get()
            : collect();
        $roofAccessories = Product::query()
            ->orderable()
            ->withSupplierChannels()
            ->with(['category', 'brand'])
            ->where(function ($query) {
                $query->where('name', 'like', '%Master Flash%')
                    ->orWhere('name', 'like', '%master flash%')
                    ->orWhere('name', 'like', '%Мастер-флеш%')
                    ->orWhere('name', 'like', '%мастер-флеш%')
                    ->orWhere('name', 'like', '%Мастер флеш%')
                    ->orWhere('name', 'like', '%мастер флеш%');
            })
            ->limit(100)
            ->get();
        $products = $products
            ->merge($pipeSections)
            ->merge($darcoSections)
            ->merge($mcBlackSections)
            ->merge($preferredAccessories)
            ->merge($roofAccessories)
            ->unique('id')
            ->values();

        $categoryIndex = Category::query()->get(['id', 'parent_id', 'slug'])->keyBy('id');
        $eligibleCategorySlugs = array_flip((array) config('shop.chimney_bundle_discount.chimney_category_slugs', []));
        $discountEligibleCategoryIds = $categoryIndex
            ->filter(function (Category $category) use ($categoryIndex, $eligibleCategorySlugs): bool {
                $categoryId = (int) $category->id;
                $seen = [];
                while ($categoryId > 0 && ! isset($seen[$categoryId])) {
                    $seen[$categoryId] = true;
                    $current = $categoryIndex->get($categoryId);
                    if (! $current) {
                        break;
                    }
                    if (isset($eligibleCategorySlugs[(string) $current->slug])) {
                        return true;
                    }
                    $categoryId = (int) $current->parent_id;
                }

                return false;
            })
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->all();

        $catalog = $products->map(function (Product $product) use ($discountEligibleCategoryIds): array {
            $text = $product->name.' '.json_encode((array) $product->specs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $diameter = $this->diameterFromText($text);
            preg_match('/(?:D|Ø)?\s*(80|100|110|115|120|130|140|150|160|180|200|210|220|230|240|250|260|280|300|310|350)\s*(?:[\/]\s*|[xх-]\s*)(120|130|150|160|180|200|210|220|230|240|250|260|280|300|310|350)/iu', $text, $diameterPair);
            if (! isset($diameterPair[1], $diameterPair[2])) {
                preg_match('/\b(50|60|70|75|80|90|100|110|115|120|130|140|150|160|170|175|178|180|200|210|220|230|240|250|254|260|275|280|300|310|330|350|400|450|467)\s*[–—-]\s*(75|80|90|100|110|115|120|130|140|150|160|170|175|178|180|200|210|220|230|240|250|254|260|275|280|300|310|330|350|400|450|467)\s*мм\b/iu', $text, $diameterPair);
            }
            preg_match('/(?:AISI\s*)?(304|316|321|430)/iu', $text, $steel);
            preg_match('/(?:304|316|321|430)\s*[-,]\s*(0[.,][5-9]|1[.,]0)/iu', $text, $thickness);
            if (! isset($thickness[1])) {
                preg_match('/\b(2(?:[.,]0)?)\s*мм\b/iu', $text, $thickness);
            }
            preg_match('/(?:\bL\s*|\/)(250|500|750|1000)\b/iu', $text, $length);
            preg_match('/[\/\s](45|87|90)(?:\s*[°*]|\s*-|\s+(?=[A-ZА-Я]))/iu', $text, $angle);
            preg_match('/(?:КР|регулируемое).*?\b(700|1000|1200)\b/iu', $text, $mountLength);
            $slug = (string) $product->category?->slug;
            $brandSlug = (string) $product->brand?->slug;
            $normalizedText = mb_strtolower($text);
            $isMcBlack = (bool) preg_match('/mc(?:\s|\x{00A0})*black/iu', $normalizedText);
            $kind = $this->kind($slug, $product->name);
            $thicknessValue = isset($thickness[1])
                ? rtrim(rtrim(str_replace(',', '.', $thickness[1]), '0'), '.')
                : ($brandSlug === 'darco' ? '2' : null);

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
                'thickness' => $thicknessValue,
                'length_mm' => isset($length[1]) ? (int) $length[1] : null,
                'angle' => $angle[1] ?? null,
                'mount_length_mm' => isset($mountLength[1]) ? (int) $mountLength[1] : null,
                'system' => in_array($kind, ['water_tank', 'heat_exchanger'], true)
                    ? 'mono'
                    : ($brandSlug === 'darco'
                    ? 'mono'
                    : (str_contains($slug, 'sendvich')
                        || str_contains($normalizedText, 'сэндвич')
                        || str_contains($normalizedText, 'термо')
                        || (bool) preg_match('/D?\s*\d{2,3}\s*[\/]\s*\d{2,3}\b/iu', $text)
                            ? 'sandwich'
                            : 'mono')),
                'kind' => $kind,
                'brand_slug' => $brandSlug,
                'material' => $brandSlug === 'darco'
                    ? 'black_steel'
                    : ($isMcBlack ? 'mc_black' : 'stainless'),
                'category' => $product->category?->name,
                'preferred' => $brandSlug === config('shop.chimney_bundle_discount.preferred_brand_slug'),
                'discount_eligible' => in_array((int) $product->category_id, $discountEligibleCategoryIds, true),
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
        $selectorToken = session('chimney_selector_token');
        if (! is_string($selectorToken) || strlen($selectorToken) < 32) {
            $selectorToken = Str::random(48);
            session(['chimney_selector_token' => $selectorToken]);
        }

        return view('pages.chimney-selector', [
            'catalog' => $catalog,
            'equipment' => $equipment,
            'defaultDiameter' => $defaultDiameter,
            'discountPercent' => (float) config('shop.chimney_bundle_discount.percent', 10),
            'selfSelectionDiscountPercent' => (float) config('shop.chimney_bundle_discount.self_selection_percent', 5),
            'discountUnlocked' => $discountUnlocked,
            'equipmentInCart' => $equipmentInCart,
            'preferredBrandName' => 'Теплов и Сухов',
            'selectorToken' => $selectorToken,
            'managerMode' => $managerMode,
            'managerCalculation' => $managerCalculation,
            'minimalLayout' => $managerMode,
        ]);
    }

    private function kind(string $slug, string $name): string
    {
        $normalizedName = mb_strtolower($name);

        return match (true) {
            str_contains($normalizedName, 'теплообмен')
                || str_contains($normalizedName, 'теплосъ')
                || str_contains($normalizedName, 'регистр') => 'heat_exchanger',
            str_contains($normalizedName, 'бак печной')
                || str_contains($normalizedName, 'бак на трубе')
                || str_contains($normalizedName, 'бак навес')
                || str_contains($normalizedName, 'бак самовар') => 'water_tank',
            str_contains($slug, 'shibery') || str_contains($normalizedName, 'шибер') => 'damper',
            str_contains($slug, 'zonty') || str_contains($normalizedName, 'зонт') || str_contains($normalizedName, 'дефлектор') => 'umbrella',
            str_contains($normalizedName, 'ппу') || str_contains($normalizedName, 'проход через') || str_contains($normalizedName, 'проходной узел') => 'ceiling_pass',
            str_contains($normalizedName, 'площадка монтажная') || str_contains($normalizedName, 'площадка сквозная') => 'support',
            str_contains($normalizedName, 'крепление настенное')
                || str_contains($normalizedName, 'крепление универсальное')
                || str_contains($normalizedName, 'крепление регулируемое')
                || str_contains($normalizedName, 'крепление основное') => 'wall_mount',
            str_contains($normalizedName, 'фартук')
                || str_contains($normalizedName, 'master flash')
                || str_contains($normalizedName, 'мастер флеш')
                || str_contains($normalizedName, 'мастер-флеш')
                || str_contains($normalizedName, 'крышная разделка') => 'roof_flashing',
            str_contains($normalizedName, 'растяж') => 'guy_wire',
            str_contains($normalizedName, 'хомут штанги') || str_contains($normalizedName, 'хомуты штанги') => 'mount',
            (bool) preg_match('/(^|\s)хомут(?:\s|$)/u', $normalizedName) => 'clamp',
            str_contains($normalizedName, 'моно/термо') || str_contains($normalizedName, 'моно термо') => 'transition',
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
        if (preg_match('/(?:D|Ø|ф|RP|SZK|KNSR?|KSR?|KS)\s*(80|100|110|115|120|130|140|150|160|180|200|210|220|230|240|250|260|280|300|310|350)\b/iu', $text, $match)) {
            return $match[1];
        }
        if (preg_match('/диаметр[^0-9]{0,20}(80|100|110|115|120|130|140|150|160|180|200|210|220|230|240|250|260|280|300|310|350)\b/iu', $text, $match)) {
            return $match[1];
        }

        return null;
    }
}
