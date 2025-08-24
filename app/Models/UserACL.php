<?php

namespace App\Models;

use App\Enums\AccessLevel;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $owner_type
 * @property int|null $owner_id
 * @property int|null $group_id
 * @property string $rule
 * @property AccessLevel $access_level
 * @property int $sort_order
 * @property int|null $group_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Group|null $group
 * @property-read User|Group|AccessToken|null $owner
 * @property-read User|null $user
 * @method static Builder<static>|UserACL newModelQuery()
 * @method static Builder<static>|UserACL newQuery()
 * @method static Builder<static>|UserACL query()
 * @mixin Eloquent
 */
class UserACL extends AccessControl
{
    protected $table = 'user_acls';

    /**
     * @return BelongsTo<User, static>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id', 'id');
    }

    /**
     * @return BelongsTo<Group, static>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id', 'id');
    }
}
