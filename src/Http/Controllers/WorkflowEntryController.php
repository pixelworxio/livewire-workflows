<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Pixelworxio\LivewireWorkflows\Support\WorkflowResolver;

/**
 * Entry controller for workflow routes.
 *
 * Handles GET requests to workflow entry points, evaluates the pipeline,
 * and redirects to the first unmet step or finish route.
 */
class WorkflowEntryController extends Controller
{
    public function __construct(
        protected WorkflowResolver $resolver,
    ) {}

    /**
     * Handle workflow entry.
     *
     * Determines the workflow from the current route name and redirects
     * to the appropriate step or completion destination.
     *
     * @param  string  $flow  The workflow identifier
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $flow = $request->route('flow');

        return $this->resolver->redirect($flow, $request);
    }
}
