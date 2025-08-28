<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property string $parent_digest
 * @property string $child_digest
 * @property string|null $os
 * @property string|null $arch
 * @property string|null $platform
 * @property int $manifest_index
 * @method static Builder<static>|ManifestManifest newModelQuery()
 * @method static Builder<static>|ManifestManifest newQuery()
 * @method static Builder<static>|ManifestManifest query()
 * @mixin Eloquent
 */
class ManifestManifest extends Pivot
{
    protected $table = 'manifest_manifest';

    protected $fillable = [
        'parent_digest',
        'child_digest',
        'os',
        'arch',
        'platform',
        'manifest_index',
    ];

    public $timestamps = false;
}
