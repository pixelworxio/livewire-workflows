<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
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
        protected WorkflowStateRepository $stateRepository,
    ) {}

    /**
     * Get a workflow definition.
     */
    public function get(string $flow): WorkflowDefinition
    {
        return $this->registrar->get($flow);
    }

    /**
     * Redirect to the appropriate step or finish route.
     *
     * @param  string|null  $doneRoute  Optional override for finish route
     */
    public function redirect(string $flow, Request $request, ?string $doneRoute = null): RedirectResponse
    {
        $workflow = $this->registrar->get($flow);
        $nextStepKey = $this->engine->nextStep($workflow, $request);
        $routeParameters = $this->extractRouteParameters($request, $workflow);

        // Store route parameters in workflow state for future navigation
        if (! empty($routeParameters)) {
            $this->storeRouteParameters($flow, $request, $workflow, $routeParameters);
        }

        if ($nextStepKey === null) {
            // Workflow complete
            $this->engine->complete($workflow, $request);

            return Redirect::route($doneRoute ?? $workflow->finishRoute, $routeParameters);
        }

        // Advance to next step
        $this->engine->advanceTo($workflow, $nextStepKey, $request);

        return Redirect::route($workflow->getStepRouteName($nextStepKey), $routeParameters);
    }

    /**
     * Get the next route name for a user.
     *
     * @param  string|null  $doneRoute  Optional override for finish route
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
     */
    public function progressFor(string $flow, Request $request): array
    {
        $workflow = $this->registrar->get($flow);

        return $this->engine->progress($workflow, $request);
    }

    /**
     * Extract route parameters from the current request.
     *
     * Filters the route parameters to only include those defined in the workflow.
     *
     * @return array<string, mixed>
     */
    protected function extractRouteParameters(Request $request, WorkflowDefinition $workflow): array
    {
        if (! $workflow->hasRouteParameters()) {
            return [];
        }

        // First, try to get stored parameters from workflow state
        $storedParameters = $this->getStoredRouteParameters($request, $workflow);
        if (! empty($storedParameters)) {
            return $storedParameters;
        }

        // Fall back to extracting from current route
        $currentRoute = $request->route();
        if (! $currentRoute) {
            return [];
        }

        $allParameters = $currentRoute->parameters();
        $workflowParameters = [];

        // Only include parameters that are defined in the workflow
        foreach ($workflow->routeParameters as $paramName) {
            if (array_key_exists($paramName, $allParameters)) {
                $workflowParameters[$paramName] = $allParameters[$paramName];
            }
        }

        return $workflowParameters;
    }

    /**
     * Store route parameters in workflow state.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function storeRouteParameters(string $flow, Request $request, WorkflowDefinition $workflow, array $parameters): void
    {
        $userKey = $this->getUserKey($request, $workflow);
        $this->stateRepository->setState($flow, $userKey, '_route_parameters', $parameters);
    }

    /**
     * Get stored route parameters from workflow state.
     *
     * @return array<string, mixed>
     */
    protected function getStoredRouteParameters(Request $request, WorkflowDefinition $workflow): array
    {
        $userKey = $this->getUserKey($request, $workflow);
        $parameters = $this->stateRepository->getState($workflow->flow, $userKey, '_route_parameters');

        return is_array($parameters) ? $parameters : [];
    }

    /**
     * Get the user key for state persistence.
     *
     * Returns the authenticated user ID, session ID, or a guest identifier.
     */
    protected function getUserKey(Request $request, WorkflowDefinition $workflow): string|int
    {
        if ($request->user()) {
            return $request->user()->getAuthIdentifier();
        }

        if ($request->hasSession()) {
            return $request->session()->getId();
        }

        return 'guest-'.md5($request->ip().($request->userAgent() ?? 'unknown'));
    }
}
