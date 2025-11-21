<?php

declare(strict_types=1);

namespace Tests\Support;

use Livewire\Component;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowState;
use Pixelworxio\LivewireWorkflows\Livewire\Concerns\InteractsWithWorkflows;

class TestComponentWithProtectedProperty extends Component
{
    use InteractsWithWorkflows;

    #[WorkflowState]
    protected ?string $protectedProp = null;

    public function boot(): void
    {
        $this->setWorkflowName('test-flow');
    }

    public function updateProtectedProperty(): void
    {
        $this->protectedProp = 'protected-value';
    }

    public function render()
    {
        return '<div>Test Component With Protected Property</div>';
    }
}
