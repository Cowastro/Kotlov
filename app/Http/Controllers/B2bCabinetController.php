<?php

namespace App\Http\Controllers;

use App\Models\B2bClient;
use App\Models\B2bClientAuditLog;
use App\Models\B2bClientInvitation;
use App\Models\B2bClientMember;
use App\Models\B2bOrder;
use App\Models\B2bSavedCart;
use App\Models\B2bSpecification;
use App\Models\B2bSpecificationAttachment;
use App\Models\CommercialContract;
use App\Models\CommercialDocument;
use App\Models\CrmCounterparty;
use App\Models\IntegrationSource;
use App\Models\IntegrationWarehouse;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\B2bCompanyInvitationNotification;
use App\Services\B2bCatalogOfferResolver;
use App\Services\B2bCheckoutTerms;
use App\Services\B2bCompanyAccess;
use App\Services\B2bOrders\B2bOrderWorkflow;
use App\Services\B2bSpecificationParser;
use App\Services\B2bTeamService;
use App\Services\CommercialDocuments\B2bSelfServiceInvoice;
use App\Services\CommercialDocuments\CommercialDocumentPdf;
use App\Services\InstallerPageProvisioner;
use App\Services\Orders\OrderPartnerAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class B2bCabinetController extends Controller
{
    private const SECTIONS = ['overview', 'profile', 'team', 'specifications', 'carts', 'leads', 'orders', 'documents', 'contracts', 'reconciliation', 'page', 'security'];

    private const SECTION_PERMISSIONS = [
        'profile' => 'profile.manage',
        'page' => 'profile.manage',
        'team' => 'team.manage',
        'specifications' => 'specifications.manage',
        'carts' => 'orders.create',
        'leads' => 'orders.view',
        'orders' => 'orders.view',
        'documents' => 'documents.view',
        'contracts' => 'documents.view',
        'reconciliation' => 'finance.view',
    ];

    public function __construct(private readonly B2bCompanyAccess $access) {}

    public function preview(Request $request, B2bClient $client, B2bCheckoutTerms $checkoutTerms)
    {
        $staff = $request->user();
        abort_unless($staff?->isManager(), 403);
        abort_unless($client->status === 'approved' && $client->user?->is_active, 404);

        $request->setUserResolver(fn (): User => $client->user);
        $request->attributes->set('b2b_admin_preview', true);

        return $this->show($request, $checkoutTerms);
    }

    public function show(Request $request, B2bCheckoutTerms $checkoutTerms)
    {
        $user = $request->user();
        abort_unless($user->isB2B(), 403);

        $client = $user->activeB2bClient(['counterparty', 'priceLevel']);
        abort_unless($client, 403);
        $role = $this->access->role($user, $client);
        $permissions = collect(B2bCompanyAccess::PERMISSIONS[$role] ?? []);
        $permissionLabels = $this->access->permissionLabels((string) $role);
        $rolePermissionLabels = collect(B2bClientMember::ROLES)
            ->mapWithKeys(fn (string $label, string $roleCode): array => [
                $roleCode => $this->access->permissionLabels($roleCode),
            ]);
        $passwordRecentlyConfirmed = (int) $request->session()->get('auth.password_confirmed_at', 0)
            >= time() - max(60, (int) config('auth.password_timeout', 10800));
        $companyUserIds = $this->access->allUserIds($client);
        $allowedSources = IntegrationSource::query()
            ->where('is_active', true)
            ->when(! $client->allow_all_integration_sources, fn (Builder $query) => $query
                ->whereIn('id', array_map('intval', $client->allowed_integration_source_ids ?? [])))
            ->orderBy('name')
            ->pluck('name');
        $allowedWarehouses = IntegrationWarehouse::query()
            ->with('source:id,name')
            ->when(! $client->allow_all_integration_warehouses, fn (Builder $query) => $query
                ->whereIn('id', array_map('intval', $client->allowed_integration_warehouse_ids ?? [])))
            ->orderBy('name')
            ->get();
        $allowedPartnerSuppliers = Supplier::query()
            ->when(! $client->allow_all_partner_suppliers, fn (Builder $query) => $query
                ->whereIn('id', array_map('intval', $client->allowed_supplier_ids ?? [])))
            ->where('is_active', true)
            ->where('b2b_partner_enabled', true)
            ->orderBy('name')
            ->get(['id', 'name', 'b2b_client_warehouse_label']);
        $requestedSection = $request->string('section')->toString();
        $section = in_array($requestedSection, self::SECTIONS, true)
            ? $request->string('section')->toString()
            : 'overview';
        $sectionPermission = self::SECTION_PERMISSIONS[$section] ?? null;
        if ($sectionPermission && ! $permissions->contains($sectionPermission)) {
            $section = 'overview';
        }
        $counterparty = $client->counterparty;
        $creditSummary = $checkoutTerms->creditSummary($client);
        $ordersQuery = B2bOrder::query()
            ->where('b2b_client_id', $client->id)
            ->with([
                'items', 'commercialDocument', 'manager:id,name',
                'comments' => fn ($query) => $query->where('visible_to_client', true)->with('author:id,name')->oldest(),
            ])
            ->latest();
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
        $savedCarts = B2bSavedCart::query()->whereIn('user_id', $companyUserIds)->with('user:id,name')->latest('updated_at')->get();
        $specifications = $client->specifications()
            ->with([
                'attachments', 'commercialDocument', 'items.product',
                'versions' => fn ($query) => $query->with('creator')->orderByDesc('number'),
            ])
            ->latest('submitted_at')
            ->paginate(10, ['*'], 'specifications_page')
            ->withQueryString();
        [$profileCompletion, $profileMissing] = $this->profileCompletion($client, $counterparty, $user);
        $invoiceProfileMissing = $this->invoiceProfileMissing($client, $counterparty, $user);
        $invoiceProfileReady = $invoiceProfileMissing === [];

        $dateFrom = $this->date($request->input('date_from'), now()->startOfYear()->toDateString());
        $dateTo = $this->date($request->input('date_to'), now()->toDateString());
        if ($dateFrom->greaterThan($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $reconciliationOrders = B2bOrder::query()->where('b2b_client_id', $client->id)
            ->whereBetween('created_at', [$dateFrom->startOfDay(), $dateTo->endOfDay()])
            ->oldest('created_at')
            ->get();
        $openingOrders = B2bOrder::query()->where('b2b_client_id', $client->id)->where('created_at', '<', $dateFrom->startOfDay())->get();
        $openingBalance = $this->balance($openingOrders);
        $periodCharges = (float) $reconciliationOrders->where('status', '!=', 'cancelled')->sum('total');
        $periodPayments = (float) $reconciliationOrders->where('payment_status', 'paid')->sum('total');
        $closingBalance = $openingBalance + $periodCharges - $periodPayments;

        $members = $client->members()->with('user:id,name,email,is_active')->orderByRaw("case role when 'owner' then 0 when 'buyer' then 1 when 'accountant' then 2 else 3 end")->get();
        $pendingInvitations = $client->invitations()
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->get();
        $auditLogs = $client->auditLogs()
            ->with(['actor:id,name,email', 'target:id,name,email'])
            ->latest()
            ->limit(30)
            ->get();
        $financialAuditLogs = $client->auditLogs()
            ->whereIn('event', ['invoice_generated', 'document_downloaded'])
            ->with('actor:id,name,email')
            ->latest()
            ->limit(20)
            ->get();
        $companyOrderTotal = (float) B2bOrder::query()->where('b2b_client_id', $client->id)->sum('total');

        $installerPage = $client->partner_type === 'installer' && in_array($section, ['page', 'overview'], true)
            ? InstallerAccountController::pageFor($user)
            : null;
        $installerPage?->load(['works' => fn ($query) => $query->latest('completed_at')->latest('id')]);
        $installerEditor = InstallerAccountController::editorOptions();
        // Site orders the centre handed to this partner's sales points.
        $clientOrdersQuery = Order::query()->whereIn('partner_branch_id', $client->branches()->select('id'))
            ->whereNotNull('partner_status');
        $offeredClientOrders = (clone $clientOrdersQuery)->where('partner_status', 'offered')->count();
        $clientOrders = $section === 'leads'
            ? $clientOrdersQuery->with(['items', 'partnerBranch.city'])->latest('partner_assigned_at')->limit(50)->get()
            : collect();
        $partnerBranches = in_array($section, ['overview', 'page'], true)
            ? $client->branches()->with('city')
                ->withCount(['contactViews as contact_views_30d_count' => fn ($views) => $views->where('created_at', '>=', now()->subDays(30))])
                ->orderBy('sort_order')->get()
            : collect();
        $adminPreview = (bool) $request->attributes->get('b2b_admin_preview', false);
        $branchCities = $section === 'page' && $client->partner_type !== 'installer'
            ? \App\Models\City::where('is_active', true)->orderBy('name')->get() : collect();

        return view('pages.b2b.cabinet', compact(
            'installerPage', 'installerEditor', 'partnerBranches', 'clientOrders', 'offeredClientOrders',
            'user', 'client', 'counterparty', 'section', 'orders', 'documents', 'contracts', 'savedCarts', 'specifications',
            'allowedSources', 'allowedWarehouses', 'allowedPartnerSuppliers', 'creditSummary',
            'profileCompletion', 'profileMissing',
            'invoiceProfileReady', 'invoiceProfileMissing',
            'role', 'permissions', 'permissionLabels', 'rolePermissionLabels', 'passwordRecentlyConfirmed',
            'members', 'pendingInvitations', 'auditLogs', 'financialAuditLogs', 'companyOrderTotal',
            'dateFrom', 'dateTo', 'reconciliationOrders', 'openingBalance', 'periodCharges', 'periodPayments', 'closingBalance',
            'adminPreview', 'branchCities',
        ));
    }

    public function acceptClientOrder(Request $request, Order $order, OrderPartnerAssignment $assignment)
    {
        $user = $request->user();
        $this->authorizeClientOrder($user, $order, 'orders.create');
        $cart = $assignment->accept($order, $user);

        return to_route('b2b.cabinet', ['section' => 'leads'])
            ->with('success', 'Заказ '.$order->number.' принят. Корзина для закупки «'.$cart->name.'» сохранена — оформите её, когда будете готовы.');
    }

    public function declineClientOrder(Request $request, Order $order, OrderPartnerAssignment $assignment)
    {
        $user = $request->user();
        $this->authorizeClientOrder($user, $order, 'orders.create');
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => 'Укажите причину отказа.']);
        $assignment->decline($order, $user, $data['reason']);

        return to_route('b2b.cabinet', ['section' => 'leads'])->with('success', 'Заказ '.$order->number.' возвращён в KOTLOV.');
    }

    private function authorizeClientOrder($user, Order $order, string $permission): void
    {
        $client = $user->activeB2bClient();
        abort_unless($client && $this->access->allows($user, $permission, $client), 403);
        abort_unless($order->partner_branch_id && $client->branches()->whereKey($order->partner_branch_id)->exists(), 404);
    }

    /** A partner without a page gets an installer page filled from the company card, sent to moderation. */
    public function createPage(Request $request)
    {
        $user = $request->user();
        $client = $user->activeB2bClient(['user']);
        abort_unless($client && $client->partner_type === 'installer' && $this->access->allows($user, 'profile.manage', $client), 403);
        if (! InstallerAccountController::pageFor($user)) {
            app(InstallerPageProvisioner::class)->ensureFor($client);
            $client->forceFill(['partner_type' => 'installer'])->save();
        }

        return to_route('b2b.cabinet', ['section' => 'page'])
            ->with('success', 'Страница создана. Заполните её — после проверки KOTLOV она появится в каталоге монтажников.');
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $resolvedClient = $user->activeB2bClient();
        abort_unless($resolvedClient && $this->access->allows($user, 'profile.manage', $resolvedClient), 403);

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
            'default_delivery_city' => ['nullable', 'string', 'max:255'],
            'default_delivery_address' => ['nullable', 'string', 'max:1000'],
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

        DB::transaction(function () use ($user, $data, $resolvedClient): void {
            $client = B2bClient::query()->whereKey($resolvedClient->id)->lockForUpdate()->firstOrFail();
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
                'default_delivery_city' => $data['default_delivery_city'] ?? null,
                'default_delivery_address' => $data['default_delivery_address'] ?? null,
            ])->save();
            $user->update(['name' => $data['contact_name'], 'phone' => $data['contact_phone']]);
        });

        return to_route('b2b.cabinet', ['section' => 'profile'])->with('success', 'Реквизиты компании сохранены.');
    }

    public function confirmSensitiveActions(Request $request)
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
            'section' => ['required', 'in:profile,team,security'],
        ], [
            'password.required' => 'Введите пароль от аккаунта.',
        ]);

        if (! Hash::check($data['password'], $request->user()->password)) {
            return to_route('b2b.cabinet', ['section' => $data['section']])
                ->withErrors(['password' => 'Неверный пароль.']);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return to_route('b2b.cabinet', ['section' => $data['section']])
            ->with('success', 'Личность подтверждена. Защищённые действия доступны на '.max(1, (int) ceil(config('auth.password_timeout') / 3600)).' ч.');
    }

    public function saveCart(Request $request)
    {
        abort_unless($this->allows($request, 'orders.create'), 403);
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
        $client = $user->activeB2bClient();
        abort_unless($client && $this->access->allows($user, 'specifications.manage', $client), 403);
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
        $client = $user->activeB2bClient();
        abort_unless($client && $this->access->allows($user, 'specifications.manage', $client) && (int) $specification->b2b_client_id === (int) $client->id, 404);
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
        $client = $user->activeB2bClient();
        abort_unless($client && $this->access->allows($user, 'orders.create', $client) && (int) $specification->b2b_client_id === (int) $client->id, 404);

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
        $client = $user->activeB2bClient();
        $ownsSpecification = $client
            && $this->access->allows($user, 'documents.view', $client)
            && (int) $specification->b2b_client_id === (int) $client->id;
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
        abort_unless($this->allows($request, 'contracts.download'), 403);
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

    public function documentPdf(
        Request $request,
        CommercialDocument $document,
        CommercialDocumentPdf $pdf,
    ): Response {
        abort_unless($this->allows($request, 'documents.download'), 403);
        abort_unless($this->documentsFor($request)->whereKey($document->id)->exists(), 404);

        $filename = ($document->type === 'invoice' ? 'Счет-' : 'Коммерческое-предложение-').$document->number.'.pdf';
        $fallback = ($document->type === 'invoice' ? 'invoice-' : 'quote-').$document->id.'.pdf';

        $contents = $pdf->render($document);
        $client = $request->user()->activeB2bClient();
        B2bClientAuditLog::query()->create([
            'b2b_client_id' => $client->id,
            'actor_user_id' => $request->user()->id,
            'event' => 'document_downloaded',
            'metadata' => [
                'document_id' => $document->id,
                'document_number' => $document->number,
                'document_type' => $document->type,
            ],
        ]);

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => (new ResponseHeaderBag)->makeDisposition('inline', $filename, $fallback),
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function generateInvoice(
        Request $request,
        Order $order,
        B2bSelfServiceInvoice $invoices,
    ) {
        $user = $request->user();
        $client = $user->activeB2bClient(['counterparty']);
        abort_unless($client && $this->access->allows($user, 'documents.generate', $client), 403);
        abort_unless($this->access->allUserIds($client)->contains((int) $order->user_id), 404);

        $document = $invoices->generate($client, $user, $order);

        return to_route('b2b.cabinet', ['section' => 'documents'])
            ->with('success', 'Счёт '.$document->number.' сформирован из подтверждённого заказа. Состав и цены зафиксированы.');
    }

    public function confirmOrder(Request $request, B2bOrder $b2bOrder, B2bOrderWorkflow $workflow)
    {
        $client = $request->user()->activeB2bClient();
        abort_unless($client
            && $this->access->allows($request->user(), 'orders.create', $client)
            && (int) $b2bOrder->b2b_client_id === (int) $client->id, 404);

        $order = $workflow->clientConfirm($b2bOrder, $request->user())->load('commercialDocument');

        return to_route('b2b.cabinet', ['section' => 'orders'])
            ->with('success', $order->commercialDocument
                ? 'Заказ '.$order->number.' подтверждён. Счёт '.$order->commercialDocument->number.' сформирован — он в карточке заказа и в разделе «КП и счета», копия отправлена на почту.'
                : 'Заказ '.$order->number.' подтверждён. Менеджер выставит счёт в ближайшее время.');
    }

    private function documentsFor(Request $request): Builder
    {
        return $this->scopeDocuments(CommercialDocument::query(), $request);
    }

    private function scopeDocuments(Builder $query, Request $request): Builder
    {
        $client = $request->user()->activeB2bClient();
        $counterpartyId = (int) ($client?->counterparty_id ?? 0);
        $companyEmails = $client
            ? User::query()->whereIn('id', $this->access->allUserIds($client))->pluck('email')->filter()->all()
            : [$request->user()->email];

        return $query
            ->where('status', '!=', 'draft')
            ->where(function (Builder $owned) use ($companyEmails, $counterpartyId, $client): void {
                // Invoices of the company's own B2B orders, whatever e-mail they were issued to.
                $owned->whereIn('id', B2bOrder::query()
                    ->where('b2b_client_id', (int) ($client?->id ?? 0))
                    ->whereNotNull('commercial_document_id')
                    ->select('commercial_document_id'));
                if ($counterpartyId > 0) {
                    $owned->orWhere('crm_counterparty_id', $counterpartyId)
                        ->orWhere(function (Builder $email) use ($companyEmails): void {
                            $email->whereNull('crm_counterparty_id')->whereIn('client_email', $companyEmails);
                        });

                    return;
                }
                $owned->orWhereIn('client_email', $companyEmails);
            });
    }

    private function authorizeSavedCart(Request $request, B2bSavedCart $savedCart): void
    {
        $client = $request->user()->activeB2bClient();
        abort_unless($client && $this->access->allows($request->user(), 'orders.create', $client), 404);
        abort_unless($this->access->allUserIds($client)->contains((int) $savedCart->user_id), 404);
    }

    public function addMember(Request $request, B2bTeamService $teams)
    {
        $owner = $request->user();
        $client = $owner->activeB2bClient();
        abort_unless($client && $this->access->allows($owner, 'team.manage', $client), 403);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:buyer,branch_operator,accountant,viewer'],
        ]);
        [$invitation, $token] = $teams->invite($client, $owner, $data['email'], $data['role']);

        try {
            Notification::route('mail', $invitation->email)
                ->notify(new B2bCompanyInvitationNotification($invitation->load(['client', 'inviter']), $token));
        } catch (\Throwable $exception) {
            $teams->revokeInvitation($invitation, $owner);
            Log::error('B2B company invitation email failed.', [
                'b2b_client_id' => $client->id,
                'invitation_id' => $invitation->id,
                'exception' => $exception->getMessage(),
            ]);

            return back()->withErrors(['email' => 'Не удалось отправить приглашение. Повторите попытку позже.']);
        }

        return to_route('b2b.cabinet', ['section' => 'team'])->with('success', 'Одноразовое приглашение отправлено на '.$invitation->email.'.');
    }

    public function updateMember(Request $request, B2bClientMember $member, B2bTeamService $teams)
    {
        $owner = $request->user();
        $client = $owner->activeB2bClient();
        abort_unless($client && $this->access->allows($owner, 'team.manage', $client), 403);
        abort_unless((int) $member->b2b_client_id === (int) $client->id && $member->role !== 'owner', 404);
        $data = $request->validate(['role' => ['required', 'in:buyer,branch_operator,accountant,viewer']]);
        $teams->changeRole($member, $owner, $data['role']);

        return to_route('b2b.cabinet', ['section' => 'team'])->with('success', 'Роль сотрудника обновлена.');
    }

    public function removeMember(Request $request, B2bClientMember $member, B2bTeamService $teams)
    {
        $owner = $request->user();
        $client = $owner->activeB2bClient();
        abort_unless($client && $this->access->allows($owner, 'team.manage', $client), 403);
        abort_unless((int) $member->b2b_client_id === (int) $client->id && $member->role !== 'owner', 404);
        $teams->revokeMember($member, $owner);

        return to_route('b2b.cabinet', ['section' => 'team'])->with('success', 'Доступ сотрудника отключён, его активные сессии завершены.');
    }

    public function revokeInvitation(Request $request, B2bClientInvitation $invitation, B2bTeamService $teams)
    {
        $owner = $request->user();
        $client = $owner->activeB2bClient();
        abort_unless($client && $this->access->allows($owner, 'team.manage', $client), 403);
        abort_unless((int) $invitation->b2b_client_id === (int) $client->id && $invitation->isAvailable(), 404);
        $teams->revokeInvitation($invitation, $owner);

        return to_route('b2b.cabinet', ['section' => 'team'])->with('success', 'Приглашение отозвано.');
    }

    private function allows(Request $request, string $permission): bool
    {
        $client = $request->user()->activeB2bClient();

        return $client !== null && $this->access->allows($request->user(), $permission, $client);
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

    /** @return array<int, string> */
    private function invoiceProfileMissing(B2bClient $client, ?CrmCounterparty $counterparty, User $user): array
    {
        return collect([
            'полное юридическое название' => $counterparty?->legal_name,
            'УНП' => preg_match('/^\d{9}$/', (string) $client->unp) ? $client->unp : null,
            'юридический адрес' => $counterparty?->legal_address,
            'email' => filter_var((string) ($client->contact_email ?: $user->email), FILTER_VALIDATE_EMAIL),
            'карточка контрагента' => $counterparty?->id,
        ])->filter(fn ($value): bool => blank($value))->keys()->values()->all();
    }
}
