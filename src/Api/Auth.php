<?php

declare(strict_types=1);

namespace TVSumare\Api;

final class Auth
{
    public static function requireToken(): void
    {
        $expected = (string)(getenv('TVSUMARE_API_TOKEN') ?: '');
        if ($expected === '') {
            return;
        }

        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($header, 'Bearer ')) {
            JsonResponse::send(['ok' => false, 'error' => 'Token ausente.'], 401);
        }

        $token = trim(substr($header, 7));
        if (!hash_equals($expected, $token)) {
            JsonResponse::send(['ok' => false, 'error' => 'Token inválido.'], 403);
        }
    }
}
