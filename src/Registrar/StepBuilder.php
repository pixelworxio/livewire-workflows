<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Registrar;

use Pixelworxio\LivewireWorkflows\Attributes\StepMiddleware;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowStep;
use Pixelworxio\LivewireWorkflows\Support\StepDefinition;

class StepBuilder
{
    protected string|array|null $component = null;

    protected ?string $guard = null;

    protected int $order = 0;

    protected array|\Closure|null $middleware = null;

    public function __construct(
        protected string $key,
        protected string $flow,
        protected FlowBuilder $flowBuilder
    ) {}

    /**
     * Set the component or controller for this step.
     *
     * @param  string|array  $component  Livewire component class, invokable controller class, or [ControllerClass, 'method']
     */
    public function goTo(string|array $component): static
    {
        $this->component = $component;

        return $this;
    }

    public function unlessPasses(string $guard): static
    {
        $this->guard = $guard;

        return $this;
    }

    public function order(int $order): static
    {
        $this->order = $order;

        return $this;
    }

    /**
     * Set middleware for this step.
     *
     * @param  array|\Closure  $middleware  Array of middleware or a closure that returns middleware
     * @return static
     */
    public function middleware(array|\Closure $middleware): static
    {
        $this->middleware = $middleware;

        return $this;
    }

    public function step(string $key): self
    {
        // Return to flow builder and start a new step
        return $this->flowBuilder->step($key);
    }

    public function build(): StepDefinition
    {
        // If no explicit middleware is set, try to read from component's StepMiddleware attribute
        $middleware = $this->middleware ?? $this->readMiddlewareFromAttribute();

        return new StepDefinition(
            key: $this->key,
            flow: $this->flow,
            component: $this->component,
            guardClass: $this->guard,
            order: $this->order,
            middleware: $middleware
        );
    }

    /**
     * Read middleware from component's WorkflowStep or StepMiddleware attribute.
     *
     * Checks WorkflowStep first (preferred), then falls back to StepMiddleware.
     *
     * @return array|null
     */
    protected function readMiddlewareFromAttribute(): ?array
    {
        // Only applicable if component is a string class name
        if (! is_string($this->component) || ! class_exists($this->component)) {
            return null;
        }

        $reflection = new \ReflectionClass($this->component);

        // Check for WorkflowStep attribute first (preferred)
        $workflowStepAttributes = $reflection->getAttributes(WorkflowStep::class);
        if (! empty($workflowStepAttributes)) {
            $attribute = $workflowStepAttributes[0]->newInstance();

            return ! empty($attribute->middleware) ? $attribute->middleware : null;
        }

        // Fall back to StepMiddleware attribute
        $stepMiddlewareAttributes = $reflection->getAttributes(StepMiddleware::class);
        if (! empty($stepMiddlewareAttributes)) {
            $attribute = $stepMiddlewareAttributes[0]->newInstance();

            return $attribute->middleware;
        }

        return null;
    }

    public function __call(string $method, array $arguments)
    {
        // Proxy unknown methods to the flow builder
        if (method_exists($this->flowBuilder, $method)) {
            return $this->flowBuilder->$method(...$arguments);
        }

        throw new \BadMethodCallException("Method {$method} does not exist on ".static::class);
    }
}
