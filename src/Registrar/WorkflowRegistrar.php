<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Registrar;

use Pixelworxio\LivewireWorkflows\Exceptions\WorkflowNotFoundException;
use Pixelworxio\LivewireWorkflows\Support\WorkflowDefinition;

/**
 * Central registry for all workflows.
 *
 * Maintains the collection of workflow definitions and provides
 * access to them throughout the application.
 */
class WorkflowRegistrar
{
    /**
     * @var array<string, WorkflowDefinition>
     */
    protected array $workflows = [];

    /**
     * Start defining a new workflow.
     *
     * @param string $flow The workflow name/identifier
     * @return FlowBuilder
     */
    public function flow(string $flow): FlowBuilder
    {
        return new FlowBuilder($flow, $this);
    }

    /**
     * Register a completed workflow definition.
     *
     * @param WorkflowDefinition $definition
     * @return void
     */
    public function register(WorkflowDefinition $definition): void
    {
        $this->workflows[$definition->flow] = $definition;
    }

    /**
     * Get a workflow definition by name.
     *
     * @param string $flow The workflow name
     * @return WorkflowDefinition
     * @throws WorkflowNotFoundException
     */
    public function get(string $flow): WorkflowDefinition
    {
        if (!isset($this->workflows[$flow])) {
            throw WorkflowNotFoundException::forFlow($flow);
        }

        return $this->workflows[$flow];
    }

    /**
     * Check if a workflow exists.
     *
     * @param string $flow The workflow name
     * @return bool
     */
    public function has(string $flow): bool
    {
        return isset($this->workflows[$flow]);
    }

    /**
     * Get all registered workflows.
     *
     * @return array<string, WorkflowDefinition>
     */
    public function all(): array
    {
        return $this->workflows;
    }

    /**
     * Clear all registered workflows (useful for testing).
     *
     * @return void
     */
    public function clear(): void
    {
        $this->workflows = [];
    }
}
