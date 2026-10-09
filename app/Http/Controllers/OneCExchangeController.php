<?php

namespace App\Http\Controllers;

use App\Models\IntegrationExchangeRun;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Order;
use App\Services\Integrations\CommerceMlCatalogImporter;
use App\Services\Integrations\CommerceMlOrderImporter;
use App\Services\Integrations\IntegrationExchangeJournal;
use App\Services\Integrations\IntegrationMonitoringWindow;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use XMLWriter;

class OneCExchangeController extends Controller
{
    private const SESSION_COOKIE = 'onec_exchange';

    public function __construct(
        private readonly CommerceMlCatalogImporter $catalogImporter,
        private readonly CommerceMlOrderImporter $orderImporter,
        private readonly IntegrationExchangeJournal $exchangeJournal,
        private readonly IntegrationMonitoringWindow $monitoringWindow,
    ) {}

    public function __invoke(Request $request, string $source = 'onec'): Response
    {
        $integrationSource = $this->resolveSource($source);

        if (! $integrationSource || ! $this->isAuthorized($request, $integrationSource)) {
            return response("failure\nAuthentication failed", 401, [
                'WWW-Authenticate' => 'Basic realm="kotlov.by 1C exchange"',
            ]);
        }

        $type = Str::lower((string) $request->query('type'));
        $mode = Str::lower((string) $request->query('mode'));

        return match ($mode) {
            'checkauth' => $this->checkAuth($integrationSource),
            'init' => $this->initializeExchange($request, $integrationSource, $type),
            'file' => $this->receiveFile($request, $integrationSource, $type),
            'import' => $this->acknowledgeImport($request, $integrationSource, $type),
            'query' => $type === 'sale' && $this->canExportOrders($integrationSource)
                ? $this->exportOrders($request, $integrationSource)
                : $this->plain("failure\nUnsupported exchange type"),
            'success' => $type === 'sale' && $this->canExportOrders($integrationSource)
                ? $this->confirmOrders($request, $integrationSource)
                : $this->plain('success'),
            default => $this->plain("failure\nUnsupported mode"),
        };
    }

    private function resolveSource(string $code): ?IntegrationSource
    {
        $source = IntegrationSource::query()->where('code', $code)->first();

        if (! $source && $code === 'onec' && filled(config('onec.exchange.username'))) {
            $source = IntegrationSource::query()->create([
                'code' => 'onec',
                'name' => '1С',
                'driver' => 'commerceml',
                'is_active' => true,
            ]);
        }

        return $source?->is_active ? $source : null;
    }

    private function isAuthorized(Request $request, IntegrationSource $source): bool
    {
        $expectedUser = $source->code === 'onec' ? (string) config('onec.exchange.username') : '';
        $expectedPassword = $source->code === 'onec' ? (string) config('onec.exchange.password') : '';

        $basicMatches = false;
        if ($expectedUser !== '' && $expectedPassword !== '') {
            $basicMatches = hash_equals($expectedUser, (string) $request->getUser())
                && hash_equals($expectedPassword, (string) $request->getPassword());
        } else {
            $basicMatches = filled($source->username)
                && filled($source->password_hash)
                && hash_equals((string) $source->username, (string) $request->getUser())
                && Hash::check((string) $request->getPassword(), (string) $source->password_hash);
        }

        if ($basicMatches) {
            return true;
        }

        $token = (string) $request->cookie(self::SESSION_COOKIE);

        return $token !== '' && Cache::has($this->sessionCacheKey($token));
    }

    private function checkAuth(IntegrationSource $source): Response
    {
        $source->forceFill(['last_authenticated_at' => now()])->saveQuietly();

        $token = Str::random(48);
        Cache::put($this->sessionCacheKey($token), true, now()->addHour());

        return $this->plain("success\n".self::SESSION_COOKIE."\n{$token}")
            ->withCookie(cookie(self::SESSION_COOKIE, $token, 60, '/', null, true, true, false, 'Lax'));
    }

