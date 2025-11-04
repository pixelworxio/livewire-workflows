<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Livewire\Concerns;

use Livewire\Features\SupportRedirects\Redirector;

/**
 * Livewire trait for workflow navigation.
 *
 * Provides convenient methods for Livewire components:
 * - continue(): Advance to next step
 * - back(): Return to previous step
 */
trait InteractsWithWorkflows
{
    /**
     * Continue to the next workflow step.
     *
     * Redirects to the workflow entry route which will evaluate guards
     * and redirect to the appropriate next step or finish route.
     *
     * @param  string  $flow  The workflow identifier
     */
    public function continue(string $flow): Redirector
    {
        $workflow = app(\Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->get($flow);

        return $this->redirect(route($workflow->entryRouteName), navigate: true);
    }

    /**
     * Go back to the previous workflow step.
     *
     * @param  string  $flow  The workflow identifier
     * @param  string  $currentKey  The current step key
     */
    public function back(string $flow, string $currentKey): ?Redirector
    {
        $resolver = app(\Pixelworxio\LivewireWorkflows\Support\WorkflowResolver::class);
        $request = request();

        $previousRoute = $resolver->previousRouteNameFor($flow, $currentKey, $request);

        if ($previousRoute === null) {
            return null;
        }

        return $this->redirect(route($previousRoute), navigate: true);
    }
}
