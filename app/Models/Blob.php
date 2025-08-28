<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $digest
 * @property int $size
 * @property int|null $created_at
 * @property int|null $updated_at
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

    protected function casts(): array
    {
        return [
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
}
