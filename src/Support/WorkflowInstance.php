<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Scoped workflow instance for fluent API.
 *
 * Provides a fluent interface for workflow operations
 * specific to a single workflow flow.
 */
class WorkflowInstance
{
    public function __construct(
        protected string $flow,
        protected WorkflowResolver $resolver
    ) {}

    /**
     * Redirect to the appropriate step or finish route.
     */
    public function redirect(Request $request, ?string $doneRoute = null): RedirectResponse
    {
        return $this->resolver->redirect($this->flow, $request, $doneRoute);
    }

    /**
     * Get the next route name for the current request.
     */
    public function nextRouteNameFor(Request $request, ?string $doneRoute = null): string
    {
        return $this->resolver->nextRouteNameFor($this->flow, $request, $doneRoute);
    }

    /**
     * Get the previous route name relative to current step.
     */
    public function previousRouteNameFor(string $currentKey, Request $request): ?string
    {
        return $this->resolver->previousRouteNameFor($this->flow, $currentKey, $request);
    }

    /**
     * Get progress information for the workflow.
     */
    public function progressFor(Request $request): array
    {
        return $this->resolver->progressFor($this->flow, $request);
    }
}
