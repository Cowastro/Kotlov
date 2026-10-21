<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnrichAzbukaTeplaOfficialCommand extends Command
{
    protected $signature = 'supplier:enrich-azbuka-tepla-official
        {--apply : Write images, characteristics and content}
        {--brand= : Process one brand}
        {--all-linked : Include older linked products; default is products created today}
        {--force : Re-enrich cards that already have local images and at least three characteristics}
        {--limit=0 : Maximum products, 0 means all}
        {--sleep=500 : Delay between source requests in milliseconds}';

    protected $description = 'Enrich Azbuka Tepla cards from verified manufacturer product pages.';

    public function handle(ProductSourceEnricher $enricher): int
    {
        $apply = (bool) $this->option('apply');
        $limit = max(0, (int) $this->option('limit'));
        $sleep = max(300, (int) $this->option('sleep'));

        $query = DB::table('supplier_products as sp')
            ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->join('products as p', 'p.id', '=', 'sp.product_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->where('s.code', 'azbuka-tepla')
            ->where('p.is_archived', false)
            ->select(['sp.id as supplier_product_id', 'sp.raw', 'p.id', 'p.name', 'p.images', 'p.specs', 'p.content', 'b.name as brand'])
            ->orderBy('p.id');

        if (! $this->option('all-linked')) {
            $query->whereDate('p.created_at', now()->toDateString());
        }
        if ($brand = trim((string) $this->option('brand'))) {
            $query->whereRaw('LOWER(b.name) = ?', [mb_strtolower($brand)]);
        }
        if ($limit > 0) {
            $query->limit($limit);
        }

        $stats = ['scanned' => 0, 'mapped' => 0, 'enriched' => 0, 'images' => 0, 'specs' => 0, 'unmapped' => 0, 'errors' => 0];

        foreach ($query->get()->unique('id') as $row) {
            if (! $this->option('force') && $this->hasCompleteSourceData($row->images, $row->specs)) {
                continue;
            }

            $stats['scanned']++;
            $raw = $this->decodeRaw($row->raw);
            $model = trim((string) ($raw['model'] ?? $raw['partner_model'] ?? ''));
            $sourceUrl = $this->officialSource((string) $row->brand, $model, (string) $row->name);
            if ($sourceUrl === null) {
                $stats['unmapped']++;
                continue;
            }

            $stats['mapped']++;
            $product = Product::query()->find((int) $row->id);
            if (! $product) {
                $stats['errors']++;
                continue;
            }

            try {
                $parsed = $enricher->preview($sourceUrl);
                $verifiedImages = $this->officialImages((string) $row->brand, $model, (string) $row->name);
                if ($verifiedImages !== []) {
                    $parsed['images'] = array_values(array_unique(array_merge($verifiedImages, $parsed['images'] ?? [])));
                }

                $result = $enricher->enrichFromParsed($product, $sourceUrl, $parsed, [
                    'preview_only' => ! $apply,
                    'replace_images' => false,
                    'update_images' => true,
                    'update_specs' => true,
                    'replace_specs' => true,
                    'min_specs_to_replace' => 3,
                    'update_service' => true,
                    'update_documents' => true,
                    'update_video' => true,
                    'update_content' => true,
                    'min_specs_for_ai' => 3,
                    'require_images_for_ai' => true,
                ]);

                $stats['images'] += (int) ($apply
                    ? ($result['images_saved'] ?? 0)
                    : ($result['images_found'] ?? 0));
                $stats['specs'] += (int) ($result['specs_found'] ?? 0);
                if ($apply && ($result['updated_fields'] ?? []) !== []) {
                    DB::table('supplier_products')->where('id', (int) $row->supplier_product_id)->update([
                        'source_url' => $sourceUrl,
                        'updated_at' => now(),
                    ]);
                    $stats['enriched']++;
                } elseif (! $apply) {
                    $stats['enriched']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn(sprintf('#%d %s: %s', $row->id, $row->name, $e->getMessage()));
            }

            usleep($sleep * 1000);
        }

        foreach ($stats as $key => $value) {
            $this->line("metric.azbuka_official.{$key}={$value}");
        }

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function officialSource(string $brand, string $model, string $name): ?string
    {
        $brandKey = mb_strtolower(trim($brand));
        $text = trim($model.' '.$name);
        $ascii = Str::upper(str_replace([' ', '-', '_', '.', '/', 'М'], '', $text));
        $ascii = strtr($ascii, ['А' => 'A', 'В' => 'B', 'С' => 'C', 'Е' => 'E', 'Н' => 'H', 'К' => 'K', 'О' => 'O', 'Р' => 'P', 'Т' => 'T', 'Х' => 'X']);

        if ($brandKey === 'auraton') {
            foreach (['aquila-set', 'auriga', 'cetus', 'libra-set', 'tucana-set'] as $slug) {
                if (str_contains(Str::slug($text), $slug)) {
                    return 'https://www.auraton.pl/en/offer/thermostats/auraton-'.$slug;
                }
            }
        }

        if ($brandKey === 'valfex' && preg_match('/ALPHA(25)(40|60|80)(130|180)/', $ascii, $match)) {
            return sprintf(
                'https://valfex.ru/catalog/seriya-alpha/tsirkulyatsionnyy-energoeffektivnyy-nasos-valfex-alpha-%s-%s/',
                $match[1],
                $match[2]
            );
        }

        if ($brandKey === 'valfex' && preg_match('/VCP25(40|60)G(130|180)/', $ascii, $match)) {
            $lengthSuffix = $match[2] === '130' ? '-130' : '';

            return sprintf('https://www.teplodvor.ru/valfex-vcp-25-%sg%s.html', $match[1], $lengthSuffix);
        }

        if ($brandKey === 'valfex' && str_contains($ascii, '10305016200')) {
            return 'https://valfex.ru/catalog/truby-metalloplastikovye/truba-metalloplastikovaya-pe-xb-al-pe-xb-2/';
        }

        if ($brandKey === 'salus') {
            return match (true) {
                str_contains($ascii, 'ERT20RF') => 'https://saluscontrols.com/global/product/ert20tx-wireless-electronic-surface-mounted-thermostat-non-programmable/',
                str_contains($ascii, 'RT310') => 'https://saluscontrols.com/global/product/rt310-wired-non-programmable-thermostat/',
                str_contains($ascii, 'RT510') => 'https://saluscontrols.com/global/product/rt510-wired-electronic-thermostat-programmable/',
                str_contains($ascii, '091FLV2') => 'https://saluscontrols.com/global/product/091flv2-wired-electronic-thermostat-programmable/',
                default => null,
            };
        }

        if ($brandKey === 'barberi') {
            if (preg_match('/V13025005[AB]R/', $ascii)) {
                return 'https://www.barberi.it/ww/ru/produktsiya/b1-thermostatic-mixing-valves/anti-condensation-thermostatic-mixing-valves/V135R';
            }
            if (preg_match('/V14025005[AB]R/', $ascii)) {
                return 'https://www.barberi.it/ww/ru/produktsiya/b1-thermostatic-mixing-valves/anti-condensation-thermostatic-mixing-valves/V145R';
            }
            if (str_contains($ascii, 'V13M2500')) {
                return 'https://www.barberi.it/ww/en/products/b1-thermostatic-mixing-valves/anti-condensation-thermostatic-mixing-valves/V13';
            }
            if (str_contains($ascii, 'V1402500') || str_contains($ascii, 'V14M3200')) {
                return 'https://www.barberi.it/ww/en/products/b1-thermostatic-mixing-valves/anti-condensation-thermostatic-mixing-valves/V14';
            }
            if (str_contains($ascii, '27B040N4P3') || str_contains($ascii, '27B040N4T3')) {
                return 'https://www.barberi.it/ww/ru/produktsiya/b3-regulating-groups-and-heating-components/recessed-regulating-groups/recessed-regulating-groups-bdhb/27BN';
            }
            if (preg_match('/(?:^|[^0-9])(460|461)[0-9A-Z]+/', $ascii)) {
                return 'https://www.barberi.it/ww/ru/produktsiya/b2-rotary-mixing-valves-and-actuators/3-way-mixing-valves/460';
            }
            if (str_contains($ascii, 'V07')) {
                foreach (['AAL2', 'ABL2', 'BAL2', 'BBL2', 'AA', 'AB', 'BA', 'BB'] as $suffix) {
                    if (str_contains($ascii, $suffix)) {
                        return 'https://www.barberi.it/ww/en/products/b1-thermostatic-mixing-valves/thermostatic-mixing-valves-l-comfort/V07'.$suffix;
                    }
                }
            }
        }

        if ($brandKey === 'vaillant') {
            return match (true) {
                str_contains($ascii, 'VU65655') => 'https://www.vaillant.com/en/products/ecotec-plus-vc--5-5/',
                str_contains($ascii, 'VRC720') => 'https://www.vaillant.com/en/products/sensocomfort/',
                str_contains($ascii, 'VR71'), str_contains($ascii, 'VR92') => 'https://professional.vaillant.co.uk/for-installers/products/sensocomfort-sensocomfort-rf-149888.html',
                default => null,
            };
        }

        if ($brandKey === 'ariston') {
            return match (true) {
                str_contains($ascii, 'CLASXC35') => 'https://www.ariston.com/en-eg/products/boilers-eg/sottocat-boiler-eg/clas-xc/',
                str_contains($ascii, 'CLASXCSYSTEM24') => 'https://www.teplodvor.ru/ariston-clas-xc-system-24-ff.html',
                str_contains($ascii, 'FASTRDISPLAY10') => 'https://www.teplodvor.ru/ariston-fast-r-display-10l-ng.html',
                str_contains($ascii, 'FASTRDISPLAY14') => 'https://www.teplodvor.ru/ariston-fast-r-display-14l-ng.html',
                str_contains($ascii, 'DGI10LCFSUPERLUX') => 'https://titan-prof.ru/catalog/vodosnabzhenie/vodonagrevateli/protochnye_gazovye_vodonagrevateli/gazovyy_protochnyy_vodonagrevatel_superlux_dgi_10l_cf_ng_3632014.html',
                default => null,
            };
        }

        if ($brandKey === 'fondital' && str_contains($ascii, 'MINORKACTFS24')) {
            return 'https://www.fondital.com/ww/en/minorca-ctfs-cu';
        }

        if ($brandKey === 'protherm' && (str_contains($ascii, 'LYNX24') || str_contains($ascii, 'РЫСЬ24'))) {
            return 'https://www.teplodvor.ru/protherm-rys-hk24.html';
        }

        if ($brandKey === 'sanha') {
            return match (true) {
                str_contains($ascii, '12305016') || str_contains($ascii, '16X2200') => 'https://www.sanha.com/en/MultiFit-Flex-pipe-furled/12300016',
                str_contains($ascii, '12305020') || str_contains($ascii, '20X2100') => 'https://www.sanha.com/en/MultiFit-Flex-pipe-furled/12300020',
                default => null,
            };
        }

        if ($brandKey === 'maincor') {
            return match (true) {
                str_contains($ascii, '4160021300') || str_contains($ascii, '300M') => 'https://www.klimaworld.com/products/maincor-mfl-pe-rt-al-pe-rt-16x2-0-300m-ring-rot',
                str_contains($ascii, '4160021500') || str_contains($ascii, '500M') => 'https://www.heat-store.de/maincor-mainfloor-fussbodenheizung-heizrohr-pe-rt-alu-pe-rt-16-x-2-0-500-m.html',
                default => null,
            };
        }

        if ($brandKey === 'afriso' && str_contains($ascii, '4261600')) {
            return 'https://afriso.pl/katalog-produktow-afriso/i-2-03-b-przewodowy-system-sterowania-ogrzewaniem-plaszczyznowym-cositherm-basic/4261600-termostat-pokojowy-ta3';
        }

        if ($brandKey === 'ипро' && str_contains($ascii, 'КОТЕЛОК')) {
            return 'https://ipro-gsm.ru/internet-magazin-2/product/kotelok';
        }

        if ($brandKey === 'tecline' && str_contains($ascii, 'ПЛЕНКА')) {
            return 'https://azbukatepla.by/shop/truby-i-armatura/pe-rt-polietilen/plenka-folga-dlya-teplogo-pola-tecline';
        }

        if ($brandKey === 'tec line' && str_contains($ascii, 'ДАТЧИКБОЙЛЕРА')) {
            return 'https://azbukatepla.by/shop/kotly-otopleniya/elektricheskie-kotly/datchik-temperatury-boylera-tecline';
        }

        if ($brandKey === 'kospel') {
            if (preg_match('/SWK(?:120|140)/', $ascii)) {
                return 'https://kospel.pl/wymienniki-i-zasobniki-cwu/swk-termo-top.html';
            }
            if (preg_match('/SW(?:120|251|301)/', $ascii)) {
                return 'https://kospel.pl/wymienniki-i-zasobniki-cwu/sw-i-swz-termo-max.html';
            }
            if (preg_match('/WP.*(?:100|120|140)/', $ascii)) {
                return 'https://kospel.pl/wymienniki-i-zasobniki-cwu/wpw.html';
            }
        }

        if ($brandKey === 'tesy') {
            return match (true) {
                str_contains($ascii, '301391') => 'https://tesy.com/products/combined-and-indirectly-heated-water-tanks/domestic-hot-water-tanks-without-with-one-heat-exchanger-with-two-heat-exchangers-eec-b-c/domestic-hot-water-tanks-class-b-c-with-two-heat-exchangers/?product=ev-10-7s2-300-65',
                str_contains($ascii, '301394') => 'https://tesy.com/products/combined-and-indirectly-heated-water-tanks/domestic-hot-water-tanks-without-with-one-heat-exchanger-with-two-heat-exchangers-eec-b-c/domestic-hot-water-tanks-class-b-c-with-one-heat-exchanger/?product=ev-12s-300-65',
                str_contains($ascii, '301395') => 'https://tesy.com/products/combined-and-indirectly-heated-water-tanks/domestic-hot-water-tanks-without-with-one-heat-exchanger-with-two-heat-exchangers-eec-b-c/domestic-hot-water-tanks-class-b-c-with-one-heat-exchanger/?product=ev-15s-500-75',
                str_contains($ascii, '301396') => 'https://tesy.com/products/combined-and-indirectly-heated-water-tanks/domestic-hot-water-tanks-without-with-one-heat-exchanger-with-two-heat-exchangers-eec-b-c/domestic-hot-water-tanks-class-b-c-with-two-heat-exchangers/?product=ev-15-7s2-500-75',
                default => null,
            };
        }

        return null;
    }

    /** @return array<int,string> */
    private function officialImages(string $brand, string $model, string $name): array
    {
        $brandKey = mb_strtolower(trim($brand));
        $ascii = Str::upper(str_replace([' ', '-', '_', '.', '/', 'М'], '', trim($model.' '.$name)));
        $ascii = strtr($ascii, ['А' => 'A', 'В' => 'B', 'С' => 'C', 'Е' => 'E', 'Н' => 'H', 'К' => 'K', 'О' => 'O', 'Р' => 'P', 'Т' => 'T', 'Х' => 'X']);

        if ($brandKey === 'valfex') {
            return match (true) {
                str_contains($ascii, 'ALPHA2540130') => ['https://valfex.ru/upload/iblock/dad/VF.AL.25.40.130.jpg'],
                str_contains($ascii, 'ALPHA2580') => ['https://valfex.ru/upload/iblock/f61/VF.AL.25.80.180.jpg'],
                str_contains($ascii, 'ALPHA2560') => ['https://valfex.ru/upload/iblock/2b7/40_-60.jpg'],
                str_contains($ascii, 'ALPHA2540') => ['https://valfex.ru/upload/iblock/754/40_-60.jpg'],
                default => [],
            };
        }

        if ($brandKey === 'salus') {
            return match (true) {
                str_contains($ascii, 'ERT20RF') => ['https://saluscontrols.com/global/wp-content/uploads/sites/3/2024/03/ERT20RF_003-1.png'],
                str_contains($ascii, 'RT310') => ['https://saluscontrols.com/global/wp-content/uploads/sites/3/2024/03/RT310_002.png'],
                default => [],
            };
        }

        if ($brandKey === 'barberi') {
            return match (true) {
                preg_match('/V13025005[AB]R/', $ascii) === 1 => ['https://www.barberi.it/media/immagini/V13.5R.png'],
                preg_match('/V14025005[AB]R/', $ascii) === 1 => ['https://www.barberi.it/media/immagini/V14.5R.png'],
                str_contains($ascii, 'V13M2500') => ['https://www.barberi.it/media/immagini/V13.png'],
                str_contains($ascii, 'V1402500'), str_contains($ascii, 'V14M3200') => ['https://www.barberi.it/media/immagini/V14.png'],
                default => [],
            };
        }

        if ($brandKey === 'vaillant') {
            return match (true) {
                str_contains($ascii, 'VU65655') => ['https://waaminioprodfc9546ee.blob.core.windows.net/b2c-products/WHBC23-912676_01.png'],
                str_contains($ascii, 'VRC720') => ['https://waaminioprodfc9546ee.blob.core.windows.net/b2c-products/CONTROL23-912563_01.png'],
                default => [],
            };
        }

        if ($brandKey === 'ariston') {
            return match (true) {
                str_contains($ascii, 'CLASXC35') => ['https://www.vestatrade.ru/image/cache/catalog/product/Ariston/Kotli/CLASXC-1000x1000.jpg'],
                str_contains($ascii, 'DGI10LCFSUPERLUX') => ['https://titan-prof.ru/upload/iblock/d31/0njvxtxoqw7o9kb18636h10snfidiwbg.jpg'],
                str_contains($ascii, 'FASTRDISPLAY10'), str_contains($ascii, 'FASTRDISPLAY14') => ['https://www.teplodvor.ru/img.php?id_image=12966'],
                default => [],
            };
        }

        if ($brandKey === 'protherm' && (str_contains($ascii, 'LYNX24') || str_contains($ascii, 'РЫСЬ24'))) {
            return ['https://www.teplodvor.ru/img.php?id_image=11852'];
        }

        if ($brandKey === 'maincor' && (str_contains($ascii, '4160021500') || str_contains($ascii, '500M'))) {
            return ['https://cdn.zwei.gmbh/files/heat-store.de/media/0d/3a/6c/1787301916/Mainfloor_Heizrohr_PE_RT_Alu_PE_RT_rot-6a88101c5f881.jpg'];
        }

        if ($brandKey === 'tecline' && str_contains($ascii, 'ПЛЕНКА')) {
            return ['https://azbukatepla.by/wp-content/uploads/2018/02/folga-dlya-teplogo-pola-Polsha.png'];
        }

        if ($brandKey === 'tec line' && str_contains($ascii, 'ДАТЧИКБОЙЛЕРА')) {
            return ['https://azbukatepla.by/wp-content/uploads/2023/11/Datchik-temperatury-boylera-TECLine.png'];
        }

        return [];
    }

    private function decodeRaw(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function hasCompleteSourceData(mixed $images, mixed $specs): bool
    {
        return count($this->decodeRaw($images)) > 0
            && count($this->decodeRaw($specs)) >= 3;
    }
}
