<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $repository
 * @property string $tag
 * @property string|null $reference
 * @property string $manifest_digest
 * @property int|null $created_at
 * @property int|null $updated_at
 * @method static Builder<static>|RepositoryTag newModelQuery()
 * @method static Builder<static>|RepositoryTag newQuery()
 * @method static Builder<static>|RepositoryTag query()
 * @mixin Eloquent
 */
class RepositoryTag extends Model
{
    protected $table = 'repository_tags';

    public $timestamps = false;

    protected $fillable = [
        'repository',
        'tag',
        'reference',
        'manifest_digest',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'timestamp',
            'updated_at' => 'timestamp',
        ];
    }

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
