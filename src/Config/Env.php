<?php

declare(strict_types=1);

namespace App\Config;

final class Env
{
    /** @var array<string, string> */
    private static array $vars = [];

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            throw new \RuntimeException(".env não encontrado: {$path}");
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            self::$vars[trim($key)] = trim($value);
        }
    }

    public static function get(string $key, ?string $default = null): string
    {
        $value = self::$vars[$key] ?? $default;
        if ($value === null) {
            throw new \RuntimeException("Variável de ambiente ausente: {$key}");
        }
        return $value;
    }
}
