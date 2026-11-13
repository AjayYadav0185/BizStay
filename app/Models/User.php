<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A login. Staff (admin / manager / staff) use the admin panel and the
 * manager side of the app; tenants only ever use the app, scoped to their
 * own guest record.
 *
 * @property UserRole $role
 * @property bool $is_active
 */
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'is_active',
        'guest_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function roleOrDefault(): UserRole
    {
        return $this->role ?? UserRole::Manager;
    }

    public function isTenant(): bool
    {
        return $this->roleOrDefault() === UserRole::Tenant;
    }

    public function isStaff(): bool
    {
        return $this->roleOrDefault()->isStaff();
    }

    public function isAdmin(): bool
    {
        return $this->roleOrDefault() === UserRole::Admin;
    }

    public function canManageFinance(): bool
    {
        return $this->roleOrDefault()->canManageFinance();
    }

    public function isActive(): bool
    {
        return $this->is_active ?? true;
    }

    /**
     * Residents never see the admin panel — it lists every guest's phone,
     * KYC and dues. Deactivated staff are locked out too.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isStaff() && $this->isActive();
    }
}
