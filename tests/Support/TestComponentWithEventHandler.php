<?php

declare(strict_types=1);

namespace Tests\Support;

use Livewire\Component;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowState;
use Pixelworxio\LivewireWorkflows\Livewire\Concerns\InteractsWithWorkflows;

class TestComponentWithEventHandler extends Component
{
    use InteractsWithWorkflows;

    #[WorkflowState]
    public ?string $email = null;

    #[WorkflowState(namespace: 'profile')]
    public ?string $name = null;

    public function boot(): void
    {
        $this->setWorkflowName('test-flow');
    }

    public function updateEmail(string $email): void
    {
        $this->email = $email;
    }

    public function updateName(string $name): void
    {
        $this->name = $name;
    }

    public function render()
    {
        return '<div>Test Component With Event Handler</div>';
    }
}
