<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, AccessControl> $access_controls
 * @property-read int|null $access_controls_count
 * @property-read UserGroup|null $pivot
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 * @method static Builder<static>|Group newModelQuery()
 * @method static Builder<static>|Group newQuery()
 * @method static Builder<static>|Group query()
 * @mixin Eloquent
 */
class Group extends Model
{
    protected $table = 'groups';

    protected $fillable = ['name', 'permissions'];

    /**
     * @return BelongsToMany<User, Group>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class,  UserGroup::class, 'group_id', 'user_id');
    }

    /**
     * @return MorphMany<AccessControl, User>
     */
    public function access_controls(): MorphMany
    {
        return $this->morphMany(AccessControl::class, 'owner')
            ->orderBy('sort_order');
    }
}
