<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar;

/**
 * Handles signed resume URL requests for workflow flows.
 *
 * Signature validation is handled by the 'signed' route middleware.
 * This controller looks up the user's current step and redirects accordingly.
 */
class WorkflowResumeController extends Controller
{
    public function __construct(
        protected WorkflowStateRepository $stateRepository,
        protected WorkflowRegistrar $registrar,
    ) {}

    /**
     * Handle a workflow resume request.
     *
     * Redirects to the user's current step, or to the workflow entry route
     * if no step has been recorded yet.
     */
    public function __invoke(Request $request, string $flow, string $userKey): RedirectResponse
    {
        $workflow = $this->registrar->get($flow);
        $currentStep = $this->stateRepository->getCurrentStep($flow, $userKey);
        $routeParameters = $this->stateRepository->getState($flow, $userKey, '_route_parameters', []);
        $routeParameters = is_array($routeParameters) ? $routeParameters : [];

        if ($currentStep && $workflow->getStep($currentStep)) {
            return redirect()->route($workflow->getStepRouteName($currentStep), $routeParameters);
        }

        return redirect()->route($workflow->entryRouteName, $routeParameters);
    }
}
