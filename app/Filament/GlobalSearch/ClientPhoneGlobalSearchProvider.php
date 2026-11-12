<?php

namespace App\Filament\GlobalSearch;

use App\Filament\Resources\B2bClients\B2bClientResource;
use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Filament\Resources\ContactRequests\ContactRequestResource;
use App\Filament\Resources\CrmContacts\CrmContactResource;
use App\Filament\Resources\CrmCounterparties\CrmCounterpartyResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\TelephonyCalls\TelephonyCallResource;
use App\Models\B2bClient;
use App\Models\CommercialDocument;
use App\Models\ContactRequest;
use App\Models\CrmContact;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\Order;
use App\Models\TelephonyCall;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\GlobalSearch\GlobalSearchResults;
use Filament\GlobalSearch\Providers\Contracts\GlobalSearchProvider;
use Filament\GlobalSearch\Providers\DefaultGlobalSearchProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The search box at the top of the admin finds a client by phone in any spelling: «+375 29 123-45-67»,
 * «80291234567», «291234567» or a part of it («1234567»). It looks in CRM counterparties and contacts, orders,
 * requests, calls, quotes and B2B clients. Anything that is not a phone goes to Filament's usual search.
 */
class ClientPhoneGlobalSearchProvider implements GlobalSearchProvider
{
    private const LIMIT = 10;

    public function getResults(string $query): ?GlobalSearchResults
    {
        $digits = self::phoneDigits($query);
        if ($digits === null) {
            return (new DefaultGlobalSearchProvider)->getResults($query);
        }

        $results = GlobalSearchResults::make();
        foreach ([
            'Контрагенты' => fn () => $this->counterparties($digits),
            'Контактные лица' => fn () => $this->contacts($digits),
            'Заказы' => fn () => $this->orders($digits),
            'Обращения' => fn () => $this->requests($digits),
            'КП и счета' => fn () => $this->documents($digits),
            'B2B-клиенты' => fn () => $this->b2bClients($digits),
            'Звонки' => fn () => $this->calls($digits),
        ] as $category => $search) {
            $found = $search();
            if ($found->isNotEmpty()) {
                $results->category($category, $found->all());
            }
        }

        return $results;
    }

    /**
     * The digits to look for when the query is a phone (only digits, spaces, + - ( ) . and at least 5 digits):
     * the last 9 — the number without 375 / 80 / 0 — or the whole part typed.
     */
    public static function phoneDigits(string $query): ?string
    {
        $query = trim($query);
        if (! preg_match('/^[\d\s+\-().]+$/', $query)) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $query) ?? '';

