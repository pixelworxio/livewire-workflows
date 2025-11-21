<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\Request;
use Pixelworxio\LivewireWorkflows\Contracts\GuardContract;

class FailingGuard implements GuardContract
{
    public function passes(Request $request): bool
    {
        return false; // Always fails - step must be shown
    }

    public function onEnter(Request $request): void {}

    public function onExit(Request $request): void {}

    public function onPass(Request $request): void {}

    public function onFail(Request $request): void {}
}
