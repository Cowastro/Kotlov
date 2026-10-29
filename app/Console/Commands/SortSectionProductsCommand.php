<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cards lying in the root of a section (no subcategory shows them) go to the subcategory their
 * title names. Rules match the transliterated title, first match wins; a card no rule knows
 * stays and is listed. Its old address answers with a 301 (ProductController).
 *
 *   php artisan catalog:sort-section-products            # dry run: card => subcategory
 *   php artisan catalog:sort-section-products --apply
 */
class SortSectionProductsCommand extends Command
{
    protected $signature = 'catalog:sort-section-products {--section=* : Only these sections (slug)} {--apply : Move the cards}';

    protected $description = 'Move cards from the root of a section to their subcategory';

    /** Section => [title pattern (Str::slug of the name), subcategory]. */
    private const RULES = [
        'komplektuyushhie-dlya-otopleniya' => [
            // Flue pipes of condensing boilers come before the Джилекс fittings: both have «otvod», «troinik».
            // Str::slug transliterates «ш» as «s», «ж» as «z», «ц» as «c», «й» as «i»: «кран шаровой» is «kran-sarovoi».
            ['/kondensatootvodcik|reviziia-s-zagluskoi/', 'kondensatootvody'],
            ['/(^|-)f(80|130)-|xomut-zazimnoi|udlinenie-mf/', 'koaxial-dymoxod'],
            ['/^truba-pe(100)?-/', 'polietilenovye-truby'],
            ['/stahlmann/', 'fitingi-dlya-gofrirovannoj-truby'],
            ['/^(mufta-komb|mufta-kombinirovannaia|mufta-soed|otvod-|troinik-|zagluska-dlia-truby|flanec-|stucer-)/', 'fitingi-dlya-pe-trub'],
            ['/^(korpus-dlia-kartridznogo|vodozabornyi-filtr)/', 'filtry'],
            ['/^(elektronasos|krot-)/', 'skvajinnye-nasosy'],
            ['/^(slang-|udlinitel-slanga|uz-|klapan-slivnoi|slivnoi-klapan|adapter-kolodeznyi|rele-davleniia|vykliucatel-poplavkovyi|termousadocnyi|demonstracionnyi|kliuc-montaznyi|rezbovoe-trubnoe|master-steker)/', 'komplektuyushchie-dlya-nasosov'],
            ['/^(sani|sanicondens)/', 'drenajnyie'],
            ['/^kran-2-xodovoi/', 'krany-s-elektroprivodom'],
            ['/^kran-sarovoi/', 'sharovye-krany'],
            ['/^klapan-obratnyi/', 'obratnye-klapany'],
            ['/sibernaia-zadvizka/', 'zadvizhki'],
            ['/^komplekt-iz-dvux/', 'komplekty-podklyucheniya'],
            ['/termostaticeskii-element/', 'smesitelnye-klapany-i-uzly'],
            ['/^gruppy-avtonomnoi-cirkuliacii/', 'gruppy-bystrogo-montazha-kotelnyx'],
            ['/trexxodov|servoprivod|^privod-|esbe-ata|perexodniki-dlia-privodov|smesitelnyi-uzel/', 'smesitelnye-klapany-i-uzly'],
            ['/termometr|termomanometr|^manometr/', 'manometry-i-termometry'],
            ['/vozduxootvodcik|separatory-vozduxa|razvozdusivatel/', 'predokhranitelnaya-i-reguliruyushchaya-armatura'],
            ['/^bak-rassiritelnyi/', 'membrannye-baki'],
            ['/^stabilizator-napriazeniia/', 'stabilizatory-napryazheniya'],
            ['/vstavka-dielektriceskaia/', 'gazovye-shlangi'],
            ['/^(kontroller-kotla|komplekt-avtomatiki|termostat-ester|moduli-rassireniia|internet-modul|pribor-distancionnogo|modul-sviazi)/', 'regulyatory'],
            ['/^(montaznyi-komplekt|komplekt-podkliuceniia|komplekt-iz-dvuh|adapter-monoblocnyi|komplekt-universalnyi)/', 'komplekty-podklyucheniya'],
            ['/kronstein|kronshteyn|^sten-|^rucka-/', 'radiatornaya-armatura'],
            ['/magnievyi-anod|innowarm-termostat/', 'komplektuyushchie-dlya-vodonagrevateley'],
            ['/^(bunker-dlia|snekovyi-transporter|ventiliator-nadduva|sveca-nakala|datcik-plameni|blok-silovoi|vmeste-s-flancem|reguliator-tiagi|regulyator-tyagi|hotex-reguliator-tiagi|vykliucatel-avtomaticeskii|predoxranitel-|komplekt-perenaladki|trubka-podaiushhei|kran-podpitki|turbonasadka|hx-28)/', 'zapchasti-dlya-kotlov'],
        ],
        'kotly' => [
            ['/^kotel-elektriceskii/', 'elektricheskie'],
            ['/^(kotel-kupper|otopitelnyi-kotel)/', 'tverdotoplivnye'],
            ['/^kolonka-gazovaia/', 'vodogreynaya-kolonka'],
            ['/^smesitelnyi-uzel/', 'smesitelnye-klapany-i-uzly'],
            ['/datcik/', 'datchiki'],
            ['/plenka-teplogo-pola/', 'ik-plenochnyj-pol'],
        ],
        'truby-i-fitingi' => [
            ['/^truba-pe100/', 'polietilenovye-truby'],
            ['/^ogolovok-osp/', 'komplektuyushchie-dlya-nasosov'],
            ['/^truba-(maincor|sanha|valfex)/', 'metalloplastikovye-truby'],
        ],
        'vodosnabzhenie' => [
            ['/^nasos-dab-evoplus/', 'tsirkulyatsionnyie'],
            ['/^nasos-dab-(genix|emkost)/', 'nasosnyie-stantsii'],
            ['/^aksessuar-dab/', 'komplektuyushchie-dlya-nasosov'],
            ['/feka/', 'drenajnyie'],
        ],
        'vodonagrevateli' => [
            ['/kosvenn|kospel-sw/', 'kosvennye'],
            ['/bufer|teploakkum/', 'bufernye-emkosti'],
            ['/kolonk/', 'vodogreynaya-kolonka'],
            ['/kombinir/', 'kombinirovannye'],
            ['/gazov/', 'gas'],
            // The rest are electric storage and flow heaters (Tesy, Ariston, Superlux, Teknix…).
            ['/./', 'electric'],
        ],
        'klimat' => [
            ['/mobiln|electrolux-eacm|ballu-bpac|shuft-sfpac/', 'mobilnye-kondicionery'],
            ['/kondicioner|split-sistem|vnutrennii-blok|vnesnii-blok|napolno-potolocn|electrolux-eacs|toshiba-ras|shuft-sf(t|l|m)|ac-electric-ace|ballu-bsgri|royal-thermo-rt(si|pi|b)-/', 'kondicionery'],
            ['/moika-vozdux|electrolux-ehaw|boneco-w/', 'mojki-vozdukha'],
            ['/uvlaznitel|osusitel|ocistitel|filtr-dlia-ocistki|boneco|electrolux-ehu|ballu-uhb|electrolux-edl|ballu-bdi|electrolux-eafr|electrolux-eafe|electrolux-fap|smola-vodorodnaia|ballu-ap-/', 'uvlazhniteli-vozdukha'],
            // Royal Thermo RAF* and Ballu BAF* are exhaust fans.
            ['/ventiliator|pritocn|electrolux-ef[ft]|electrolux-etf|ballu-bf[ft]|royal-thermo-raf|ballu-baf/', 'ventilyatory'],
        ],
    ];

