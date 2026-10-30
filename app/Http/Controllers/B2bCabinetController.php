<?php

namespace App\Http\Controllers;

use App\Models\B2bSavedCart;
use App\Models\B2bSpecification;
use App\Models\B2bSpecificationAttachment;
use App\Models\CommercialContract;
use App\Models\CommercialDocument;
use App\Models\CrmCounterparty;
use App\Models\IntegrationSource;
use App\Models\IntegrationWarehouse;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\B2bCatalogOfferResolver;
use App\Services\B2bSpecificationParser;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class B2bCabinetController extends Controller
{
    private const SECTIONS = ['overview', 'profile', 'specifications', 'carts', 'orders', 'documents', 'contracts', 'reconciliation', 'security'];

    public function show(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isB2B(), 403);

        $client = $user->b2bClient()->with(['counterparty', 'priceLevel'])->firstOrFail();
        $allowedSources = IntegrationSource::query()
            ->whereIn('id', array_map('intval', $client->allowed_integration_source_ids ?? []))
            ->orderBy('name')
            ->pluck('name');
        $allowedWarehouses = IntegrationWarehouse::query()
            ->with('source:id,name')
            ->whereIn('id', array_map('intval', $client->allowed_integration_warehouse_ids ?? []))
            ->orderBy('name')
            ->get();
        $allowedPartnerSuppliers = Supplier::query()
            ->whereIn('id', array_map('intval', $client->allowed_supplier_ids ?? []))
            ->where('is_active', true)
            ->where('b2b_partner_enabled', true)
            ->orderBy('name')
            ->get(['id', 'name', 'b2b_client_warehouse_label']);
        $section = in_array($request->string('section')->toString(), self::SECTIONS, true)
            ? $request->string('section')->toString()
            : 'overview';
        $counterparty = $client->counterparty;
        $ordersQuery = $user->orders()->with('items')->latest();
        $documentsQuery = $this->documentsFor($request);

        $orders = (clone $ordersQuery)->paginate(12, ['*'], 'orders_page')->withQueryString();
        $documents = (clone $documentsQuery)
            ->with('contracts')
            ->latest('issued_at')
            ->paginate(12, ['*'], 'documents_page')
            ->withQueryString();
        $contracts = CommercialContract::query()
            ->whereHas('commercialDocument', fn (Builder $query) => $this->scopeDocuments($query, $request))
            ->with('commercialDocument')
            ->latest('contract_date')
            ->get();
        $savedCarts = $user->b2bSavedCarts()->latest('updated_at')->get();
        $specifications = $client->specifications()
            ->with([
                'attachments', 'commercialDocument', 'items.product',
                'versions' => fn ($query) => $query->with('creator')->orderByDesc('number'),
            ])
            ->latest('submitted_at')
            ->paginate(10, ['*'], 'specifications_page')
            ->withQueryString();
        [$profileCompletion, $profileMissing] = $this->profileCompletion($client, $counterparty, $user);

        $dateFrom = $this->date($request->input('date_from'), now()->startOfYear()->toDateString());
        $dateTo = $this->date($request->input('date_to'), now()->toDateString());
        if ($dateFrom->greaterThan($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $reconciliationOrders = $user->orders()
            ->whereBetween('created_at', [$dateFrom->startOfDay(), $dateTo->endOfDay()])
            ->oldest('created_at')
            ->get();
        $openingOrders = $user->orders()->where('created_at', '<', $dateFrom->startOfDay())->get();
        $openingBalance = $this->balance($openingOrders);
        $periodCharges = (float) $reconciliationOrders->where('status', '!=', 'cancelled')->sum('total');
        $periodPayments = (float) $reconciliationOrders->where('payment_status', 'paid')->sum('total');
        $closingBalance = $openingBalance + $periodCharges - $periodPayments;

        return view('pages.b2b.cabinet', compact(
            'user', 'client', 'counterparty', 'section', 'orders', 'documents', 'contracts', 'savedCarts', 'specifications',
            'allowedSources', 'allowedWarehouses', 'allowedPartnerSuppliers',
            'profileCompletion', 'profileMissing',
            'dateFrom', 'dateTo', 'reconciliationOrders', 'openingBalance', 'periodCharges', 'periodPayments', 'closingBalance',
        ));
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isB2B(), 403);

        $bankBic = mb_strtoupper(preg_replace('/\s+/', '', (string) $request->input('bank_bic')));
        $bankIban = mb_strtoupper(preg_replace('/\s+/', '', (string) $request->input('bank_iban')));
        $request->merge([
            'unp' => preg_replace('/\D+/', '', (string) $request->input('unp')),
            'bank_bic' => $bankBic !== '' ? $bankBic : null,
            'bank_iban' => $bankIban !== '' ? $bankIban : null,
        ]);

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'unp' => ['required', 'regex:/^\d{9}$/'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:40'],
            'contact_email' => ['required', 'email', 'max:255'],
            'legal_address' => ['nullable', 'string', 'max:1000'],
            'postal_address' => ['nullable', 'string', 'max:1000'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_bic' => ['nullable', 'regex:/^[A-Z0-9]{8,11}$/'],
            'bank_iban' => ['nullable', 'regex:/^BY[A-Z0-9]{26}$/'],
            'authorized_representative' => ['nullable', 'string', 'max:255'],
            'representative_position' => ['nullable', 'string', 'max:255'],
            'authority_basis' => ['nullable', 'string', 'max:255'],
        ], [
            'unp.regex' => 'УНП должен состоять из 9 цифр.',
            'bank_bic.regex' => 'Проверьте BIC: требуется от 8 до 11 латинских букв и цифр.',
            'bank_iban.regex' => 'Проверьте IBAN: белорусский счёт должен начинаться с BY и содержать 28 символов.',
        ]);

        DB::transaction(function () use ($user, $data): void {
            $client = $user->b2bClient()->lockForUpdate()->firstOrFail();
            $counterparty = $client->counterparty ?: new CrmCounterparty([
                'type' => 'organization',
                'classification' => 'b2b_partner',
                'business_unit' => 'wholesale',
                'status' => 'active',
                'source' => 'manual',
            ]);
            $counterparty->fill([
                'name' => $data['company_name'],
                'public_name' => $data['company_name'],
                'legal_name' => $data['legal_name'] ?: $data['company_name'],
                'unp' => $data['unp'],
                'legal_address' => $data['legal_address'] ?? null,
                'postal_address' => $data['postal_address'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'bank_bic' => $data['bank_bic'] ?? null,
                'bank_iban' => $data['bank_iban'] ?? null,
                'authorized_representative' => $data['authorized_representative'] ?? null,
                'representative_position' => $data['representative_position'] ?? null,
                'authority_basis' => $data['authority_basis'] ?? null,
            ])->save();

            $client->fill([
                'counterparty_id' => $counterparty->id,
                'company_name' => $data['company_name'],
                'unp' => $data['unp'],
                'contact_name' => $data['contact_name'],
                'contact_email' => $data['contact_email'],
                'contact_phone' => $data['contact_phone'],
            ])->save();
            $user->update(['name' => $data['contact_name'], 'phone' => $data['contact_phone']]);
        });

        return to_route('b2b.cabinet', ['section' => 'profile'])->with('success', 'Реквизиты компании сохранены.');
    }

    public function saveCart(Request $request)
    {
        abort_unless($request->user()->isB2B(), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $items = collect($request->session()->get('cart', []))
            ->filter(fn (array $item): bool => ($item['pricing_type'] ?? null) === 'b2b')
            ->values();

        if ($items->isEmpty()) {
            return back()->withErrors(['cart' => 'В B2B-корзине нет товаров для сохранения.']);
        }

        $request->user()->b2bSavedCarts()->create([
            'name' => $data['name'],
            'items' => $items->all(),
            'items_count' => $items->sum(fn (array $item): int => (int) ($item['quantity'] ?? 0)),
            'total' => $items->sum(fn (array $item): float => (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 0)),
        ]);

        return to_route('b2b.cabinet', ['section' => 'carts'])->with('success', 'Корзина сохранена.');
    }

    public function restoreCart(Request $request, B2bSavedCart $savedCart, B2bCatalogOfferResolver $resolver)
    {
        $this->authorizeSavedCart($request, $savedCart);
        $products = Product::query()
            ->active()
            ->notArchived()
            ->with('category')
            ->whereIn('id', collect($savedCart->items)->pluck('id'))
            ->get()
            ->keyBy('id');
        $cart = [];
        $skipped = 0;

        foreach ($savedCart->items as $savedItem) {
            $product = $products->get((int) ($savedItem['id'] ?? 0));
            $quantity = min(999, max(1, (int) ($savedItem['quantity'] ?? 1)));
            $offer = $product ? $resolver->forProduct($product, $request->user()) : null;
            if (! $product || ! $offer || ! $resolver->canOrder($offer, $quantity)) {
                $skipped++;

                continue;
            }

            $cart[$product->id] = [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'price' => $resolver->priceWithTax($offer, $request->user()),
                'image' => $product->image_url,
                'category_slug' => $product->category?->slug ?? 'catalog',
                'quantity' => $quantity,
                'pricing_type' => 'b2b',
                'price_tax_mode' => 'inclusive',
                'integration_product_id' => $offer->id,
                'source_name' => $offer->source?->name,
                'chimney_selector_selected' => false,
            ];
        }

        if ($cart === []) {
            return back()->withErrors(['cart' => 'Товары из этой корзины сейчас недоступны.']);
        }

        $request->session()->put('cart', $cart);
        $message = 'Корзина восстановлена с актуальными ценами и остатками.';
        if ($skipped > 0) {
            $message .= " Недоступных позиций: {$skipped}.";
        }

        return to_route('cart')->with('success', $message);
    }

    public function deleteCart(Request $request, B2bSavedCart $savedCart)
    {
        $this->authorizeSavedCart($request, $savedCart);
        $savedCart->delete();

        return to_route('b2b.cabinet', ['section' => 'carts'])->with('success', 'Сохранённая корзина удалена.');
    }

    public function storeSpecification(Request $request, B2bSpecificationParser $parser)
    {
        $user = $request->user();
        abort_unless($user->isB2B(), 403);
        $client = $user->b2bClient()->firstOrFail();
        abort_unless($client->specification_service_enabled, 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:3000'],
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => ['required', 'file', 'max:15360', 'mimes:pdf,xlsx,xls,csv,ods,png,jpg,jpeg,webp'],
        ], [
            'files.required' => 'Добавьте хотя бы один файл спецификации.',
            'files.max' => 'За один раз можно загрузить не более 5 файлов.',
            'files.*.max' => 'Размер каждого файла не должен превышать 15 МБ.',
            'files.*.mimes' => 'Допустимы PDF, Excel, CSV и изображения JPG, PNG или WEBP.',
        ]);

        $storedPaths = [];

        try {
            $specification = DB::transaction(function () use ($client, $user, $data, $request, &$storedPaths): B2bSpecification {
                $specification = B2bSpecification::query()->create([
                    'b2b_client_id' => $client->id,
                    'user_id' => $user->id,
                    'title' => $data['title'],
                    'comment' => $data['comment'] ?? null,
                    'status' => 'submitted',
                    'submitted_at' => now(),
                ]);

                foreach ($request->file('files', []) as $file) {
                    $extension = mb_strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
                    $path = $file->storeAs(
                        "b2b-specifications/{$client->id}/{$specification->id}",
                        Str::uuid().'.'.$extension,
                        'local',
                    );
                    if (! $path) {
                        throw new \RuntimeException('Не удалось сохранить файл спецификации.');
                    }

                    $storedPaths[] = $path;
                    $specification->attachments()->create([
                        'disk' => 'local',
                        'path' => $path,
                        'original_name' => Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 255, ''),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                        'sha256' => hash_file('sha256', $file->getRealPath()),
                    ]);
                }

                return $specification;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }

        try {
            $result = $parser->parse($specification->fresh('attachments'), null, 'upload', $user);
        } catch (\Throwable) {
            $specification->update([
                'parsing_status' => 'failed',
                'parse_message' => 'Автоматический разбор временно недоступен. Файл сохранён и будет обработан менеджером.',
                'parsed_at' => now(),
            ]);
            $result = ['matched' => 0];
        }

        $message = "Спецификация {$specification->reference} принята.";
        if (($result['matched'] ?? 0) > 0) {
            $message .= " Автоматически найдено позиций: {$result['matched']}.";
        }

        return to_route('b2b.cabinet', [
            'section' => 'specifications',
        ])->with('success', $message.' Статус обработки виден в кабинете.');
    }

    public function storeSpecificationRevision(
        Request $request,
        B2bSpecification $specification,
        B2bSpecificationParser $parser,
    ) {
        $user = $request->user();
        abort_unless($user->isB2B() && (int) $specification->user_id === (int) $user->id, 404);
        $client = $user->b2bClient()->firstOrFail();
        abort_unless($client->specification_service_enabled, 403);
        abort_if(in_array($specification->status, ['closed', 'cancelled'], true), 422, 'Завершённую заявку нельзя изменить.');

        $data = $request->validate([
            'revision_note' => ['nullable', 'string', 'max:1000'],
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => ['required', 'file', 'max:15360', 'mimes:pdf,xlsx,xls,csv,ods,png,jpg,jpeg,webp'],
        ], [
            'files.required' => 'Добавьте хотя бы один файл новой редакции.',
            'files.max' => 'За один раз можно загрузить не более 5 файлов.',
            'files.*.max' => 'Размер каждого файла не должен превышать 15 МБ.',
            'files.*.mimes' => 'Допустимы PDF, Excel, CSV и изображения JPG, PNG или WEBP.',
        ]);

        $storedPaths = [];
        $attachments = collect();

        try {
            DB::transaction(function () use ($specification, $request, &$storedPaths, &$attachments): void {
                foreach ($request->file('files', []) as $file) {
                    $extension = mb_strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
                    $path = $file->storeAs(
                        "b2b-specifications/{$specification->b2b_client_id}/{$specification->id}",
                        Str::uuid().'.'.$extension,
                        'local',
                    );
                    if (! $path) {
                        throw new \RuntimeException('Не удалось сохранить новую редакцию спецификации.');
                    }

                    $storedPaths[] = $path;
                    $attachments->push($specification->attachments()->create([
                        'disk' => 'local',
                        'path' => $path,
                        'original_name' => Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 255, ''),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                        'sha256' => hash_file('sha256', $file->getRealPath()),
                    ]));
                }
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }

        try {
            $result = $parser->parse(
                $specification->fresh(['attachments', 'currentVersion']),
                $attachments->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                'revision',
                $user,
                $data['revision_note'] ?? null,
            );
        } catch (\Throwable) {
            $specification->update([
                'parsing_status' => 'failed',
                'parse_message' => 'Новая редакция сохранена, но автоматический разбор временно недоступен.',
                'parsed_at' => now(),
            ]);

            return back()->withErrors(['files' => 'Файлы сохранены. Менеджер обработает новую редакцию вручную.']);
        }

        $diff = $result['diff'];
        $message = "Редакция №{$result['version']} сохранена. Добавлено: {$diff['added']}, изменено: {$diff['changed']}, удалено: {$diff['removed']}.";

        return to_route('b2b.cabinet', ['section' => 'specifications'])
            ->with('success', $message);
    }

    public function specificationCart(Request $request, B2bSpecification $specification, B2bCatalogOfferResolver $resolver)
    {
        $user = $request->user();
        abort_unless($user->isB2B() && (int) $specification->user_id === (int) $user->id, 404);

        $items = $specification->items()
            ->where('status', 'matched')
            ->whereNotNull('product_id')
            ->with('product.category')
            ->get();
        $cart = $request->session()->get('cart', []);
        $added = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $product = $item->product;
            $quantity = min(999, max(1, (int) ceil((float) $item->quantity)));
            $offer = $product ? $resolver->forProduct($product, $user) : null;
            if (! $product || ! $offer || ! $resolver->canOrder($offer, $quantity)) {
                $skipped++;

                continue;
            }

            $quote = $resolver->priceQuote($offer, $user);
            $existingQuantity = (int) ($cart[$product->id]['quantity'] ?? 0);
            $cart[$product->id] = [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'price' => $quote['price'],
                'image' => $product->image_url,
                'category_slug' => $product->category?->slug ?? 'catalog',
                'quantity' => min(999, $existingQuantity + $quantity),
                'chimney_selector_selected' => false,
                'pricing_type' => 'b2b',
                'price_tax_mode' => 'inclusive',
                'integration_product_id' => $offer->id,
                'source_name' => $offer->source?->name,
                'warehouse_stock_id' => null,
                'warehouse_name' => data_get($offer->source?->settings, 'warehouse_label'),
                'warehouse_delivery_days' => null,
                'b2b_base_price' => $quote['base_price'],
                'b2b_price_level_id' => $quote['price_level_id'],
                'b2b_price_level_code' => $quote['price_level_code'],
                'b2b_price_level_name' => $quote['price_level_name'],
                'b2b_discount_percent' => $quote['discount_percent'],
            ];
            $added++;
        }

        if ($added === 0) {
            return back()->withErrors(['specification' => 'Сопоставленные товары сейчас недоступны для заказа. Менеджер проверит остальные позиции.']);
        }

        $request->session()->put('cart', $cart);
        $message = "В корзину добавлено позиций: {$added}.";
        if ($skipped > 0) {
            $message .= " Требуют проверки менеджера: {$skipped}.";
        }

        return to_route('cart')->with('success', $message);
    }

    public function specificationAttachment(Request $request, B2bSpecificationAttachment $attachment): StreamedResponse
    {
        $user = $request->user();
        $specification = $attachment->specification()->firstOrFail();
        $ownsSpecification = $user->isB2B()
            && (int) $specification->user_id === (int) $user->id;
        abort_unless($user->isManager() || $ownsSpecification, 404);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function contractFile(Request $request, CommercialContract $contract, string $format): BinaryFileResponse|StreamedResponse
    {
        abort_unless($request->user()->isB2B(), 403);
        abort_unless(in_array($format, ['pdf', 'docx'], true), 404);
        abort_unless($this->documentsFor($request)->whereKey($contract->commercial_document_id)->exists(), 404);

        $path = $format === 'pdf' ? $contract->generated_pdf_path : $contract->generated_docx_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        $filename = 'Договор-'.$contract->number.'.'.$format;

        if ($format === 'pdf') {
            return response()->file(Storage::disk('local')->path($path), [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => (new ResponseHeaderBag)->makeDisposition('inline', $filename, 'contract-'.$contract->id.'.pdf'),
                'Cache-Control' => 'private, no-store, max-age=0',
            ]);
        }

        return Storage::disk('local')->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    private function documentsFor(Request $request): Builder
    {
        return $this->scopeDocuments(CommercialDocument::query(), $request);
    }

    private function scopeDocuments(Builder $query, Request $request): Builder
    {
        $client = $request->user()->b2bClient;
        $counterpartyId = (int) ($client?->counterparty_id ?? 0);

        return $query
            ->where('status', '!=', 'draft')
            ->where(function (Builder $owned) use ($request, $counterpartyId): void {
                if ($counterpartyId > 0) {
                    $owned->where('crm_counterparty_id', $counterpartyId)
                        ->orWhere(function (Builder $email) use ($request): void {
                            $email->whereNull('crm_counterparty_id')->where('client_email', $request->user()->email);
                        });
                } else {
                    $owned->where('client_email', $request->user()->email);
                }
            });
    }

    private function authorizeSavedCart(Request $request, B2bSavedCart $savedCart): void
    {
        abort_unless($request->user()->isB2B() && (int) $savedCart->user_id === (int) $request->user()->id, 404);
    }

    private function date(mixed $value, string $fallback): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse(filled($value) ? (string) $value : $fallback);
        } catch (\Throwable) {
            return CarbonImmutable::parse($fallback);
        }
    }

    private function balance($orders): float
    {
        $charges = (float) $orders->where('status', '!=', 'cancelled')->sum('total');
        $payments = (float) $orders->where('payment_status', 'paid')->sum('total');

        return $charges - $payments;
    }

    /** @return array{int, array<int, string>} */
    private function profileCompletion($client, ?CrmCounterparty $counterparty, $user): array
    {
        $fields = [
            'Полное юридическое название' => $counterparty?->legal_name,
            'УНП' => $client->unp,
            'Юридический адрес' => $counterparty?->legal_address,
            'Контактное лицо' => $client->contact_name ?: $user->name,
            'Телефон' => $client->contact_phone ?: $user->phone,
            'Email' => $client->contact_email ?: $user->email,
            'Банк' => $counterparty?->bank_name,
            'BIC банка' => $counterparty?->bank_bic,
            'IBAN' => $counterparty?->bank_iban,
            'ФИО подписанта' => $counterparty?->authorized_representative,
            'Должность подписанта' => $counterparty?->representative_position,
            'Основание полномочий' => $counterparty?->authority_basis,
        ];
        $missing = collect($fields)
            ->filter(fn (mixed $value): bool => blank($value))
            ->keys()
            ->values()
            ->all();
        $completed = count($fields) - count($missing);

        return [(int) round($completed / count($fields) * 100), $missing];
    }
}
