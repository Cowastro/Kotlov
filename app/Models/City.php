<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class City extends Model
{
    protected $fillable = ['slug', 'name', 'name_in', 'name_title', 'region', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

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
        $city = Schema::hasTable((new static)->getTable())
            ? static::where('slug', $slug)->where('is_active', true)->first()
            : null;

        if ($city) {
            return $city;
        }

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
