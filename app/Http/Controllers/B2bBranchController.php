<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\PartnerBranch;
use App\Services\B2bCompanyAccess;
use App\Services\PartnerBranchModeration;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class B2bBranchController extends Controller
{
    public function store(Request $request, PartnerBranchModeration $moderation, ?PartnerBranch $branch = null)
    {
        $user = $request->user();
        $client = $user->activeB2bClient();
        abort_unless($client && $client->partner_type !== 'installer' && app(B2bCompanyAccess::class)->allows($user, 'profile.manage', $client), 403);
        abort_if($branch && (int) $branch->b2b_client_id !== (int) $client->id, 403);
        $data = $request->validate([
            'city_id' => ['required', Rule::exists('cities', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50', 'regex:/^\+?[\d\s()\-]{7,50}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'working_hours' => ['nullable', 'string', 'max:255'],
            'map_url' => ['nullable', 'url:http,https', 'max:1000'],
            'storefront_title' => ['nullable', 'string', 'max:255'],
            'storefront_intro' => ['nullable', 'string', 'max:1200'],
            'storefront_promo' => ['nullable', 'string', 'max:1500'],
            'hero' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'photos' => ['nullable', 'array', 'max:6'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $data['phones'] = [$data['phone']];
        if ($request->hasFile('hero')) {
            $data['hero_image'] = $request->file('hero')->store('partner-branches/submissions/'.$client->id, 'public');
        }
        if ($request->hasFile('photos')) {
            $data['gallery'] = collect($request->file('photos'))->map(fn ($file) => $file->store('partner-branches/submissions/'.$client->id, 'public'))->all();
        }
        $moderation->submit($client, $user, $data, $branch);

        return to_route('b2b.cabinet', ['section' => 'page'])->with('success', 'Магазин отправлен на проверку KOTLOV. После согласования данные появятся на сайте.');
    }

    public function preview(Request $request, PartnerBranch $branch)
    {
        $user = $request->user();
        $client = $user->activeB2bClient();
        $staff = $user->isManager();
        abort_unless($staff || ($client && (int) $client->id === (int) $branch->b2b_client_id
            && app(B2bCompanyAccess::class)->allows($user, 'profile.manage', $client)), 403);
        $primaryBranch = clone $branch;
        $primaryBranch->forceFill(array_intersect_key($branch->pending_changes ?? [], array_flip(PartnerBranchModeration::FIELDS)));
        $city = City::findOrFail($primaryBranch->city_id);

        return response()->view('pages.b2b.branch-preview', compact('primaryBranch', 'city'))
            ->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'private, no-store');
    }
}
