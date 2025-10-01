<?php

namespace App\Lib\Registry;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

final class RegistryClient
{
    public static function scope(string $scope): PendingRequest|Factory
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

    public static function blobMTime(string $digest): ?int
    {
        if (!config('registry.storage.enabled')) return null;
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

    public static function tagMTime(string $repo, string $tag): ?int
    {
        if (!config('registry.storage.enabled')) return null;
        $current = "docker/registry/v2/repositories/$repo/_manifests/tags/$tag/current/link";
        $disk = Storage::disk(config('registry.storage.disk'));
        if ($disk->exists($current)) {
            return $disk->lastModified($current);
        } else {
            return null;
        }
    }
}
