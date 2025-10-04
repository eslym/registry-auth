<?php

namespace App\Models;

use App\Lib\Registry\RegistryClient;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $repository
 * @property string $tag
 * @property string|null $reference
 * @property string $manifest_digest
 * @property Carbon|null $flagged_prune_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $api_path
 * @property-read Manifest|null $manifest
 * @property-read Repository|null $repo
 * @property-read string $storage_path
 * @method static Builder<static>|RepositoryTag newModelQuery()
 * @method static Builder<static>|RepositoryTag newQuery()
 * @method static Builder<static>|RepositoryTag query()
 * @mixin Eloquent
 */
class RepositoryTag extends Model
{
    use Prunable;

    protected $table = 'repository_tags';

    public $timestamps = false;

    protected $fillable = [
        'repository',
        'tag',
        'reference',
        'manifest_digest',
        'created_at',
        'updated_at',
    ];

    public $incrementing = false;

    protected bool $storageCleanup = false;

    protected static function boot(): void
    {
        parent::boot();
        static::deleted(function (self $tag) {
            if (!$tag->storageCleanup) return;
            if (config('registry.storage.enabled')) {
                $disk = Storage::disk(config('registry.storage.disk'));
                if ($disk->exists($tag->storage_path)) {
                    $disk->delete($tag->storage_path);
                }
            } else {
                RegistryClient::scope("repository:{$tag->repository}:*")->delete($tag->api_path);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'flagged_prune_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getKeyName(): string
    {
        return 'reference';
    }

    /**
     * @return string
     */
    public function getKeyType(): string
    {
        return 'string';
    }

    public function repo(): BelongsTo
    {
        return $this->belongsTo(Repository::class, 'repository', 'name');
    }

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(Manifest::class, 'manifest_digest', 'digest');
    }

    public function markCleanup(): void
    {
        $this->storageCleanup = true;
    }

    protected function storagePath(): Attribute
    {
        return Attribute::get(function ($_, $attrs): string {
            $repo = $attrs['repository'];
            $tag = $attrs['tag'];
            return "docker/registry/v2/repositories/{$repo}/_manifests/tags/{$tag}";
        });
    }

    protected function apiPath(): Attribute
    {
        return Attribute::get(function ($_, $attrs): string {
            $repo = $attrs['repository'];
            $tag = $attrs['tag'];
            return "/v2/{$repo}/manifests/{$tag}";
        });
    }

    public function prunable(): Builder
    {
        $threshold = now()->sub(config('registry.storage.blob_cleanup'));
        return $this->where('flagged_prune_at', '<', $threshold);
    }
}
