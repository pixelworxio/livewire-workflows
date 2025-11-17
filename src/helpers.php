<?php

declare(strict_types=1);

use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\Support\WorkflowInstance;
use Pixelworxio\LivewireWorkflows\Support\WorkflowResolver;
use Pixelworxio\LivewireWorkflows\Support\WorkflowStateManager;

if (! function_exists('workflow')) {
    /**
     * Get a workflow instance for a specific flow.
     *
     * @param  string  $flow  The workflow name
     */
    function workflow(string $flow): WorkflowInstance
    {
        $resolver = app(WorkflowResolver::class);

        return new WorkflowInstance($flow, $resolver);
    }
}

if (! function_exists('workflowState')) {
    /**
     * Get a workflow state manager for managing state in controllers.
     *
     * Provides a fluent API for controllers to interact with workflow state
     * that is compatible with Livewire components using #[WorkflowState] attributes.
     *
     * @param  string  $workflowName  The workflow name
     *
     * @example
     * // In a controller
     * workflowState('onboarding')
     *     ->forRequest($request)
     *     ->set('name', 'John Doe')
     *     ->set('email', 'john@example.com');
     *
     * // Get state
     * $name = workflowState('onboarding')->forRequest($request)->get('name');
     */
    function workflowState(string $workflowName): WorkflowStateManager
    {
        $repository = app(WorkflowStateRepository::class);

        return new WorkflowStateManager($workflowName, $repository);
    }
}
