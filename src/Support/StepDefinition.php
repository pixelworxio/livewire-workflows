<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Support;

use Pixelworxio\LivewireWorkflows\Contracts\GuardContract;
use Pixelworxio\LivewireWorkflows\Exceptions\InvalidWorkflowConfigurationException;

class StepDefinition
{
    public function __construct(
        public readonly string $key,
        public readonly string $flow,
        public readonly ?string $component = null,
        public readonly ?string $guardClass = null,
        public readonly int $order = 0,
    ) {
        $this->validate();
    }

    protected function validate(): void
    {
        if ($this->component === null) {
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

        if ($this->guardClass !== null) {
            $implements = class_implements($this->guardClass);
            if (! in_array(GuardContract::class, $implements ?: [])) {
                throw new InvalidWorkflowConfigurationException(
                    "Step '{$this->key}' guard class '{$this->guardClass}' must implement GuardContract."
                );
            }
        }
    }

    public function hasGuard(): bool
    {
        return $this->guardClass !== null;
    }

    public function getGuard(): ?GuardContract
    {
        if ($this->guardClass === null) {
            return null;
        }

        return app($this->guardClass);
    }
}
