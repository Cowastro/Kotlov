<?php

namespace App\Services\Crm;

use App\Models\B2bClient;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\CrmMailingList;
use App\Models\CrmMailingListMember;
use App\Models\PartnerBranch;
use App\Models\Supplier;
use App\Services\Telephony\PhoneNumber;
use Illuminate\Support\Collection;

/**
 * Finds partners by the names a manager pastes («Олещик/ТД Пинск», «ИП Кузин», «грандтеплокомпани») across CRM
 * counterparties, B2B clients, their branches and suppliers, and gathers every contact channel of a list member:
 * e-mails, phones and messengers from all those places.
 */
class MailingAudience
{
    private const LEGAL_FORMS = ['ип', 'ооо', 'одо', 'оао', 'зао', 'чуп', 'чтуп', 'уп', 'чп', 'чпуп', 'пк', 'ао', 'llc'];

    private const MAX_MATCHES_PER_LINE = 5;

    /** @var array<int, array{kind:string,id:int,counterparty:?int,names:array<int,array<int,string>>}>|null */
    private ?array $index = null;

    /**
     * Adds the partners named in $text (one per line) to the list.
     *
     * @return array{lines:int,matched:int,added:int,missing:array<int,string>}
     */
    public function addByNames(CrmMailingList $list, string $text): array
    {
        $lines = collect(preg_split('/\R/u', $text) ?: [])->map(fn (string $l): string => trim($l))->filter()->unique()->values();
        $stats = ['lines' => $lines->count(), 'matched' => 0, 'added' => 0, 'missing' => []];

        foreach ($lines as $line) {
            $found = $this->find($line);
            if ($found === []) {
                $stats['missing'][] = $line;

                continue;
            }
            $stats['matched']++;
            foreach ($found as $entity) {
                $stats['added'] += (int) $this->addMember($list, $entity, $line);
            }
        }

        return $stats;
    }

    /** @return bool true when a new member row was created */
    public function addMember(CrmMailingList $list, array $entity, ?string $query = null): bool
    {
        $attributes = match ($entity['kind']) {
            'counterparty' => ['counterparty_id' => $entity['id']],
            'b2b' => $entity['counterparty'] ? ['counterparty_id' => $entity['counterparty']] : ['b2b_client_id' => $entity['id']],
            'supplier' => $entity['counterparty'] ? ['counterparty_id' => $entity['counterparty']] : ['supplier_id' => $entity['id']],
        };
        if ($list->members()->where($attributes)->exists()) {
            return false;
        }
        $member = $list->members()->create([...$attributes, 'query' => $query]);
        $member->forceFill(['name' => $this->displayName($member)])->save();

        return true;
    }

    /**
     * Entities whose name contains every variant of the line («Позитивстронг/Лукомль» — either part).
     *
     * @return array<int, array{kind:string,id:int,counterparty:?int}>
     */
    public function find(string $line): array
    {
        $variants = collect(explode('/', $line))->map(fn (string $v): array => $this->words($v))
            ->filter(fn (array $w): bool => mb_strlen(implode('', $w)) >= 4)->unique(fn (array $w): string => implode('', $w));
        if ($variants->isEmpty()) {
            return [];
        }

        // Whole words only, written together or apart («грандтеплокомпани» = «Гранд Тепло Компани»), so «Иванов» does not
        // find «…Иванович» and «Кравцов» does not find «Кравцова». Exact name first, then the name containing the query,
        // then the query containing the whole name («Теплая баня-Павлюк» → «Теплая баня»).
        $hits = [];
        foreach ($this->index() as $row) {
            $score = 0;
            foreach ($variants as $variantWords) {
                $variant = implode('', $variantWords);
                $variantRuns = null;
                foreach ($row['names'] as $words) {
                    $full = implode('', $words);
                    if ($full === $variant) {
                        $score = 3;
                    } elseif (isset($this->runs($words)[$variant])) {
                        $score = max($score, 2);
                    } elseif (mb_strlen($full) >= 6 && isset(($variantRuns ??= $this->runs($variantWords))[$full])) {
                        $score = max($score, 1);
                    }
                }
            }
            if ($score > 0) {
                $hits[] = [...$row, 'score' => $score];
            }
        }
        if ($hits === []) {
            return [];
        }
        usort($hits, fn (array $a, array $b): int => [$b['score'], $a['kind'] === 'counterparty' ? 0 : 1] <=> [$a['score'], $b['kind'] === 'counterparty' ? 0 : 1]);
        $best = $hits[0]['score'];

        return collect($hits)->where('score', $best)
            // One company is one member: a B2B client or supplier of an already found counterparty is the same partner.
            ->unique(fn (array $hit): string => $hit['counterparty'] ? 'c'.$hit['counterparty'] : $hit['kind'].$hit['id'])
            ->take(self::MAX_MATCHES_PER_LINE)
            ->map(fn (array $hit): array => ['kind' => $hit['kind'], 'id' => $hit['id'], 'counterparty' => $hit['counterparty']])
            ->values()->all();
    }

