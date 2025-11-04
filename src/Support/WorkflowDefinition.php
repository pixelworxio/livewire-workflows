<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Support;

use Pixelworxio\LivewireWorkflows\Exceptions\InvalidWorkflowConfigurationException;

/**
 * Immutable workflow definition DTO.
 *
 * Contains all configuration for a single workflow:
 * - Flow name and routing
 * - Steps collection
 * - History mode
 * - Entry and finish routes
 */
class WorkflowDefinition
{
    /**
     * @param  string  $flow  The workflow name/identifier
     * @param  string  $entryRouteName  The route name for workflow entry
     * @param  string  $entryPath  The base path for the workflow
     * @param  string  $finishRoute  The route name to redirect on completion
     * @param  string  $historyMode  History tracking mode: 'none' or 'stack'
     * @param  array<string, StepDefinition>  $steps  Associative array of step definitions keyed by step key
     */
    public function __construct(
        public readonly string $flow,
        public readonly string $entryRouteName,
        public readonly string $entryPath,
        public readonly string $finishRoute,
        public readonly string $historyMode,
        public readonly array $steps,
    ) {
        $this->validate();
    }

    /**
     * Get a step by its key.
     *
     * @param  string  $key  The step key
     */
    public function getStep(string $key): ?StepDefinition
    {
        return $this->steps[$key] ?? null;
    }

    /**
     * Get all steps ordered by their order value.
     *
     * @return array<StepDefinition>
     */
    public function getOrderedSteps(): array
    {
        $steps = array_values($this->steps);

        usort($steps, fn (StepDefinition $a, StepDefinition $b) => $a->order <=> $b->order);

        return $steps;
    }

    /**
     * Get the previous step relative to a given step key.
     *
     * @param  string  $currentKey  The current step key
     */
    public function getPreviousStep(string $currentKey): ?StepDefinition
    {
        $orderedSteps = $this->getOrderedSteps();
        $currentIndex = null;

        foreach ($orderedSteps as $index => $step) {
            if ($step->key === $currentKey) {
                $currentIndex = $index;
                break;
            }
        }

        if ($currentIndex === null || $currentIndex === 0) {
            return null;
        }

        return $orderedSteps[$currentIndex - 1];
    }

    /**
     * Get the route name for a step.
     *
     * @param  string  $stepKey  The step key
     * @return string Route name in format: {flow}.{stepKey}
     */
    public function getStepRouteName(string $stepKey): string
    {
        return "{$this->flow}.{$stepKey}";
    }

    /**
     * Get the URI path for a step.
     *
     * @param  string  $stepKey  The step key
     * @return string URI path in format: {entryPath}/{stepKey}
     */
    public function getStepPath(string $stepKey): string
    {
        return rtrim($this->entryPath, '/').'/'.ltrim($stepKey, '/');
    }

    /**
     * Check if history tracking is enabled.
     */
    public function hasHistory(): bool
    {
        return $this->historyMode === 'stack';
    }

    /**
     * Validate the workflow definition.
     *
     * @throws InvalidWorkflowConfigurationException
     */
    protected function validate(): void
    {
        if (empty($this->flow)) {
            throw new InvalidWorkflowConfigurationException('Workflow name cannot be empty.');
        }

        if (empty($this->entryRouteName)) {
            throw new InvalidWorkflowConfigurationException("Workflow '{$this->flow}' must have an entry route name.");
        }

        if (empty($this->entryPath)) {
            throw new InvalidWorkflowConfigurationException("Workflow '{$this->flow}' must have an entry path.");
        }

        if (empty($this->finishRoute)) {
            throw new InvalidWorkflowConfigurationException("Workflow '{$this->flow}' must have a finish route.");
        }

        if (! in_array($this->historyMode, ['none', 'stack'], true)) {
            throw new InvalidWorkflowConfigurationException("History mode must be 'none' or 'stack', got '{$this->historyMode}'.");
        }

        if (empty($this->steps)) {
            throw new InvalidWorkflowConfigurationException("Workflow '{$this->flow}' must have at least one step.");
        }

        // Validate unique step keys
        $keys = array_keys($this->steps);
        if (count($keys) !== count(array_unique($keys))) {
            throw new InvalidWorkflowConfigurationException("Workflow '{$this->flow}' has duplicate step keys.");
        }

        // Validate each step
        foreach ($this->steps as $step) {
            if ($step->flow !== $this->flow) {
                throw new InvalidWorkflowConfigurationException(
                    "Step '{$step->key}' flow mismatch: expected '{$this->flow}', got '{$step->flow}'."
                );
            }
        }
    }
}
