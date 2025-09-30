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
 * @property-read Blob|null $blob
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

    public function repository(): BelongsTo
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
