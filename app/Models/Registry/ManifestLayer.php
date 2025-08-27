<?php

namespace App\Models\Registry;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property string $manifest_digest
 * @property string $blob_digest
 * @property int $layer_index
 * @method static Builder<static>|ManifestLayer newModelQuery()
 * @method static Builder<static>|ManifestLayer newQuery()
 * @method static Builder<static>|ManifestLayer query()
 * @mixin Eloquent
 */
class ManifestLayer extends Pivot
{
    protected $table = 'manifest_layer';

    protected $fillable = [
        'manifest_digest',
        'blob_digest',
        'layer_index',
    ];

    public $timestamps = false;
}
