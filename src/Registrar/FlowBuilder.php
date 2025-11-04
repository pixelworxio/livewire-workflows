<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Registrar;

use Pixelworxio\LivewireWorkflows\Exceptions\InvalidWorkflowConfigurationException;
use Pixelworxio\LivewireWorkflows\Support\WorkflowDefinition;

class FlowBuilder
{
    protected ?string $entryName = null;
    protected ?string $entryPath = null;
    protected ?string $finishRoute = null;
    protected string $historyMode = 'none';
    protected ?StepBuilder $currentStep = null;
    public array $steps = [];
    protected bool $isBuilt = false;

    public function __construct(
        protected string $flow,
        protected WorkflowRegistrar $registrar
    ) {
    }

    public function entersAt(string $name, string $path): static
    {
        $this->entryName = $name;
        $this->entryPath = $path;

        return $this;
    }

    public function finishesAt(string $route): static
    {
        $this->finishRoute = $route;

        // Eager validation: if entryName is not set, fail immediately
        if ($this->entryName === null) {
            throw new InvalidWorkflowConfigurationException(
                "Workflow '{$this->flow}' must define an entry route using entersAt()."
            );
        }

        return $this;
    }

    public function historyMode(string $mode): static
    {
        if (! in_array($mode, ['none', 'stack'])) {
            throw new InvalidWorkflowConfigurationException(
                "History mode must be 'none' or 'stack', '{$mode}' given."
            );
        }

        $this->historyMode = $mode;

        return $this;
    }

    public function step(string $key): StepBuilder
    {
        if ($this->currentStep !== null) {
            $stepDef = $this->currentStep->build();
            $this->steps[$stepDef->key] = $stepDef;
        }

        $this->currentStep = new StepBuilder($key, $this->flow, $this);

        return $this->currentStep;
    }

    public function build(): WorkflowDefinition
    {
        if ($this->isBuilt) {
            return $this->registrar->get($this->flow);
        }

        if ($this->currentStep !== null) {
            $stepDef = $this->currentStep->build();
            $this->steps[$stepDef->key] = $stepDef;
            $this->currentStep = null;
        }

        if ($this->entryName === null || $this->entryPath === null) {
            throw new InvalidWorkflowConfigurationException(
                "Workflow '{$this->flow}' must define an entry route using entersAt()."
            );
        }

        if ($this->finishRoute === null) {
            throw new InvalidWorkflowConfigurationException(
                "Workflow '{$this->flow}' must define a finish route using finishesAt()."
            );
        }

        $workflow = new WorkflowDefinition(
            flow: $this->flow,
            entryRouteName: $this->entryName,
            entryPath: $this->entryPath,
            finishRoute: $this->finishRoute,
            historyMode: $this->historyMode,
            steps: $this->steps
        );

        $this->registrar->register($workflow);
        $this->isBuilt = true;

        return $workflow;
    }

    public function __destruct()
    {
        if (!$this->isBuilt && $this->finishRoute !== null) {
            try {
                $this->build();
            } catch (\Throwable $e) {
                // Suppress in destructor
            }
        }
    }
}
