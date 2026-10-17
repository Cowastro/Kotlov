<?php

namespace App\Support;

final class CategoryIconResolver
{
    /** @var array<string, string> */
    private const EXACT = [
        'gazovye' => 'icon-Sparkle',
        'tverdotoplivnye' => 'icon-Leaf',
        'elektricheskie' => 'icon-Lightning-1',
        'vodonagrevateli' => 'icon-Timer',
        'topki' => 'icon-Sparkle',
        'elektrokamini' => 'icon-Lightning',
        'oblicovki' => 'icon-Layout',
        'pechi-kaminy' => 'icon-Sparkle',
        'pechi' => 'icon-Sparkle',
        'burzhuiki-pechi' => 'icon-Sparkle',
        'dlya-dachi' => 'icon-HouseLine',
        'pechnoe-i-kaminnoe-lite' => 'icon-GearSix',
        'dymohody-mono' => 'icon-ListDashes',
        'dymohody-sendvich' => 'icon-CopySimple',
        'shibery-dymohod' => 'icon-GearSix',
        'kondensatootvody' => 'icon-Leaf',
        'krepleniya-dymohod' => 'icon-PuzzlePiece',
        'zonty-deflektory' => 'icon-ShieldCheck',
        'teplosyomniki' => 'icon-ArrowClockwise',
        'perehody-adaptery-dymohod' => 'icon-ArrowsLeftRight',
        'zaglushki-dymohod' => 'icon-Exclude',
        'koaxial-dymoxod' => 'icon-ArrowFatUp',
        'truby-mono' => 'icon-ListDashes',
        'troyniki-mono' => 'icon-GitDiff',
        'kolena-mono' => 'icon-ArrowUDownLeft',
        'truby-sendvich' => 'icon-CopySimple',
        'troyniki-sendvich' => 'icon-GitDiff',
        'kolena-sendvich' => 'icon-ArrowUDownLeft',
        'regulyatory' => 'icon-GearSix',
        'stabilizatory-napryazheniya' => 'icon-Lightning',
        'nasosyi' => 'icon-ArrowClockwise',
        'datchiki' => 'icon-Timer',
        'radiatory' => 'icon-Layout',
        'membrannye-baki' => 'icon-Package',
        'bufernye-emkosti' => 'icon-Package',
        'grebenki' => 'icon-GitDiff',
        'solnechnye-kollektory' => 'icon-Sparkle',
        'schetchiki-gaza' => 'icon-Timer',
        'akkumuliruyushhie-baki' => 'icon-Package',
        'signalizatory-zagazovannosti' => 'icon-ShieldCheck',
        'teplyj-pol' => 'icon-HouseLine',
        'truby-i-fitingi' => 'icon-ListDashes',
        'komplektuyushhie-dlya-otopleniya' => 'icon-PuzzlePiece',
        'gruppy-bystrogo-montazha-kotelnyx' => 'icon-GearSix',
        'istochniki-besperebojnogo-pitaniya' => 'icon-Lightning-1',
        'filtry' => 'icon-filter',
        'regulyatoryi-davleniya-gaza' => 'icon-GearSix',
        'elektricheskie-konvektoryi' => 'icon-Lightning',
        'teplonositeli' => 'icon-Leaf',
        'pelletnyie-gorelki' => 'icon-Sparkle',
        'elektricheskie-teny-dlya-otopleniya' => 'icon-Lightning-1',
        'gidravlicheskie-razdeliteli-i-kollektory' => 'icon-GitDiff',
        'pechi-dlya-bani' => 'icon-Sparkle',
        'drovyanye-pechi-dlya-bani' => 'icon-Leaf',
        'elektrokamenki' => 'icon-Lightning',
        'aksessuary-dlya-bani' => 'icon-Tag',
        'nasosy' => 'icon-ArrowClockwise',
        'nasosy-pogruzhnye-skvazhinnye' => 'icon-ArrowLongBottom',
    ];

