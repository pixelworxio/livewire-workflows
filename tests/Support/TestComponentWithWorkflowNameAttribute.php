<?php

namespace Tests\Support;

use Livewire\Component;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowName;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowState;
use Pixelworxio\LivewireWorkflows\Livewire\Concerns\InteractsWithWorkflows;

#[WorkflowName('test-flow')]
class TestComponentWithWorkflowNameAttribute extends Component
{
    use InteractsWithWorkflows;

    #[WorkflowState]
    public ?string $email = null;

    #[WorkflowState]
    public ?string $name = null;

    public function render()
    {
        return '<div>Test Component</div>';
    }
}