    private function receiveFile(Request $request, IntegrationSource $source, string $type): Response
    {
        $filename = basename((string) $request->query('filename'));
        if ($filename === '' || ! preg_match('/\.(xml|zip)$/i', $filename)) {
            return $this->plain("failure\nInvalid filename");
        }

        $contents = $request->getContent();
        $path = $this->sessionPath($request, $source).'/'.$filename;
        $disk = Storage::disk((string) config('onec.exchange.storage_disk'));

        $normalizedContents = ltrim($contents, "\xEF\xBB\xBF\x00\x09\x0A\x0D\x20");
        $startsNewFile = str_starts_with($normalizedContents, '<?xml')
            || str_starts_with($normalizedContents, '<КоммерческаяИнформация')
            || str_starts_with($contents, "PK\x03\x04")
            || $disk->exists($path.'.received');
        if ($startsNewFile) {
            $disk->delete([$path.'.received', $path.'.result.json']);
        }

        $existing = ! $startsNewFile && $disk->exists($path) ? $disk->get($path) : '';
        if (strlen($existing) + strlen($contents) > (int) config('onec.exchange.file_limit')) {
            return $this->plain("failure\nFile is too large");
        }

        $disk->put($path, $existing.$contents);

        $operation = match ($type) {
            'catalog' => 'catalog',
            'sale' => 'order_statuses',
            default => null,
        };

        if ($operation) {
            $run = $this->currentRun($request, $source, $operation)
                ?? $this->startRun($request, $source, 'inbound', $operation);
            $this->exchangeJournal->recordFile($run, strlen($contents));
        }

        return $this->plain('success');
    }

    private function initializeExchange(Request $request, IntegrationSource $source, string $type): Response
    {
        Storage::disk((string) config('onec.exchange.storage_disk'))
            ->deleteDirectory($this->sessionPath($request, $source));

        if ($type === 'catalog') {
            $this->startRun($request, $source, 'inbound', 'catalog');
        } elseif ($type === 'sale') {
            $this->startRun($request, $source, 'inbound', 'order_statuses');
        }

        return $this->plain("zip=no\nfile_limit=".config('onec.exchange.file_limit'));
    }

