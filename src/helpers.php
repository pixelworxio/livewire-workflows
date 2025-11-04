<?php

declare(strict_types=1);

use Pixelworxio\LivewireWorkflows\Support\WorkflowResolver;

if (! function_exists('workflow')) {
    /**
     * Get the workflow resolver for a specific flow.
     *
     * @param  string  $flow  The workflow name
     */
    function workflow(string $flow): WorkflowResolver
    {
        $resolver = app(WorkflowResolver::class);

        // Return a scoped instance that knows the flow
        return new class($flow, $resolver)
        {
            public function __construct(
                protected string $flow,
                protected WorkflowResolver $resolver
            ) {}

            public function redirect(\Illuminate\Http\Request $request, ?string $doneRoute = null): \Illuminate\Http\RedirectResponse
            {
                return $this->resolver->redirect($this->flow, $request, $doneRoute);
            }

            public function nextRouteNameFor(\Illuminate\Http\Request $request, ?string $doneRoute = null): string
            {
                return $this->resolver->nextRouteNameFor($this->flow, $request, $doneRoute);
            }

            public function previousRouteNameFor(string $currentKey, \Illuminate\Http\Request $request): ?string
            {
                return $this->resolver->previousRouteNameFor($this->flow, $currentKey, $request);
            }

            public function progressFor(\Illuminate\Http\Request $request): array
            {
                return $this->resolver->progressFor($this->flow, $request);
            }
        };
    }
}
