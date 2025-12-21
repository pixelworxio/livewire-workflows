<?php

namespace Tests\Support;

use Livewire\Component;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowState;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowStep;
use Pixelworxio\LivewireWorkflows\Livewire\Concerns\InteractsWithWorkflows;

#[WorkflowStep(flow: 'test-flow', key: 'test-step')]
class TestComponentWithWorkflowStepAttribute extends Component
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
