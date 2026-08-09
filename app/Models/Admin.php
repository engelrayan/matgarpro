<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A platform operator. Never a merchant — see the admins migration for why the
 * two live in separate tables.
 */
class Admin extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\AdminFactory> */
    use HasFactory;

    /** Everything, including other operators, plans and pricing. */
    public const ROLE_SUPER = 'super';

    /** Support: reads everything, operates stores, cannot change the rules. */
    public const ROLE_STAFF = 'staff';

    public const ROLES = [self::ROLE_SUPER, self::ROLE_STAFF];

    /**
     * `role` and `is_active` are absent on purpose.
     *
     * They are the two fields that decide what this account may do, so they are
     * only ever set explicitly — a mass-assigned payload can never grant
     * itself super, and there is no request path that could.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(AdminActivityLog::class);
    }

    public function isSuper(): bool
    {
        return $this->role === self::ROLE_SUPER;
    }

    public function roleLabel(): string
    {
        return [
            self::ROLE_SUPER => 'مدير عام',
            self::ROLE_STAFF => 'فريق الدعم',
        ][$this->role] ?? $this->role;
    }
}
