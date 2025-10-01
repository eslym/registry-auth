<?php

namespace App\Console\Commands\Registry;

use App\Lib\Registry\RegistryClient;
use App\Models\Blob;
use App\Models\Manifest;
use App\Models\ManifestLayer;
use App\Models\ManifestManifest;
use App\Models\Repository;
use App\Models\RepositoryLayer;
use App\Models\RepositoryManifest;
use App\Models\RepositoryTag;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use function Laravel\Prompts\confirm;

class SyncCatalogCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'registry:sync-catalog {--T|truncate : Truncate existing data before sync}';

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
        $q = $this->option('truncate') ? 'This will erase existing indexed data and re-sync from the registry. ' : '';
        if (app()->isProduction() && !confirm($q . 'Are you sure?')) {
            $this->info('Aborted.');
            return self::FAILURE;
        }
        if ($this->option('truncate')) {
            RepositoryTag::truncate();
            RepositoryManifest::truncate();
            RepositoryLayer::truncate();
            Repository::truncate();
            ManifestLayer::truncate();
            ManifestManifest::truncate();
            Manifest::truncate();
            Blob::truncate();
        }
        $this->syncCatalog();
        $this->syncBlobs();
        return self::SUCCESS;
    }

    protected function syncCatalog(): void
    {
        $next = '/v2/_catalog?n=1000';
        do {
            $response = RegistryClient::scope('registry:catalog:*')->get($next);
            $repo = $response->json('repositories', []);
            if (!empty($repo)) {
                Repository::upsert(array_map(fn($name) => [
                    'name' => $name,
                ], $repo), ['name'], ['name']);
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
            $response = RegistryClient::scope("repository:$repo:pull")->get($next);
            $tags = $response->json('tags', []);

            foreach ($tags as $tag) {
                $this->info("Found tag: $repo:$tag");
                $manifest = $this->syncManifest($repo, $tag);
                $mtime = Carbon::createFromTimestamp(RegistryClient::tagMTime($repo, $tag) ?? time());

                $tag = RepositoryTag::where('repository', $repo)->where('tag', $tag)->first();

                if (!$tag) {
                    RepositoryTag::create([
                        'repository' => $repo,
                        'tag' => $tag,
                        'manifest_digest' => $manifest->digest,
                        'created_at' => $mtime,
                        'updated_at' => $mtime,
                    ]);
                } else {
                    $tag->manifest_digest = $manifest->digest;
                    $tag->updated_at = $mtime;
                    $tag->save();
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

    protected function syncManifest(string $repo, string $reference): Manifest
    {
        $response = RegistryClient::scope("repository:$repo:pull")->get("/v2/$repo/manifests/$reference");
        $digest = $response->header('Docker-Content-Digest');

        $manifest = Manifest::find($digest);
        if ($manifest) {
            RepositoryManifest::upsert([
                [
                    'repository' => $repo,
                    'digest' => $digest,
                ]
            ], ['repository', 'digest'], ['digest']);
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
        $mtime = Carbon::createFromTimestamp(RegistryClient::blobMTime($digest) ?? time());

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
                $mtime = Carbon::createFromTimestamp(RegistryClient::blobMTime($digest) ?? time());

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
            ManifestLayer::upsert($layers, ['manifest_digest', 'blob_digest'], ['layer_index']);
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

            ManifestManifest::upsert($inserts, ['parent_digest', 'child_digest'], ['arch', 'os', 'manifest_index']);
            RepositoryManifest::upsert($manifests, ['repository', 'digest'], ['digest']);

            $manifest->total_size = $total;
            $manifest->save();
        }

        return $manifest;
    }

    protected function syncBlobs(): void
    {
        if (!config('registry.storage.enabled')) return;
        $disk = Storage::disk(config('registry.storage.disk'));
        $listing = $disk->allFiles('docker/registry/v2/blobs');
        $blobs = [];
        foreach ($listing as $path) {
            [$algo, $_, $hash] = explode('/', Str::after($path, 'docker/registry/v2/blobs/'));
            $mtime = Carbon::createFromTimestamp($disk->lastModified($path));
            $blobs[] = [
                'digest' => "$algo:$hash",
                'size' => $disk->size($path),
                'created_at' => $mtime,
                'updated_at' => now(),
            ];
        }
        Blob::upsert($blobs, ['digest'], ['size', 'updated_at']);
    }
}
