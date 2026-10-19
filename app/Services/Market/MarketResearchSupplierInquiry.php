<?php

namespace App\Services\Market;

use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Models\MarketResearchReport;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CrmMail\SupplierRequestMailDraftService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        $mailbox = CrmMailbox::query()->manualSending()->where('is_enabled', true)->first()
            ?? CrmMailbox::query()->manualSending()->first();

        return [
            'supplier_id' => $supplier?->id,
            'participant_name' => $supplier?->name,
            'mailbox_id' => $mailbox?->id,
            'recipient' => $supplier?->contactEmailAddresses()[0] ?? '',
            'cc' => [],
            'attach_russian_representative' => false,
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

        return $this->bodyForProduct($report->product);
    }

    public function subjectForProduct(?Product $product): string
    {
        return $this->subjectForName($product?->name);
    }

    public function bodyForProduct(?Product $product): string
    {
        return $this->bodyForName($product?->name);
    }

    public function subjectForName(?string $productName): string
    {
        return 'Запрос цены и наличия — '.(filled($productName) ? trim($productName) : 'товар');
    }

    public function bodyForName(?string $productName): string
    {
        return implode("\n", [
            'Здравствуйте!',
            '',
            'Просим сообщить актуальную партнёрскую B2B-цену и наличие на складе:',
            '',
            filled($productName) ? trim($productName) : 'Товар',
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
        array $cc,
        ?string $participantName,
        string $subject,
        string $body,
        bool $attachRussianRepresentative = false,
    ): CrmMailThread {
        return $this->createProductDraft(
            $report->product()->first(),
            $supplier,
            $mailbox,
            $user,
            $recipient,
            $cc,
            $participantName,
            $subject,
            $body,
            $attachRussianRepresentative,
            $report,
        );
    }

    public function createStandaloneDraft(
        ?Product $product,
        ?Supplier $supplier,
        CrmMailbox $mailbox,
        User $user,
        string $recipient,
        array $cc,
        ?string $participantName,
        string $subject,
        string $body,
        bool $attachRussianRepresentative = false,
        ?string $customProductName = null,
    ): CrmMailThread {
        return $this->createProductDraft(
            $product,
            $supplier,
            $mailbox,
            $user,
            $recipient,
            $cc,
            $participantName,
            $subject,
            $body,
            $attachRussianRepresentative,
            null,
            $customProductName,
        );
    }

    private function createProductDraft(
        ?Product $product,
        ?Supplier $supplier,
        CrmMailbox $mailbox,
        User $user,
        string $recipient,
        array $cc,
        ?string $participantName,
        string $subject,
        string $body,
        bool $attachRussianRepresentative = false,
        ?MarketResearchReport $report = null,
        ?string $customProductName = null,
    ): CrmMailThread {
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Укажите корректный email поставщика.');
        }

        $participantName = filled($participantName)
            ? trim((string) $participantName)
            : $supplier?->name;

        $productName = $product?->name ?: (filled($customProductName) ? trim($customProductName) : 'Товар');

        return DB::transaction(function () use ($product, $productName, $report, $supplier, $mailbox, $user, $recipient, $cc, $participantName, $subject, $body, $attachRussianRepresentative): CrmMailThread {
            [$attachments, $attachmentNames] = $this->russianRepresentativeAttachment($attachRussianRepresentative);
            if ($attachRussianRepresentative) {
                $body = rtrim($body)."\n\nДля оформления поставки и выставления счёта в РФ используйте реквизиты нашего российского представительства ООО «МИНСКАЯ ПРОМЫШЛЕННАЯ ГРУППА» (ИНН 6732146305). Реквизиты приложены к письму.";
            }

            $thread = $this->drafts->create(
                $mailbox,
                $user,
                $recipient,
                $subject,
                $body,
                $participantName,
                $cc,
                $attachments,
                $attachmentNames,
            );

            $thread->forceFill([
                'crm_counterparty_id' => $supplier?->crm_counterparty_id ?: $thread->crm_counterparty_id,
                'manager_note' => $report
                    ? 'Запрос партнёрской B2B-цены из анализа рынка №'.$report->id.'.'
                    : 'B2B-заявка поставщику по товару «'.$productName.'».',
            ])->save();

            $draft = $thread->messages()->where('direction', 'draft')->latest('id')->first();
            if ($draft) {
                $metadata = $draft->metadata ?? [];
                $draft->forceFill(['metadata' => [
                    ...$metadata,
                    'source' => $report ? 'market_research_b2b_inquiry' : 'supplier_b2b_inquiry',
                    'market_research_report_id' => $report?->id,
                    'supplier_id' => $supplier?->id,
                    'product_id' => $product?->id,
                    'product_name' => $productName,
                ]])->save();
            }

            return $thread->fresh(['mailbox', 'messages']);
        });
    }

    /** @return array{0: list<string>, 1: array<string, string>} */
    private function russianRepresentativeAttachment(bool $attach): array
    {
        if (! $attach) {
            return [[], []];
        }

        $source = resource_path('documents/mpg-requisites.pdf');
        if (! is_file($source)) {
            throw new RuntimeException('Файл реквизитов ООО «МПГ» не найден.');
        }

        $path = 'crm-mail-attachments/system/mpg-requisites.pdf';
        Storage::disk('local')->put($path, file_get_contents($source));

        return [[$path], [$path => 'Реквизиты ООО МПГ.pdf']];
    }
}
