<?php

namespace App\Http\Controllers;

use App\Models\InstallRequest;
use App\Models\InstallerProfile;
use App\Models\User;
use App\Notifications\NewInstallRequestNotification;
use Illuminate\Http\Request;

class InstallRequestController extends Controller
{
    public function create(Request $request)
    {
        $installer = null;

        if ($request->filled('installer')) {
            $installer = InstallerProfile::query()
                ->where('id', $request->integer('installer'))
                ->where('is_published', true)
                ->where('status', 'active')
                ->first();
        }

        $specializations = [
            'heating'       => 'Монтаж котла',
            'heatpump'      => 'Монтаж теплового насоса',
            'fireplace'     => 'Монтаж камина',
            'chimney'       => 'Монтаж дымохода',
            'sauna'         => 'Монтаж банной печи',
            'service'       => 'Сервис',
            'commissioning' => 'Пусконаладка',
            'other'         => 'Другое',
        ];

        $regions = [
            'Минск'               => 'Минск',
            'Минская область'     => 'Минская область',
            'Гомельская область'  => 'Гомельская область',
            'Гродненская область' => 'Гродненская область',
            'Брестская область'   => 'Брестская область',
            'Витебская область'   => 'Витебская область',
            'Могилёвская область' => 'Могилёвская область',
        ];

        return view('pages.install-request', compact('installer', 'specializations', 'regions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name'       => 'required|string|max:255',
            'customer_phone'      => 'required|string|max:50',
            'customer_email'      => 'nullable|email|max:255',
            'city'                => 'nullable|string|max:255',
            'region'              => 'nullable|string|max:255',
            'address'             => 'nullable|string|max:255',
            'specialization'      => 'nullable|string|max:255',
            'description'         => 'nullable|string',
            'preferred_date'      => 'nullable|date',
            'budget'              => 'nullable|numeric|min:0',
            'installer_profile_id' => 'nullable|integer',
        ], [
            'customer_name.required'  => 'Укажите ваше имя.',
            'customer_phone.required' => 'Укажите номер телефона.',
            'email.email'             => 'Введите корректный email.',
            'preferred_date.date'     => 'Некорректная дата.',
            'budget.numeric'          => 'Бюджет должен быть числом.',
        ]);

        // Проверяем монтажника отдельно (must be active + published)
        $installerProfileId = null;
        if (!empty($validated['installer_profile_id'])) {
            $exists = InstallerProfile::where('id', $validated['installer_profile_id'])
                ->where('is_published', true)
                ->where('status', 'active')
                ->exists();
            if ($exists) {
                $installerProfileId = $validated['installer_profile_id'];
            }
        }

        $installRequest = InstallRequest::create([
            'customer_name'        => $validated['customer_name'],
            'customer_phone'       => $validated['customer_phone'],
            'customer_email'       => $validated['customer_email'] ?? null,
            'city'                 => $validated['city'] ?? null,
            'region'               => $validated['region'] ?? null,
            'address'              => $validated['address'] ?? null,
            'specialization'       => $validated['specialization'] ?? null,
            'description'          => $validated['description'] ?? null,
            'preferred_date'       => $validated['preferred_date'] ?? null,
            'budget'               => $validated['budget'] ?? null,
            'installer_profile_id' => $installerProfileId,
            'source'               => $installerProfileId ? 'installer_profile' : 'installers_page',
            'status'               => 'new',
        ]);

        User::where('role', 'admin')->each(
            fn (User $admin) => $admin->notify(new NewInstallRequestNotification($installRequest))
        );

        return redirect()
            ->route('install-requests.create', $installerProfileId ? ['installer' => $installerProfileId] : [])
            ->with('success', 'Заявка отправлена. Мы свяжемся с вами для уточнения деталей.');
    }
}
