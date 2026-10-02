<?php

namespace App\Http\Controllers;

use App\Models\InstallerProfile;
use App\Models\InstallerWork;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InstallerAccountController extends Controller
{
    private const SPECIALIZATIONS = [
        'heating' => 'Системы отопления и котельные',
        'heatpump' => 'Монтаж тепловых насосов',
        'radiators' => 'Монтаж радиаторов',
        'solid_fuel' => 'Твердотопливные котлы',
        'pellet' => 'Пеллетные котлы',
        'underfloor' => 'Тёплые полы',
        'fireplace' => 'Камины и печи',
        'chimney' => 'Дымоходы',
        'sauna' => 'Банные печи',
        'service' => 'Сервис и ремонт',
        'commissioning' => 'Пусконаладка',
    ];

    private const WORK_TYPES = [
        'heating' => 'Монтаж котла',
        'heatpump' => 'Тепловой насос',
        'radiators' => 'Радиаторное отопление',
        'solid_fuel' => 'Твердотопливный котёл',
        'pellet' => 'Пеллетный котёл',
        'underfloor' => 'Тёплый пол',
        'fireplace' => 'Камин / печь',
        'chimney' => 'Дымоход',
        'sauna' => 'Баня / сауна',
        'service' => 'Сервис',
        'commissioning' => 'Пусконаладка',
        'other' => 'Другое',
    ];

    private const REGIONS = [
        'Минск',
        'Минская область',
        'Гомельская область',
        'Гродненская область',
        'Брестская область',
        'Витебская область',
        'Могилёвская область',
    ];

    public function edit(Request $request): View
    {
        $profile = $this->profile($request)
            ->load(['works' => fn ($query) => $query->latest('completed_at')->latest('id')]);

        return view('pages.account-installer-profile', [
            'user' => $request->user(),
            'profile' => $profile,
            'specializations' => self::SPECIALIZATIONS,
            'workTypes' => self::WORK_TYPES,
            'regions' => self::REGIONS,
        ]);
    }

    public function preview(Request $request, InstallerProfile $profile): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $profile->load(['works' => fn ($query) => $query->latest('completed_at')->latest('id')]);

        return view('pages.account-installer-profile', [
            'user' => $profile->user,
            'profile' => $profile,
            'specializations' => self::SPECIALIZATIONS,
            'workTypes' => self::WORK_TYPES,
            'regions' => self::REGIONS,
            'previewMode' => true,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $profile = $this->profile($request);

        $validated = $request->validate([
            'contact_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:50'],
            'additional_phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'telegram' => ['nullable', 'string', 'max:100'],
            'viber' => ['nullable', 'string', 'max:100'],
            'whatsapp' => ['nullable', 'string', 'max:100'],
            'company_name' => ['nullable', 'string', 'max:200'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:3000'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:70'],
            'price_from' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'city' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', Rule::in(self::REGIONS)],
            'work_regions_text' => ['nullable', 'string', 'max:1000'],
            'work_cities_text' => ['nullable', 'string', 'max:1000'],
            'work_radius_km' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'nationwide' => ['nullable', 'boolean'],
            'specializations' => ['nullable', 'array', 'max:8'],
            'specializations.*' => ['string', Rule::in(array_keys(self::SPECIALIZATIONS))],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        unset($validated['photo'], $validated['work_regions_text'], $validated['work_cities_text']);

        $validated['nationwide'] = $request->boolean('nationwide');
        $validated['specializations'] = array_values($validated['specializations'] ?? []);
        $validated['work_regions'] = $this->commaSeparated($request->input('work_regions_text'));
        $validated['work_cities'] = $this->commaSeparated($request->input('work_cities_text'));

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store("installer-profiles/{$profile->id}/profile", 'public');
        }

        $profile->update($validated);

        return back()->with('success', 'Профиль монтажника обновлён.');
    }

    public function storeWork(Request $request): RedirectResponse
    {
        $profile = $this->profile($request);
        $validated = $this->validateWork($request);
        $validated['installer_profile_id'] = $profile->id;
        $validated['is_published'] = $request->boolean('is_published');
        $validated['photos'] = $this->storeWorkPhotos($request, $profile);

        InstallerWork::create($validated);

        return back()->with('success', 'Работа добавлена в портфолио.');
    }

    public function updateWork(Request $request, InstallerWork $work): RedirectResponse
    {
        $profile = $this->profile($request);
        $work = $this->ownedWork($profile, $work);
        $validated = $this->validateWork($request);
        unset($validated['photos']);
        $validated['is_published'] = $request->boolean('is_published');

        $newPhotos = $this->storeWorkPhotos($request, $profile);
        if ($newPhotos !== []) {
            $validated['photos'] = array_values(array_merge($work->photos ?? [], $newPhotos));
        }

        $work->update($validated);

        return back()->with('success', 'Работа обновлена.');
    }

    public function destroyWork(Request $request, InstallerWork $work): RedirectResponse
    {
        $profile = $this->profile($request);
        $work = $this->ownedWork($profile, $work);

        foreach ($work->photos ?? [] as $photo) {
            if (str_starts_with($photo, "installer-profiles/{$profile->id}/")) {
                Storage::disk('public')->delete($photo);
            }
        }

        $work->delete();

        return back()->with('success', 'Работа удалена из портфолио.');
    }

    private function profile(Request $request): InstallerProfile
    {
        return $request->user()->installerProfile()->firstOrFail();
    }

    private function ownedWork(InstallerProfile $profile, InstallerWork $work): InstallerWork
    {
        return $profile->works()->whereKey($work->getKey())->firstOrFail();
    }

    private function validateWork(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'work_type' => ['nullable', Rule::in(array_keys(self::WORK_TYPES))],
            'city' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', Rule::in(self::REGIONS)],
            'equipment_type' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:150'],
            'completed_at' => ['nullable', 'date', 'before_or_equal:today'],
            'is_published' => ['nullable', 'boolean'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);
    }

    private function storeWorkPhotos(Request $request, InstallerProfile $profile): array
    {
        return collect($request->file('photos', []))
            ->map(fn ($photo) => $photo->store("installer-profiles/{$profile->id}/works", 'public'))
            ->values()
            ->all();
    }

    private function commaSeparated(?string $value): array
    {
        return collect(preg_split('/[,;\n]+/u', (string) $value))
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
