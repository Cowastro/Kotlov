<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CommercialDocument extends Model
{
    public const TYPES = [
        'quote' => 'Коммерческое предложение',
        'invoice' => 'Счёт на оплату',
    ];

    public const STATUSES = [
        'draft' => 'Черновик',
        'sent' => 'Отправлен',
        'needs_revision' => 'Нужна корректировка',
        'accepted' => 'Согласован',
        'paid' => 'Оплачен',
        'rejected' => 'Отказ клиента',
        'expired' => 'Срок истёк',
        'cancelled' => 'Отменён',
    ];

    public const OUTCOME_REASONS = [
        'price' => 'Не устроила цена',
        'delivery_time' => 'Не устроил срок поставки',
        'competitor' => 'Выбран другой поставщик',
        'budget' => 'Нет бюджета / закупка отложена',
        'requirements_changed' => 'Изменилась потребность',
        'no_response' => 'Клиент не отвечает',
        'duplicate' => 'Дублирующее предложение',
        'other' => 'Другая причина',
    ];

    protected $fillable = [
        'number', 'type', 'status', 'outcome_reason', 'outcome_note', 'ai_assisted',
        'source_document_id', 'revision_of_id', 'revision_number', 'created_by', 'manager_id', 'crm_mail_thread_id',
        'crm_counterparty_id', 'crm_contact_id',
        'client_name', 'client_company', 'client_legal_name', 'client_email', 'client_phone', 'client_unp', 'client_address',
        'issued_at', 'valid_until', 'currency', 'subtotal', 'discount_total', 'delivery_price', 'delivery_to_client', 'total',
        'vat_rate', 'payment_terms', 'delivery_terms', 'notes', 'seller_snapshot', 'sent_at', 'accepted_at', 'closed_at',
        'last_control_notified_at', 'allow_unconfirmed_purchase_price',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'valid_until' => 'date',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'closed_at' => 'datetime',
        'last_control_notified_at' => 'datetime',
        'ai_assisted' => 'boolean',
        'delivery_to_client' => 'boolean',
        'allow_unconfirmed_purchase_price' => 'boolean',
        'seller_snapshot' => 'array',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'delivery_price' => 'decimal:2',
        'total' => 'decimal:2',
        'vat_rate' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (CommercialDocument $document): void {
            $document->issued_at ??= today();
            $document->valid_until ??= today()->addDays($document->type === 'invoice' ? 5 : 10);
            $document->seller_snapshot ??= config('legal.commercial_documents');
            $document->created_by ??= auth()->id();
            $document->manager_id ??= auth()->id();
            $document->number ??= self::nextNumber($document->type);
        });
    }

    public static function nextNumber(string $type): string
    {
        $prefix = $type === 'invoice' ? 'СЧ' : 'КП';
        $year = now()->format('Y');
        $lastId = (int) self::query()->max('id') + 1;

        return sprintf('%s-%s-%05d', $prefix, $year, $lastId);
    }

    public function reference(bool $includeFirstRevision = false): string
    {
        $revision = (int) ($this->revision_number ?: 1);

        if ($this->type !== 'quote' || ($revision === 1 && ! $includeFirstRevision)) {
            return (string) $this->number;
        }

        return $this->number.', редакция №'.$revision;
    }

    public function recalculate(): void
    {
        $subtotal = round((float) $this->items()->sum('line_total'), 2);
        $total = max(0, round($subtotal - (float) $this->discount_total + (float) $this->delivery_price, 2));
        $this->forceFill(['subtotal' => $subtotal, 'total' => $total])->saveQuietly();
    }

    public function needsManagerControl(): bool
    {
        if ($this->newerRevision()) {
            return false;
        }

        return ($this->type === 'quote' && $this->status === 'needs_revision')
            || ($this->type === 'quote' && $this->status === 'sent' && $this->valid_until?->lte(today()->addDays(3)))
            || ($this->type === 'quote' && $this->status === 'accepted' && ! $this->order()->exists());
    }

    public function controlLabel(): string
    {
        if ($newer = $this->newerRevision()) {
            return 'Открыть редакцию №'.$newer->revision_number;
        }

        if ($this->type === 'quote' && $this->status === 'needs_revision') {
            return 'Создать новую редакцию';
        }

        if ($this->type === 'quote' && $this->status === 'accepted' && ! $this->order()->exists()) {
            return 'Создать заказ';
        }

        if ($this->type !== 'quote' || $this->status !== 'sent' || ! $this->valid_until) {
            return '—';
        }

        if ($this->valid_until->isBefore(today())) {
            return 'Просрочено';
        }

        if ($this->valid_until->isToday()) {
            return 'Истекает сегодня';
        }

        if ($this->valid_until->lte(today()->addDays(3))) {
            return 'Связаться до '.$this->valid_until->format('d.m');
        }

        return 'Контроль '.$this->valid_until->format('d.m');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CommercialDocumentItem::class)->orderBy('sort_order');
    }

    public function newerRevision(): ?self
    {
        if ($this->type !== 'quote') {
            return null;
        }

        return self::query()
            ->where('type', 'quote')
            ->where('revision_of_id', $this->revision_of_id ?: $this->id)
            ->where('revision_number', '>', $this->revision_number ?: 1)
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('revision_number')
            ->first();
    }

    public function statusLabel(): string
    {
        if ($newer = $this->newerRevision()) {
            return $newer->status === 'draft'
                ? 'Новая редакция подготовлена'
                : 'Заменена новой редакцией';
        }

        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function scopeCurrentRevision(Builder $query): Builder
    {
        return $query->where(function (Builder $current): void {
            $current->where('commercial_documents.type', '!=', 'quote')
                ->orWhereNotExists(function ($newer): void {
                    $newer->selectRaw('1')->from('commercial_documents as newer_revision')
                        ->where('newer_revision.type', 'quote')
                        ->whereRaw('newer_revision.revision_of_id = COALESCE(commercial_documents.revision_of_id, commercial_documents.id)')
                        ->whereColumn('newer_revision.revision_number', '>', 'commercial_documents.revision_number')
                        ->where('newer_revision.status', '!=', 'cancelled');
                });
        });
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_document_id');
    }

    public function derivedDocuments(): HasMany
    {
        return $this->hasMany(self::class, 'source_document_id');
    }

    public function revisionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revision_of_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'revision_of_id')->orderBy('revision_number');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function mailThread(): BelongsTo
    {
        return $this->belongsTo(CrmMailThread::class, 'crm_mail_thread_id');
    }

    public function clientCounterparty(): BelongsTo
    {
        return $this->belongsTo(CrmCounterparty::class, 'crm_counterparty_id');
    }

    public function clientContact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    public function portalLinks(): HasMany
    {
        return $this->hasMany(CommercialDocumentPortalLink::class);
    }

    public function portalEvents(): HasMany
    {
        return $this->hasMany(CommercialDocumentPortalEvent::class);
    }
}
