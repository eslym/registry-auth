<?php

namespace App\Models\Registry;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $digest
 * @property int $size
 * @property string|null $created_at
 * @property string|null $updated_at
 * @method static Builder<static>|Blob newModelQuery()
 * @method static Builder<static>|Blob newQuery()
 * @method static Builder<static>|Blob query()
 * @mixin Eloquent
 */
class Blob extends Model
{
    protected $table = 'blobs';

    protected $fillable = [
        'digest',
        'size',
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
