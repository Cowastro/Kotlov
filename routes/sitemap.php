<?php

use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Category;
use App\Models\InstallerProfile;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

Route::get('/sitemap.xml', function () {
    $xml = Cache::remember('sitemap.xml.v3', 86400, function () {
        $baseUrl = 'https://kotlov.by';
        $urls = [];

        $addUrl = function (string $path, $lastModified = null, ?string $image = null, ?string $imageTitle = null) use (&$urls, $baseUrl) {
            $path = '/'.ltrim($path, '/');
            $location = $baseUrl.($path === '/' ? '' : $path);

            $imageUrl = null;
            if ($image && ! str_contains($image, 'product-placeholder')) {
                $candidate = str_starts_with($image, 'http://') || str_starts_with($image, 'https://')
                    ? $image
                    : $baseUrl.'/'.ltrim($image, '/');

                if (parse_url($candidate, PHP_URL_HOST) === 'kotlov.by') {
                    $imageUrl = $candidate;
                }
            }

            $urls[$location] = [
                'location' => $location,
                'last_modified' => $lastModified?->toAtomString(),
                'image' => $imageUrl,
                'image_title' => $imageUrl ? $imageTitle : null,
            ];
        };

        foreach ([
            '/', '/about', '/dostavka', '/reviews', '/catalog',
            '/brands', '/akcii', '/akcii/kotlov-xo-ceramic-pro', '/akcii/kotlov-xo-evo-26', '/akcii/hotta-ceramik-20-30', '/contacts', '/installers', '/become-installer',
            '/montazh-teplovyh-nasosov', '/montazh-kaminov', '/partners', '/suppliers', '/faq', '/privacy', '/blog',
            '/teplovye-nasosy-r290', '/teplovye-nasosy-dlya-radiatorov',
            '/teplovye-nasosy-dlya-teplogo-pola', '/teplovye-nasosy-dlya-doma',
        ] as $path) {
            $addUrl($path);
        }

        Category::active()
            ->whereNotNull('slug')
            ->where('slug', '!=', 'aktsiiiskidki')
            ->orderBy('sort_order')->orderBy('id')
            ->get(['slug', 'updated_at'])->each(fn ($c) => $addUrl($c->slug, $c->updated_at));

        Product::active()->notArchived()
            ->with('category:id,slug,is_active')
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->orderBy('id')
            ->select(['id', 'category_id', 'slug', 'name', 'sku', 'images', 'updated_at'])
            ->chunk(500, function ($products) use ($addUrl) {
                foreach ($products as $product) {
                    if ($product->category?->slug && $product->slug) {
                        $addUrl(
                            $product->category->slug.'/'.$product->slug,
                            $product->updated_at,
                            $product->image_url,
                            $product->name
                        );
                    }
                }
            });

        Brand::active()->whereNotNull('slug')->orderBy('name')
            ->get(['slug', 'updated_at'])->each(fn ($b) => $addUrl('brands/'.strtolower($b->slug), $b->updated_at));

        BlogPost::published()->whereNotNull('slug')->orderByDesc('published_at')
            ->get(['slug', 'updated_at'])->each(fn ($p) => $addUrl('blog/'.$p->slug, $p->updated_at));

        InstallerProfile::query()->where('is_published', true)->whereNotNull('slug')
            ->orderBy('id')->get(['slug', 'updated_at'])
            ->each(fn ($i) => $addUrl('installers/'.$i->slug, $i->updated_at));

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $sitemapNamespace = 'http://www.sitemaps.org/schemas/sitemap/0.9';
        $imageNamespace = 'http://www.google.com/schemas/sitemap-image/1.1';
        $urlset = $doc->createElementNS($sitemapNamespace, 'urlset');
        $urlset->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:image', $imageNamespace);
        $doc->appendChild($urlset);

        foreach (array_values($urls) as $url) {
            $urlNode = $doc->createElementNS($sitemapNamespace, 'url');
            $locNode = $doc->createElementNS($sitemapNamespace, 'loc');
            $locNode->appendChild($doc->createTextNode($url['location']));
            $urlNode->appendChild($locNode);

            if ($url['last_modified']) {
                $lastmodNode = $doc->createElementNS($sitemapNamespace, 'lastmod');
                $lastmodNode->appendChild($doc->createTextNode($url['last_modified']));
                $urlNode->appendChild($lastmodNode);
            }

            if ($url['image']) {
                $imageNode = $doc->createElementNS($imageNamespace, 'image:image');
                $imageLocationNode = $doc->createElementNS($imageNamespace, 'image:loc');
                $imageLocationNode->appendChild($doc->createTextNode($url['image']));
                $imageNode->appendChild($imageLocationNode);

                if ($url['image_title']) {
                    $imageTitleNode = $doc->createElementNS($imageNamespace, 'image:title');
                    $imageTitleNode->appendChild($doc->createTextNode($url['image_title']));
                    $imageNode->appendChild($imageTitleNode);
                }

                $urlNode->appendChild($imageNode);
            }

            $urlset->appendChild($urlNode);
        }

        return $doc->saveXML();
    });

    return response($xml, 200)
        ->header('Content-Type', 'application/xml; charset=UTF-8')
        ->header('Cache-Control', 'public, max-age=86400');
})->name('sitemap');
