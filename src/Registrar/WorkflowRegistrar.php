<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Registrar;

use Pixelworxio\LivewireWorkflows\Exceptions\WorkflowNotFoundException;
use Pixelworxio\LivewireWorkflows\Support\WorkflowDefinition;

class WorkflowRegistrar
{
    protected array $workflows = [];
    protected array $pendingBuilders = [];

    public function flow(string $name): FlowBuilder
    {
        $builder = new FlowBuilder($name, $this);
        $this->pendingBuilders[$name] = $builder;

        return $builder;
    }

    public function register(WorkflowDefinition $workflow): void
    {
        $this->workflows[$workflow->flow] = $workflow;
        unset($this->pendingBuilders[$workflow->flow]);
    }

    public function get(string $flow): WorkflowDefinition
    {
        $this->finalizePending($flow);

        if (! $this->has($flow)) {
            throw WorkflowNotFoundException::forFlow($flow);
        }

        return $this->workflows[$flow];
    }

    public function has(string $flow): bool
    {
        $this->finalizePending($flow);

        return isset($this->workflows[$flow]);
    }

    public function all(): array
    {
        $this->finalizeAllPending();

        return $this->workflows;
    }

    public function clear(): void
    {
        $this->workflows = [];
        $this->pendingBuilders = [];
    }

    protected function finalizePending(string $flow): void
    {
        if (isset($this->pendingBuilders[$flow])) {
            $this->pendingBuilders[$flow]->build();
        }
    }

    protected function finalizeAllPending(): void
    {
        foreach ($this->pendingBuilders as $builder) {
            try {
                $builder->build();
            } catch (\Throwable $e) {
                // Skip invalid builders
            }
        }
    }
}
