<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Support;

use Pixelworxio\LivewireWorkflows\Exceptions\InvalidWorkflowConfigurationException;

/**
 * Immutable step definition DTO.
 *
 * Represents a single step within a workflow:
 * - Step identification (flow, key, order)
 * - Livewire component binding
 * - Guard class
 */
class StepDefinition
{
    /**
     * @param  string  $flow  The parent workflow name
     * @param  string  $key  The step identifier/key
     * @param  string  $component  Full class name of the Livewire component
     * @param  string|null  $guardClass  Full class name of the guard (optional)
     * @param  int  $order  Sort order for this step
     */
    public function __construct(
        public readonly string $flow,
        public readonly string $key,
        public readonly string $component,
        public readonly ?string $guardClass,
        public readonly int $order,
    ) {
        $this->validate();
    }

    /**
     * Check if this step has a guard.
     */
    public function hasGuard(): bool
    {
        return $this->guardClass !== null;
    }

    /**
     * Validate the step definition.
     *
     * @throws InvalidWorkflowConfigurationException
     */
    protected function validate(): void
    {
        if (empty($this->flow)) {
            throw new InvalidWorkflowConfigurationException('Step flow name cannot be empty.');
        }

        if (empty($this->key)) {
            throw new InvalidWorkflowConfigurationException("Step in flow '{$this->flow}' must have a key.");
        }

        if (empty($this->component)) {
            throw new InvalidWorkflowConfigurationException("Step '{$this->key}' in flow '{$this->flow}' must have a component.");
        }

        if (! class_exists($this->component)) {
            throw new InvalidWorkflowConfigurationException(
                "Step '{$this->key}' component class '{$this->component}' does not exist."
            );
        }

        if ($this->guardClass !== null && ! class_exists($this->guardClass)) {
            throw new InvalidWorkflowConfigurationException(
                "Step '{$this->key}' guard class '{$this->guardClass}' does not exist."
            );
        }
    }
}
