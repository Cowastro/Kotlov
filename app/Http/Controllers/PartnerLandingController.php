<?php

namespace App\Http\Controllers;

use App\Models\PartnerProgramLevel;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartnerLandingController extends Controller
{
    public function business(Request $request): View
    {
        return view('pages.partners', $this->seo('/partners', 'Для бизнеса') + [
            'levels' => PartnerProgramLevel::query()->where('is_active', true)->orderBy('rank')->get(),
            'partnerType' => $request->query('type') === 'installer' ? 'installer' : 'wholesale',
        ]);
    }

    public function installer(): View
    {
        return view('pages.become-installer', $this->seo('/become-installer', 'Стать монтажником', [
            ['Монтажники', '/installers'],
        ]));
    }

    private function seo(string $path, string $name, array $parents = []): array
    {
        $base = 'https://kotlov.by';
        $canonical = $base.$path;
        $items = [];
        foreach ([['Главная', '/'], ...$parents, [$name, $path]] as $index => [$label, $url]) {
            $items[] = ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $label, 'item' => $base.$url];
        }

        $breadcrumbJson = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return compact('canonical', 'breadcrumbJson');
    }
}
