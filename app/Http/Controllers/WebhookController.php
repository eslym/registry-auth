<?php

namespace App\Http\Controllers;

use App\Lib\Registry\RegistryClient;
use App\Models\Blob;
use App\Models\Manifest;
use App\Models\ManifestLayer;
use App\Models\ManifestManifest;
use App\Models\Repository;
use App\Models\RepositoryLayer;
use App\Models\RepositoryManifest;
use App\Models\RepositoryTag;
use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    private array $manifests = [];
    private array $repoTags = [];

    public function __invoke(Request $request): Response
    {
        $token = config('registry.webhook.token');

        if ($token && !$this->safeCompare($request->bearerToken() ?? '', $token)) {
            return response('Forbidden', 403)->header('Content-Type', 'text/plain');
        }

        $valid = validator($request->json()->all(), [
            'events' => 'required|array',
            'events.*.timestamp' => 'required|date',
            'events.*.action' => 'required|string|in:push,pull,delete,mount',
            'events.*.target.mediaType' => 'required_unless:events.*.action,delete|string',
            'events.*.target.repository' => 'required|string',
            'events.*.target.tag' => 'sometimes|nullable|string',
            'events.*.target.digest' => 'sometimes|nullable|string',
        ]);

        if ($valid->fails()) {
            Log::debug('Invalid webhook payload', ['errors' => $valid->errors()->all(), 'payload' => $request->json()->all()]);
            return response('Bad Request', 400)->header('Content-Type', 'text/plain');
        }

        $events = $valid->validated()['events'];

        $repos = [];

        foreach ($events as $event) {
            if (!in_array($event['action'], ['push', 'delete'])) {
                continue;
            }

            try {
                Log::debug('Received registry event', $event);
                $repoName = $event['target']['repository'];
                $repo = $repos[$repoName] ??= Repository::firstOrCreate([
                    'name' => $repoName,
                ]);

                if ($event['action'] === 'delete') {
                    if (isset($event['target']['tag']) && $event['target']['tag']) {
                        RepositoryTag::where('repository', $repo->name)
                            ->where('tag', $event['target']['tag'])
                            ->delete();
                    } else if (isset($event['target']['digest']) && $event['target']['digest']) {
                        $digest = $event['target']['digest'];
                        RepositoryManifest::where('repository', $repo->name)
                            ->where('digest', $digest)
                            ->delete();
                    }
                    continue;
                }

                if (!in_array($event['target']['mediaType'], [
                    'application/vnd.docker.distribution.manifest.v2+json',
                    'application/vnd.oci.image.manifest.v1+json',
                    'application/vnd.docker.distribution.manifest.list.v2+json',
                    'application/vnd.oci.image.index.v1+json',
                ])) {
                    continue;
                }

                $mtime = Carbon::parse($event['timestamp']);
                if (isset($event['target']['tag']) && $event['target']['tag']) {
                    $this->syncTag($repo, $event['target']['tag'], $mtime);
                } else if (isset($event['target']['digest']) && $event['target']['digest']) {
                    $this->syncManifest($repo, $event['target']['digest'], $mtime);
                }
            } catch (\Throwable $th) {
                \Log::error($th);
            }
        }

        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

    private function safeCompare(string $a, string $b): bool
    {
        $a_bytes = array_values(unpack('C*', $a));
        $b_bytes = array_values(unpack('C*', $b));
        $same = 1;
        $len = max(count($a_bytes), count($b_bytes));
        if (count($a_bytes) < $len) {
            $a_bytes = array_pad($a_bytes, $len, -1);
        }
        if (count($b_bytes) < $len) {
            $b_bytes = array_pad($b_bytes, $len, -1);
        }
        for ($i = 0; $i < $len; $i++) {
            $same &= $a_bytes[$i] === $b_bytes[$i];
        }
        return (bool)$same;
    }

    private function syncTag(Repository $repo, string $tag, Carbon $mtime): void
    {
        $key = "{$repo->name}:{$tag}";

        try {
            $manifest = $this->syncManifest($repo, $tag, $mtime);
        } catch (RequestException $e) {
            if ($e->response->status() === Response::HTTP_NOT_FOUND) {
                if (isset($this->repoTags[$key])) unset($this->repoTags[$key]);
                RepositoryTag::where('repository', $repo->name)
                    ->where('tag', $tag)
                    ->delete();
                return;
            }
            throw $e;
        }

        if (isset($this->repoTags[$key])) {
            /** @var RepositoryTag $tag */
            $tag = $this->repoTags[$key];
            if ($tag->manifest_digest !== $manifest->digest) {
                $tag->manifest_digest = $manifest->digest;
                $tag->updated_at = $mtime;
                $tag->save();
            }
            return;
        }

        $t = $this->repoTags[$key] = RepositoryTag::find($key);

        if ($t && $t->manifest_digest !== $manifest->digest) {
            $t->manifest_digest = $manifest->digest;
            $t->updated_at = $mtime;
            $t->save();
            return;
        }

        $this->repoTags[$key] = RepositoryTag::create(
            [
                'repository' => $repo->name,
                'tag' => $tag,
                'manifest_digest' => $manifest->digest,
                'created_at' => $mtime,
                'updated_at' => $mtime,
            ],
        );
    }

    private function syncManifest(Repository $repository, string $digest, Carbon $mtime): Manifest
    {
        $response = RegistryClient::scope("repository:{$repository->name}:pull")->get("/v2/{$repository->name}/manifests/{$digest}");

        $digest = $response->header('Docker-Content-Digest');

        $manifest = $this->manifests[$digest] ?? Manifest::find($digest);
        if ($manifest) {
            $this->manifests[$digest] = $manifest;
            RepositoryManifest::upsert([
                [
                    'repository' => $repository->name,
                    'digest' => $digest,
                ]
            ], ['repository', 'digest'], ['digest']);
            $manifest->load(['layers', 'manifests']);

            $subs = $manifest->layers->map(fn(ManifestLayer $l) => [
                'repository' => $repository->name,
                'digest' => $l->blob_digest,
            ])->toArray();

            $subs = array_merge($subs, $manifest->manifests->map(fn(ManifestManifest $m) => [
                'repository' => $repository->name,
                'digest' => $m->child_digest,
            ])->toArray());

            RepositoryManifest::upsert($subs, ['repository', 'digest'], ['digest']);

            $layers = $manifest->layers->map(fn(ManifestLayer $l) => [
                'repository' => $repository->name,
                'digest' => $l->blob_digest,
            ])->toArray();

            RepositoryLayer::upsert($layers, ['repository', 'digest'], ['digest']);

            return $manifest;
        }

        $type = $response->header('Content-Type');
        $payload = $response->json();

        $manifest = Manifest::create([
            'digest' => $digest,
            'manifest_type' => $type,
            'media_type' => Arr::get($payload, 'config.mediaType') ?? null,
            'annotations' => $payload['annotations'] ?? null,
            'created_at' => $mtime,
            'updated_at' => now(),
        ]);
        $this->manifests[$digest] = $manifest;

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
                    'repository' => $repository->name,
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
                $sub = $this->syncManifest($repository, $subManifest['digest'], $mtime);
                $platform = $subManifest['platform'] ?? [];
                $inserts [] = [
                    'parent_digest' => $manifest->digest,
                    'child_digest' => $sub->digest,
                    'arch' => $platform['architecture'] ?? null,
                    'os' => $platform['os'] ?? null,
                    'manifest_index' => $index,
                ];
                $manifests [] = [
                    'repository' => $repository->name,
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
}
