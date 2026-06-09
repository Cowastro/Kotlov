<?php

namespace App\Providers;

use App\Models\Brand;
use App\Models\Category;
use App\Events\NewOrderCreated;
use App\Listeners\HandleNewOrderCreated;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(NewOrderCreated::class, HandleNewOrderCreated::class);

        View::share('navCategories', $this->getNavCategories());
        View::share('navBrands', $this->getNavBrands());

        // Города по областям для попапа выбора города
        $cities = $this->getCitiesByRegion();
        View::share('obl1', $cities['Минская']);
        View::share('obl2', $cities['Гомельская']);
        View::share('obl3', $cities['Брестская']);
        View::share('obl4', $cities['Гродненская']);
        View::share('obl5', $cities['Могилёвская']);
        View::share('obl6', $cities['Витебская']);
    }


private function getCitiesByRegion(): array
{
    $all = [
        // Минская область
        ['slug' => 'berezino',       'name' => 'Березино',      'region' => 'Минская'],
        ['slug' => 'borisov',        'name' => 'Борисов',        'region' => 'Минская'],
        ['slug' => 'vileyka',        'name' => 'Вилейка',        'region' => 'Минская'],
        ['slug' => 'volojin',        'name' => 'Воложин',        'region' => 'Минская'],
        ['slug' => 'dzerjinsk',      'name' => 'Дзержинск',      'region' => 'Минская'],
        ['slug' => 'jodino',         'name' => 'Жодино',         'region' => 'Минская'],
        ['slug' => 'zaslavl',        'name' => 'Заславль',       'region' => 'Минская'],
        ['slug' => 'kletsk',         'name' => 'Клецк',          'region' => 'Минская'],
        ['slug' => 'kopyil',         'name' => 'Копыль',         'region' => 'Минская'],
        ['slug' => 'krupki',         'name' => 'Крупки',         'region' => 'Минская'],
        ['slug' => 'logoysk',        'name' => 'Логойск',        'region' => 'Минская'],
        ['slug' => 'lyuban',         'name' => 'Любань',         'region' => 'Минская'],
        ['slug' => 'marina-gorka',   'name' => 'Марьина Горка',  'region' => 'Минская'],
        ['slug' => '',               'name' => 'Минск',          'region' => 'Минская'], // главный домен
        ['slug' => 'molodechno',     'name' => 'Молодечно',      'region' => 'Минская'],
        ['slug' => 'myadel',         'name' => 'Мядель',         'region' => 'Минская'],
        ['slug' => 'nesvij',         'name' => 'Несвиж',         'region' => 'Минская'],
        ['slug' => 'slutsk',         'name' => 'Слуцк',          'region' => 'Минская'],
        ['slug' => 'smolevichi',     'name' => 'Смолевичи',      'region' => 'Минская'],
        ['slug' => 'soligorsk',      'name' => 'Солигорск',      'region' => 'Минская'],
        ['slug' => 'staryie-dorogi', 'name' => 'Старые Дороги',  'region' => 'Минская'],
        ['slug' => 'stolbtsyi',      'name' => 'Столбцы',        'region' => 'Минская'],
        ['slug' => 'uzda',           'name' => 'Узда',           'region' => 'Минская'],
        ['slug' => 'cherven',        'name' => 'Червень',        'region' => 'Минская'],
        // Гомельская область
        ['slug' => 'buda-koshelevo', 'name' => 'Буда-Кошелёво', 'region' => 'Гомельская'],
        ['slug' => 'vasilevichi',    'name' => 'Василевичи',    'region' => 'Гомельская'],
        ['slug' => 'vetka',          'name' => 'Ветка',         'region' => 'Гомельская'],
        ['slug' => 'gomel',          'name' => 'Гомель',        'region' => 'Гомельская'],
        ['slug' => 'dobrush',        'name' => 'Добруш',        'region' => 'Гомельская'],
        ['slug' => 'elsk',           'name' => 'Ельск',         'region' => 'Гомельская'],
        ['slug' => 'jitkovichi',     'name' => 'Житковичи',     'region' => 'Гомельская'],
        ['slug' => 'jlobin',         'name' => 'Жлобин',        'region' => 'Гомельская'],
        ['slug' => 'kalinkovichi',   'name' => 'Калинковичи',   'region' => 'Гомельская'],
        ['slug' => 'mozyir',         'name' => 'Мозырь',        'region' => 'Гомельская'],
        ['slug' => 'narovlya',       'name' => 'Наровля',       'region' => 'Гомельская'],
        ['slug' => 'petrikov',       'name' => 'Петриков',      'region' => 'Гомельская'],
        ['slug' => 'rechitsa',       'name' => 'Речица',        'region' => 'Гомельская'],
        ['slug' => 'rogachev',       'name' => 'Рогачёв',       'region' => 'Гомельская'],
        ['slug' => 'svetlogorsk',    'name' => 'Светлогорск',   'region' => 'Гомельская'],
        ['slug' => 'turov',          'name' => 'Туров',         'region' => 'Гомельская'],
        ['slug' => 'hoyniki',        'name' => 'Хойники',       'region' => 'Гомельская'],
        ['slug' => 'chechersk',      'name' => 'Чечерск',       'region' => 'Гомельская'],
        // Брестская область
        ['slug' => 'baranovichi',    'name' => 'Барановичи',    'region' => 'Брестская'],
        ['slug' => 'beloozersk',     'name' => 'Белоозёрск',    'region' => 'Брестская'],
        ['slug' => 'bereza',         'name' => 'Берёза',        'region' => 'Брестская'],
        ['slug' => 'brest',          'name' => 'Брест',         'region' => 'Брестская'],
        ['slug' => 'vyisokoe',       'name' => 'Высокое',       'region' => 'Брестская'],
        ['slug' => 'gantsevichi',    'name' => 'Ганцевичи',     'region' => 'Брестская'],
        ['slug' => 'david-gorodok',  'name' => 'Давид-Городок', 'region' => 'Брестская'],
        ['slug' => 'drogichin',      'name' => 'Дрогичин',      'region' => 'Брестская'],
        ['slug' => 'jabinka',        'name' => 'Жабинка',       'region' => 'Брестская'],
        ['slug' => 'ivanovo',        'name' => 'Иваново',       'region' => 'Брестская'],
        ['slug' => 'ivatsevichi',    'name' => 'Ивацевичи',     'region' => 'Брестская'],
        ['slug' => 'kamenets',       'name' => 'Каменец',       'region' => 'Брестская'],
        ['slug' => 'kobrin',         'name' => 'Кобрин',        'region' => 'Брестская'],
        ['slug' => 'luninets',       'name' => 'Лунинец',       'region' => 'Брестская'],
        ['slug' => 'lyahovichi',     'name' => 'Ляховичи',      'region' => 'Брестская'],
        ['slug' => 'malorita',       'name' => 'Малорита',      'region' => 'Брестская'],
        ['slug' => 'mikashevichi',   'name' => 'Микашевичи',    'region' => 'Брестская'],
        ['slug' => 'pinsk',          'name' => 'Пинск',         'region' => 'Брестская'],
        ['slug' => 'prujanyi',       'name' => 'Пружаны',       'region' => 'Брестская'],
        ['slug' => 'stolin',         'name' => 'Столин',        'region' => 'Брестская'],
        // Гродненская область
        ['slug' => 'berezovka',      'name' => 'Берёзовка',     'region' => 'Гродненская'],
        ['slug' => 'volkovyisk',     'name' => 'Волковыск',     'region' => 'Гродненская'],
        ['slug' => 'grodno',         'name' => 'Гродно',        'region' => 'Гродненская'],
        ['slug' => 'dyatlovo',       'name' => 'Дятлово',       'region' => 'Гродненская'],
        ['slug' => 'ive',            'name' => 'Ивье',          'region' => 'Гродненская'],
        ['slug' => 'lida',           'name' => 'Лида',          'region' => 'Гродненская'],
        ['slug' => 'mostyi',         'name' => 'Мосты',         'region' => 'Гродненская'],
        ['slug' => 'novogrudok',     'name' => 'Новогрудок',    'region' => 'Гродненская'],
        ['slug' => 'ostrovets',      'name' => 'Островец',      'region' => 'Гродненская'],
        ['slug' => 'oshmyanyi',      'name' => 'Ошмяны',        'region' => 'Гродненская'],
        ['slug' => 'svisloch',       'name' => 'Свислочь',      'region' => 'Гродненская'],
        ['slug' => 'skidel',         'name' => 'Скидель',       'region' => 'Гродненская'],
        ['slug' => 'slonim',         'name' => 'Слоним',        'region' => 'Гродненская'],
        ['slug' => 'smorgon',        'name' => 'Сморгонь',      'region' => 'Гродненская'],
        ['slug' => 'schuchin',       'name' => 'Щучин',         'region' => 'Гродненская'],
        // Могилёвская область
        ['slug' => 'belyinichi',     'name' => 'Белыничи',      'region' => 'Могилёвская'],
        ['slug' => 'bobruysk',       'name' => 'Бобруйск',      'region' => 'Могилёвская'],
        ['slug' => 'byihov',         'name' => 'Быхов',         'region' => 'Могилёвская'],
        ['slug' => 'gorki',          'name' => 'Горки',         'region' => 'Могилёвская'],
        ['slug' => 'kirovsk',        'name' => 'Кировск',       'region' => 'Могилёвская'],
        ['slug' => 'klimovichi',     'name' => 'Климовичи',     'region' => 'Могилёвская'],
        ['slug' => 'klichev',        'name' => 'Кличев',        'region' => 'Могилёвская'],
        ['slug' => 'kostyukovichi',  'name' => 'Костюковичи',   'region' => 'Могилёвская'],
        ['slug' => 'krichev',        'name' => 'Кричев',        'region' => 'Могилёвская'],
        ['slug' => 'krugloe',        'name' => 'Круглое',       'region' => 'Могилёвская'],
        ['slug' => 'mogilev',        'name' => 'Могилёв',       'region' => 'Могилёвская'],
        ['slug' => 'mstislavl',      'name' => 'Мстиславль',    'region' => 'Могилёвская'],
        ['slug' => 'osipovichi',     'name' => 'Осиповичи',     'region' => 'Могилёвская'],
        ['slug' => 'slavgorod',      'name' => 'Славгород',     'region' => 'Могилёвская'],
        ['slug' => 'chausyi',        'name' => 'Чаусы',         'region' => 'Могилёвская'],
        ['slug' => 'cherikov',       'name' => 'Чериков',       'region' => 'Могилёвская'],
        ['slug' => 'shklov',         'name' => 'Шклов',         'region' => 'Могилёвская'],
        // Витебская область
        ['slug' => 'baran',          'name' => 'Барань',        'region' => 'Витебская'],
        ['slug' => 'braslav',        'name' => 'Браслав',       'region' => 'Витебская'],
        ['slug' => 'verhnedvinsk',   'name' => 'Верхнедвинск',  'region' => 'Витебская'],
        ['slug' => 'vitebsk',        'name' => 'Витебск',       'region' => 'Витебская'],
        ['slug' => 'glubokoe',       'name' => 'Глубокое',      'region' => 'Витебская'],
        ['slug' => 'gorodok',        'name' => 'Городок',       'region' => 'Витебская'],
        ['slug' => 'disna',          'name' => 'Дисна',         'region' => 'Витебская'],
        ['slug' => 'dokshitsyi',     'name' => 'Докшицы',       'region' => 'Витебская'],
        ['slug' => 'dubrovno',       'name' => 'Дубровно',      'region' => 'Витебская'],
        ['slug' => 'lepel',          'name' => 'Лепель',        'region' => 'Витебская'],
        ['slug' => 'mioryi',         'name' => 'Миоры',         'region' => 'Витебская'],
        ['slug' => 'novolukoml',     'name' => 'Новолукомль',   'region' => 'Витебская'],
        ['slug' => 'novopolotsk',    'name' => 'Новополоцк',    'region' => 'Витебская'],
        ['slug' => 'orsha',          'name' => 'Орша',          'region' => 'Витебская'],
        ['slug' => 'polotsk',        'name' => 'Полоцк',        'region' => 'Витебская'],
        ['slug' => 'postavyi',       'name' => 'Поставы',       'region' => 'Витебская'],
        ['slug' => 'senno',          'name' => 'Сенно',         'region' => 'Витебская'],
        ['slug' => 'tolochin',       'name' => 'Толочин',       'region' => 'Витебская'],
        ['slug' => 'chashniki',      'name' => 'Чашники',       'region' => 'Витебская'],
    ];

    $grouped = ['Минская' => [], 'Гомельская' => [], 'Брестская' => [],
                'Гродненская' => [], 'Могилёвская' => [], 'Витебская' => []];

    foreach ($all as $city) {
        $link = $city['slug'] ? "https://{$city['slug']}.kotlov.by" : 'https://kotlov.by';
        $grouped[$city['region']][] = [
            'name'   => $city['name'],
            'link'   => $link,
            'active' => '',
        ];
    }

    return $grouped;
}

private function getNavBrands(): \Illuminate\Support\Collection
{
    return Brand::active()
        ->orderBy('sort_order')
        ->get(['name', 'slug'])
        ->keyBy('slug');
}

private function getNavCategories()
{
    return Category::query()
        ->where('parent_id', 0)
        ->where('is_active', true)
        ->with(['children' => function ($q) {
            $q->where('is_active', true)
              ->orderBy('sort_order');
        }])
        ->orderBy('sort_order')
        ->get();
}
}