    public function handle(): int
    {
        $sections = (array) $this->option('section') ?: array_keys(self::RULES);
        $categories = DB::table('categories')->pluck('id', 'slug');
        $stats = ['cards' => 0, 'sorted' => 0, 'left' => 0, 'missing_target' => 0];

        foreach ($sections as $section) {
            $rootId = $categories[$section] ?? null;
            if (! $rootId || ! isset(self::RULES[$section])) {
                continue;
            }
            $cards = DB::table('products')->where('category_id', $rootId)->where('is_archived', false)->orderBy('id')->get(['id', 'name', 'slug']);
            foreach ($cards as $card) {
                $stats['cards']++;
                $title = Str::slug((string) $card->name);
                $target = collect(self::RULES[$section])->first(fn (array $rule): bool => (bool) preg_match($rule[0], $title))[1] ?? null;
                if ($target === null) {
                    $stats['left']++;
                    $this->line(sprintf('audit.sort_section.left=%s:%d:%s', $section, $card->id, Str::limit($title, 70, '')));

                    continue;
                }
                if (! isset($categories[$target])) {
                    $stats['missing_target']++;
                    $this->line(sprintf('audit.sort_section.missing=%s', $target));

                    continue;
                }
                $stats['sorted']++;
                $this->line(sprintf('audit.sort_section.sorted=%s:%d:%s:%s', $section, $card->id, $target, Str::limit($title, 60, '')));
                if ($this->option('apply')) {
                    DB::table('products')->where('id', $card->id)->update(['category_id' => $categories[$target], 'updated_at' => now()]);
                }
            }
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.sort_section.{$key}={$value}");
        }

        return self::SUCCESS;
    }
}
