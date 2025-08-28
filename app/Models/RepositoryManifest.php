<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property string $repository
 * @property string $digest
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
}
