<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 * @property-read bool $password_expired
 * @property-read UserGroup|null $pivot
 * @property-read Collection<int, Group> $groups
 * @property-read int|null $groups_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @method static UserFactory factory($count = null, $state = [])
 * @method static Builder<static>|User newModelQuery()
 * @method static Builder<static>|User newQuery()
 * @method static Builder<static>|User query()
 * @mixin Eloquent
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
                now()->isAfter($this->password_expired_at)
        );
    }

    /**
     * @return BelongsToMany<Group, User, UserGroup>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, UserGroup::class, 'user_id', 'group_id')
            ->orderBy('sort_order');
    }

    /**
     * @return MorphMany<AccessControl, User>
     */
    public function access_controls(): MorphMany
    {
        return $this->morphMany(AccessControl::class, 'owner')
            ->orderBy('sort_order');
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
        $this->load('access_controls', 'groups.access_controls');
        return $this->access_controls->merge($this->groups->flatMap(function (Group $group) {
            return $group->access_controls;
        }));
    }
}
