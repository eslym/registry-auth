<?php

namespace App\Models;

use App\Enums\AccessLevel;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $owner_type
 * @property int $owner_id
 * @property string $repository
 * @property AccessLevel $access_level
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|Group $owner
 * @method static Builder<static>|AccessControl newModelQuery()
 * @method static Builder<static>|AccessControl newQuery()
 * @method static Builder<static>|AccessControl query()
 * @mixin Eloquent
 */
class AccessControl extends Model
{
    protected $table = 'access_controls';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'repository',
        'access_level',
        'sort_order',
    ];

    /**
     * @return MorphTo<User|Group, AccessControl>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function casts(): array
    {
        return [
            'access_level' => AccessLevel::class,
        ];
    }
}
