<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\Request;
use Pixelworxio\LivewireWorkflows\Contracts\GuardContract;

class TrackableGuard implements GuardContract
{
    public static bool $shouldPass = false;

    public static array $calls = [];

    public function passes(Request $request): bool
    {
        self::$calls[] = 'passes';

        return self::$shouldPass;
    }

    public function onEnter(Request $request): void
    {
        self::$calls[] = 'onEnter';
    }

    public function onExit(Request $request): void
    {
        self::$calls[] = 'onExit';
    }

    public function onPass(Request $request): void
    {
        self::$calls[] = 'onPass';
    }

    public function onFail(Request $request): void
    {
        self::$calls[] = 'onFail';
    }

    public static function reset(): void
    {
        self::$shouldPass = false;
        self::$calls = [];
    }
}
