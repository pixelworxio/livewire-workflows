<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Registrar;

use Pixelworxio\LivewireWorkflows\Support\StepDefinition;

class StepBuilder
{
    protected ?string $component = null;
    protected ?string $guard = null;
    protected int $order = 0;

    public function __construct(
        protected string $key,
        protected string $flow,
        protected FlowBuilder $flowBuilder
    ) {
    }

    public function goTo(string $component): static
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

    public function step(string $key): self
    {
        // Return to flow builder and start a new step
        return $this->flowBuilder->step($key);
    }

    public function build(): StepDefinition
    {
        return new StepDefinition(
            key: $this->key,
            flow: $this->flow,
            component: $this->component,
            guardClass: $this->guard,
            order: $this->order
        );
    }

    public function __call(string $method, array $arguments)
    {
        // Proxy unknown methods to the flow builder
        if (method_exists($this->flowBuilder, $method)) {
            return $this->flowBuilder->$method(...$arguments);
        }

        throw new \BadMethodCallException("Method {$method} does not exist on " . static::class);
    }
}
