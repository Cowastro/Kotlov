<?php

namespace App\Support\Catalog;

use App\Models\Category;

class HeaterCategoryClassifier
{
    public static function slug(string $name, string $source = ''): ?string
    {
        $name = mb_strtolower($name);
        // Components must win over the heater type mentioned in their description.
        if (preg_match('~bft/|bct/evu|bec/wf|cwm-02|ech/tu[ie]|eft/(?:ag|rr)~u', $name)
            || (preg_match('~шасси|подставк|кронштейн|блок управлен|модуль.*(?:wi-fi|wifi)~u', $name)
                && preg_match('~конвектор|обогревател~u', $name))) {
            return 'komplektuyushchie-dlya-obogrevateley';
        }
        if (preg_match('~зарядн.*станци|станци.*зарядн~u', $name)) return 'zaryadnye-stantsii-dlya-elektromobiley';
        if (preg_match('~масля[нн]*|boh/|\btor\s*21~u', $name)) return 'maslyanye-obrevateli';
        if (preg_match('~инфракрас|eih/ag~u', $name)) return 'infrakrasnye-obrevateli';
        if (preg_match('~теплов.*завес~u', $name)) return 'teplovye-zavesy';
        if (preg_match('~теплов.*пушк~u', $name)) return 'teplovye-pushki';
        if (preg_match('~тепловентилятор|тепловой вентилятор~u', $name)) return 'teploventilyatory';
        if (preg_match('~электрокамин|\befp(?:\b|/)~u', $name)) return 'elektrokamini';
        if (preg_match('~вентилятор|\bbif-~u', $name)) return 'ventilyatory';
        if (! preg_match('~водян|внутрипол|газов~u', $name) && preg_match('~конвектор|\bbec/|\bech/|\bgch/|\brchc/~u', $name)) return 'elektricheskie-konvektoryi';

        $source = mb_strtolower($source);
        foreach ([
            'zaryadnye-stantsii-dlya-elektromobiley' => ['зарядн', 'zaryadn'],
            'teplovye-pushki' => ['teplovye-pushki', 'тепловые пушки'],
            'teploventilyatory' => ['teploventilyatory', 'тепловентиляторы'],
            'teplovye-zavesy' => ['teplovye-zavesy', 'тепловые завесы'],
            'maslyanye-obrevateli' => ['maslyanye', 'масляные'],
            'infrakrasnye-obrevateli' => ['infrakrasnye', 'инфракрасные'],
            'komplektuyushchie-dlya-obogrevateley' => ['аксессуары для обогревател'],
        ] as $slug => $terms) {
            foreach ($terms as $term) {
                if (str_contains($source, $term)) return $slug;
            }
        }
        return null;
    }

    public static function categoryId(string $name, string $source = ''): ?int
    {
        $slug = self::slug($name, $source);
        return $slug ? Category::active()->where('slug', $slug)->value('id') : null;
    }
}
