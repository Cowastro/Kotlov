<?php

namespace App\Console\Commands;

use App\Models\CrmContactPoint;
use App\Models\CrmMailbox;
use App\Models\OrderItem;
use App\Models\Supplier;
use App\Services\Telephony\PhoneNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Wholesale contacts of ООО «СанБизнесГруп» for the retail department and
 * order pages: phone +375 29 351 29 82 and b2b@kotlov.by replace the old
 * city number in the supplier card, its CRM counterparty and the contact
 * snapshots of open orders. Also registers the b2b@kotlov.by CRM mailbox
 * (disabled until the password is entered in «Настройка почты»).
 *
 *   php artisan supplier:set-sanbusinessgroup-contacts            # dry run
 *   php artisan supplier:set-sanbusinessgroup-contacts --apply
 */
class SetSanBusinessGroupContactsCommand extends Command
{
    protected $signature = 'supplier:set-sanbusinessgroup-contacts {--apply : Save the new contacts}';

    protected $description = 'Set the wholesale phone and b2b@kotlov.by as the СанБизнесГруп supplier contacts';

    public const SUPPLIER_CODE = 'sanbusinessgroup';

    public const PHONE = '+375 29 351 29 82';

    public const EMAIL = 'b2b@kotlov.by';

    private const LABEL = 'Оптовый отдел';

    private const CLOSED_ORDER_STATUSES = ['delivered', 'cancelled'];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $supplier = Supplier::query()->where('code', self::SUPPLIER_CODE)->first();
        if (! $supplier) {
            $this->error('Поставщик sanbusinessgroup не найден.');

            return self::FAILURE;
        }

        $phone = PhoneNumber::normalize(self::PHONE);
        $before = $supplier->contactPhoneNumbers();
        foreach ($before as $number) {
            $this->line('audit.sbg_contacts.phone_before=***'.substr((string) PhoneNumber::normalize($number), -4));
        }
        $points = $supplier->crmCounterparty
            ? CrmContactPoint::query()->where('counterparty_id', $supplier->crm_counterparty_id)->where('type', 'phone')->where('is_active', true)->get()
            : collect();
        $stalePoints = $points->reject(fn (CrmContactPoint $p): bool => $p->normalized_value === $phone);
        $openItems = OrderItem::query()
            ->where(fn ($q) => $q->where('fulfillment_supplier_id', $supplier->id)->orWhere('supply_supplier_id', $supplier->id))
            ->whereHas('order', fn ($q) => $q->whereNotIn('status', self::CLOSED_ORDER_STATUSES));
        $metrics = [
            'phones_before' => count($before),
            'emails_before' => count($supplier->contactEmailAddresses()),
            'has_counterparty' => (int) (bool) $supplier->crmCounterparty,
            'phone_points_to_disable' => $stalePoints->count(),
            'open_order_items' => (clone $openItems)->count(),
            'mailbox_exists' => (int) CrmMailbox::query()->where('email', self::EMAIL)->exists(),
        ];

        if ($apply) {
            DB::transaction(function () use ($supplier, $stalePoints, $openItems, &$metrics): void {
                $channels = collect($supplier->communication_channels ?? [])
                    ->filter(fn ($channel): bool => is_array($channel)
                        && ! in_array(mb_strtolower((string) ($channel['type'] ?? '')), ['phone', 'whatsapp', 'viber', 'telegram', 'email', 'mail'], true))
                    ->values()
                    ->all();
                $supplier->forceFill([
                    'contact' => self::LABEL.': '.self::PHONE.', '.self::EMAIL,
                    'communication_channels' => [
                        ['type' => 'phone', 'value' => self::PHONE, 'label' => self::LABEL],
                        ['type' => 'email', 'value' => self::EMAIL, 'label' => self::LABEL],
                        ...$channels,
                    ],
                ])->save();

                $counterparty = $supplier->crmCounterparty;
                if ($counterparty) {
                    CrmContactPoint::query()->whereIn('id', $stalePoints->pluck('id'))->update(['is_active' => false, 'is_primary' => false, 'updated_at' => now()]);
                    CrmContactPoint::query()->where('counterparty_id', $counterparty->id)->where('type', 'email')->update(['is_primary' => false, 'updated_at' => now()]);
                    foreach ([['phone', self::PHONE], ['email', self::EMAIL]] as [$type, $value]) {
                        $point = CrmContactPoint::query()->firstOrNew([
                            'counterparty_id' => $counterparty->id,
                            'type' => $type,
                            'normalized_value' => $type === 'phone' ? PhoneNumber::normalize($value) : mb_strtolower($value),
                        ]);
                        $point->fill(['value' => $value, 'label' => self::LABEL, 'is_primary' => true, 'is_active' => true])->save();
                    }
                }

                $summary = $supplier->fresh()->contactSummary();
                $metrics['order_items_updated'] = (clone $openItems)->update(['fulfillment_supplier_contact' => $summary, 'updated_at' => now()]);

                $mailbox = CrmMailbox::query()->firstOrCreate(['email' => self::EMAIL], [
                    'name' => 'KOTLOV — оптовый отдел',
                    'username' => self::EMAIL,
                    'is_enabled' => false,
                    'create_leads' => true,
                ]);
                $metrics['mailbox_created'] = (int) $mailbox->wasRecentlyCreated;
            });
            $after = Supplier::query()->find($supplier->id);
            $metrics['phones_after'] = count($after->contactPhoneNumbers());
            $metrics['new_phone_first'] = (int) (PhoneNumber::normalize($after->contactPhoneNumbers()[0] ?? '') === $phone);
            $metrics['new_email_first'] = (int) (($after->contactEmailAddresses()[0] ?? null) === self::EMAIL);
        }

        foreach ($metrics as $key => $value) {
            $this->line("metric.sbg_contacts.{$key}={$value}");
        }
        if (! $apply) {
            $this->warn('Предпросмотр: база не изменена. Для записи добавьте --apply.');
        }

        return self::SUCCESS;
    }
}
