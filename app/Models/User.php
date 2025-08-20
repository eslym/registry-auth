<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\GrantRegistryToken;
use App\Models\Contracts\CanGrantRegistryAccess;
use Database\Factories\UserFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as BaseCollection;

/**
 * @property int $id
 * @property string|null $username
 * @property string|null $password
 * @property string|null $remember_token
 * @property bool $is_admin
 * @property Carbon|null $password_expired_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, AccessControl> $access_controls
 * @property-read int|null $access_controls_count
 * @property-read Collection<int, UserACL> $all_access_controls
 * @property-read int|null $all_access_controls_count
 * @property-read UserGroup|null $pivot
 * @property-read Collection<int, Group> $groups
 * @property-read int|null $groups_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read bool $password_expired
 * @method static UserFactory factory($count = null, $state = [])
 * @method static Builder<static>|User newModelQuery()
 * @method static Builder<static>|User newQuery()
 * @method static Builder<static>|User query()
 * @mixin Eloquent
 */
class User extends Authenticatable implements CanGrantRegistryAccess
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, GrantRegistryToken;

    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'password',
        'is_admin',
        'password_expired_at',
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
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'password_expired_at' => 'datetime',
        ];
    }

    protected function passwordExpired(): Attribute
    {
        return Attribute::get(
            fn($attributes): bool => $this->password_expired_at &&
                $this->password_expired_at->isPast()
        );
    }

    /**
     * @return BelongsToMany<Group, static, UserGroup>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, UserGroup::class, 'user_id', 'group_id')
            ->orderBy('sort_order');
    }

    /**
     * @return HasMany<AccessToken, static>
     */
    public function access_tokens(): HasMany
    {
        return $this->hasMany(AccessToken::class, 'user_id', 'id');
    }

    /**
     * @return MorphMany<AccessControl, static>
     */
    public function access_controls(): MorphMany
    {
        return $this->morphMany(AccessControl::class, 'owner')
            ->orderBy('sort_order');
    }

    /**
     * @return HasMany<UserACL, static>
     */
    public function all_access_controls(): HasMany
    {
        return $this->hasMany(UserACL::class, 'owner_id');
    }

    public function isAnonymous(): bool
    {
        return $this->username === null;
    }

    /**
     * @return BaseCollection<int, AccessControl>
     */
    public function getAllAccessControls(): BaseCollection
    {
        return collect($this->all_access_controls->all());
    }

    function canListCatalog(): bool
    {
        return $this->username !== null || config('registry.anonymous_catalog', false);
    }

    function getUsername(): string
    {
        return $this->username ?? '';
    }
}
