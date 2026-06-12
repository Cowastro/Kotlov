<?php

namespace App\Console\Commands;

class SyncThermostudioViessmannCommand extends SyncThermostudioAristonCommand
{
    protected $signature = 'supplier:sync-thermostudio-viessmann
        {--apply : Write changes to the database}
        {--dry-run : Preview without writing changes}
        {--limit= : Limit number of products for testing}
        {--no-images : Skip product images}
        {--enrich : Generate unique SEO descriptions via AI}
        {--sleep=500 : Delay between requests in milliseconds}';

    protected $description = 'Scrape Viessmann gas boilers from teplo.by and sync prices, cards, service info, documents and attributes.';

    protected const SYNC_KEY = 'thermostudio_viessmann_gas_boilers';
    protected const SOURCE_URL = 'https://teplo.by/catalog/gazovye-kotly/?jsf=jet-woo-products-grid&tax=product_cat:554';
    protected const CATALOG_PAGE_QUERY = '?jsf=jet-woo-products-grid&tax=product_cat:554';
    protected const BRAND_NAME = 'Viessmann';
    protected const BRAND_SLUG = 'viessmann';
    protected const BRAND_COUNTRY = 'Германия';
    protected const PRODUCT_URL_HINTS = ['viessmann', 'vitodens', 'vitopend', 'vitocrossal'];
    protected const IMAGE_DISK_PATH = 'img/products/thermostudio/viessmann';
}
