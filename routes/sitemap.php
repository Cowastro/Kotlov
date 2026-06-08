<?php

use App\Models\Brand;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\InstallerProfile;
use App\Models\Product;

Route::get('/sitemap.xml', function () {
    $xml = \Illuminate\Support\Facades\Cache::remember('sitemap.xml', 86400, function () {
        $baseUrl = 'https://kotlov.by';
        $urls = collect();

        $addUrl = function (string $path) use (&$urls, $baseUrl) {
            $path = '/' . ltrim($path, '/');
            $urls->push($baseUrl . ($path === '/' ? '' : $path));
        };

        foreach ([
            '/', '/about', '/dostavka', '/reviews', '/catalog',
            '/brands', '/akcii', '/contacts', '/installers', '/become-installer',
            '/partners', '/suppliers', '/faq', '/privacy', '/blog',
        ] as $path) {
            $addUrl($path);
        }

        Category::active()->whereNotNull('slug')->orderBy('sort_order')->orderBy('id')
            ->get(['slug'])->each(fn ($c) => $addUrl($c->slug));

        Product::active()
            ->with('category:id,slug,is_active')
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->orderBy('id')
            ->chunk(500, function ($products) use ($addUrl) {
                foreach ($products as $product) {
                    if ($product->category?->slug && $product->slug) {
                        $addUrl($product->category->slug . '/' . $product->slug);
                    }
                }
            });

        Brand::active()->whereNotNull('slug')->orderBy('name')
            ->get(['slug'])->each(fn ($b) => $addUrl('brands/' . strtolower($b->slug)));

        BlogPost::published()->whereNotNull('slug')->orderByDesc('published_at')
            ->get(['slug'])->each(fn ($p) => $addUrl('blog/' . $p->slug));

        InstallerProfile::query()->where('is_published', true)->whereNotNull('slug')
            ->orderBy('id')->get(['slug'])
            ->each(fn ($i) => $addUrl('installers/' . $i->slug));

        $doc = new DOMDocument('1.0', 'UTF-8');
        $urlset = $doc->createElement('urlset');
        $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $doc->appendChild($urlset);

        foreach ($urls->unique()->values() as $url) {
            $urlNode = $doc->createElement('url');
            $locNode = $doc->createElement('loc');
            $locNode->appendChild($doc->createTextNode($url));
            $urlNode->appendChild($locNode);
            $urlset->appendChild($urlNode);
        }

        return $doc->saveXML();
    });

    return response($xml, 200)
        ->header('Content-Type', 'application/xml; charset=UTF-8')
        ->header('Cache-Control', 'public, max-age=86400');
})->name('sitemap');
