<?php

namespace App\Console\Commands;

use App\Models\B2bClient;
use App\Models\CrmContactPoint;
use App\Models\PartnerBranch;
use App\Models\Supplier;
use App\Services\Telephony\PhoneNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Brings every stored phone to «+375 29 123-45-67» for mailings: CRM contact points, B2B client phones, partner
 * branch phones/Viber/WhatsApp and supplier communication channels. A field holding several numbers
 * («801562 50655, 029 6645244») becomes one contact point per number; words next to the number («тел/факс»,
 * «доб. 12») go to the label. Non-Belarusian and unreadable numbers stay as written. Prints counts only.
 *
 *   php artisan crm:normalize-phones            # dry run
 *   php artisan crm:normalize-phones --apply
 */
class NormalizePhonesCommand extends Command
{
    protected $signature = 'crm:normalize-phones {--apply : Write the formatted numbers}';

    protected $description = 'Format all stored phones as +375 XX XXX-XX-XX';

    private array $stats = ['points' => 0, 'points_formatted' => 0, 'points_split' => 0, 'points_added' => 0, 'points_duplicate_off' => 0,
        'points_unreadable' => 0, 'b2b_formatted' => 0, 'branch_formatted' => 0, 'supplier_formatted' => 0, 'already' => 0];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $run = function () use ($apply): void {
            $this->contactPoints($apply);
            $this->b2bClients($apply);
            $this->branches($apply);
            $this->suppliers($apply);
        };
        $apply ? DB::transaction($run) : $run();

        foreach ($this->stats as $key => $value) {
            $this->line("metric.normalize_phones.{$key}={$value}");
        }
        if (! $apply) {
            $this->warn('Предпросмотр: база не изменена. Для записи добавьте --apply.');
        }

        return self::SUCCESS;
    }

    private function contactPoints(bool $apply): void
    {
        CrmContactPoint::query()->where('type', 'phone')->orderBy('id')->chunkById(500, function ($points) use ($apply): void {
            foreach ($points as $point) {
                $this->stats['points']++;
                $parsed = PhoneNumber::extract($point->value);
                $phones = $parsed['phones'];
                if ($phones === [] || PhoneNumber::format($phones[0]) === null) {
                    $this->stats['points_unreadable']++;

                    continue;
                }
                if ($phones === [$point->value] && $parsed['note'] === '') {
                    $this->stats['already']++;

                    continue;
                }
                $this->stats['points_formatted']++;
                $this->stats['points_split'] += (int) (count($phones) > 1);
                $label = $point->label ?: ($parsed['note'] !== '' ? mb_substr($parsed['note'], 0, 100) : null);

                $owner = fn ($q) => $q->where('type', 'phone')->where('id', '!=', $point->id)->where('is_active', true)
                    ->where(fn ($w) => $point->contact_id ? $w->where('contact_id', $point->contact_id) : $w->whereNull('contact_id')->where('counterparty_id', $point->counterparty_id));
                $first = array_shift($phones);
                $duplicate = CrmContactPoint::query()->where($owner)->where('normalized_value', PhoneNumber::normalize($first))->exists();
                $this->stats['points_duplicate_off'] += (int) ($duplicate && $point->is_active);
                foreach ($phones as $extra) {
                    if (! CrmContactPoint::query()->where($owner)->where('normalized_value', PhoneNumber::normalize($extra))->exists()) {
                        $this->stats['points_added']++;
                        $apply && CrmContactPoint::query()->create([
                            'contact_id' => $point->contact_id, 'counterparty_id' => $point->counterparty_id, 'type' => 'phone',
                            'value' => $extra, 'label' => $label, 'is_primary' => false, 'is_active' => $point->is_active,
                        ]);
                    }
                }
                if ($apply) {
                    // The same number written twice on one card stays once.
                    $point->forceFill(['value' => $first, 'label' => $label, 'is_active' => $point->is_active && ! $duplicate])->save();
                }
            }
        });
    }

    private function b2bClients(bool $apply): void
    {
        foreach (B2bClient::query()->whereNotNull('contact_phone')->where('contact_phone', '!=', '')->get(['id', 'contact_phone']) as $client) {
            $formatted = PhoneNumber::format($client->contact_phone);
            if ($formatted !== null && $formatted !== $client->contact_phone) {
                $this->stats['b2b_formatted']++;
                $apply && DB::table('b2b_clients')->where('id', $client->id)->update(['contact_phone' => $formatted]);
            }
        }
    }

    private function branches(bool $apply): void
    {
        foreach (PartnerBranch::query()->get(['id', 'phones', 'viber_phone', 'whatsapp_phone']) as $branch) {
            $phones = collect((array) $branch->phones)->flatMap(fn ($p): array => is_scalar($p) ? PhoneNumber::extract((string) $p)['phones'] : [])->unique()->values()->all();
            $changes = array_filter([
                'phones' => $phones !== array_values((array) $branch->phones) ? json_encode($phones, JSON_UNESCAPED_UNICODE) : null,
                'viber_phone' => $this->changed($branch->viber_phone),
                'whatsapp_phone' => $this->changed($branch->whatsapp_phone),
            ]);
            if ($changes !== []) {
                $this->stats['branch_formatted']++;
                $apply && DB::table('partner_branches')->where('id', $branch->id)->update($changes);
            }
        }
    }

    private function suppliers(bool $apply): void
    {
        foreach (Supplier::query()->whereNotNull('communication_channels')->get(['id', 'communication_channels']) as $supplier) {
            $channels = (array) $supplier->communication_channels;
            $changed = false;
            foreach ($channels as $i => $channel) {
                if (is_array($channel) && in_array($channel['type'] ?? null, ['phone', 'viber', 'whatsapp'], true) && ($new = $this->changed($channel['value'] ?? null)) !== null) {
                    $channels[$i]['value'] = $new;
                    $changed = true;
                }
            }
            if ($changed) {
                $this->stats['supplier_formatted']++;
                $apply && DB::table('suppliers')->where('id', $supplier->id)->update(['communication_channels' => json_encode($channels, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }

    private function changed(?string $phone): ?string
    {
        $formatted = PhoneNumber::format($phone);

        return $formatted !== null && $formatted !== $phone ? $formatted : null;
    }
}
