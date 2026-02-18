<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Support;

use Illuminate\Support\Facades\Route;
use Pixelworxio\LivewireWorkflows\Http\Controllers\WorkflowEntryController;
use Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar;

/**
 * Handles registration of workflow routes.
 */
class RouteRegistrar
{
    public function __construct(
        protected WorkflowRegistrar $workflowRegistrar,
    ) {}

    /**
     * Register all workflow routes.
     */
    public function register(?array $middleware = null): void
    {
        $globalMiddleware = $middleware ?? config('livewire-workflows.middleware', ['web']);
        $precedenceMode = config('livewire-workflows.middleware_precedence', 'override');

        foreach ($this->workflowRegistrar->all() as $workflow) {
            // Resolve entry route middleware: workflow > global
            $entryMiddleware = $this->resolveMiddleware(
                $workflow->middleware,
                null, // No step-level for entry route
                $globalMiddleware,
                $precedenceMode
            );

            // Register entry route (preserves route parameters from entryPath)
            Route::middleware($entryMiddleware)
                ->get($workflow->entryPath, [WorkflowEntryController::class, '__invoke'])
                ->defaults('flow', $workflow->flow)
                ->name($workflow->entryRouteName);

            // Register step routes (inherits parameters from entry path)
            foreach ($workflow->steps as $step) {
                $routeName = $workflow->getStepRouteName($step->key);
                $routePath = $workflow->getStepPath($step->key);

                // Resolve step middleware: step > workflow > global
                $stepMiddleware = $this->resolveMiddleware(
                    $workflow->middleware,
                    $step->middleware,
                    $globalMiddleware,
                    $precedenceMode
                );

                Route::middleware($stepMiddleware)
                    ->get($routePath, $step->component)
                    ->name($routeName);
            }
        }
    }

    /**
     * Resolve middleware based on precedence mode.
     *
     * @param  array|\Closure|null  $workflowMiddleware  Workflow-level middleware
     * @param  array|\Closure|null  $stepMiddleware  Step-level middleware
     * @param  array  $globalMiddleware  Global default middleware
     * @param  string  $precedenceMode  'override' or 'merge'
     * @return array Resolved middleware array
     */
    protected function resolveMiddleware(
        array|\Closure|null $workflowMiddleware,
        array|\Closure|null $stepMiddleware,
        array $globalMiddleware,
        string $precedenceMode
    ): array {
        // Evaluate callables to arrays
        $workflowMiddleware = $this->evaluateMiddleware($workflowMiddleware);
        $stepMiddleware = $this->evaluateMiddleware($stepMiddleware);

        // Override mode: Step completely replaces workflow/global
        if ($precedenceMode === 'override') {
            return $stepMiddleware
                ?? $workflowMiddleware
                ?? $globalMiddleware;
        }

        // Merge mode: Combine step + workflow + global (deduplicate)
        if ($precedenceMode === 'merge') {
            $merged = array_merge(
                $globalMiddleware,
                $workflowMiddleware ?? [],
                $stepMiddleware ?? []
            );

            // Remove duplicates while preserving order
            return array_values(array_unique($merged));
        }

        // Fallback to global if invalid mode
        return $globalMiddleware;
    }

    /**
     * Evaluate middleware if it's a closure.
     */
    protected function evaluateMiddleware(array|\Closure|null $middleware): ?array
    {
        if ($middleware instanceof \Closure) {
            $result = $middleware(request());

            return is_array($result) ? $result : null;
        }

        return $middleware;
    }
}
