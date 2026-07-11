<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Category extends Model
{
    protected $fillable = [
        'parent_id', 'name', 'slug', 'h1', 'type',
        'sort_order', 'is_active', 'content',
        'meta_title', 'meta_keywords', 'meta_description',
        'image', 'icon',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(Attribute::class)->orderBy('sort_order');
    }

    public function getImageUrlAttribute(): string
    {
        return $this->imageUrl();
    }

    public function imageUrl(): string
    {
        $path = trim((string) $this->image);

        if ($path !== '') {
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }

            if (str_starts_with($path, 'img/') || str_starts_with($path, '/img/')) {
                return asset(ltrim($path, '/'));
            }

            return asset('storage/' . ltrim($path, '/'));
        }

        return asset(static::fallbackImagePath($this->slug) ?? 'img/popular/catalog.jpg');
    }

    public static function fallbackImagePath(?string $slug): ?string
    {
        return [
            'kotly' => 'img/popular/boiler_img.jpg',
            'kotly-otopleniya' => 'img/popular/boiler_img.jpg',
            'gazovye' => 'img/popular/boiler_img.jpg',
            'tverdotoplivnye' => 'img/popular/boiler_img.jpg',
            'elektricheskie' => 'img/popular/boiler_img.jpg',
            'komplektyi-dlya-tverdotoplivnyih-kotlov' => 'img/popular/boiler_img.jpg',
            'teplovyie-nasosyi' => 'img/popular/heatpump.jpg',
            'teplovye-nasosy' => 'img/popular/heatpump.jpg',
            'kaminy' => 'img/popular/fireplace.jpg',
            'topki' => 'img/popular/fireplace.jpg',
            'elektrokamini' => 'img/popular/fireplace.jpg',
            'oblicovki' => 'img/popular/fireplace.jpg',
            'bio-kaminy' => 'img/popular/fireplace.jpg',
            'biokaminy' => 'img/popular/fireplace.jpg',
            'aksessuary-kaminy' => 'img/popular/fireplace.jpg',
            'drovnitsyi' => 'img/popular/fireplace.jpg',
            'kaminnye-nabory' => 'img/popular/fireplace.jpg',
            'kaminnye-reshyotki' => 'img/popular/fireplace.jpg',
            'pechki' => 'img/popular/pech.jpg',
            'pechi' => 'img/popular/pech.jpg',
            'pechi-dlya-bani' => 'img/popular/pech.jpg',
            'pechi-kaminy' => 'img/popular/pech.jpg',
            'burzhuiki-pechi' => 'img/popular/pech.jpg',
            'dlya-dachi' => 'img/popular/pech.jpg',
            'pechnoe-i-kaminnoe-lite' => 'img/popular/pech.jpg',
            'peci-drovianye-otopitelnye' => 'img/popular/pech.jpg',
            'dymohody' => 'img/popular/chimney.jpg',
            'dymoxody-dlya-pechej' => 'img/popular/chimney.jpg',
            'dymoxody-dlya-bani' => 'img/popular/chimney.jpg',
            'dymoxody-dlya-kaminov' => 'img/popular/chimney.jpg',
            'prochie-dymohod' => 'img/popular/chimney.jpg',
            'dymohody-nerzhaveyushchie' => 'img/popular/chimney.jpg',
            'koaxial-dymoxod' => 'img/popular/chimney.jpg',
            'shibery-dymohod' => 'img/popular/chimney.jpg',
            'kondensatootvody' => 'img/popular/chimney.jpg',
            'krepleniya-dymohod' => 'img/popular/chimney.jpg',
            'zonty-deflektory' => 'img/popular/chimney.jpg',
            'teplosyomniki' => 'img/popular/chimney.jpg',
            'perehody-adaptery-dymohod' => 'img/popular/chimney.jpg',
            'zaglushki-dymohod' => 'img/popular/chimney.jpg',
            'truby-mono' => 'img/popular/chimney.jpg',
            'troyniki-mono' => 'img/popular/chimney.jpg',
            'kolena-mono' => 'img/popular/chimney.jpg',
            'truby-sendvich' => 'img/popular/chimney.jpg',
            'troyniki-sendvich' => 'img/popular/chimney.jpg',
            'kolena-sendvich' => 'img/popular/chimney.jpg',
            'bani-i-sauny' => 'img/popular/sauna.jpg',
            'drovyanye-pechi-dlya-bani' => 'img/popular/sauna.jpg',
            'drovianye-peci-bannye' => 'img/popular/sauna.jpg',
            'elektrokamenki' => 'img/popular/sauna.jpg',
            'aksessuary-dlya-bani' => 'img/popular/sauna.jpg',
            'aksessuaryi-dlya-bani' => 'img/popular/sauna.jpg',
            'kupeli' => 'img/popular/sauna.jpg',
            'kupeli-2' => 'img/popular/sauna.jpg',
            'dveri-dlya-ban-i-saun' => 'img/popular/sauna.jpg',
            'mangalyi' => 'img/popular/sauna.jpg',
            'kamni-dlya-bani' => 'img/popular/sauna.jpg',
            'registry' => 'img/popular/sauna.jpg',
            'izmeritelnye-pribory' => 'img/popular/sauna.jpg',
            'ventilyacionnye-klapana' => 'img/popular/sauna.jpg',
            'kovriki-dlya-bani' => 'img/popular/sauna.jpg',
            'shajki-dlya-bani' => 'img/popular/sauna.jpg',
            'oblivnye-ustrojstva' => 'img/popular/sauna.jpg',
            'zaparniki' => 'img/popular/sauna.jpg',
            'otdelka-dlya-parnoj' => 'img/popular/sauna.jpg',
            'abazhury-dlya-bani' => 'img/popular/sauna.jpg',
            'metall-dlya-bani' => 'img/popular/sauna.jpg',
            'vodonagrevateli' => 'img/banners/baner_boiler.jpg',
            'electric' => 'img/banners/baner_boiler.jpg',
            'gas' => 'img/banners/baner_boiler.jpg',
            'kosvennye' => 'img/banners/baner_boiler.jpg',
            'kombinirovannye' => 'img/banners/baner_boiler.jpg',
            'vodogreynaya-kolonka' => 'img/banners/baner_boiler.jpg',
            'protochnye' => 'img/banners/baner_boiler.jpg',
            'pelletnye-gorelki' => 'img/popular/pellet_burner.jpg',
            'otoplenie' => 'img/popular/heater.jpg',
            'vodosnabzhenie' => 'img/popular/nasosy.jpg',
            'nasosy' => 'img/popular/nasosy.jpg',
            'nasosyi' => 'img/popular/nasosy.jpg',
            'tsirkulyatsionnyie' => 'img/popular/nasosy.jpg',
            'skvajinnye-nasosy' => 'img/popular/nasosy.jpg',
            'drenajnyie' => 'img/popular/nasosy.jpg',
            'poverhnostnyie' => 'img/popular/nasosy.jpg',
            'nasosnyie-stantsii' => 'img/popular/nasosy.jpg',
            'nasosy-povysheniya-davleniya' => 'img/popular/nasosy.jpg',
            'nasosy-dlya-kolodtsa' => 'img/popular/nasosy.jpg',
            'fekalnye-nasosy' => 'img/popular/nasosy.jpg',
            'kanalizatsionnye-nasosy' => 'img/popular/nasosy.jpg',
            'klimat' => 'img/popular/air.jpg',
            'radiatory' => 'img/popular/radiatory.jpg',
            'alyuminievye-radiatory' => 'img/popular/radiatory.jpg',
            'chugunnye-radiatory' => 'img/popular/radiatory.jpg',
            'stalnye-radiatory' => 'img/popular/radiatory.jpg',
            'bimetallicheskie-radiatory' => 'img/popular/radiatory.jpg',
            'vodyanye-konvektory' => 'img/popular/radiatory.jpg',
            'truby-i-fitingi' => 'img/popular/truby-i-fitingi.jpg',
            'polipropilenovye-truby' => 'img/popular/truby-i-fitingi.jpg',
            'polietilenovye-truby' => 'img/popular/truby-i-fitingi.jpg',
            'truby-iz-sshitogo-polietilena' => 'img/popular/truby-i-fitingi.jpg',
            'metalloplastikovye-truby' => 'img/popular/truby-i-fitingi.jpg',
            'kanalizatsionnye-truby' => 'img/popular/truby-i-fitingi.jpg',
            'gofrirovanye-truby' => 'img/popular/truby-i-fitingi.jpg',
            'truby-dlya-teplogo-vodyanogo-pola' => 'img/popular/truby-i-fitingi.jpg',
            'napornye-truby-iz-polietilena' => 'img/popular/truby-i-fitingi.jpg',
            'truby-zashchitnye' => 'img/popular/truby-i-fitingi.jpg',
            'fitingi-dlya-metalloplastikovykh-trub' => 'img/popular/truby-i-fitingi.jpg',
            'rezbovye-fitingi' => 'img/popular/truby-i-fitingi.jpg',
            'press-fitingi' => 'img/popular/truby-i-fitingi.jpg',
            'kompressionnye-fitingi' => 'img/popular/truby-i-fitingi.jpg',
            'krepleniya-dlya-trub' => 'img/popular/truby-i-fitingi.jpg',
            'sharovye-krany' => 'img/popular/truby-i-fitingi.jpg',
            'teplyj-pol' => 'img/popular/teplyj-pol.jpg',
            'nagrevatelnye-maty' => 'img/popular/teplyj-pol.jpg',
            'nagrevatelnye-kabeli' => 'img/popular/teplyj-pol.jpg',
            'ik-plenochnyj-pol' => 'img/popular/teplyj-pol.jpg',
            'podlozhka-pod-teplyj-pol' => 'img/popular/teplyj-pol.jpg',
            'teplyj-pol-pod-laminat' => 'img/popular/teplyj-pol.jpg',
            'teplyj-pol-pod-plitku' => 'img/popular/teplyj-pol.jpg',
            'vodyanoy-teplyy-pol' => 'img/popular/teplyj-pol.jpg',
            'termoregulyatory-dlya-teplogo-pola' => 'img/popular/teplyj-pol.jpg',
            'komplektuyushchie-dlya-teplogo-pola' => 'img/popular/teplyj-pol.jpg',
            'samoreguliruyushchiesya-kabeli' => 'img/popular/teplyj-pol.jpg',
            'elektricheskie-konvektoryi' => 'img/popular/elektricheskie-konvektoryi.jpg',
            'obogrevateli' => 'img/popular/elektricheskie-konvektoryi.jpg',
            'komplektuyushhie-dlya-otopleniya' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'komplekty-podklyucheniya' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'bloki-upravleniya' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'solnechnye-kollektory' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'istochniki-besperebojnogo-pitaniya' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'regulyatoryi-davleniya-gaza' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'elektricheskie-teny-dlya-otopleniya' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'montajnyie-komplektyi' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'gruppy-bystrogo-montazha-kotelnyx' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'membrannye-baki' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'bufernye-emkosti' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'akkumuliruyushhie-baki' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'grebenki' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'gidravlicheskie-razdeliteli-i-kollektory' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'regulyatory' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'radiatornaya-armatura' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'stabilizatory-napryazheniya' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'datchiki' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'signalizatory-zagazovannosti' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'schetchiki-gaza' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'teplonositeli' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'predokhranitelnaya-i-reguliruyushchaya-armatura' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'smesitelnaya-armatura' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'instrumenty-dlya-montazha' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'sistema-zashchity-ot-protechek' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'krany-i-zapornaya-armatura' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'smesitelnye-klapany-i-uzly' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'gruppy-bezopasnosti' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'germetiki-i-montazhnye-materialy' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'filtry' => 'img/popular/filtry.jpg',
        ][$slug] ?? null;
    }

    public function scopeRoot($query)
    {
        return $query->where('parent_id', 0);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
