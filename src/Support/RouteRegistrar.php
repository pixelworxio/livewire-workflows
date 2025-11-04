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
        $middleware = $middleware ?? config('livewire-workflows.middleware', ['web']);

        foreach ($this->workflowRegistrar->all() as $workflow) {
            // Register entry route
            Route::middleware($middleware)
                ->get($workflow->entryPath, [WorkflowEntryController::class, '__invoke'])
                ->defaults('flow', $workflow->flow)
                ->name($workflow->entryRouteName);

            // Register step routes
            foreach ($workflow->steps as $step) {
                $routeName = $workflow->getStepRouteName($step->key);
                $routePath = $workflow->getStepPath($step->key);

                Route::middleware($middleware)
                    ->get($routePath, $step->component)
                    ->name($routeName);
            }
        }
    }
}
