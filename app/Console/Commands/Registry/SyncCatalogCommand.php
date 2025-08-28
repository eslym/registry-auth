<?php

namespace App\Console\Commands\Registry;

use App\Lib\Registry\Token;
use App\Models\Blob;
use App\Models\Manifest;
use App\Models\Repository;
use App\Models\RepositoryTag;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
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
        Repository::truncate();
        RepositoryTag::truncate();
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
                Repository::upsert(array_map(fn($name) => [
                    'name' => $name,
                    'is_synced' => true,
                    'is_dangling' => false,
                ], $repo), 'name', ['is_synced', 'is_dangling']);
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

        $n = Repository::where('is_synced', false)
            ->update([
                'is_synced' => true,
                'is_dangling' => true,
            ]);

        if ($n > 0) {
            $r = Str::plural('repository', $n);
            $this->info("$n $r is missing from registry, marked as dangling.");
        }
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

                if (config('registry.storage.enabled')) {
                    $mtime = Carbon::createFromTimestamp($this->blobMTime($manifest->digest));
                } else {
                    $mtime = now();
                }

                RepositoryTag::upsert([
                    [
                        'repository' => $repo,
                        'tag' => $tag,
                        'manifest_digest' => $manifest->digest,
                        'is_synced' => true,
                        'is_dangling' => false,
                        'created_at' => $mtime,
                        'updated_at' => now(),
                    ]
                ], ['repository', 'tag'], ['manifest_digest', 'is_synced', 'is_dangling', 'updated_at']);
            }

            $link = $response->header('Link');
            if ($link && preg_match('/<([^>]+)>; rel="next"/', $link, $matches)) {
                $next = $matches[1];
            } else {
                $next = null;
            }
        } while ($next);

        if (isset($repository)) {
            $n = RepositoryTag::where('repository_id', $repository->id)
                ->where('is_synced', false)
                ->update([
                    'is_synced' => true,
                    'is_dangling' => true,
                ]);

            if ($n > 0) {
                $r = Str::plural('tag', $n);
                $this->info("$n $r of repository '$repo' is missing from registry, marked as dangling.");
            }
        }
    }

    protected function syncManifest(string $repo, string $reference): Manifest
    {
        $response = $this->scope("repository:$repo:pull")->get("/v2/$repo/manifests/$reference");
    }

    protected function blobMTime(string $digest): ?int
    {
        [$algo, $hash] = explode(':', $digest, 2);
        $dir = substr($hash, 0, 2);
        $path = "docker/registry/v2/blobs/$algo/$dir/$hash/data";
        $disk = Storage::disk(config('registry.storage.disk'));
        if ($disk->exists($path)) {
            return $disk->lastModified($path);
        } else {
            return null;
        }
    }
}
