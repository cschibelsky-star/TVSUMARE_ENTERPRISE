<?php

declare(strict_types=1);

namespace TVSumare\Admin;

final class PostRedirect
{
    public static function back(string $path, string $status = 'ok'): void
    {
        $separator = str_contains($path, '?') ? '&' : '?';
        header('Location: ' . $path . $separator . 'status=' . rawurlencode($status), true, 303);
        exit;
    }
}
