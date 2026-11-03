<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\PartnerOnboarding;
use App\Services\PartnerOnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PartnerOnboardingController extends Controller
{
    public function show(string $token, PartnerOnboardingService $service): Response
    {
        $onboarding = $this->find($token);
        $service->markOpened($onboarding);

        // A personal link: never indexed.
        return response()->view('pages.partner-onboarding', [
            'onboarding' => $onboarding,
            'cities' => City::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'data' => $onboarding->data ?? [],
        ])->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function submit(Request $request, string $token, PartnerOnboardingService $service): RedirectResponse
    {
        $onboarding = $this->find($token);
        abort_unless($onboarding->isEditableByPartner(), 410);

        $request->merge(['points' => collect($request->input('points', []))
            ->filter(fn ($point): bool => is_array($point) && filled($point['city_id'] ?? null))
            ->values()->all()]);
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'unp' => ['required', 'digits:9'],
            'legal_address' => ['nullable', 'string', 'max:500'],
            'partner_type' => ['required', 'in:wholesale,installer'],
            'contact_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'points' => [$request->input('partner_type') === 'wholesale' ? 'required' : 'nullable', 'array', 'max:20'],
            'points.*.city_id' => ['required', 'integer', 'exists:cities,id'],
            'points.*.name' => ['nullable', 'string', 'max:255'],
            'points.*.address' => ['required', 'string', 'max:255'],
            'points.*.phones' => ['required', 'string', 'max:255'],
            'points.*.working_hours' => ['nullable', 'string', 'max:255'],
        ], [
            'unp.digits' => 'УНП — 9 цифр.',
            'points.required' => 'Добавьте хотя бы одну точку продаж.',
            'points.*.address.required' => 'Укажите адрес точки продаж.',
            'points.*.phones.required' => 'Укажите телефон точки продаж.',
        ]);

        $data['email'] = mb_strtolower(trim($data['email']));
        $data['points'] = collect($data['points'] ?? [])->map(fn (array $point): array => [
            'city_id' => (int) $point['city_id'],
            'name' => trim((string) ($point['name'] ?? '')) ?: null,
            'address' => trim($point['address']),
            'phones' => collect(preg_split('/[,;\n]+/', $point['phones']))->map(fn ($phone) => trim($phone))->filter()->values()->all(),
            'working_hours' => trim((string) ($point['working_hours'] ?? '')) ?: null,
        ])->all();

        $service->submit($onboarding, $data);

        return to_route('partner-onboarding.show', $token)->with('success', 'Спасибо! Анкета отправлена менеджеру KOTLOV — после проверки мы откроем доступ и пришлём письмо.');
    }

    private function find(string $token): PartnerOnboarding
    {
        return PartnerOnboarding::query()->where('token', $token)->firstOrFail();
    }
}