    private function acknowledgeImport(Request $request, IntegrationSource $source, string $type): Response
    {
        $filename = basename((string) $request->query('filename'));
        $path = $this->sessionPath($request, $source).'/'.$filename;
        $disk = Storage::disk((string) config('onec.exchange.storage_disk'));

        if ($filename === '' || ! $disk->exists($path)) {
            return $this->plain("failure\nUploaded file not found");
        }

        $operation = match ($type) {
            'catalog' => 'catalog',
            'sale' => 'order_statuses',
            default => null,
        };
        if (! $operation) {
            return $this->plain("failure\nUnsupported exchange type");
        }

        $run = $this->currentRun($request, $source, $operation)
            ?? $this->startRun($request, $source, 'inbound', $operation);

        try {
            $stats = $type === 'sale'
                ? $this->orderImporter->import($disk->get($path))
                : $this->catalogImporter->import($disk->get($path), $source->code);
        } catch (\Throwable $exception) {
            report($exception);
            $this->exchangeJournal->fail($run, $exception);

            return $this->plain("failure\nCommerceML import failed");
        }

        // The raw file and staging result are retained for mapping review. The importer
        // never mutates products, prices or stock.
        $disk->put($path.'.received', now()->toIso8601String());
        $disk->put($path.'.result.json', json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $metrics = $type === 'sale'
            ? [
                'orders_count' => (int) $stats['matched'],
                'items_received' => (int) $stats['documents'],
                'items_updated' => (int) $stats['updated'],
                'items_skipped' => (int) $stats['unchanged'] + (int) $stats['unmatched'],
                'summary' => $stats,
            ]
            : [
                'items_received' => max((int) $stats['products'], (int) $stats['offers']),
                'items_updated' => (int) $stats['matched'] + (int) $stats['suggested']
                    + (int) $stats['ambiguous'] + (int) $stats['unmatched'],
                'summary' => $stats,
            ];
        $this->exchangeJournal->succeed($run, $metrics);

        return $this->plain('success');
    }

    private function exportOrders(Request $request, IntegrationSource $source): Response
    {
        $run = $this->startRun($request, $source, 'outbound', 'orders');
        $orders = Order::query()
            ->whereNull('onec_exported_at')
            ->when(
                $this->monitoringWindow->ordersStartAtFor($source),
                fn ($query, $startAt) => $query->where('created_at', '>=', $startAt),
            )
            ->with('items.product')
            ->orderBy('id')
            ->limit(100)
            ->get();

        $this->exchangeJournal->progress($run, ['orders_count' => $orders->count()]);

        Cache::put(
            $this->pendingOrdersCacheKey($request, $source),
            $orders->pluck('id')->all(),
            now()->addHour()
        );

        $externalIds = IntegrationProduct::query()
            ->whereNotNull('product_id')
            ->where('integration_source_id', $source->id)
            ->whereIn('product_id', $orders->flatMap->items->pluck('product_id')->filter()->unique())
            ->pluck('external_id', 'product_id');

        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('КоммерческаяИнформация');
        $xml->writeAttribute('ВерсияСхемы', '2.10');
        $xml->writeAttribute('ДатаФормирования', now()->format('Y-m-d\TH:i:s'));

        foreach ($orders as $order) {
            $this->writeOrder($xml, $order, $externalIds);
        }

        $xml->endElement();
        $xml->endDocument();

        return response($xml->outputMemory(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function confirmOrders(Request $request, IntegrationSource $source): Response
    {
        $ids = Cache::pull($this->pendingOrdersCacheKey($request, $source), []);
        $run = $this->currentRun($request, $source, 'orders');

        if ($ids !== []) {
            Order::query()->whereIn('id', $ids)->whereNull('onec_exported_at')->update([
                'onec_exported_at' => now(),
            ]);
        }

        $this->exchangeJournal->succeed($run, [
            'orders_count' => count($ids),
            'summary' => ['confirmed_order_ids' => array_values($ids)],
        ]);

        return $this->plain('success');
    }

    /** @param Collection<int, string> $externalIds */
    private function writeOrder(XMLWriter $xml, Order $order, Collection $externalIds): void
    {
        $xml->startElement('Документ');
        $this->element($xml, 'Ид', 'kotlov-order-'.$order->id);
        $this->element($xml, 'Номер', $order->number);
        $this->element($xml, 'Дата', $order->created_at->format('Y-m-d'));
        $this->element($xml, 'ХозОперация', 'Заказ товара');
        $this->element($xml, 'Роль', 'Продавец');
        $this->element($xml, 'Валюта', 'BYN');
        $this->element($xml, 'Курс', '1');
        $this->element($xml, 'Сумма', number_format((float) $order->total, 2, '.', ''));

        $xml->startElement('Контрагенты');
        $xml->startElement('Контрагент');
        $this->element($xml, 'Ид', 'kotlov-customer-'.$order->id);
        $this->element($xml, 'Наименование', $order->customer_name);
        $this->element($xml, 'ПолноеНаименование', $order->company_name ?: $order->customer_name);
        $this->element($xml, 'Роль', 'Покупатель');
        $xml->startElement('Контакты');
        $this->writeContact($xml, 'Телефон рабочий', $order->customer_phone);
        $this->writeContact($xml, 'Почта', $order->customer_email);
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();

        $xml->startElement('Товары');
        foreach ($order->items as $item) {
            $xml->startElement('Товар');
            $externalId = $externalIds->get($item->product_id);
            $this->element($xml, 'Ид', $externalId ?: ($item->product_sku ?: 'kotlov-product-'.$item->product_id));
            $this->element($xml, 'Артикул', $item->product_sku ?: '');
            $this->element($xml, 'Наименование', $item->product_name);
            $this->element($xml, 'ЦенаЗаЕдиницу', number_format((float) $item->price, 2, '.', ''));
            $this->element($xml, 'Количество', (string) $item->quantity);
            $this->element($xml, 'Сумма', number_format((float) $item->total, 2, '.', ''));
            $xml->endElement();
        }
        $xml->endElement();

        $xml->startElement('ЗначенияРеквизитов');
        $this->writeRequisite($xml, 'Статус заказа', $order->status_label);
        $this->writeRequisite($xml, 'Способ доставки', Order::DELIVERY_TYPES[$order->delivery_type] ?? $order->delivery_type);
        $this->writeRequisite($xml, 'Способ оплаты', Order::PAYMENT_TYPES[$order->payment_type] ?? $order->payment_type);
        $this->writeRequisite($xml, 'Адрес доставки', trim(implode(', ', array_filter([
            $order->delivery_region,
            $order->delivery_city,
            $order->delivery_address,
        ]))));
        $this->writeRequisite($xml, 'Комментарий', $order->comment ?: '');
        $xml->endElement();
        $xml->endElement();
    }

    private function writeContact(XMLWriter $xml, string $type, ?string $value): void
    {
        if (! filled($value)) {
            return;
        }

        $xml->startElement('Контакт');
        $this->element($xml, 'Тип', $type);
        $this->element($xml, 'Значение', (string) $value);
        $xml->endElement();
    }

    private function writeRequisite(XMLWriter $xml, string $name, string $value): void
    {
        $xml->startElement('ЗначениеРеквизита');
        $this->element($xml, 'Наименование', $name);
        $this->element($xml, 'Значение', $value);
        $xml->endElement();
    }

    private function element(XMLWriter $xml, string $name, string $value): void
    {
        $xml->writeElement($name, $value);
    }

    private function canExportOrders(IntegrationSource $source): bool
    {
        return $source->code === 'onec' || (bool) data_get($source->settings, 'allow_order_export', false);
    }

    private function sessionPath(Request $request, IntegrationSource $source): string
    {
        $token = (string) $request->cookie(self::SESSION_COOKIE);
        $identity = $token !== '' ? hash('sha256', $token) : hash('sha256', (string) $request->getUser());

        return trim((string) config('onec.exchange.storage_path'), '/').'/'.$source->code.'/'.$identity;
    }

    private function pendingOrdersCacheKey(Request $request, IntegrationSource $source): string
    {
        return 'onec_exchange_pending_orders:'.hash('sha256', $this->sessionPath($request, $source));
    }

    private function startRun(
        Request $request,
        IntegrationSource $source,
        string $direction,
        string $operation,
    ): ?IntegrationExchangeRun {
        $run = $this->exchangeJournal->start(
            $source,
            $direction,
            $operation,
            hash('sha256', $this->sessionPath($request, $source))
        );

        if ($run) {
            Cache::put($this->exchangeRunCacheKey($request, $source, $operation), $run->id, now()->addHour());
        }

        return $run;
    }

    private function currentRun(
        Request $request,
        IntegrationSource $source,
        string $operation,
    ): ?IntegrationExchangeRun {
        return $this->exchangeJournal->find(
            Cache::get($this->exchangeRunCacheKey($request, $source, $operation))
        );
    }

    private function exchangeRunCacheKey(
        Request $request,
        IntegrationSource $source,
        string $operation,
    ): string {
        return 'integration_exchange_run:'.$operation.':'.hash(
            'sha256',
            $this->sessionPath($request, $source)
        );
    }

    private function sessionCacheKey(string $token): string
    {
        return 'onec_exchange_session:'.hash('sha256', $token);
    }

    private function plain(string $body, int $status = 200): Response
    {
        return response($body, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
