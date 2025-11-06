<?php

namespace Tests\Support;

use Livewire\Component;
use Pixelworxio\LivewireWorkflows\Livewire\Concerns\InteractsWithWorkflows;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowState;

class TestComponentWithEncryption extends Component
{
    use InteractsWithWorkflows;

    #[WorkflowState]
    public ?string $publicData = null;

    #[WorkflowState(encrypt: true)]
    public ?string $privateData = null;

    #[WorkflowState(encrypt: true)]
    public ?array $sensitiveArray = null;

    public function boot(): void
    {
        // Explicitly set workflow name for testing
        $this->setWorkflowName('secure-flow');
    }

    public function render()
    {
        return '<div>Secure Component</div>';
    }
}
