<?php

declare(strict_types=1);

namespace Tests\Support;

use Livewire\Component;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowState;
use Pixelworxio\LivewireWorkflows\Livewire\Concerns\InteractsWithWorkflows;

class TestComponentWithoutWorkflowName extends Component
{
    use InteractsWithWorkflows;

    #[WorkflowState]
    public ?string $email = null;

    // Intentionally not setting workflow name

    public function render()
    {
        return '<div>Test Component Without Workflow Name</div>';
    }
}
