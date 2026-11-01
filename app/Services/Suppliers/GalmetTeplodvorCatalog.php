<?php

namespace App\Services\Suppliers;

/**
 * The Galmet and Apamet goods of teplodvor.by for the cards made from the Акватеплосервис price list:
 * a page is found by the Galmet article in its title, else by the same model (AkvateploservisPriceList::modelKey).
 */
class GalmetTeplodvorCatalog extends FirewayTeplodvorCatalog
{
    protected const BRAND = 'galmet|apamet';

    public function findOffer(string $name, string $article): ?string
    {
        $this->byModel ??= $this->index();

        return ($article !== '' ? $this->byModel['art:'.mb_strtoupper($article)] ?? null : null) ?? $this->find($name);
    }

    /** teplodvor.by titles rarely name the colour or «SH»: the page is found without them. */
    protected static function matchKey(string $name): string
    {
        $words = array_diff(explode(' ', AkvateploservisPriceList::modelKey($name)), ['серый', 'белый', 'черный', 'графит', 'sh']);

        return implode(' ', $words);
    }

    protected function titleNames(string $title): array
    {
        return [$title];
    }

    /** @return array<string, string> */
    protected function index(): array
    {
        $index = parent::index();
        // «… FL (26-408000N)»: a title with the Galmet article is found by it too.
        foreach ($index as $key => $url) {
            $page = $this->page($url);
            if ($page && preg_match('/\b(\d{2}-\d{6}N?)\b/u', mb_strtoupper($page['name']), $m)) {
                $index['art:'.$m[1]] = $url;
            }
        }

        return $index;
    }
}
