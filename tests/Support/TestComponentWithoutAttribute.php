<?php

declare(strict_types=1);

namespace Tests\Support;

use Livewire\Component;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowState;
use Pixelworxio\LivewireWorkflows\Livewire\Concerns\InteractsWithWorkflows;

class TestComponentWithoutAttribute extends Component
{
    use InteractsWithWorkflows;

    #[WorkflowState]
    public ?string $email = null;

    // Property without WorkflowState attribute
    public ?string $notTracked = null;

    public function boot(): void
    {
        $this->setWorkflowName('test-flow');
    }

    public function updateProperties(): void
    {
        $this->email = 'tracked@example.com';
        $this->notTracked = 'should-not-be-saved';
    }

    public function render()
    {
        return '<div>Test Component Without Attribute</div>';
    }
}
