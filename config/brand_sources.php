<?php

/**
 * Manufacturer website sources for product enrichment.
 *
 * Keys must match brand names in DB (case-insensitive).
 * search_url: %s is replaced with the URL-encoded search query.
 * product_link_pattern: regex to extract product URL from search results page.
 */
return [

    'royal thermo' => [
        'site'                 => 'https://www.royal-thermo.ru',
        'search_url'           => 'https://www.royal-thermo.ru/search/?q=%s',
        'product_link_pattern' => '#href=["\'](/catalog/[a-z0-9_/\-]+/)["\']#i',
    ],

    'electrolux' => [
        'site'           => 'https://electrolux-home.ru',
        'sitemap_url'    => 'https://electrolux-home.ru/sitemap.xml',
        'sitemap_filter' => ['kondicionery', 'obogrevateli-i-teplovye-zavesy', 'uvlazhniteli', 'ventilyatory', 'ochistit', 'nagrevateli', 'teplyy-pol', 'termoregulyator'],
        'additional_sitemaps' => [
            ['url' => 'https://www.teplodvor.by/map/sitemap/1/', 'filter' => 'electrolux'],
            ['url' => 'https://www.teplodvor.by/map/sitemap/2/', 'filter' => 'electrolux'],
            ['url' => 'https://www.teplodvor.by/map/sitemap/3/', 'filter' => 'electrolux'],
            ['url' => 'https://www.teplodvor.by/map/sitemap/4/', 'filter' => 'electrolux'],
            ['url' => 'https://www.teplodvor.by/map/sitemap/5/', 'filter' => 'electrolux'],
            ['url' => 'https://www.teplodvor.by/map/sitemap/6/', 'filter' => 'electrolux'],
        ],
        'fallbacks' => [
            [
                'site'         => 'https://www.21vek.by',
                'search_url'   => 'https://www.21vek.by/conditioners/all/electrolux/',
                'link_pattern' => '#href=["\'](/conditioners/[a-z0-9_\-]+electrolux[^"\']*\.html)["\']#i',
            ],
            [
                'site'         => 'https://www.21vek.by',
                'search_url'   => 'https://www.21vek.by/humidifiers/all/electrolux/',
                'link_pattern' => '#href=["\'](/humidifiers/[a-z0-9_\-]+electrolux[^"\']*\.html)["\']#i',
            ],
            [
                'site'         => 'https://agrox.by',
                'search_url'   => 'https://agrox.by/klimaticheskoe-oborudovanie/klimat/kondicionery-nastennye/',
                'link_pattern' => '#href=["\'](/klimaticheskoe-oborudovanie/klimat/kondicionery-nastennye/[a-z0-9\-]+)["\']#i',
            ],
            [
                'site'         => 'https://www.idstore.by',
                'search_url'   => 'https://www.idstore.by/conditioners/electrolux/',
                'link_pattern' => '#href=["\'](/conditioners/electrolux/[a-z0-9]+)["\']#i',
            ],
        ],
    ],

    'ballu' => [
        'site'                 => 'https://www.ballu.ru',
        'search_url'           => 'https://www.ballu.ru/search/?q=%s',
        'product_link_pattern' => '#href=["\'](/catalog/[a-z0-9_/\-]+/)["\']#i',
    ],

    'grundfos' => [
        'site'                 => 'https://product.grundfos.com',
        'search_url'           => 'https://product.grundfos.com/ru/products/%s',
        'product_link_pattern' => '#href=["\']([^"\']+/[A-Z0-9\-]+)["\']#',
    ],

    'vaillant' => [
        'site'                 => 'https://www.vaillant.ru',
        'search_url'           => 'https://www.vaillant.ru/search/?q=%s',
        'product_link_pattern' => '#href=["\'](/products/[a-z0-9\-/]+)["\']#i',
    ],

    'ariston' => [
        'site'                 => 'https://www.ariston.com',
        'search_url'           => 'https://www.ariston.com/ru-ru/search?q=%s',
        'product_link_pattern' => '#href=["\']([^"\']+/products/[^"\']+)["\']#i',
    ],

    'ferroli' => [
        'site'                 => 'https://www.ferroli.ru',
        'search_url'           => 'https://www.ferroli.ru/search/?q=%s',
        'product_link_pattern' => '#href=["\'](/catalog/[a-z0-9_/\-]+/)["\']#i',
    ],

    'baxi' => [
        'site'                 => 'https://www.baxi.ru',
        'search_url'           => 'https://www.baxi.ru/search/?q=%s',
        'product_link_pattern' => '#href=["\'](/catalog/[a-z0-9_/\-]+/)["\']#i',
    ],

];
