<?php

namespace App\Http\Controllers;

use App\Models\InstallerProfile;
use App\Models\InstallerWork;
use Illuminate\Http\Request;

class InstallerController extends Controller
{
    public function index(Request $request)
    {
        $query = InstallerProfile::query()
            ->where('is_published', true)
            ->where('status', 'active')
            ->withCount('works');

        // ── Фильтр: регион ───────────────────────────────────────────────
        if ($request->filled('region')) {
            $region = $request->region;
            $query->where(function ($q) use ($region) {
                $q->where('region', $region)
                  ->orWhere('nationwide', true)
                  ->orWhereJsonContains('work_regions', $region);
            });
        }

        // ── Фильтр: город ────────────────────────────────────────────────
        if ($request->filled('city')) {
            $city = $request->city;
            $query->where(function ($q) use ($city) {
                $q->where('city', $city)
                  ->orWhere('nationwide', true)
                  ->orWhereJsonContains('work_cities', $city);
            });
        }

        // ── Фильтр: специализация ─────────────────────────────────────────
        if ($request->filled('specialization')) {
            $query->whereJsonContains('specializations', $request->specialization);
        }

        // ── Фильтр: рейтинг ──────────────────────────────────────────────
        if ($request->filled('rating')) {
            $query->where('rating', '>=', (float) $request->rating);
        }

        // ── Сортировка ────────────────────────────────────────────────────
        $query->orderByDesc('is_verified')
              ->orderByDesc('rating')
              ->orderByDesc('reviews_count')
              ->orderByDesc('created_at');

        $installers = $query->paginate(12)->withQueryString();

        $regions = [
            'Минск'               => 'Минск',
            'Минская область'     => 'Минская область',
            'Гомельская область'  => 'Гомельская область',
            'Гродненская область' => 'Гродненская область',
            'Брестская область'   => 'Брестская область',
            'Витебская область'   => 'Витебская область',
            'Могилёвская область' => 'Могилёвская область',
        ];

        $specializations = [
            'heating'       => 'Монтаж котлов',
            'heatpump'      => 'Монтаж тепловых насосов',
            'fireplace'     => 'Монтаж каминов',
            'chimney'       => 'Монтаж дымоходов',
            'sauna'         => 'Монтаж банных печей',
            'service'       => 'Сервис котлов',
            'commissioning' => 'Пусконаладка',
        ];

        $ratings = [
            '4'   => 'от 4 ★',
            '4.5' => 'от 4.5 ★',
            '5'   => '5 ★',
        ];

        $installersCount = InstallerProfile::where('is_published', true)->where('status', 'active')->count();
        $worksCount      = InstallerWork::count();
        $reviewsCount    = InstallerProfile::where('is_published', true)->sum('reviews_count');

        return view('pages.installers', compact(
            'installers',
            'regions',
            'specializations',
            'ratings',
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
