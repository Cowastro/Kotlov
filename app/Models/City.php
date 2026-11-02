<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class City extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'name_in',
        'name_title',
        'region',
        'is_active',
        'storefront_enabled',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'storefront_enabled' => 'boolean',
    ];

    private const KNOWN_CITY_SLUGS = [
        'baran', 'baranovichi', 'beloozersk', 'belyinichi', 'bereza', 'berezino',
        'berezovka', 'bobruysk', 'borisov', 'braslav', 'brest', 'buda-koshelevo',
        'byihov', 'chashniki', 'chausyi', 'chechersk', 'cherikov', 'cherven',
        'david-gorodok', 'disna', 'dobrush', 'dokshitsyi', 'drogichin', 'dubrovno',
        'dyatlovo', 'dzerjinsk', 'elsk', 'gantsevichi', 'glubokoe', 'gomel',
        'gorki', 'gorodok', 'grodno', 'hoyniki', 'ivanovo', 'ivatsevichi', 'ive',
        'jabinka', 'jitkovichi', 'jlobin', 'jodino', 'kalinkovichi', 'kamenets',
        'kirovsk', 'kletsk', 'klichev', 'klimovichi', 'kobrin', 'kopyil',
        'kostyukovichi', 'krichev', 'krugloe', 'krupki', 'lepel', 'lida',
        'logoysk', 'luninets', 'lyahovichi', 'lyuban', 'malorita', 'marina-gorka',
        'mikashevichi', 'mioryi', 'mogilev', 'molodechno', 'mostyi', 'mozyir',
        'mstislavl', 'myadel', 'narovlya', 'nesvij', 'novogrudok', 'novolukoml',
        'novopolotsk', 'orsha', 'oshmyanyi', 'osipovichi', 'ostrovets', 'petrikov',
        'pinsk', 'polotsk', 'postavyi', 'prujanyi', 'rechitsa', 'rogachev',
        'schuchin', 'senno', 'shklov', 'skidel', 'slavgorod', 'slonim', 'slutsk',
        'smolevichi', 'smorgon', 'soligorsk', 'staryie-dorogi', 'stolbtsyi',
        'stolin', 'svetlogorsk', 'svisloch', 'tolochin', 'turov', 'uzda',
        'vasilevichi', 'verhnedvinsk', 'vetka', 'vileyka', 'vitebsk', 'volkovyisk',
        'volojin', 'vyisokoe', 'zaslavl',
    ];

    public static function findBySlug(string $slug): ?self
    {
        if (Schema::hasTable((new static)->getTable())) {
            $city = static::where('slug', $slug)->first();

            // A persisted city is the source of truth. Falling back to the
            // legacy slug list for an explicitly disabled row made it
            // impossible to take a city storefront out of service safely.
            if ($city) {
                if (! $city->is_active) {
                    return null;
                }

                if (Schema::hasColumn($city->getTable(), 'storefront_enabled')
                    && ! $city->storefront_enabled) {
                    return null;
                }

                return $city;
            }

            return null;
        }

        // Keep the legacy list only as a bootstrap fallback for installations
        // where the cities table has not been created yet.
        if (! in_array($slug, self::KNOWN_CITY_SLUGS, true)) {
            return null;
        }

        $name = str($slug)->replace('-', ' ')->title()->toString();

        return new self([
            'slug' => $slug,
            'name' => $name,
            'name_in' => 'в ' . $name,
            'name_title' => $name,
            'region' => null,
            'is_active' => true,
        ]);
    }
}
