<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $user_id
 * @property int $group_id
 * @property int $sort_order
 * @property-read Group $group
 * @property-read User $user
 * @method static Builder<static>|UserGroup newModelQuery()
 * @method static Builder<static>|UserGroup newQuery()
 * @method static Builder<static>|UserGroup query()
 * @mixin Eloquent
 */
class UserGroup extends Pivot
{
    protected $table = 'user_group';

    public $timestamps = false;

    /**
     * @return BelongsTo<User, UserGroup>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * @return BelongsTo<Group, UserGroup>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id', 'id');
    }
}
