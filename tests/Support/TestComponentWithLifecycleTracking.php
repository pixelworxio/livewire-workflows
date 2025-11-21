<?php

declare(strict_types=1);

namespace Tests\Support;

use Livewire\Component;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowState;
use Pixelworxio\LivewireWorkflows\Livewire\Concerns\InteractsWithWorkflows;

class TestComponentWithLifecycleTracking extends Component
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

    public function updated($property): void
    {
        // Modify another property when email is updated
        if ($property === 'email') {
            $this->name = 'Updated from hook';
        }
    }

    public function directAssignment(): void
    {
        // Direct assignment without using ->set()
        $this->email = 'direct@example.com';
    }

    public function multipleModifications(): void
    {
        $this->email = 'initial@example.com';
        $this->name = 'Initial Name';

        // Multiple modifications
        $this->email = 'middle@example.com';
        $this->name = 'Middle Name';

        // Final values
        $this->email = 'final@example.com';
        $this->name = 'Final Name';
    }

    public function render()
    {
        return '<div>Test Component With Lifecycle Tracking</div>';
    }
}
