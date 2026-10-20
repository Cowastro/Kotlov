<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmMailbox extends Model
{
    public const SERVICE_MAILBOXES = [
        'order@kotlov.by' => 'Системные заказы и заявки',
        'no-reply@kotlov.by' => 'Автоматические письма доступа',
    ];

    protected $fillable = [
        'name', 'email', 'username', 'password', 'imap_host', 'imap_port',
        'imap_encryption', 'imap_folder', 'smtp_host', 'smtp_port',
        'smtp_encryption', 'is_enabled', 'create_leads', 'last_uid',
        'last_sync_at', 'last_success_at', 'last_error',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'password' => 'encrypted',
        'is_enabled' => 'boolean',
        'create_leads' => 'boolean',
        'last_sync_at' => 'datetime',
        'last_success_at' => 'datetime',
    ];

    public function threads(): HasMany
    {
        return $this->hasMany(CrmMailThread::class, 'mailbox_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CrmMailMessage::class, 'mailbox_id');
    }

    public function imapPath(): string
    {
        $flags = match ($this->imap_encryption) {
            'ssl' => '/imap/ssl',
            'tls' => '/imap/tls',
            default => '/imap/notls',
        };

        return sprintf('{%s:%d%s}%s', $this->imap_host, $this->imap_port, $flags, $this->imap_folder);
    }

    public function isServiceMailbox(): bool
    {
        return array_key_exists(mb_strtolower(trim((string) $this->email)), self::SERVICE_MAILBOXES);
    }

    public function servicePurpose(): ?string
    {
        return self::SERVICE_MAILBOXES[mb_strtolower(trim((string) $this->email))] ?? null;
    }

    public function scopeManualSending(Builder $query): Builder
    {
        return $query->whereNotIn('email', array_keys(self::SERVICE_MAILBOXES));
    }

    /** Mailboxes an employee may choose in the CRM composer. */
    public function scopeOperatorSending(Builder $query): Builder
    {
        return $query->where('email', '!=', 'no-reply@kotlov.by');
    }
}
