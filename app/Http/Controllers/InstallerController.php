<?php

namespace App\Http\Controllers;

use App\Models\InstallerProfile;
use App\Models\InstallerWork;
use Illuminate\Http\Request;

class InstallerController extends Controller
{
    public function index(Request $request)
    {
        $regions = [
            'Минск'               => 'Минск',
            'Минская область'     => 'Минская область',
            'Гомельская область'  => 'Гомельская область',
            'Гродненская область' => 'Гродненская область',
            'Брестская область'   => 'Брестская область',
            'Витебская область'   => 'Витебская область',
            'Могилёвская область' => 'Могилёвская область',
        ];

        $cities = [
            'Минск' => 'Минск',
            'Брест' => 'Брест',
            'Витебск' => 'Витебск',
            'Гомель' => 'Гомель',
            'Гродно' => 'Гродно',
            'Могилёв' => 'Могилёв',
            'Барановичи' => 'Барановичи',
            'Бобруйск' => 'Бобруйск',
            'Борисов' => 'Борисов',
            'Молодечно' => 'Молодечно',
            'Солигорск' => 'Солигорск',
        ];

        $specializations = [
            'heating'       => 'Монтаж котлов',
            'heatpump'      => 'Монтаж тепловых насосов',
            'fireplace'     => 'Камины и печи',
            'chimney'       => 'Дымоходы',
            'sauna'         => 'Банные печи',
            'service'       => 'Сервис и ремонт',
            'commissioning' => 'Пусконаладка',
        ];

        $ratings = [
            '4'   => 'от 4 ★',
            '4.5' => 'от 4,5 ★',
            '5'   => '5 ★',
        ];

        $experienceOptions = [
            '5' => 'от 5 лет',
            '10' => 'от 10 лет',
            '20' => 'от 20 лет',
        ];

        $query = InstallerProfile::query()
            ->where('is_published', true)
            ->where('status', 'active')
            ->whereNotNull('slug')
            ->whereNotNull('specializations')
            ->where(function ($q) {
                $q->whereNotNull('contact_name')->orWhereNotNull('company_name');
            })
            ->withCount([
                'works' => fn ($q) => $q->where('is_published', true),
            ]);

        if ($request->filled('q')) {
            $search = mb_substr(trim((string) $request->input('q')), 0, 100);
            $query->where(function ($q) use ($search) {
                $q->where('contact_name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('region', 'like', "%{$search}%");
            });
        }

        // ── Фильтр: регион ───────────────────────────────────────────────
        if ($request->filled('region') && isset($regions[$request->input('region')])) {
            $region = $request->region;
            $query->where(function ($q) use ($region) {
                $q->where('region', $region)
                  ->orWhere('nationwide', true)
                  ->orWhereJsonContains('work_regions', $region);
            });
        }

        // ── Фильтр: город ────────────────────────────────────────────────
        if ($request->filled('city') && isset($cities[$request->input('city')])) {
            $city = $request->city;
            $query->where(function ($q) use ($city) {
                $q->where('city', $city)
                  ->orWhere('nationwide', true)
                  ->orWhereJsonContains('work_cities', $city);
            });
        }

        // ── Фильтр: специализация ─────────────────────────────────────────
        if ($request->filled('specialization') && isset($specializations[$request->input('specialization')])) {
            $query->whereJsonContains('specializations', $request->specialization);
        }

        // ── Фильтр: рейтинг ──────────────────────────────────────────────
        if ($request->filled('rating') && isset($ratings[$request->input('rating')])) {
            $query->where('rating', '>=', (float) $request->rating);
        }

        if ($request->filled('experience') && isset($experienceOptions[$request->input('experience')])) {
            $query->where('experience_years', '>=', (int) $request->input('experience'));
        }

        if ($request->boolean('verified')) {
            $query->where('is_verified', true);
        }

        if ($request->boolean('nationwide')) {
            $query->where('nationwide', true);
        }

        // ── Сортировка ────────────────────────────────────────────────────
        $sort = $request->input('sort', 'recommended');
        if ($sort === 'rating') {
            $query->orderByDesc('rating')->orderByDesc('reviews_count');
        } elseif ($sort === 'experience') {
            $query->orderByDesc('experience_years');
        } else {
            $query->orderByDesc('is_verified')
                ->orderByDesc('works_count')
                ->orderByDesc('rating')
                ->orderByDesc('reviews_count');
        }

        $query->orderByDesc('created_at');

        $installers = $query->paginate(12)->withQueryString();

        $installersCount = InstallerProfile::where('is_published', true)->where('status', 'active')->count();
        $worksCount      = InstallerWork::where('is_published', true)->count();
        $reviewsCount    = InstallerProfile::where('is_published', true)->sum('reviews_count');

        return view('pages.installers', compact(
            'installers',
            'regions',
            'cities',
            'specializations',
            'ratings',
            'experienceOptions',
            'installersCount',
            'worksCount',
            'reviewsCount'
        ));
    }

    public function show(string $slug)
    {
        $installer = InstallerProfile::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->where('status', 'active')
            ->with([
                'works' => fn ($q) => $q->where('is_published', true)
                                        ->orderByDesc('completed_at'),
                'reviews' => fn ($q) => $q->where('is_approved', true)
                                          ->latest(),
                'user',
            ])
            ->firstOrFail();

        $specLabels = [
            'heating'       => 'Монтаж котлов',
            'heatpump'      => 'Монтаж тепловых насосов',
            'fireplace'     => 'Монтаж каминов и печей',
            'chimney'       => 'Монтаж дымоходов',
            'sauna'         => 'Монтаж банных печей',
            'service'       => 'Сервис котлов',
            'commissioning' => 'Пусконаладка',
        ];

        return view('pages.installer-profile', compact('installer', 'specLabels'));
    }
}
