<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $name
 * @property string|null $description
 * @property array<array-key, mixed>|null $prune_rules
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static Builder<static>|Repository newModelQuery()
 * @method static Builder<static>|Repository newQuery()
 * @method static Builder<static>|Repository query()
 * @mixin Eloquent
 */
class Repository extends Model
{
    protected $table = 'repositories';

    protected $fillable = [
        'name',
        'description',
        'prune_rules',
    ];

    protected function casts(): array
    {
        return [
            'prune_rules' => 'array',
        ];
    }

    public function getKeyName(): string
    {
        return 'name';
    }

    /**
     * @return string
     */
    public function getKeyType(): string
    {
        return 'string';
    }
}