        return strlen($digits) < 5 ? null : (strlen($digits) > 9 ? substr($digits, -9) : $digits);
    }

    /** The column without the usual separators, so «+375 (29) 123-45-67» compares as «375291234567». */
    private static function whereDigits(Builder $query, string $column, string $digits): Builder
    {
        $sql = $query->getQuery()->getGrammar()->wrap($column);
        foreach ([' ', '-', '(', ')', '+', '.'] as $char) {
            $sql = "REPLACE({$sql}, '{$char}', '')";
        }

        return $query->whereRaw("{$sql} LIKE ?", ['%'.$digits.'%']);
    }

    private function phonePoints(string $digits): Builder
    {
        return CrmContactPoint::query()->where('type', 'phone')->where('normalized_value', 'like', '%'.$digits.'%');
    }

    /** @return Collection<int, GlobalSearchResult> */
    private function counterparties(string $digits): Collection
    {
        if (! CrmCounterpartyResource::canViewAny()) {
            return collect();
        }
        $points = $this->phonePoints($digits)->with('contact:id,counterparty_id')->limit(50)->get();
        $ids = $points->pluck('counterparty_id')->merge($points->pluck('contact.counterparty_id'))->filter()->unique();

        return CrmCounterparty::query()->whereIn('id', $ids)->limit(self::LIMIT)->get()
            ->map(fn (CrmCounterparty $counterparty) => new GlobalSearchResult(
                title: (string) $counterparty->name,
                url: CrmCounterpartyResource::getUrl('view', ['record' => $counterparty]),
                details: array_filter([
                    'Телефон' => $points->first(fn ($point) => $point->counterparty_id === $counterparty->id
                        || $point->contact?->counterparty_id === $counterparty->id)?->value,
                    'УНП' => $counterparty->unp,
                ]),
            ));
    }

    /** @return Collection<int, GlobalSearchResult> */
    private function contacts(string $digits): Collection
    {
        if (! CrmContactResource::canViewAny()) {
            return collect();
        }
        $points = $this->phonePoints($digits)->whereNotNull('contact_id')->limit(50)->get();

        return CrmContact::query()->with('counterparty:id,name')->whereIn('id', $points->pluck('contact_id')->unique())->limit(self::LIMIT)->get()
            ->map(fn (CrmContact $contact) => new GlobalSearchResult(
                title: (string) ($contact->full_name ?: 'Без имени'),
                url: CrmContactResource::getUrl('view', ['record' => $contact]),
                details: array_filter([
                    'Телефон' => $points->firstWhere('contact_id', $contact->id)?->value,
                    'Контрагент' => $contact->counterparty?->name,
                ]),
            ));
    }

    /** @return Collection<int, GlobalSearchResult> */
    private function orders(string $digits): Collection
    {
        if (! OrderResource::canViewAny()) {
            return collect();
        }

        return self::whereDigits(Order::query(), 'customer_phone', $digits)->latest('id')->limit(self::LIMIT)->get()
            ->map(fn (Order $order) => new GlobalSearchResult(
                title: 'Заказ '.$order->number,
                url: OrderResource::getUrl('view', ['record' => $order]),
                details: array_filter([
                    'Клиент' => $order->company_name ?: $order->customer_name,
                    'Телефон' => $order->customer_phone,
                    'Дата' => $order->created_at?->format('d.m.Y'),
                ]),
            ));
    }

    /** @return Collection<int, GlobalSearchResult> */
    private function requests(string $digits): Collection
    {
        if (! ContactRequestResource::canViewAny()) {
            return collect();
        }

        return self::whereDigits(ContactRequest::query(), 'phone', $digits)->latest('id')->limit(self::LIMIT)->get()
            ->map(fn (ContactRequest $request) => new GlobalSearchResult(
                title: (string) ($request->name ?: 'Обращение №'.$request->id),
                url: ContactRequestResource::getUrl('view', ['record' => $request]),
                details: array_filter([
                    'Телефон' => $request->phone,
                    'Дата' => $request->created_at?->format('d.m.Y'),
                ]),
            ));
    }

    /** @return Collection<int, GlobalSearchResult> */
    private function documents(string $digits): Collection
    {
        if (! CommercialDocumentResource::canViewAny()) {
            return collect();
        }

        return self::whereDigits(CommercialDocument::query(), 'client_phone', $digits)->latest('id')->limit(self::LIMIT)->get()
            ->map(fn (CommercialDocument $document) => new GlobalSearchResult(
                title: (string) ($document->number ?: 'Документ №'.$document->id),
                url: CommercialDocumentResource::getUrl('view', ['record' => $document]),
                details: array_filter([
                    'Клиент' => $document->client_company ?: $document->client_name,
                    'Телефон' => $document->client_phone,
                ]),
            ));
    }

    /** @return Collection<int, GlobalSearchResult> */
    private function b2bClients(string $digits): Collection
    {
        if (! B2bClientResource::canViewAny()) {
            return collect();
        }

        return self::whereDigits(B2bClient::query(), 'contact_phone', $digits)->limit(self::LIMIT)->get()
            ->map(fn (B2bClient $client) => new GlobalSearchResult(
                title: (string) ($client->company_name ?: $client->contact_name ?: 'B2B-клиент №'.$client->id),
                url: B2bClientResource::getUrl('edit', ['record' => $client]),
                details: array_filter(['Контакт' => $client->contact_name, 'Телефон' => $client->contact_phone]),
            ));
    }

    /** @return Collection<int, GlobalSearchResult> */
    private function calls(string $digits): Collection
    {
        if (! TelephonyCallResource::canViewAny()) {
            return collect();
        }

        return self::whereDigits(TelephonyCall::query(), 'customer_phone', $digits)->latest('id')->limit(5)->get()
            ->map(fn (TelephonyCall $call) => new GlobalSearchResult(
                title: Str::of(match ($call->direction) { 'inbound' => 'Входящий', 'outbound' => 'Исходящий', default => 'Звонок' })->append(' ', (string) $call->customer_phone)->toString(),
                url: TelephonyCallResource::getUrl('view', ['record' => $call]),
                details: array_filter(['Дата' => $call->started_at?->format('d.m.Y H:i')]),
            ));
    }
}
