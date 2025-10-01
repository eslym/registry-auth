<?php

namespace App\Console\Commands\Registry;

use App\Lib\Registry\Token;
use App\Models\Blob;
use App\Models\Manifest;
use App\Models\ManifestLayer;
use App\Models\ManifestManifest;
use App\Models\Repository;
use App\Models\RepositoryLayer;
use App\Models\RepositoryManifest;
use App\Models\RepositoryTag;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use function Laravel\Prompts\confirm;

class SyncCatalogCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'registry:sync-catalog';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync registry catalog with database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (app()->isProduction() && !confirm('This will erase existing indexed data and re-sync from the registry. Are you sure?')) {
            $this->info('Aborted.');
            return self::FAILURE;
        }
        RepositoryTag::truncate();
        RepositoryManifest::truncate();
        RepositoryLayer::truncate();
        Repository::truncate();
        ManifestLayer::truncate();
        ManifestManifest::truncate();
        Manifest::truncate();
        Blob::truncate();
        $this->syncCatalog();
        return self::SUCCESS;
    }

    protected function scope(string $scope): PendingRequest|Factory
    {
        return Http::withHeader('Authorization', 'Bearer ' . Token::cachedServiceToken('', $scope))
            ->withHeader('Accept', join(', ', [
                'application/json',
                'application/vnd.docker.distribution.manifest.v2+json',
                'application/vnd.docker.distribution.manifest.list.v2+json',
                'application/vnd.oci.image.manifest.v1+json',
                'application/vnd.oci.image.index.v1+json',
            ]))
            ->baseUrl(config('registry.endpoint'))
            ->throw();
    }

    protected function syncCatalog(): void
    {
        $next = '/v2/_catalog?n=1000';
        do {
            $response = $this->scope('registry:catalog:*')->get($next);
            $repo = $response->json('repositories', []);
            if (!empty($repo)) {
                Repository::insert(array_map(fn($name) => [
                    'name' => $name,
                ], $repo));
                $this->info("Found repositories: " . join(', ', $repo));
                foreach ($repo as $name) {
                    $this->syncTags($name);
                }
            }
            $link = $response->header('Link');
            if ($link && preg_match('/<([^>]+)>; rel="next"/', $link, $matches)) {
                $next = $matches[1];
            } else {
                $next = null;
            }
        } while ($next);
    }

    protected function syncTags(string $repo): void
    {
        $next = "/v2/$repo/tags/list?n=1000";
        do {
            $response = $this->scope("repository:$repo:pull")->get($next);
            $tags = $response->json('tags', []);

            foreach ($tags as $tag) {
                $this->info("Found tag: $repo:$tag");
                $manifest = $this->syncManifest($repo, $tag);
                $mtime = Carbon::createFromTimestamp($this->tagMTime($repo, $tag));

                RepositoryTag::create(
                    [
                        'repository' => $repo,
                        'tag' => $tag,
                        'manifest_digest' => $manifest->digest,
                        'created_at' => $mtime,
                        'updated_at' => now(),
                    ]
                );
            }

            $link = $response->header('Link');
            if ($link && preg_match('/<([^>]+)>; rel="next"/', $link, $matches)) {
                $next = $matches[1];
            } else {
                $next = null;
            }
        } while ($next);
    }

    protected function syncManifest(string $repo, string $reference): Manifest
    {
        $response = $this->scope("repository:$repo:pull")->get("/v2/$repo/manifests/$reference");
        $digest = $response->header('Docker-Content-Digest');

        $manifest = Manifest::find($digest);
        if ($manifest) {
            RepositoryManifest::upsert([
                [
                    'repository' => $repo,
                    'digest' => $digest,
                ]
            ], ['repository', 'digest'], []);
            $manifest->load(['layers', 'manifests']);

            $subs = $manifest->layers->map(fn(ManifestLayer $l) => [
                'repository' => $repo,
                'digest' => $l->blob_digest,
            ])->toArray();

            $subs = array_merge($subs, $manifest->manifests->map(fn(ManifestManifest $m) => [
                'repository' => $repo,
                'digest' => $m->child_digest,
            ])->toArray());

            RepositoryManifest::upsert($subs, ['repository', 'digest'], ['digest']);

            $layers = $manifest->layers->map(fn(ManifestLayer $l) => [
                'repository' => $repo,
                'digest' => $l->blob_digest,
            ])->toArray();

            RepositoryLayer::upsert($layers, ['repository', 'digest'], ['digest']);

            return $manifest;
        }

        $type = $response->header('Content-Type');
        $payload = $response->json();
        $mtime = Carbon::createFromTimestamp($this->blobMTime($digest));

        $manifest = Manifest::create([
            'digest' => $digest,
            'manifest_type' => $type,
            'media_type' => Arr::get($payload, 'config.mediaType') ?? null,
            'annotations' => $payload['annotations'] ?? null,
            'created_at' => $mtime,
            'updated_at' => now(),
        ]);

        Blob::upsert([
            [
                'digest' => $digest,
                'size' => $response->header('Content-Length'),
                'created_at' => $mtime,
                'updated_at' => now(),
            ]
        ], ['digest'], ['size', 'updated_at']);

        if (in_array($type, [
            'application/vnd.docker.distribution.manifest.v2+json',
            'application/vnd.oci.image.manifest.v1+json',
        ])) {
            $total = 0;

            $blobs = [];
            $layers = [];
            $repoLayers = [];

            foreach ($payload['layers'] as $index => $layer) {
                $digest = $layer['digest'];
                $mtime = Carbon::createFromTimestamp($this->blobMTime($digest));

                $blobs[$digest] = [
                    'digest' => $digest,
                    'size' => $layer['size'],
                    'created_at' => $mtime,
                    'updated_at' => now(),
                ];

                $layers [] = [
                    'manifest_digest' => $manifest->digest,
                    'blob_digest' => $digest,
                    'layer_index' => $index,
                ];

                $repoLayers [] = [
                    'repository' => $repo,
                    'digest' => $digest,
                ];

                $total += $layer['size'];
            }

            Blob::upsert(array_values($blobs), ['digest'], ['size', 'updated_at']);
            ManifestLayer::insert($layers);
            RepositoryLayer::upsert($repoLayers, ['repository', 'digest'], ['digest']);

            $manifest->total_size = $total;
            $manifest->save();
        } elseif (in_array($type, [
            'application/vnd.docker.distribution.manifest.list.v2+json',
            'application/vnd.oci.image.index.v1+json',
        ])) {
            $total = 0;
            $inserts = [];
            $manifests = [];

            foreach ($payload['manifests'] as $index => $subManifest) {
                $sub = $this->syncManifest($repo, $subManifest['digest']);
                $platform = $subManifest['platform'] ?? [];
                $inserts [] = [
                    'parent_digest' => $manifest->digest,
                    'child_digest' => $sub->digest,
                    'arch' => $platform['architecture'] ?? null,
                    'os' => $platform['os'] ?? null,
                    'manifest_index' => $index,
                ];
                $manifests [] = [
                    'repository' => $repo,
                    'digest' => $sub->digest,
                ];
                $total += $sub->total_size;
            }

            ManifestManifest::insert($inserts);
            RepositoryManifest::upsert($manifests, ['repository', 'digest'], ['digest']);

            $manifest->total_size = $total;
            $manifest->save();
        }

        return $manifest;
    }

    protected function blobMTime(string $digest): int
    {
        if (!config('registry.storage.enabled')) return time();
        [$algo, $hash] = explode(':', $digest, 2);
        $dir = substr($hash, 0, 2);
        $path = "docker/registry/v2/blobs/$algo/$dir/$hash/data";
        $disk = Storage::disk(config('registry.storage.disk'));
        if ($disk->exists($path)) {
            return $disk->lastModified($path);
        } else {
            $this->warn('Blob not found in storage: ' . $path);
            return time();
        }
    }

    protected function tagMTime(string $repo, string $tag): int
    {
        if (!config('registry.storage.enabled')) return time();
        $current = "docker/registry/v2/repositories/$repo/_manifests/tags/$tag/current/link";
        $disk = Storage::disk(config('registry.storage.disk'));
        if ($disk->exists($current)) {
            return $disk->lastModified($current);
        } else {
            $this->warn('Tag not found in storage: ' . $current);
            return time();
        }
    }
}
