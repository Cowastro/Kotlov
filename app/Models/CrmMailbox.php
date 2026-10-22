<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    /** Employees who own this mailbox; none = shared by every manager. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'crm_mailbox_user', 'mailbox_id', 'user_id')->withTimestamps();
    }

    public function isPersonal(): bool
    {
        return $this->users()->exists();
    }

    public function isVisibleTo(?User $user): bool
    {
        return static::query()->whereKey($this->getKey())->visibleTo($user)->exists();
    }

    /**
     * Administrators see every mailbox; other employees see shared mailboxes
     * and the ones assigned to them.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->isAdmin()) {
            return $query;
        }

        return $query->where(fn (Builder $query): Builder => $query
            ->whereDoesntHave('users')
            ->when($user, fn (Builder $query): Builder => $query
                ->orWhereHas('users', fn (Builder $users): Builder => $users->whereKey($user->getKey()))));
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
