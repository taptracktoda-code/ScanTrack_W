<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    public const ROLE_SUPER_ADMIN = 'SUPER_ADMIN';
    public const ROLE_MANAGER = 'MANAGER';
    public const ROLE_DRIVER = 'DRIVER';

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'admins';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'unit',
        'user_id',
        'license_number',
        'contact',
        'address',
        'status',
        'shift_status',
        'driver_id',
        'must_change_password',
        'organization_id',
        'first_name',
        'middle_name',
        'last_name',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function driver()
    {
        return $this->hasOne(Driver::class, 'id', 'driver_id');
    }

    public function organization()
    {
        return $this->belongsTo(TodaOrganization::class, 'organization_id');
    }

    public function terminalActivities()
    {
        return $this->hasMany(TerminalActivity::class, 'admin_id');
    }

    public function hasRole(string $role): bool
    {
        return strcasecmp((string) $this->role, $role) === 0;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN)
            || $this->hasRole('ADMINISTRATOR');
    }

    public function isManager(): bool
    {
        return $this->hasRole(self::ROLE_MANAGER);
    }

    public function isDriver(): bool
    {
        return $this->hasRole(self::ROLE_DRIVER);
    }
}
