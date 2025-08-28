<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $digest
 * @property string $media_type
 * @property int|null $total_size
 * @property array<array-key, mixed>|null $annotations
 * @property int|null $created_at
 * @property int|null $updated_at
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

    protected function casts(): array
    {
        return [
            'annotations' => 'array',
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
