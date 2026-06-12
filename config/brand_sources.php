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
        'site'                 => 'https://www.electrolux.com.by',
        'search_url'           => 'https://www.electrolux.com.by/search/?q=%s',
        'product_link_pattern' => '#href=["\'](/[a-z][a-z0-9\-]*/[a-z][a-z0-9\-]*/)["\']#i',
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
