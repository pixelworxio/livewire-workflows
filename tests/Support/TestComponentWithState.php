<?php

namespace Tests\Support;

use Livewire\Component;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowState;
use Pixelworxio\LivewireWorkflows\Livewire\Concerns\InteractsWithWorkflows;

class TestComponentWithState extends Component
{
    use InteractsWithWorkflows;

    #[WorkflowState]
    public ?string $email = null;

    #[WorkflowState(encrypt: true)]
    public ?string $password = null;

    #[WorkflowState(namespace: 'profile')]
    public ?string $name = null;

    public function boot(): void
    {
        $this->setWorkflowName('test-flow');
    }

    public function render()
    {
        return '<div>Test Component</div>';
    }
}
