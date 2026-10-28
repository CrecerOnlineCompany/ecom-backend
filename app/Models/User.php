<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_SUPERADMIN = 'Superadmin';
    public const ROLE_ADMIN = 'Admin';
    public const ROLE_EDITOR = 'Editor';
    public const ROLE_CUSTOMER = 'Cliente';

    /**
     * Roles allowed to sign in to the admin panel.
     */
    public const MANAGE_ROLES = [self::ROLE_SUPERADMIN, self::ROLE_ADMIN, self::ROLE_EDITOR];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'siteid',
        'site_code',
        'superuser',
        'role',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'superuser' => 'boolean',
        'status' => 'integer',
    ];

    public function isSuperadmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    public function canManage(): bool
    {
        return in_array($this->role, self::MANAGE_ROLES, true) && (int) $this->status !== 0;
    }
}
