<?php

namespace Tests\Support;

use Livewire\Component;
use Pixelworxio\LivewireWorkflows\Livewire\Concerns\InteractsWithWorkflows;

class TestComponentWithHelpers extends Component
{
    use InteractsWithWorkflows;

    public function boot(): void
    {
        // Explicitly set workflow name for testing
        $this->setWorkflowName('test-flow');
    }

    public function saveData()
    {
        $this->putWorkflowState('user_data', ['name' => 'John', 'age' => 30]);
    }

    public function loadData()
    {
        return $this->getWorkflowState('user_data');
    }

    public function checkData()
    {
        return $this->hasWorkflowState('user_data');
    }

    public function removeData()
    {
        $this->forgetWorkflowState('user_data');
    }

    public function clearAll(?string $namespace = null)
    {
        $this->clearWorkflowState($namespace);
    }

    public function getAllData()
    {
        return $this->allWorkflowState();
    }

    public function render()
    {
        return '<div>Test Component</div>';
    }
}
