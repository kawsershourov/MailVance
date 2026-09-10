<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\PermissionRegistry;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_path',
        'job_title',
        'company',
        'phone',
        'timezone',
        'bio',
        'is_active',
        'last_login_at',
    ];

    /**
     * Defaults applied to a freshly instantiated user, so a newly created
     * account is usable in memory without re-reading it from the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'timezone' => 'UTC',
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
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /** Memoised effective permission slugs, so a request resolves them once. */
    protected ?array $resolvedPermissions = null;

    public function smtpConfigs()
    {
        return $this->hasMany(SmtpConfig::class);
    }

    public function contactLists()
    {
        return $this->hasMany(ContactList::class);
    }

    public function templates()
    {
        return $this->hasMany(EmailTemplate::class);
    }

    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    /** Per-user permission overrides layered on top of the user's roles. */
    public function directPermissions()
    {
        return $this->belongsToMany(Permission::class)->withPivot('granted');
    }

    /*
    |--------------------------------------------------------------------------
    | Roles & permissions
    |--------------------------------------------------------------------------
    */

    public function isSuperAdmin(): bool
    {
        return $this->roles->contains(fn (Role $role) => $role->isSuperAdmin());
    }

    /** True when the user holds any of the given role slugs. */
    public function hasRole(string ...$slugs): bool
    {
        return $this->roles->whereIn('slug', $slugs)->isNotEmpty();
    }

    /**
     * Effective permission slugs: the union of every role's permissions, with
     * per-user overrides applied last (an explicit deny beats a role grant).
     *
     * @return list<string>
     */
    public function permissionSlugs(): array
    {
        if ($this->resolvedPermissions !== null) {
            return $this->resolvedPermissions;
        }

        if ($this->isSuperAdmin()) {
            return $this->resolvedPermissions = PermissionRegistry::slugs();
        }

        $this->loadMissing(['roles.permissions', 'directPermissions']);

        $slugs = $this->roles
            ->flatMap(fn (Role $role) => $role->permissions->pluck('slug'))
            ->unique()
            ->all();

        $slugs = array_flip($slugs);

        foreach ($this->directPermissions as $permission) {
            if ($permission->pivot->granted) {
                $slugs[$permission->slug] = true;
            } else {
                unset($slugs[$permission->slug]);
            }
        }

        return $this->resolvedPermissions = array_keys($slugs);
    }

    /** True when the user holds ALL of the given permission slugs. */
    public function hasPermission(string ...$slugs): bool
    {
        if ($slugs === []) {
            return true;
        }

        $held = $this->permissionSlugs();

        foreach ($slugs as $slug) {
            if (! in_array($slug, $held, true)) {
                return false;
            }
        }

        return true;
    }

    /** True when the user holds AT LEAST ONE of the given permission slugs. */
    public function hasAnyPermission(string ...$slugs): bool
    {
        return array_intersect($slugs, $this->permissionSlugs()) !== [];
    }

    /** Drops the memoised permission set after roles/overrides change. */
    public function forgetPermissionCache(): void
    {
        $this->resolvedPermissions = null;
        $this->unsetRelation('roles')->unsetRelation('directPermissions');
    }

    /*
    |--------------------------------------------------------------------------
    | Profile helpers
    |--------------------------------------------------------------------------
    */

    /** Up to two initials, used by the avatar fallback everywhere in the UI. */
    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $parts = array_values(array_filter($parts));

        if ($parts === []) {
            return strtoupper(substr((string) $this->email, 0, 1)) ?: 'U';
        }

        $initials = mb_substr($parts[0], 0, 1);

        if (count($parts) > 1) {
            $initials .= mb_substr(end($parts), 0, 1);
        }

        return mb_strtoupper($initials);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar_path || ! Storage::disk('local')->exists($this->avatar_path)) {
            return null;
        }

        return route('profile.avatar', ['user' => $this->id, 'v' => $this->updated_at?->timestamp]);
    }

    public function getRoleNamesAttribute(): string
    {
        return $this->roles->pluck('name')->implode(', ') ?: 'No role';
    }
}
