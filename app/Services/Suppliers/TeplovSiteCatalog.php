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
