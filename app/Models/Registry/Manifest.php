<?php

namespace App\Models\Registry;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $digest
 * @property string $media_type
 * @property int|null $total_size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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
        'media_type',
        'total_size',
        'created_at',
        'updated_at',
    ];

    // disable touching timestamps
    public $timestamps = false;

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
}
