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
        private readonly MarketResearchReportPresenter $presenter,
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
            'mailbox_id' => $mailbox?->id,
            'recipient' => $supplier?->contactEmailAddresses()[0] ?? '',
            'subject' => $this->subject($report),
            'body' => $this->body($report, $supplier),
            'send_now' => true,
        ];
    }

    public function subject(MarketResearchReport $report): string
    {
        $product = $report->product;
        $sku = filled($product?->sku) ? ' ['.$product->sku.']' : '';

        return 'Запрос партнёрской B2B-цены — '.($product?->name ?? 'товар').$sku;
    }

    public function body(MarketResearchReport $report, ?Supplier $supplier = null): string
    {
        $report->loadMissing('product');
        $presentation = $this->presenter->present($report);
        $research = $presentation['research'];
        $product = $report->product;
        $retailPrice = $product?->price ?: $report->price_after ?: $report->price_before;
        $marketPrice = $research['market']['median'] ?? null;
        $lines = [
            'Здравствуйте'.($supplier ? ', команда '.$supplier->name : '').'!',
            '',
            'Просим предоставить актуальные партнёрские B2B-условия для KOTLOV.BY по позиции:',
            '',
            'Товар: '.($product?->name ?? ($research['product_name'] ?? 'товар')),
            'Артикул: '.($product?->sku ?: ($research['sku'] ?? 'не указан')),
        ];

        if ($retailPrice !== null && (float) $retailPrice > 0) {
            $lines[] = 'Текущая цена в нашем каталоге: '.$this->money((float) $retailPrice);
        }
        if ($marketPrice !== null && (float) $marketPrice > 0) {
            $lines[] = 'Ориентир розничного рынка по актуальному анализу: '.$this->money((float) $marketPrice);
        }

        return implode("\n", [
            ...$lines,
            '',
            'Просим указать:',
            '1. Нашу партнёрскую B2B-цену (отдельно отметьте, включает ли она НДС).',
            '2. Актуальное наличие и доступное количество на складе.',
            '3. Ближайший срок отгрузки.',
            '4. Срок действия предложенной цены.',
            '',
            'Важно: нам нужна именно партнёрская закупочная цена для дилера, а не публичная розничная цена.',
            '',
            'Заранее благодарим за оперативный ответ.',
        ]);
    }

    public function createDraft(
        MarketResearchReport $report,
        Supplier $supplier,
        CrmMailbox $mailbox,
        User $user,
        string $recipient,
        string $subject,
        string $body,
    ): CrmMailThread {
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Укажите корректный email поставщика.');
        }

        return DB::transaction(function () use ($report, $supplier, $mailbox, $user, $recipient, $subject, $body): CrmMailThread {
            $thread = $this->drafts->create(
                $mailbox,
                $user,
                $recipient,
                $subject,
                $body,
                $supplier->name,
            );

            $thread->forceFill([
                'crm_counterparty_id' => $supplier->crm_counterparty_id ?: $thread->crm_counterparty_id,
                'manager_note' => 'Запрос партнёрской B2B-цены из анализа рынка №'.$report->id.'.',
            ])->save();

            $draft = $thread->messages()->where('direction', 'draft')->latest('id')->first();
            if ($draft) {
                $metadata = $draft->metadata ?? [];
                $draft->forceFill(['metadata' => [
                    ...$metadata,
                    'source' => 'market_research_b2b_inquiry',
                    'market_research_report_id' => $report->id,
                    'supplier_id' => $supplier->id,
                    'product_id' => $report->product_id,
                ]])->save();
            }

            return $thread->fresh(['mailbox', 'messages']);
        });
    }

    private function money(float $value): string
    {
        return number_format($value, 2, ',', ' ').' BYN';
    }
}
