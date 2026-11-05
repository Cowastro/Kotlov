<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\City;
use App\Models\PartnerBranch;
use Illuminate\Http\Request;

class RegionalNewsController extends Controller
{
    private function city(Request $request): ?City
    {
        $domain = config('app.base_domain', 'kotlov.by');
        $host = $request->getHost();
        if (! str_ends_with($host, '.'.$domain)) {
            return null;
        }
        $city = City::findBySlug(substr($host, 0, -strlen('.'.$domain)));

        return $city && PartnerBranch::query()->visibleIn($city)->exists() ? $city : null;
    }

    public function index(Request $request)
    {
        $city = $this->city($request);
        if (! $city) {
            return redirect()->away('https://'.config('app.base_domain', 'kotlov.by').'/regions/news', 301);
        }

        return $this->listing($city);
    }

    public function all()
    {
        return $this->listing(null);
    }

    private function listing(?City $city)
    {
        $posts = BlogPost::published()->regional()->with('city')
            ->when($city, fn ($query) => $query->where('city_id', $city->id))
            ->orderByDesc('published_at')->paginate(12)->withQueryString();
        $heading = $city ? 'Новости KOTLOV в '.$city->name_title : 'Новости магазинов KOTLOV в регионах';
        $canonical = $city ? $city->storefrontUrl().'/news' : 'https://'.config('app.base_domain', 'kotlov.by').'/regions/news';
        if ($posts->currentPage() > 1) { $canonical .= '?page='.$posts->currentPage(); }

        return view('pages.regional-news', [
            'posts' => $posts, 'newsCity' => $city, 'heading' => $heading,
            'title' => $heading.' — магазины, оборудование и события',
            'description' => $city
                ? 'Новости магазина KOTLOV в '.$city->name_title.': ассортимент, события и полезная информация для покупателей.'
                : 'Новости региональных магазинов KOTLOV: местные события, ассортимент и информация для покупателей. Выберите свой город.',
            'canonical' => $canonical,
            'robots' => $posts->total() === 0 ? 'noindex, follow' : null,
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $city = $this->city($request);
        abort_unless($city, 404);
        $post = BlogPost::published()->regional()->where('city_id', $city->id)
            ->where('slug', $slug)->with('city')->firstOrFail();
        BlogPost::withoutTimestamps(fn () => $post->increment('views_count'));
        $canonical = $post->publicUrl();
        $schemaJson = json_encode([
            '@context' => 'https://schema.org', '@type' => 'NewsArticle',
            'headline' => $post->title, 'mainEntityOfPage' => $canonical,
            'image' => [$post->cover_image_url],
            'datePublished' => $post->published_at->toAtomString(),
            'dateModified' => $post->updated_at->toAtomString(),
            'contentLocation' => ['@type' => 'City', 'name' => $city->name],
            'author' => ['@type' => 'Organization', 'name' => 'KOTLOV', 'url' => 'https://'.config('app.base_domain', 'kotlov.by').'/about'],
            'publisher' => ['@type' => 'Organization', 'name' => 'KOTLOV'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

        return view('pages.regional-news-single', [
            'post' => $post, 'newsCity' => $city, 'canonical' => $canonical,
            'title' => $post->meta_title ?: $post->title.' | KOTLOV',
            'description' => $post->meta_description ?: strip_tags($post->excerpt ?? ''),
            'ogType' => 'article', 'ogUrl' => $canonical, 'ogImage' => $post->cover_image_url,
            'schemaJson' => $schemaJson,
        ]);
    }
}
