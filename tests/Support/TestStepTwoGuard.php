<?php

namespace Tests\Support;

use Illuminate\Http\Request;
use Pixelworxio\LivewireWorkflows\Contracts\GuardContract;

class TestStepTwoGuard implements GuardContract
{
    public static bool $shouldPass = false;

    public function passes(Request $request): bool
    {
        return self::$shouldPass;
    }

    public function onEnter(Request $request): void {}

    public function onExit(Request $request): void {}

    public function onPass(Request $request): void {}

    public function onFail(Request $request): void {}
}
