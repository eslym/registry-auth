<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $digest
 * @property int $size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ManifestLayer> $layers
 * @property-read int|null $layers_count
 * @property-read Manifest|null $manifest
 * @property-read string $storage_path
 * @method static Builder<static>|Blob newModelQuery()
 * @method static Builder<static>|Blob newQuery()
 * @method static Builder<static>|Blob query()
 * @mixin Eloquent
 */
class Blob extends Model
{
    use Prunable;

    protected $table = 'blobs';

    public $incrementing = false;

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
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
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

    public function layers(): HasMany
    {
        return $this->hasMany(ManifestLayer::class, 'blob_digest', 'digest');
    }

    public function manifest(): BelongsTo
    {
        return $this->BelongsTo(Manifest::class, 'digest', 'digest');
    }

    protected function storagePath(): Attribute
    {
        return Attribute::get(function ($_, $attrs): string {
            $digest = $attrs['digest'];
            [$algo, $hash] = explode(':', $digest, 2);
            $dir = substr($hash, 0, 2);
            return "docker/registry/v2/blobs/$algo/$dir/$hash";
        })->shouldCache();
    }

    public function prunable(): Builder
    {
        return $this
            ->where('created_at', '<', now()->subDays(config('registry.storage.blob_cleanup')))
            ->whereDoesntHave('layers')
            ->whereDoesntHave('manifest');
    }

    protected function pruning(): void
    {
        if (!config('registry.storage.enabled')) return;
        Storage::disk(config('registry.storage.disk'))->deleteDirectory($this->storage_path);
    }
}
