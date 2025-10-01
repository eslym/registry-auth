<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property string $repository
 * @property string $digest
 * @property-read Blob|null $blob
 * @property-read Repository|null $repo
 * @property-read string $storage_path
 * @method static Builder<static>|RepositoryLayer newModelQuery()
 * @method static Builder<static>|RepositoryLayer newQuery()
 * @method static Builder<static>|RepositoryLayer query()
 * @mixin Eloquent
 */
class RepositoryLayer extends Pivot
{
    public $timestamps = false;

    protected $table = 'repository_layer';

    protected $fillable = [
        'repository',
        'digest',
    ];

    public function repo(): BelongsTo
    {
        return $this->belongsTo(Repository::class, 'repository', 'name');
    }

    public function blob(): BelongsTo
    {
        return $this->belongsTo(Blob::class, 'digest', 'digest');
    }

    protected function storagePath(): Attribute
    {
        return Attribute::get(function ($_, $attrs): string {
            $repo = $attrs['repository'];
            $digest = $attrs['digest'];
            [$algo, $hash] = explode(':', $digest, 2);
            return "docker/registry/v2/repositories/{$repo}/_layers/{$algo}/{$hash}";
        });
    }
}
