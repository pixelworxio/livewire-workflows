<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Registrar;

use Pixelworxio\LivewireWorkflows\Support\StepDefinition;

/**
 * Fluent builder for defining a workflow step.
 *
 * Provides chainable methods:
 * - goTo() to specify the Livewire component
 * - unlessPasses() to specify the guard
 * - order() to set step ordering
 */
class StepBuilder
{
    protected string $component = '';
    protected ?string $guardClass = null;
    protected int $order = 0;

    public function __construct(
        protected string $flow,
        protected string $key,
        protected FlowBuilder $flowBuilder,
    ) {
    }

    /**
     * Specify the Livewire component to render for this step.
     *
     * @param string $componentClass Full class name of the Livewire component
     * @return static
     */
    public function goTo(string $componentClass): static
    {
        $this->component = $componentClass;

        return $this;
    }

    /**
     * Specify the guard that determines if this step should be shown.
     *
     * If the guard's passes() method returns false, the step is shown.
     * If passes() returns true, the step is skipped.
     *
     * @param string $guardClass Full class name of the guard
     * @return static
     */
    public function unlessPasses(string $guardClass): static
    {
        $this->guardClass = $guardClass;

        return $this;
    }

    /**
     * Set the sort order for this step.
     *
     * @param int $order Numeric sort order (lower numbers come first)
     * @return static
     */
    public function order(int $order): static
    {
        $this->order = $order;

        return $this;
    }

    /**
     * Begin defining another step (returns to the flow builder).
     *
     * @param string $key The next step's identifier
     * @return StepBuilder
     */
    public function step(string $key): StepBuilder
    {
        return $this->flowBuilder->step($key);
    }

    /**
     * Set the workflow's entry route (returns to the flow builder).
     *
     * @param string $name Route name
     * @param string $path URI path
     * @return FlowBuilder
     */
    public function entersAt(string $name, string $path): FlowBuilder
    {
        return $this->flowBuilder->entersAt($name, $path);
    }

    /**
     * Set the workflow's finish route (returns to the flow builder).
     *
     * @param string $route Route name
     * @return FlowBuilder
     */
    public function finishesAt(string $route): FlowBuilder
    {
        return $this->flowBuilder->finishesAt($route);
    }

    /**
     * Set the workflow's history mode (returns to the flow builder).
     *
     * @param string $mode 'none' or 'stack'
     * @return FlowBuilder
     */
    public function historyMode(string $mode): FlowBuilder
    {
        return $this->flowBuilder->historyMode($mode);
    }

    /**
     * Build and add this step to the parent workflow.
     *
     * @return void
     * @internal
     */
    public function build(): void
    {
        $step = new StepDefinition(
            flow: $this->flow,
            key: $this->key,
            component: $this->component,
            guardClass: $this->guardClass,
            order: $this->order,
        );

        $this->flowBuilder->addStep($step);
    }
}
