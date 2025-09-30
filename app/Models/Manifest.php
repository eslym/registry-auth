<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $digest
 * @property string $manifest_type
 * @property string|null $media_type
 * @property int|null $total_size
 * @property array<array-key, mixed>|null $annotations
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property-read Blob|null $blob
 * @property-read Collection<int, ManifestLayer> $layers
 * @property-read int|null $layers_count
 * @property-read Collection<int, ManifestManifest> $manifests
 * @property-read int|null $manifests_count
 * @property-read Collection<int, Manifest> $parent
 * @property-read int|null $parent_count
 * @property-read Collection<int, Repository> $repositories
 * @property-read int|null $repositories_count
 * @property-read Collection<int, RepositoryTag> $tags
 * @property-read int|null $tags_count
 * @method static Builder<static>|Manifest newModelQuery()
 * @method static Builder<static>|Manifest newQuery()
 * @method static Builder<static>|Manifest query()
 * @mixin Eloquent
 */
class Manifest extends Model
{
    protected $table = 'manifests';

    protected $fillable = [
        'digest',
        'manifest_type',
        'media_type',
        'total_size',
        'annotations',
        'created_at',
        'updated_at',
    ];

    // disable touching timestamps
    public $timestamps = false;

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'annotations' => 'array',
            'created_at' => 'timestamp',
            'updated_at' => 'timestamp',
        ];
    }

    public function getKeyName(): string
    {
        return 'digest';
    }

    /**
     * @return string
     */
    public function getKeyType(): string
    {
        return 'string';
    }

    public function layers(): HasMany
    {
        return $this->hasMany(ManifestLayer::class, 'manifest_digest', 'digest')
            ->orderBy('layer_index');
    }

    public function manifests(): HasMany
    {
        return $this->hasMany(ManifestManifest::class, 'parent_digest', 'digest')
            ->orderBy('manifest_index');
    }

    public function repositories(): BelongsToMany
    {
        return $this->belongsToMany(Repository::class, 'repository_manifests', 'manifest_digest', 'repository', 'digest', 'name');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(RepositoryTag::class, 'manifest_digest', 'digest');
    }

    public function parent(): BelongsToMany
    {
        return $this->belongsToMany(Manifest::class, 'manifest_manifests', 'child_digest', 'parent_digest', 'digest', 'digest');
    }

    public function blob(): BelongsTo
    {
        return $this->belongsTo(Blob::class, 'digest', 'digest');
    }
}
