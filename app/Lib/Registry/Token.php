<?php

namespace App\Lib\Registry;

use App\Lib\Crypto\CertificateService;
use Firebase\JWT\JWT;
use Illuminate\Support\Str;

/**
 * Issues Docker Distribution-compatible JWTs using config('registry').
 *
 * - Signs with the configured leaf private key (supports passphrase/secret)
 * - Adds JOSE headers: alg (from config), kid (from leaf cert), x5c ([leaf[, ca]])
 * - Claims: iss, aud, iat, nbf, exp, jti, access (+ optional sub)
 * - TTL defaults to config('registry.jwt.ttl')
 */
class Token
{
    /**
     * Issue a token.
     *
     * @param string|null $subject Optional subject/username (sub claim)
     * @param array $access  Array of scope objects per Distribution spec
     *                       e.g. [["type"=>"repository","name"=>"foo/bar","actions"=>["pull"]], ...]
     * @param int|null $ttlSeconds Override validity period (seconds)
     */
    public static function issue(?string $subject, array $access, ?int $ttlSeconds = null): array
    {
        $now = time();
        $ttl = $ttlSeconds ?? (int) config('registry.jwt.ttl', 300);

        $claims = [
            'sub'    => $subject ?? '', // empty string when anonymous
            'iss'    => (string) config('registry.jwt.issuer', 'registry_token_issuer'),
            'aud'    => (string) config('registry.service', 'container_registry'),
            'iat'    => $now,
            'nbf'    => $now - 1,
            'exp'    => $now + max(1, $ttl),
            'jti'    => Str::uuid()->toString(),
            'access' => $access,
        ];

        // Prepare JOSE headers
        $headers = static::buildJoseHeaders();

        // Load private key (resource) — supports passphrase-protected keys
        $keyPem = CertificateService::readFileOrFail(CertificateService::leafKeyPath());
        $pass   = CertificateService::leafPassphrase();
        $pkey   = CertificateService::openPrivateKey($keyPem, $pass);

        $alg    = CertificateService::alg();

        $jwt = JWT::encode($claims, $pkey, $alg, null, $headers);

        return [
            'token' => $jwt,
            'access_token' => $jwt,
            'expires_in' => $ttl,
            'issued_at' => gmdate('c', $now),
        ];
    }

    /** Build JOSE headers (alg implied by encode(); include kid + x5c if certs present) */
    private static  function buildJoseHeaders(): array
    {
        $headers = [];

        $leafCertPath = CertificateService::leafCertPath();
        $leafPem = CertificateService::readFileOrFail($leafCertPath);
        $headers['x5c'] = CertificateService::buildX5C($leafPem);

        return $headers;
    }
}
