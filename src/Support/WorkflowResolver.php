<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar;

/**
 * High-level workflow resolution and navigation helper.
 *
 * Provides public API methods for redirecting and retrieving workflow state.
 */
class WorkflowResolver
{
    public function __construct(
        protected WorkflowRegistrar $registrar,
        protected WorkflowEngine $engine,
    ) {
    }

    /**
     * Get a workflow definition.
     *
     * @param string $flow
     * @return WorkflowDefinition
     */
    public function get(string $flow): WorkflowDefinition
    {
        return $this->registrar->get($flow);
    }

    /**
     * Redirect to the appropriate step or finish route.
     *
     * @param string $flow
     * @param Request $request
     * @param string|null $doneRoute Optional override for finish route
     * @return RedirectResponse
     */
    public function redirect(string $flow, Request $request, ?string $doneRoute = null): RedirectResponse
    {
        $workflow = $this->registrar->get($flow);
        $nextStepKey = $this->engine->nextStep($workflow, $request);

        if ($nextStepKey === null) {
            // Workflow complete
            $this->engine->complete($workflow, $request);
            return Redirect::route($doneRoute ?? $workflow->finishRoute);
        }

        // Advance to next step
        $this->engine->advanceTo($workflow, $nextStepKey, $request);

        return Redirect::route($workflow->getStepRouteName($nextStepKey));
    }

    /**
     * Get the next route name for a user.
     *
     * @param string $flow
     * @param Request $request
     * @param string|null $doneRoute Optional override for finish route
     * @return string
     */
    public function nextRouteNameFor(string $flow, Request $request, ?string $doneRoute = null): string
    {
        $workflow = $this->registrar->get($flow);
        $nextStepKey = $this->engine->nextStep($workflow, $request);

        if ($nextStepKey === null) {
            return $doneRoute ?? $workflow->finishRoute;
        }

        return $workflow->getStepRouteName($nextStepKey);
    }

    /**
     * Get the previous route name relative to current step.
     *
     * @param string $flow
     * @param string $currentKey
     * @param Request $request
     * @return string|null
     */
    public function previousRouteNameFor(string $flow, string $currentKey, Request $request): ?string
    {
        $workflow = $this->registrar->get($flow);
        $previousStepKey = $this->engine->previousStep($workflow, $currentKey, $request);

        if ($previousStepKey === null) {
            return null;
        }

        return $workflow->getStepRouteName($previousStepKey);
    }

    /**
     * Get progress information for a workflow.
     *
     * @param string $flow
     * @param Request $request
     * @return array
     */
    public function progressFor(string $flow, Request $request): array
    {
        $workflow = $this->registrar->get($flow);

        return $this->engine->progress($workflow, $request);
    }
}
