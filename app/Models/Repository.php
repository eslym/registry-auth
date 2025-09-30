<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $name
 * @property string|null $description
 * @property array<array-key, mixed>|null $prune_rules
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Manifest> $manifests
 * @property-read int|null $manifests_count
 * @property-read Collection<int, RepositoryTag> $tags
 * @property-read int|null $tags_count
 * @method static Builder<static>|Repository newModelQuery()
 * @method static Builder<static>|Repository newQuery()
 * @method static Builder<static>|Repository query()
 * @mixin Eloquent
 */
class Repository extends Model
{
    protected $table = 'repositories';

    protected $fillable = [
        'name',
        'description',
        'prune_rules',
    ];

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'prune_rules' => 'array',
        ];
    }

    public function getKeyName(): string
    {
        return 'name';
    }

    /**
     * @return string
     */
    public function getKeyType(): string
    {
        return 'string';
    }

    public function tags(): HasMany
    {
        return $this->hasMany(RepositoryTag::class, 'repository', 'name');
    }

    public function manifests(): BelongsToMany {
        return $this->belongsToMany(Manifest::class, 'repository_manifest', 'repository', 'digest', 'name', 'digest');
    }
}
