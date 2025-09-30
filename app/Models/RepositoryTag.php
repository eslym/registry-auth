<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Repository|null $repository
 * @property string $tag
 * @property string|null $reference
 * @property string $manifest_digest
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $api_path
 * @property-read Manifest|null $manifest
 * @property-read string $storage_path
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

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
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

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class, 'repository', 'name');
    }

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(Manifest::class, 'manifest_digest', 'digest');
    }

    protected function storagePath(): Attribute
    {
        return Attribute::get(function ($_, $attrs): string {
            $repo = $attrs['repository'];
            $tag = $attrs['tag'];
            return "docker/registry/v2/repositories/{$repo}/_manifests/tags/{$tag}";
        });
    }

    protected function apiPath(): Attribute
    {
        return Attribute::get(function ($_, $attrs): string {
            $repo = $attrs['repository'];
            $tag = $attrs['tag'];
            return "/v2/{$repo}/manifests/{$tag}";
        });
    }
}
