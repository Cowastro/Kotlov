<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password',
        'role', 'phone', 'avatar', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'is_active'         => 'boolean',
    ];

    // Роли
    public const ROLES = [
        'admin'     => 'Администратор',
        'supplier'  => 'Поставщик',
        'installer' => 'Монтажник',
        'client'    => 'Клиент',
    ];

    // Проверки ролей
    public function isAdmin(): bool    { return $this->role === 'admin'; }
    public function isSupplier(): bool { return $this->role === 'supplier'; }
    public function isInstaller(): bool{ return $this->role === 'installer'; }
    public function isClient(): bool   { return $this->role === 'client'; }

    // Доступ в Filament панель
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() && $this->is_active;
    }

    // Отношения
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function supplierProfile(): HasOne
    {
        return $this->hasOne(SupplierProfile::class);
    }

    public function installerProfile(): HasOne
    {
        return $this->hasOne(InstallerProfile::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'supplier_id');
    }

    public function installRequests(): HasMany
    {
        return $this->hasMany(InstallRequest::class, 'client_id');
    }

    public function assignedInstalls(): HasMany
    {
        return $this->hasMany(InstallRequest::class, 'installer_id');
    }

    public function messageThreads(): HasMany
    {
        return $this->hasMany(MessageThread::class, 'client_id');
    }

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
