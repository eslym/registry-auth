<?php

namespace App\Lib\Crypto;

use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * CertificateService — config-aware implementation for Registry Auth
 *
 * Reads all paths/algorithms from config('registry') as provided by
 * config/registry.php in the user message.
 *
 * Notable behaviors:
 * - Supports RSA (RS256/384/512) and EC (ES256/384/512) keypairs
 * - Uses passphrases from either direct config value or a secret file path
 *   (when ...pass_secret is truthy and points to a readable file)
 * - Writes keys/certs atomically and creates parent directories as needed
 * - Emits x5c arrays and computes kid from leaf certificate
 */
class CertificateService
{
    /** Create the parent directory for a given path if necessary */
    public static function ensureParentDir(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException("Failed to create directory: $dir");
        }
    }

    /** Write a file atomically with chmod */
    public static function writeAtomic(string $path, string $contents, int $mode = 0600): void
    {
        self::ensureParentDir($path);
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(6));
        if (file_put_contents($tmp, $contents) === false) {
            throw new RuntimeException("Failed to write temp file: $tmp");
        }
        @chmod($tmp, $mode);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException("Failed to move $tmp to $path");
        }
    }

    /** Resolve passphrase: prefer secret file if provided and readable */
    public static function resolvePassphrase(?string $pass, $passSecret): ?string
    {
        $secretPath = self::normalizeSecretPath($passSecret);
        if ($secretPath && is_file($secretPath)) {
            $val = file_get_contents($secretPath);
            if ($val === false) {
                throw new RuntimeException("Unable to read passphrase secret file: $secretPath");
            }
            return trim($val); // remove trailing newlines
        }
        // fall back to inline pass if present
        return $pass !== null && $pass !== '' ? $pass : null;
    }

    /** pass_secret can be boolean false or a string path; treat empty/"false" as disabled */
    private static function normalizeSecretPath($passSecret): ?string
    {
        if ($passSecret === false || $passSecret === null) return null;
        if (is_string($passSecret)) {
            $s = trim($passSecret);
            if ($s === '' || strtolower($s) === 'false' || strtolower($s) === '0') return null;
            return $s;
        }
        // Any other truthy non-string (unlikely) => disabled
        return null;
    }

    // =============================
    // Config accessors
    // =============================

    public static function alg(): string
    {
        return strtoupper((string)config('registry.jwt.algorithm', 'RS256'));
    }

    public static function keyType(): string
    {
        // Derive from algorithm if not explicitly set coherently
        $cfg = strtoupper((string)config('registry.jwt.key.type', 'RSA'));
        $alg = self::alg();
        if (str_starts_with($alg, 'RS')) return 'RSA';
        if (str_starts_with($alg, 'ES')) return 'EC';
        return $cfg;
    }

    public static function rsaBits(): int
    {
        return (int)config('registry.jwt.key.size', 4096);
    }

    public static function ecCurve(): string
    {
        // Prefer explicit config; otherwise map from ES* algorithm
        $curve = (string)config('registry.jwt.key.curve', '');
        if ($curve !== '') return $curve;
        return match (self::alg()) {
            'ES384' => 'secp384r1',
            'ES512' => 'secp521r1',
            default => 'prime256v1',
        };
    }

    public static function digestAlg(): string
    {
        return match (self::alg()) {
            'RS384', 'ES384' => 'sha384',
            'RS512', 'ES512' => 'sha512',
            default => 'sha256',
        };
    }

    public static function leafKeyPath(): string
    {
        return (string)config('registry.jwt.key.path');
    }

    public static function leafCertPath(): string
    {
        return (string)config('registry.jwt.key.cert');
    }

    public static function leafCertTtlDays(): int
    {
        return (int)config('registry.jwt.key.ttl', 365);
    }

    public static function leafPassphrase(): ?string
    {
        return self::resolvePassphrase(
            config('registry.jwt.key.pass'),
            config('registry.jwt.key.pass_secret')
        );
    }

    public static function caEnabled(): bool
    {
        return (bool)config('registry.jwt.stable_ca.enabled', false);
    }

    public static function caKeyPath(): string
    {
        return (string)config('registry.jwt.stable_ca.key');
    }

    public static function caCertPath(): string
    {
        return (string)config('registry.jwt.stable_ca.cert');
    }

    public static function caCertTtlDays(): int
    {
        return (int)config('registry.jwt.stable_ca.ttl', 3650);
    }

    public static function caPassphrase(): ?string
    {
        return self::resolvePassphrase(
            config('registry.jwt.stable_ca.pass'),
            config('registry.jwt.stable_ca.pass_secret')
        );
    }

    // =============================
    // Key generation & CSR
    // =============================

    /** Generate a private key based on configured algorithm */
    public static function generatePrivateKey(): array
    {
        $type = self::keyType();
        if ($type === 'EC') {
            $args = [
                'private_key_type' => OPENSSL_KEYTYPE_EC,
                'curve_name'       => self::ecCurve(),
            ];
        } else { // RSA default
            $args = [
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
                'private_key_bits' => self::rsaBits(),
            ];
        }
        $key = openssl_pkey_new($args);
        if (!$key) throw new RuntimeException('openssl_pkey_new failed: ' . openssl_error_string());
        openssl_pkey_export($key, $pem, self::leafPassphrase());
        return [$key, $pem];
    }

    public static function generateCAPrivateKey(): array
    {
        $type = self::keyType();
        if ($type === 'EC') {
            $args = [
                'private_key_type' => OPENSSL_KEYTYPE_EC,
                'curve_name'       => self::ecCurve(),
            ];
        } else { // RSA default
            $args = [
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
                'private_key_bits' => self::rsaBits(),
            ];
        }
        $key = openssl_pkey_new($args);
        if (!$key) throw new RuntimeException('openssl_pkey_new (CA) failed: ' . openssl_error_string());
        openssl_pkey_export($key, $pem, self::caPassphrase());
        return [$key, $pem];
    }

    /**
     * Create a CSR from a private key.
     * @param mixed $privateKey openssl key resource/object
     * @param array $dn         e.g. ['CN' => 'registry-auth']
     */
    public static function createCsr($privateKey, array $dn = ['CN' => 'registry-auth'])
    {
        $csr = openssl_csr_new($dn, $privateKey, ['digest_alg' => self::digestAlg()]);
        if (!$csr) throw new RuntimeException('openssl_csr_new failed: ' . openssl_error_string());
        return $csr;
    }

    /** Self-sign a CA certificate */
    public static function selfSignCA($csr, $privateKey, ?int $days = null): string
    {
        $days = $days ?: self::caCertTtlDays();
        [$cnfPath, $section] = self::makeTempOpensslConfig(true);
        $x509 = openssl_csr_sign(
            $csr,
            null,
            $privateKey,
            $days,
            [
                'digest_alg' => self::digestAlg(),
                'config' => $cnfPath,
                'x509_extensions' => $section,
            ]
        );
        @unlink($cnfPath);
        if (!$x509) throw new RuntimeException('openssl_csr_sign (CA) failed: ' . openssl_error_string());
        openssl_x509_export($x509, $crtPem);
        return $crtPem;
    }

    /** Sign a leaf CSR with the configured CA */
    public static function signLeaf($leafCsr, ?int $days = null): string
    {
        if (!self::caEnabled()) {
            throw new RuntimeException('Stable CA is disabled in config (registry.jwt.stable_ca.enabled=false).');
        }
        $days = $days ?: self::leafCertTtlDays();

        $caCrtPem = self::readFileOrFail(self::caCertPath());
        $caKeyPem = self::readFileOrFail(self::caKeyPath());
        $caKey = self::openPrivateKey($caKeyPem, self::caPassphrase());

        $caX509 = openssl_x509_read($caCrtPem);
        if (!$caX509) throw new RuntimeException('Invalid CA certificate.');

        [$cnfPath, $section] = self::makeTempOpensslConfig(false);
        $x509 = openssl_csr_sign(
            $leafCsr,
            $caX509,
            $caKey,
            $days,
            [
                'digest_alg' => self::digestAlg(),
                'config' => $cnfPath,
                'x509_extensions' => $section,
            ]
        );
        @unlink($cnfPath);
        if (!$x509) throw new RuntimeException('openssl_csr_sign (leaf) failed: ' . openssl_error_string());
        openssl_x509_export($x509, $crtPem);
        return $crtPem;
    }

    /** Self-sign a LEAF certificate (CA:FALSE extensions). Useful when stable CA is disabled. */
    public static function selfSignLeaf($leafCsr, $leafPrivateKey, ?int $days = null): string
    {
        $days = $days ?: self::leafCertTtlDays();
        [$cnfPath, $section] = self::makeTempOpensslConfig(false); // v3_leaf
        $x509 = openssl_csr_sign(
            $leafCsr,
            null,                 // self-signed
            $leafPrivateKey,
            $days,
            [
                'digest_alg' => self::digestAlg(),
                'config' => $cnfPath,
                'x509_extensions' => $section,
            ]
        );
        @unlink($cnfPath);
        if (!$x509) {
            throw new RuntimeException('openssl_csr_sign (self-signed leaf) failed: ' . openssl_error_string());
        }
        openssl_x509_export($x509, $crtPem);
        return $crtPem;
    }

    /** Sign with CA if enabled; otherwise self-sign the leaf certificate. */
    public static function signLeafWithFallback($leafCsr, $leafPrivateKey, ?int $days = null): string
    {
        if (self::caEnabled()) {
            return self::signLeaf($leafCsr, $days);
        }
        return self::selfSignLeaf($leafCsr, $leafPrivateKey, $days);
    }

    /** Open a private key from PEM with optional passphrase */
    public static function openPrivateKey(string $pem, ?string $passphrase = null): OpenSSLAsymmetricKey
    {
        $pk = openssl_pkey_get_private($pem, $passphrase ?? '');
        if (!$pk) throw new RuntimeException('Unable to open private key: ' . openssl_error_string());
        return $pk;
    }

    /** Utility: read file and throw a helpful error if missing */
    public static function readFileOrFail(string $path): string
    {
        if (!is_file($path)) throw new RuntimeException("File not found: $path");
        $data = file_get_contents($path);
        if ($data === false) throw new RuntimeException("Unable to read file: $path");
        return $data;
    }

    // =============================
    // x5c / kid helpers
    // =============================

    /** Convert PEM cert → DER (binary) */
    public static function pemToDer(string $pem): string
    {
        $b64 = preg_replace('/-----(BEGIN|END) CERTIFICATE-----|\s+/', '', $pem);
        return base64_decode($b64);
    }

    /** x5c array: [leafDERb64, caDERb64] (if CA enabled) */
    public static function buildX5C(string $leafPem): array
    {
        $x5c = [ base64_encode(self::pemToDer($leafPem)) ];
        if (self::caEnabled()) {
            $caPem = self::readFileOrFail(self::caCertPath());
            $x5c[] = base64_encode(self::pemToDer($caPem));
        }
        return $x5c;
    }

    /** Compute RFC 7638-like kid from leaf certificate public key */
    public static function computeKidFromCert(string $leafPem): string
    {
        $pub = openssl_pkey_get_public($leafPem);
        $details = openssl_pkey_get_details($pub);
        if (!$details) throw new RuntimeException('Unable to read public key details.');
        if ($details['type'] === OPENSSL_KEYTYPE_RSA) {
            $jwk = [
                'kty' => 'RSA',
                'n'   => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
                'e'   => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
            ];
        } elseif ($details['type'] === OPENSSL_KEYTYPE_EC) {
            // Extract uncompressed point from ASN.1 EC public key (last 65 bytes)
            $raw = substr($details['ec']['pub_key'], -65);
            $x = substr($raw, 1, 32);
            $y = substr($raw, 33, 32);
            $jwk = [
                'kty' => 'EC',
                'crv' => self::mapCurveToCrv(self::ecCurve()),
                'x'   => rtrim(strtr(base64_encode($x), '+/', '-_'), '='),
                'y'   => rtrim(strtr(base64_encode($y), '+/', '-_'), '='),
            ];
        } else {
            throw new RuntimeException('Unsupported key type for kid computation.');
        }
        $json = json_encode($jwk, JSON_UNESCAPED_SLASHES);
        return rtrim(strtr(base64_encode(hash('sha256', $json, true)), '+/', '-_'), '=');
    }

    private static function mapCurveToCrv(string $curve): string
    {
        return match (strtolower($curve)) {
            'secp384r1'               => 'P-384',
            'secp521r1'               => 'P-521',
            default                   => 'P-256',
        };
    }

    // =============================
    // OpenSSL config helpers
    // =============================

    private static function makeTempOpensslConfig(bool $forCA): array
    {
        $section = $forCA ? 'v3_ca' : 'v3_leaf';
        $cnf = <<<CONF
[ req ]
distinguished_name = dn
[ dn ]

[ v3_ca ]
basicConstraints = critical,CA:TRUE,pathlen:1
keyUsage = critical,keyCertSign,cRLSign
subjectKeyIdentifier = hash
authorityKeyIdentifier = keyid:always,issuer

[ v3_leaf ]
basicConstraints = CA:FALSE
keyUsage = digitalSignature
extendedKeyUsage = codeSigning
subjectKeyIdentifier = hash
authorityKeyIdentifier = keyid,issuer
CONF;
        $tmp = sys_get_temp_dir() . '/openssl_' . bin2hex(random_bytes(6)) . '.cnf';
        file_put_contents($tmp, $cnf);
        return [$tmp, $section];
    }
}
