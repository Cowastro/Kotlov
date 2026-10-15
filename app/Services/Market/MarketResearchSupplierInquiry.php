<?php

namespace App\Services\Market;

use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Models\MarketResearchReport;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CrmMail\SupplierRequestMailDraftService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MarketResearchSupplierInquiry
{
    public function __construct(
        private readonly SupplierRequestMailDraftService $drafts,
    ) {}

    /** @return array<int, string> */
    public function supplierOptions(): array
    {
        return Supplier::query()
            ->where('is_active', true)
            ->with('crmCounterparty.contactPoints')
            ->orderBy('name')
            ->get()
            ->filter(fn (Supplier $supplier): bool => $supplier->contactEmailAddresses() !== [])
            ->mapWithKeys(fn (Supplier $supplier): array => [
                $supplier->id => $supplier->name.' — '.$supplier->contactEmailAddresses()[0],
            ])
            ->all();
    }

    public function recommendedSupplier(MarketResearchReport $report): ?Supplier
    {
        $report->loadMissing([
            'product.supplierProducts.supplier.crmCounterparty.contactPoints',
            'product.brand.suppliers.crmCounterparty.contactPoints',
        ]);

        $linked = $report->product?->supplierProducts
            ?->sortByDesc(fn ($offer): array => [
                (int) ($offer->stock_quantity ?? 0),
                (float) ($offer->price_byn ?? 0),
            ])
            ->pluck('supplier')
            ->filter();

        return $linked?->first(fn (Supplier $supplier): bool => $supplier->contactEmailAddresses() !== [])
            ?? $report->product?->brand?->suppliers
                ?->first(fn (Supplier $supplier): bool => $supplier->contactEmailAddresses() !== []);
    }

    /** @return array<string, mixed> */
    public function formData(MarketResearchReport $report, ?Supplier $supplier = null): array
    {
        $supplier ??= $this->recommendedSupplier($report);
        $mailbox = CrmMailbox::query()->where('is_enabled', true)->first()
            ?? CrmMailbox::query()->first();

        return [
            'supplier_id' => $supplier?->id,
            'participant_name' => $supplier?->name,
            'mailbox_id' => $mailbox?->id,
            'recipient' => $supplier?->contactEmailAddresses()[0] ?? '',
            'subject' => $this->subject($report),
            'body' => $this->body($report),
            'send_now' => true,
        ];
    }

    public function subject(MarketResearchReport $report): string
    {
        $product = $report->product;

        return 'Запрос цены и наличия — '.($product?->name ?? 'товар');
    }

    public function body(MarketResearchReport $report): string
    {
        $report->loadMissing('product');
        $product = $report->product;

        return implode("\n", [
            'Здравствуйте!',
            '',
            'Просим сообщить актуальную партнёрскую B2B-цену и наличие на складе:',
            '',
            $product?->name ?? 'Товар',
            '',
            'Спасибо.',
        ]);
    }

    public function createDraft(
        MarketResearchReport $report,
        ?Supplier $supplier,
        CrmMailbox $mailbox,
        User $user,
        string $recipient,
        ?string $participantName,
        string $subject,
        string $body,
    ): CrmMailThread {
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Укажите корректный email поставщика.');
        }

        $participantName = filled($participantName)
            ? trim((string) $participantName)
            : $supplier?->name;

        return DB::transaction(function () use ($report, $supplier, $mailbox, $user, $recipient, $participantName, $subject, $body): CrmMailThread {
            $thread = $this->drafts->create(
                $mailbox,
                $user,
                $recipient,
                $subject,
                $body,
                $participantName,
            );

            $thread->forceFill([
                'crm_counterparty_id' => $supplier?->crm_counterparty_id ?: $thread->crm_counterparty_id,
                'manager_note' => 'Запрос партнёрской B2B-цены из анализа рынка №'.$report->id.'.',
            ])->save();

            $draft = $thread->messages()->where('direction', 'draft')->latest('id')->first();
            if ($draft) {
                $metadata = $draft->metadata ?? [];
                $draft->forceFill(['metadata' => [
                    ...$metadata,
                    'source' => 'market_research_b2b_inquiry',
                    'market_research_report_id' => $report->id,
                    'supplier_id' => $supplier?->id,
                    'product_id' => $report->product_id,
                ]])->save();
            }

            return $thread->fresh(['mailbox', 'messages']);
        });
    }
}
