<?php

declare(strict_types=1);

namespace Tests\Support;

use Livewire\Component;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowState;
use Pixelworxio\LivewireWorkflows\Livewire\Concerns\InteractsWithWorkflows;

class TestComponentWithControllerState extends Component
{
    use InteractsWithWorkflows;

    #[WorkflowState]
    public ?string $controller_name = null;

    #[WorkflowState]
    public ?string $controller_email = null;

    #[WorkflowState]
    public ?string $component_data = null;

    public function mount(): void
    {
        $this->setWorkflowName('onboarding');
    }

    public function setComponentData(string $data): void
    {
        $this->component_data = $data;
    }

    public function render()
    {
        return '<div>Test Component with Controller State</div>';
    }
}
