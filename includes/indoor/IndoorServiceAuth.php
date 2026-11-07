<?php
declare(strict_types=1);

/**
 * Transport authentication primitive only: not an HTTP endpoint.
 * The caller provides a secret bound to a single tenant/service and an atomic
 * nonce-claim callback backed by shared storage (e.g. DB unique constraint).
 */
final class IndoorServiceAuth
{
    private const CLOCK_SKEW_SECONDS = 120;

    public static function signature(
        string $secret,
        string $method,
        string $path,
        string $body,
        int $timestamp,
        string $nonce
    ): string {
        self::validateInput($secret, $method, $path, $nonce);
        return hash_hmac('sha256', self::canonical($method, $path, $body, $timestamp, $nonce), $secret);
    }

    /**
     * Returns false on any invalid request. $claimNonce must atomically reject
     * replays across workers and instances and expire claims after >= 240 s.
     * Credentials and tenant ID MUST be established by trusted caller, not body.
     */
    public static function verify(
        string $secret,
        string $method,
        string $path,
        string $body,
        int $timestamp,
        string $nonce,
        string $suppliedSignature,
        callable $claimNonce,
        ?int $now = null
    ): bool {
        $now = $now ?? time();
        if (abs($now - $timestamp) > self::CLOCK_SKEW_SECONDS) return false;
        if (!preg_match('/^[a-f0-9]{64}$/D', $suppliedSignature)) return false;
        try {
            $expected = self::signature($secret, $method, $path, $body, $timestamp, $nonce);
        } catch (InvalidArgumentException $e) {
            return false;
        }
        if (!hash_equals($expected, $suppliedSignature)) return false;
        // Claim only after validating signature; avoid filling nonce DB with bad requests.
        return $claimNonce(hash('sha256', $nonce), $timestamp) === true;
    }

    private static function canonical(string $method, string $path, string $body, int $timestamp, string $nonce): string
    {
        return implode("\n", [$method, $path, hash('sha256', $body), (string) $timestamp, $nonce]);
    }

    private static function validateInput(string $secret, string $method, string $path, string $nonce): void
    {
        if (strlen($secret) < 32) throw new InvalidArgumentException('Secret too short');
        if (!preg_match('/^(GET|POST)$/D', $method)) throw new InvalidArgumentException('Method');
        // Only path+query canonicalization accepted; never full URL or fragment.
        if (!preg_match('~^/api/indoor/v1/[A-Za-z0-9/_?=&%.-]{1,400}$~D', $path)
            || str_contains($path, '..') || str_contains($path, '//')) {
            throw new InvalidArgumentException('Path');
        }
        if (!preg_match('/^[a-f0-9]{32,64}$/D', $nonce)) throw new InvalidArgumentException('Nonce');
    }
}
