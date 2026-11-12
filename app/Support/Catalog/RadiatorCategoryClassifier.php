<?php

namespace App\Support\Catalog;

use App\Models\Category;

final class RadiatorCategoryClassifier
{
    public const CATEGORIES = ['radiatory', 'alyuminievye-radiatory', 'bimetallicheskie-radiatory', 'stalnye-radiatory', 'chugunnye-radiatory', 'vodyanye-konvektory', 'radiatornaya-armatura'];

    public static function slug(string $name, string $source = '', string $brand = ''): ?string
    {
        $name = mb_strtolower(str_replace('ё', 'е', $name));
        $context = mb_strtolower($brand.' '.$name.' '.$source);
        // An oil heater is not a hydronic radiator, even if the supplier calls it a radiator.
        if (preg_match('~маслян|boh/|\btor\s*21|электроконвектор~u', $name)) return null;
        // Some accessory titles contain only a thread size; their dedicated source branch is explicit.
        if (str_contains($context, 'raditory/komplektuyuschie-k-radiatoram') && ! preg_match('~\bрадиатор\b|\bradiator\b~u', $name)) return 'radiatornaya-armatura';
        // Logafix BH1-W0 is a thermostatic head, not a radiator.
        if (str_contains($context, 'buderus') && preg_match('~\bbh1[-\s]*w0\b~u', $name)) return 'radiatornaya-armatura';
        $radiatorContext = preg_match('~радиатор|radiator|raditory~u', $context);
        if ($radiatorContext && preg_match('~^(?:(?:монтажный|присоединительный|универсальный|термостатический)\s+)?(?:набор|комплект)\b|пробк|футорк|кронштейн|термоголов|термостат|\b(?:кран|клапан|вентиль|воздухоотвод|заглушк)|удлинител.*поток|подключения.*узел|узел.*подключения~u', $name)) return 'radiatornaya-armatura';
        if (preg_match('~внутрипольн|водян.*конвектор|конвектор.*водян|\bkermi\s+kvn\b~u', $name)) return 'vodyanye-konvektory';
        $isRadiator = (bool) preg_match('~\bрадиатор(?:ы)?\b|\bradiator\b~u', $name);

        // The actual product type wins over an incorrect supplier category.
        if ($isRadiator) {
            if (str_contains($name, 'биметалл')) return 'bimetallicheskie-radiatory';
            if (str_contains($name, 'алюмин')) return 'alyuminievye-radiatory';
            if (preg_match('~стальн|стальной~u', $name)) return 'stalnye-radiatory';
            if (str_contains($name, 'чугун')) return 'chugunnye-radiatory';
        }

        // Manufacturer-documented families; never classify an entire brand as one material.
        if (preg_match('~royal[\s-]*thermo~u', $context)) {
            if (preg_match('~piano\s*forte|biliner|revolution\s+bimetall|infinity|monoblock\s+b\b~u', $name)) return 'bimetallicheskie-radiatory';
            if (preg_match('~monoblock\s+a\b|\b(?:dreamliner|indigo)(?:\s+a)?\s*(?:350|500)\b|\brevolution\s+(?:350|500)\b~u', $name)) return 'alyuminievye-radiatory';
            if (preg_match('~insignia|shift\s*[qr]|ventil\s*compact|compact\s+v?c(?:10|11|20|21|22|30|33)\b~u', $name)) return 'stalnye-radiatory';
        }
        if (preg_match('~лидея|lideya|lidea~u', $context) && preg_match('~\b(?:лк|лу|lk|lu)\b~u', $name)) return 'stalnye-radiatory';
        if (preg_match('~lammin|ламмин~u', $context) && preg_match('~\b(?:eco|premium)\s*(al|bm)(?=\b|\d)~u', $name, $m)) return $m[1] === 'bm' ? 'bimetallicheskie-radiatory' : 'alyuminievye-radiatory';
        if (preg_match('~rifar|рифар~u', $context)) {
            if (preg_match('~\balum\b~u', $name)) return 'alyuminievye-radiatory';
            if (preg_match('~\b(?:base|monolit|supremo|ecobuild|convex)\b~u', $name)) return 'bimetallicheskie-radiatory';
            if (str_contains($name, 'tubog')) return 'stalnye-radiatory';
        }
        if (preg_match('~\bkermi\b~u', $context) && preg_match('~\b(?:fko|ftv|fkv|profil|plan|verteo)~u', $name)) return 'stalnye-radiatory';
        if (str_contains($context, 'zehnder') && str_contains($name, 'charleston')) return 'stalnye-radiatory';
        if (str_contains($context, 'prado') && preg_match('~classic|universal|style~u', $name)) return 'stalnye-radiatory';
        if (str_contains($context, 'buderus') && preg_match('~\b(?:vk|k)-profil\b~u', $name)) return 'stalnye-radiatory';
        if (str_contains($context, 'fondital')) {
            if (str_contains($name, 'alustal bimetal')) return 'bimetallicheskie-radiatory';
            if (preg_match('~exclusivo super (?:b4|d3)|calidor super b4~u', $name)) return 'alyuminievye-radiatory';
        }

        return $isRadiator ? 'radiatory' : null;
    }

    public static function categoryId(string $name, string $source = '', string $brand = ''): ?int
    {
        $slug = self::slug($name, $source, $brand);

        return $slug ? Category::active()->where('slug', $slug)->value('id') : null;
    }
}
