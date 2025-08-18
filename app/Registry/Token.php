<?php

namespace App\Registry;

use Firebase\JWT\JWT;

class Token
{
    public static function issue(string $sub, array $access)
    {
        $now = time();
        $payload = [
            'iss' => config('registry.issuer'),
            'sub' => $sub,
            'aud' => config('registry.service'),
            'nbf' => $now,
            'iat' => $now,
            'exp' => $now + config('registry.ttl'),
            'access' => $access,
        ];

        $key = file_get_contents(storage_path('registry_auth.key'));
        $pass = config('registry.key.pass') ?: null;

        $token = JWT::encode(
            $payload,
            $pass ? openssl_get_privatekey($key, $pass) : $key,
            'RS256',
        );

        return [
            'token' => $token,
            'expires_in' => config('registry.ttl'),
            'issued_at' => gmdate('c', $now),
        ];
    }
}
