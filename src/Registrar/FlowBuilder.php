<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Registrar;

use Pixelworxio\LivewireWorkflows\Support\StepDefinition;
use Pixelworxio\LivewireWorkflows\Support\WorkflowDefinition;

/**
 * Fluent builder for defining a workflow.
 *
 * Provides the readable DSL for configuring workflows:
 * - entersAt() for entry routing
 * - finishesAt() for completion destination
 * - historyMode() for state tracking
 * - step() for adding workflow steps
 */
class FlowBuilder
{
    protected string $entryRouteName = '';

    protected string $entryPath = '';

    protected string $finishRoute = '';

    protected string $historyMode = 'none';

    /**
     * @var array<string, StepDefinition>
     */
    protected array $steps = [];

    protected ?StepBuilder $currentStepBuilder = null;

    public function __construct(
        protected string $flow,
        protected WorkflowRegistrar $registrar,
    ) {}

    /**
     * Define the workflow entry route.
     *
     * @param  string  $name  The route name (e.g., 'onboarding.start')
     * @param  string  $path  The base URI path (e.g., '/onboarding')
     */
    public function entersAt(string $name, string $path): static
    {
        $this->finalizeCurrentStep();

        $this->entryRouteName = $name;
        $this->entryPath = $path;

        return $this;
    }

    /**
     * Define where to redirect on workflow completion.
     *
     * @param  string  $route  The route name to redirect to
     */
    public function finishesAt(string $route): static
    {
        $this->finalizeCurrentStep();

        $this->finishRoute = $route;

        return $this;
    }

    /**
     * Set the history tracking mode.
     *
     * @param  string  $mode  Either 'none' (default) or 'stack'
     */
    public function historyMode(string $mode): static
    {
        $this->finalizeCurrentStep();

        $this->historyMode = $mode;

        return $this;
    }

    /**
     * Begin defining a new step.
     *
     * @param  string  $key  The step identifier
     */
    public function step(string $key): StepBuilder
    {
        $this->finalizeCurrentStep();

        $this->currentStepBuilder = new StepBuilder($this->flow, $key, $this);

        return $this->currentStepBuilder;
    }

    /**
     * Add a completed step to this workflow.
     *
     * @internal
     */
    public function addStep(StepDefinition $step): void
    {
        $this->steps[$step->key] = $step;
    }

    /**
     * Finalize the current step being built.
     */
    protected function finalizeCurrentStep(): void
    {
        if ($this->currentStepBuilder !== null) {
            $this->currentStepBuilder->build();
            $this->currentStepBuilder = null;
        }
    }

    /**
     * Build and register the workflow definition.
     *
     * This is called automatically when a new workflow is started
     * or when the service provider finishes loading workflows.
     */
    public function build(): WorkflowDefinition
    {
        $this->finalizeCurrentStep();

        $definition = new WorkflowDefinition(
            flow: $this->flow,
            entryRouteName: $this->entryRouteName,
            entryPath: $this->entryPath,
            finishRoute: $this->finishRoute,
            historyMode: $this->historyMode,
            steps: $this->steps,
        );

        $this->registrar->register($definition);

        return $definition;
    }
}
