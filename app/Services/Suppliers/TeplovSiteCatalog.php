<?php

namespace App\Services\Suppliers;

use Illuminate\Support\Facades\Http;

/**
 * The maker's catalogue of Теплов и Сухов (teplov.ru): a product page per element with its photo,
 * description, specs and Russian retail price. Product URLs are the Bitrix transliteration of the
 * name — «Конус Термо КТ-Р 430-0.5/430 D130/200 (М)» → /catalog/detail/konus_termo_kt_r_430_0_5_430_d130_200_m/ —
 * which is how the 1C names of the same parts are found.
 */
class TeplovSiteCatalog
{
    public const SITE = 'https://teplov.ru';

    private const LETTERS = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'ye', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    /** @var array<string,string>|null slug => compact slug */
    private ?array $slugs = null;

    /** Bitrix-style transliteration of a product name into its URL slug. */
    public static function slug(string $name): string
    {
        $text = strtr(mb_strtolower($name), self::LETTERS);

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $text), '_');
    }

    /** The product page URL for a name, or null when the maker has no such product. */
    public function find(string $name): ?string
    {
        $slugs = $this->slugs();
        $slug = self::slug($name);
        // 1C adds marks the site omits: «(У)», «(У2)», «ТС».
        foreach ([$slug, (string) preg_replace('/_(u2?|ts)$/', '', $slug)] as $candidate) {
            if (isset($slugs[$candidate])) {
                return self::SITE.'/catalog/detail/'.$candidate.'/';
            }
        }
        // «L1000» vs «L 1000», «D 130-150» vs «D130_150»: compare without separators.
        $compact = str_replace('_', '', $slug);
        $found = array_search($compact, $slugs, true);

        return $found !== false ? self::SITE.'/catalog/detail/'.$found.'/' : null;
    }

    /**
     * @return array{name:string, description:string, specs:array<string,string>, price_rub:?float, images:array<int,string>}|null
     */
    public function page(string $url): ?array
    {
        $html = $this->get($url);
        if ($html === null || ! preg_match('/<h1[^>]*itemprop="name"[^>]*>(.*?)<\/h1>/su', $html, $name)) {
            return null;
        }
        $description = preg_match('/class="product__description[^"]*"[^>]*>(.*?)<\/div>/su', $html, $d)
            ? trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['<br />', '<br>'], ' ', $d[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8')))
            : '';
        $specs = [];
        preg_match_all('/class="prop__name">(.*?)<\/span>.*?class="prop__value">(.*?)<\/span>/su', $html, $props, PREG_SET_ORDER);
        foreach ($props as $prop) {
            $key = trim(html_entity_decode(strip_tags($prop[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $value = trim(html_entity_decode(strip_tags($prop[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($key !== '' && $value !== '') {
                $specs[$key] = $value;
            }
        }
        $price = preg_match('/itemprop="price"\s+content="([\d.]+)"/u', $html, $p) ? (float) $p[1] : null;
        preg_match_all('/class="product__img-link"[^>]*href="([^"]+)"/u', $html, $images);

        return [
            'name' => trim(html_entity_decode(strip_tags($name[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            'description' => $description,
            'specs' => $specs,
            'price_rub' => $price > 0 ? $price : null,
            'images' => array_values(array_unique(array_map(fn (string $src) => str_starts_with($src, 'http') ? $src : self::SITE.$src, $images[1] ?? []))),
        ];
    }

    /**
     * A working photo for a part: its own page's photo, else the photo of the same series in the
     * nearest diameter (teplov.ru serves many photos as 404; «Труба L250 ТМ-Р 430-0.8» D250 and D200
     * are broken, D120 is fine — the same pipe, steel, thickness and length).
     *
     * @return array<int,string>
     */
    public function workingPhotos(string $name): array
    {
        $url = $this->find($name);
        if ($url === null) {
            return [];
        }
        $own = $this->page($url)['images'] ?? [];
        $working = array_values(array_filter($own, fn (string $image) => $this->opens($image)));
        if ($working !== []) {
            return $working;
        }
        $slug = basename(rtrim($url, '/'));
        $diameter = self::firstDiameter($slug);
        // The same series first, then the same series with any of the «У / У2 / ТС / М» marks.
        foreach ([fn (string $s) => self::seriesPattern($s), fn (string $s) => (string) preg_replace('/(_(u2?|u3|ts|m|fm|tf))+$/', '', self::seriesPattern($s))] as $key) {
            $pattern = $key($slug);
            $series = collect(array_keys($this->slugs()))
                ->filter(fn (string $other) => $other !== $slug && $key($other) === $pattern)
                ->sortBy(fn (string $other) => abs(self::firstDiameter($other) - $diameter))
                ->take(15);
            foreach ($series as $other) {
                $images = array_values(array_filter($this->page(self::SITE.'/catalog/detail/'.$other.'/')['images'] ?? [], fn (string $image) => $this->opens($image)));
                if ($images !== []) {
                    return $images;
                }
            }
        }

        return [];
    }

    /** «truba_l250_tm_r_430_0_8_d250» → «truba_l250_tm_r_430_0_8_d#»: the series without its diameters. */
    public static function seriesPattern(string $slug): string
    {
        return (string) preg_replace('/_d_?\d+(?:_\d+)*/', '_d#', $slug);
    }

    private static function firstDiameter(string $slug): int
    {
        return preg_match('/_d_?(\d+)/', $slug, $m) ? (int) $m[1] : 0;
    }

    /** @var array<string,bool> */
    private array $opens = [];

    private function opens(string $image): bool
    {
        if (! isset($this->opens[$image])) {
            try {
                $response = Http::timeout(30)->withUserAgent('Mozilla/5.0 (compatible; KotlovBot)')->get($image);
                $this->opens[$image] = $response->successful() && str_contains(strtolower((string) $response->header('Content-Type')), 'image');
            } catch (\Throwable) {
                $this->opens[$image] = false;
            }
        }

        return $this->opens[$image];
    }

    /** @return array<string,string> every product slug of the catalogue (from the section pages of the menu) */
    private function slugs(): array
    {
        if ($this->slugs !== null) {
            return $this->slugs;
        }
        $this->slugs = [];
        $menu = $this->get(self::SITE.'/search/?q=') ?? '';
        preg_match_all('/href="(\/catalog\/[a-z0-9_]+\/(?:[a-z0-9_]+\/)?)"/', $menu, $sections);
        foreach (array_unique($sections[1] ?? []) as $section) {
            preg_match_all('/href="\/catalog\/detail\/([a-z0-9_]+)\/"/', $this->get(self::SITE.$section) ?? '', $details);
            foreach ($details[1] ?? [] as $slug) {
                $this->slugs[$slug] = str_replace('_', '', $slug);
            }
        }

        return $this->slugs;
    }

    private function get(string $url): ?string
    {
        try {
            $response = Http::timeout(90)->withUserAgent('Mozilla/5.0 (compatible; KotlovBot)')->retry(2, 500)->get($url);

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
