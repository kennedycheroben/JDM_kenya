<?php

declare(strict_types=1);

namespace Jdm\OAuth\Support;

use RuntimeException;

final readonly class OAuthConfig
{
    private function __construct(
        public string $issuer,
        public string $privateKeyPath,
        public string $publicKeyPath,
        public string $encryptionKey,
        public string $auditSalt,
        public int $authorizationCodeTtl,
        public int $accessTokenTtl,
    ) {}

    public static function fromEnvironment(): self
    {
        $enabled = filter_var(self::env('JDM_OAUTH_ENABLED', 'false'), FILTER_VALIDATE_BOOL);
        if (! $enabled) {
            throw new RuntimeException('JDM sign-in is temporarily unavailable.');
        }

        $issuer = rtrim((string) self::env('JDM_OAUTH_ISSUER'), '/');
        $privateKey = (string) self::env('JDM_OAUTH_PRIVATE_KEY_PATH');
        $publicKey = (string) self::env('JDM_OAUTH_PUBLIC_KEY_PATH');
        $encryptionKey = (string) self::env('JDM_OAUTH_ENCRYPTION_KEY');
        $auditSalt = (string) self::env('JDM_OAUTH_AUDIT_SALT');

        if ($issuer === '' || ! self::issuerIsAllowed($issuer)) {
            throw new RuntimeException('JDM sign-in configuration is incomplete.');
        }

        foreach ([$privateKey, $publicKey] as $keyPath) {
            error_log("OAuthConfig check: keyPath=$keyPath, is_file=".(is_file($keyPath) ? 'yes' : 'no').", is_readable=".(is_readable($keyPath) ? 'yes' : 'no'));
            if ($keyPath === '' || ! is_file($keyPath) || ! is_readable($keyPath)) {
                throw new RuntimeException('JDM sign-in configuration is incomplete.');
            }
        }

        if (strlen($encryptionKey) < 32 || strlen($auditSalt) < 32) {
            throw new RuntimeException('JDM sign-in configuration is incomplete.');
        }

        if (! extension_loaded('sodium') && ! filter_var(self::env('JDM_OAUTH_ALLOW_INSECURE_LOCAL', 'false'), FILTER_VALIDATE_BOOL)) {
            throw new RuntimeException('JDM sign-in requires the native sodium extension.');
        }

        $projectRoot = realpath(dirname(__DIR__, 4));
        foreach ([$privateKey, $publicKey] as $keyPath) {
            $resolved = realpath($keyPath);
            if ($projectRoot && $resolved && str_starts_with($resolved, $projectRoot.DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('JDM sign-in keys must be outside the public project root.');
            }
        }

        return new self($issuer, $privateKey, $publicKey, $encryptionKey, $auditSalt, 90, 300);
    }

    private static function env(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }
        return $_SERVER[$key] ?? $default;
    }

    private static function issuerIsAllowed(string $issuer): bool
    {
        $parts = parse_url($issuer);
        if (! is_array($parts)
            || empty($parts['scheme'])
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            return false;
        }

        if ($parts['scheme'] === 'https') {
            return true;
        }

        $allowLocal = filter_var(self::env('JDM_OAUTH_ALLOW_INSECURE_LOCAL', 'false'), FILTER_VALIDATE_BOOL);

        return $allowLocal && $parts['scheme'] === 'http' && in_array(strtolower($parts['host']), ['127.0.0.1', 'localhost'], true);
    }
}
