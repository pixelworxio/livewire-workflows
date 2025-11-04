<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Contracts;

use Illuminate\Http\Request;

/**
 * Guard contract for workflow steps.
 *
 * Implements positive semantics: passes() returns true if the step can be skipped.
 * Use with unlessPasses() in the DSL: if passes() returns false, the step is shown.
 */
interface GuardContract
{
    /**
     * Determine if the guard passes.
     *
     * @param Request $request The current request
     * @return bool True if the step can be skipped, false if the step should be shown
     */
    public function passes(Request $request): bool;

    /**
     * Hook called when entering this step.
     *
     * @param Request $request The current request
     * @return void
     */
    public function onEnter(Request $request): void;

    /**
     * Hook called when exiting this step.
     *
     * @param Request $request The current request
     * @return void
     */
    public function onExit(Request $request): void;
}
