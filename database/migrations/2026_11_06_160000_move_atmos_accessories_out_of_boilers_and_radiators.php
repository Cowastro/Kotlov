<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** ATMOS price-list cards that landed among boilers, radiators and manifolds go to their own groups. */
return new class extends Migration
{
    private const MOVES = [
        'regulyatory' => ['plata-udalennogo-upravleniia', 'komnatnyi-termostat-mora-top-geti-grt01-provodnoi', 'termostat-komnatnyi-besprovodnoi-zota-zt-20w-wi-fi',
            'modul-zota-gsmgprs', 'modul-upravleniia-zota-lan', 'modul-distancionnogo-upravleniia-zota-gsm-gprs-wifi', 'modul-upravleniia-zota-gsm-gprs-wifi',
            'komplekt-avtomatiki-zota-turboset%', 'komplekt-zota-turboset%', 'komplekt-turboset-dlia-kotlov-zota'],
        'datchiki' => ['datcik-temperatury-dlia-boilera-kosvennogo-nagreva'],
        'smesitelnye-klapany-i-uzly' => ['trexxodovoi-razdelitelnyi-klapan-termica-34', 'klapan-prioriteta-boilera-gvs-zota-bpv'],
        'komplekty-podklyucheniya' => ['komplekt-dlia-podkliuceniia-boilera', 'komplekt-dlia-podkliuceniia-kotla-mora-top-helios'],
        'koaxial-dymoxod' => ['dymoxod-mora-top', 'koaksialnyi-komplekt-o60100-termica-kit%'],
        'dymohody-mono' => ['truba-o80-termica-%'],
        'radiatornaya-armatura' => ['kronstein-ankernyi-%', 'kronstein-universalnyi-uglovoi-termica-%', 'kronstein-napolnyi-reguliruemyi-%'],
        'gruppy-bystrogo-montazha-kotelnyx' => ['nasosnye-gruppy-bystrogo-montaza-hansa-%', 'priamaia-nasosnaia-gruppa-hansa-%', 'smesitelnaia-nasosnaia-gruppa-hansa-%',
            'nasosnaia-gruppa-hansa-fwe-%', 'nasosnaia-gruppa-priamaia', 'nasosnaia-gruppa-s-termostaticeskim-smesitelnym-klapanom', 'nasosnaia-gruppa-s-trexxodovym-smesitelnym-klapanom'],
        'predokhranitelnaya-i-reguliruyushchaya-armatura' => ['perepusknoi-klapan-hansa-dn-15'],
        'kompressionnye-fitingi' => ['fiting-obzimnoi-termica-f1634', 'fiting-obzimnoi-dlia-mednoi-truby-altstream-%', 'kompressionnyi-fiting-altstream-%',
            'kompressionnye-fitingi-evrokonus-hansa-%'],
    ];

    public function up(): void
    {
        $ids = DB::table('categories')->whereIn('slug', array_keys(self::MOVES))->pluck('id', 'slug');
        foreach (self::MOVES as $slug => $patterns) {
            if (! isset($ids[$slug])) {
                continue;
            }
            DB::table('products')->where('sku', 'like', 'ATMOS-%')
                ->where(fn ($q) => collect($patterns)->each(fn (string $p) => $q->orWhere('slug', 'like', $p)))
                ->update(['category_id' => $ids[$slug], 'updated_at' => now()]);
        }
        // A placeholder row of the price list made a card: hidden.
        DB::table('products')->where('sku', 'like', 'ATMOS-%')->where('slug', 'novyi-tovar-v-gatmosax')
            ->update(['is_active' => false, 'is_archived' => true, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Not moved back into the wrong groups.
    }
};
