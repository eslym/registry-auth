<?php

namespace App\Models\Registry;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $repository
 * @property string $tag
 * @property string|null $reference
 * @property string $manifest_digest
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static Builder<static>|RepositoryTag newModelQuery()
 * @method static Builder<static>|RepositoryTag newQuery()
 * @method static Builder<static>|RepositoryTag query()
 * @mixin Eloquent
 */
class RepositoryTag extends Model
{
    public function getKeyName(): string
    {
        return 'reference';
    }

    /**
     * @return string
     */
    public function getKeyType(): string
    {
        return 'string';
    }
}