    /** @return array{emails:array<int,string>,phones:array<int,string>,messengers:array<int,string>} */
    public function channels(CrmMailingListMember $member): array
    {
        $emails = $phones = $messengers = [];
        $counterparty = $member->counterparty;
        $clients = collect([$member->b2bClient, $counterparty?->b2bClient])->filter()->unique('id');
        $suppliers = collect([$member->supplier])->merge($counterparty ? Supplier::query()->where('crm_counterparty_id', $counterparty->id)->get() : [])->filter()->unique('id');

        if ($counterparty) {
            $contactIds = $counterparty->contacts()->pluck('id');
            $points = CrmContactPoint::query()->where('is_active', true)
                ->where(fn ($q) => $q->where('counterparty_id', $counterparty->id)->orWhereIn('contact_id', $contactIds))
                ->orderByDesc('is_primary')->get();
            foreach ($points as $point) {
                match ($point->type) {
                    'email' => $emails[] = $point->value,
                    'phone' => $phones[] = $point->value,
                    default => $messengers[] = $point->type.': '.$point->value,
                };
            }
        }
        foreach ($clients as $client) {
            $emails[] = (string) $client->contact_email;
            $emails[] = (string) $client->user?->email;
            $phones[] = (string) $client->contact_phone;
            foreach (PartnerBranch::query()->where('b2b_client_id', $client->id)->get() as $branch) {
                $emails[] = (string) $branch->email;
                array_push($phones, ...array_map('strval', array_filter((array) $branch->phones, 'is_scalar')));
                $branch->telegram_username && $messengers[] = 'Telegram: '.$branch->telegram_username;
                $branch->viber_phone && $messengers[] = 'Viber: '.$branch->viber_phone;
                $branch->whatsapp_phone && $messengers[] = 'WhatsApp: '.$branch->whatsapp_phone;
                $branch->instagram_username && $messengers[] = 'Instagram: '.$branch->instagram_username;
            }
        }
        foreach ($suppliers as $supplier) {
            array_push($emails, ...$supplier->contactEmailAddresses());
            array_push($phones, ...$supplier->contactPhoneNumbers());
            foreach ((array) $supplier->communication_channels as $channel) {
                if (is_array($channel) && in_array(mb_strtolower((string) ($channel['type'] ?? '')), ['telegram', 'viber', 'whatsapp', 'instagram'], true)) {
                    $messengers[] = ucfirst((string) $channel['type']).': '.trim((string) ($channel['value'] ?? ''));
                }
            }
        }

        return [
            'emails' => collect($emails)->map(fn (string $e): string => mb_strtolower(trim($e)))->filter(fn (string $e): bool => filter_var($e, FILTER_VALIDATE_EMAIL) !== false)->unique()->values()->all(),
            'phones' => collect($phones)->flatMap(fn (string $p): array => PhoneNumber::extract($p)['phones'])->filter(fn (string $p): bool => strlen(preg_replace('/\D/', '', $p) ?? '') >= 7)
                ->unique(fn (string $p): string => substr(preg_replace('/\D/', '', $p) ?? '', -9))->values()->all(),
            'messengers' => collect($messengers)->map(fn (string $m): string => trim($m))->filter(fn (string $m): bool => ! str_ends_with($m, ':'))->unique()->values()->all(),
        ];
    }

    public function displayName(CrmMailingListMember $member): string
    {
        return (string) ($member->counterparty?->name ?? $member->b2bClient?->company_name ?? $member->supplier?->name ?? $member->query ?? '—');
    }

    /** Lower-case letters and digits only, legal forms and quotes dropped: «ООО "Гранд Тепло Компани"» → грандтеплокомпани. */
    public function key(string $name): string
    {
        return implode('', $this->words($name));
    }

    /** @return array<int, string> «ООО "Гранд Тепло-Компани"» → [гранд, тепло, компани] */
    public function words(string $name): array
    {
        $name = str_replace('ё', 'е', mb_strtolower($name));
        $words = preg_split('/[^\p{L}\p{N}]+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter($words, fn (string $w): bool => ! in_array($w, self::LEGAL_FORMS, true)));
    }

    /**
     * Every run of consecutive words written together: [гранд, тепло, компани] → гранд, грандтепло, грандтеплокомпани, тепло…
     *
     * @param  array<int, string>  $words
     * @return array<string, true>
     */
    private function runs(array $words): array
    {
        $runs = [];
        $count = min(count($words), 12);
        for ($i = 0; $i < $count; $i++) {
            $run = '';
            for ($j = $i; $j < $count; $j++) {
                $run .= $words[$j];
                $runs[$run] = true;
            }
        }

        return $runs;
    }

    /** @return array<int, array{kind:string,id:int,counterparty:?int,names:array<int,array<int,string>>}> */
    private function index(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }
        $rows = [];
        $add = function (string $kind, int $id, ?int $counterparty, array $names) use (&$rows): void {
            $list = collect($names)->map(fn ($n): array => $this->words((string) $n))
                ->filter(fn (array $w): bool => mb_strlen(implode('', $w)) >= 3)->unique(fn (array $w): string => implode(' ', $w))->values()->all();
            if ($list !== []) {
                $rows[] = ['kind' => $kind, 'id' => $id, 'counterparty' => $counterparty, 'names' => $list];
            }
        };
        CrmCounterparty::query()->select(['id', 'name', 'public_name', 'legal_name'])->chunkById(2000, function (Collection $chunk) use ($add): void {
            foreach ($chunk as $c) {
                $add('counterparty', (int) $c->id, (int) $c->id, [$c->name, $c->public_name, $c->legal_name]);
            }
        });
        $branches = PartnerBranch::query()->get(['b2b_client_id', 'name'])->groupBy('b2b_client_id');
        foreach (B2bClient::query()->get(['id', 'counterparty_id', 'company_name']) as $client) {
            $add('b2b', (int) $client->id, $client->counterparty_id ? (int) $client->counterparty_id : null,
                [$client->company_name, ...($branches->get($client->id)?->pluck('name')->all() ?? [])]);
        }
        foreach (Supplier::query()->get(['id', 'crm_counterparty_id', 'name']) as $supplier) {
            $add('supplier', (int) $supplier->id, $supplier->crm_counterparty_id ? (int) $supplier->crm_counterparty_id : null, [$supplier->name]);
        }

        return $this->index = $rows;
    }
}
