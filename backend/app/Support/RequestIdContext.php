<?php

namespace App\Support;

final class RequestIdContext
{
    private static ?string $requestId = null;

    public static function set(string $requestId): void
    {
        self::$requestId = $requestId;
    }

    public static function get(): ?string
    {
        return self::$requestId;
    }

    public static function reset(): void
    {
        self::$requestId = null;
    }
}
