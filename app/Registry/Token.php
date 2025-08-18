<?php

namespace App\Registry;

use Firebase\JWT\JWT;
use Illuminate\Support\Str;
use RuntimeException;

final class Token
{
    public static function issue(string $sub, array $access, ?string $aud = null): array
    {
        $now = time();
        $ttl = (int)config('registry.ttl', 300);

        $payload = [
            'iss' => config('registry.issuer'),
            'sub' => $sub,                                      // "" for anonymous is OK
            'aud' => $aud ?? config('registry.service'),
            'nbf' => $now,
            'iat' => $now,
            'exp' => $now + $ttl,
            'jti' => (string)Str::uuid(),
            'access' => array_values($access),                     // [{type,name,actions:[...]}]
        ];

        // Load private key (RSA or EC), with optional passphrase
        $keyPath = config('registry.key.path', storage_path('registry_auth.key'));
        if (!is_readable($keyPath)) {
            throw new RuntimeException("Private key not readable: {$keyPath}");
        }
        $pem = file_get_contents($keyPath);
        $pass = config('registry.key.pass') ?: null;

        $pkey = openssl_pkey_get_private($pem, $pass);
        if (!$pkey) {
            throw new RuntimeException('Invalid private key: ' . implode(' | ', self::opensslErrors()));
        }

        // Choose alg based on key type
        $alg = self::algForKey($pkey); // RS256 for RSA, ES256 for EC

        // Include x5c header so the registry can validate the token signer
        $headers = [];
        $certPath = config('registry.key.cert', storage_path('registry_auth_ca.crt'));
        if (is_readable($certPath)) {
            $certPem = file_get_contents($certPath) ?: '';
            $der = trim(str_replace(
                ["-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----", "\r", "\n", " "],
                '',
                $certPem
            ));
            if ($der !== '') {
                $headers['x5c'] = [$der]; // leaf first; self-signed → single element
            }
        }

        $jwt = JWT::encode($payload, $pkey, $alg, null, $headers);

        return [
            'token' => $jwt,
            'expires_in' => $ttl,
            'issued_at' => gmdate('c', $now),
        ];
    }

    private static function algForKey($pkey): string
    {
        $d = openssl_pkey_get_details($pkey);
        if (!$d || !isset($d['type'])) {
            throw new RuntimeException('Unknown key type');
        }
        return match ($d['type']) {
            OPENSSL_KEYTYPE_RSA => 'RS256',
            OPENSSL_KEYTYPE_EC => 'ES256',
            default => throw new RuntimeException('Unsupported key type for JWT'),
        };
    }

    private static function opensslErrors(): array
    {
        $out = [];
        while ($e = openssl_error_string()) {
            $out[] = $e;
        }
        return $out;
    }
}
