<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property Repository|null $repository
 * @property string $digest
 * @property-read string $api_path
 * @property-read Manifest|null $manifest
 * @property-read string $storage_path
 * @method static Builder<static>|RepositoryManifest newModelQuery()
 * @method static Builder<static>|RepositoryManifest newQuery()
 * @method static Builder<static>|RepositoryManifest query()
 * @mixin Eloquent
 */
class RepositoryManifest extends Pivot
{
    public $timestamps = false;

    protected $table = 'repository_manifest';

    protected $fillable = [
        'repository',
        'digest',
    ];

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class, 'repository', 'name');
    }

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(Manifest::class, 'digest', 'digest');
    }

    protected function storagePath(): Attribute
    {
        return Attribute::get(function ($_, $attrs): string {
            $repo = $attrs['repository'];
            $digest = $attrs['digest'];
            [$algo, $hash] = explode(':', $digest, 2);
            return "docker/registry/v2/repositories/{$repo}/_manifests/revisions/{$algo}/{$hash}";
        })->shouldCache();
    }

    protected function apiPath(): Attribute
    {
        return Attribute::get(function ($_, $attrs): string {
            $repo = $attrs['repository'];
            $digest = $attrs['digest'];
            return "/v2/{$repo}/manifests/{$digest}";
        })->shouldCache();
    }
}