    /** @var array<int, array{terms: array<int, string>, icon: string}> */
    private const RULES = [
        ['terms' => ['signalizator', 'zagazov', 'утечк', 'сигнализатор'], 'icon' => 'icon-ShieldCheck'],
        ['terms' => ['instrument', 'инструмент'], 'icon' => 'icon-Ruler'],
        ['terms' => ['smesit', 'смесит'], 'icon' => 'icon-ArrowsLeftRight'],
        ['terms' => ['shkaf', 'шкаф'], 'icon' => 'icon-Layout'],
        ['terms' => ['zaglush', 'заглуш', 'ogolov', 'оголов'], 'icon' => 'icon-Exclude'],
        ['terms' => ['zont', 'зонт', 'deflektor', 'дефлектор'], 'icon' => 'icon-ShieldCheck'],
        ['terms' => ['kondensat', 'конденсат', 'дренаж'], 'icon' => 'icon-Leaf'],
        ['terms' => ['kreplen', 'креплен', 'montazh', 'монтаж'], 'icon' => 'icon-PuzzlePiece'],
        ['terms' => ['perehod', 'переход', 'adapter', 'адаптер'], 'icon' => 'icon-ArrowsLeftRight'],
        ['terms' => ['troynik', 'тройник', 'razdelitel', 'разделител', 'kollektor', 'коллектор', 'greben', 'гребен'], 'icon' => 'icon-GitDiff'],
        ['terms' => ['kolen', 'колен', 'otvod', 'отвод'], 'icon' => 'icon-ArrowUDownLeft'],
        ['terms' => ['sendvich', 'сэндвич', 'двустенн'], 'icon' => 'icon-CopySimple'],
        ['terms' => ['dymohod', 'дымоход', 'trub', 'труб', 'fiting', 'фитинг'], 'icon' => 'icon-ListDashes'],
        ['terms' => ['shiber', 'шибер', 'zadvizh', 'задвиж', 'klapan', 'клапан', 'regulyator', 'регулятор'], 'icon' => 'icon-GearSix'],
        ['terms' => ['teplosyom', 'теплосъ', 'rekuper', 'теплообмен'], 'icon' => 'icon-ArrowClockwise'],
        ['terms' => ['pogruz', 'погруж', 'skvazhin', 'скважин'], 'icon' => 'icon-ArrowLongBottom'],
        ['terms' => ['nasos', 'насос', 'tsirkuly', 'циркуляц'], 'icon' => 'icon-ArrowClockwise'],
        ['terms' => ['shlang', 'шланг', 'podvodk', 'подводк'], 'icon' => 'icon-ListDashes'],
        ['terms' => ['vozduhovod', 'воздуховод'], 'icon' => 'icon-Wind'],
        ['terms' => ['filtr', 'фильтр', 'очистк'], 'icon' => 'icon-filter'],
        ['terms' => ['stabiliz', 'стабилиз', 'transformator', 'трансформатор', 'ibp', 'бесперебойн', 'ten', 'тэн', 'elektr', 'электр'], 'icon' => 'icon-Lightning-1'],
        ['terms' => ['datchik', 'датчик', 'schetchik', 'счетчик', 'счётчик', 'termometr', 'термометр'], 'icon' => 'icon-Timer'],
        ['terms' => ['radiator', 'радиатор', 'konvektor', 'конвектор'], 'icon' => 'icon-Layout'],
        ['terms' => ['bak', 'бак', 'emkost', 'емкост', 'boiler', 'бойлер'], 'icon' => 'icon-Package'],
        ['terms' => ['teplyj-pol', 'теплый пол', 'тёплый пол'], 'icon' => 'icon-HouseLine'],
        ['terms' => ['gaz', 'газов'], 'icon' => 'icon-Sparkle'],
        ['terms' => ['pech', 'печ', 'kamin', 'камин', 'gorelk', 'горелк', 'mangal', 'мангал'], 'icon' => 'icon-Sparkle'],
        ['terms' => ['pellet', 'пеллет', 'drov', 'дров', 'tverdotop', 'твердотоп'], 'icon' => 'icon-Leaf'],
        ['terms' => ['solnech', 'солнеч'], 'icon' => 'icon-Sparkle'],
        ['terms' => ['avtomatik', 'автоматик', 'kontroller', 'контроллер', 'termostat', 'термостат'], 'icon' => 'icon-Devices'],
        ['terms' => ['armatur', 'арматур'], 'icon' => 'icon-GearSix'],
        ['terms' => ['komplektuyush', 'комплектующ'], 'icon' => 'icon-PuzzlePiece'],
        ['terms' => ['dver', 'двер', 'portal', 'портал', 'oblicov', 'облицов'], 'icon' => 'icon-Layout'],
        ['terms' => ['aksessuar', 'аксессуар', 'komplekt', 'комплект'], 'icon' => 'icon-Tag'],
    ];

    public static function resolve(string $slug, ?string $name = null): string
    {
        if (isset(self::EXACT[$slug])) {
            return self::EXACT[$slug];
        }

        $searchable = mb_strtolower($slug.' '.($name ?? ''));

        foreach (self::RULES as $rule) {
            foreach ($rule['terms'] as $term) {
                if (str_contains($searchable, $term)) {
                    return $rule['icon'];
                }
            }
        }

        return 'icon-Package';
    }
}
