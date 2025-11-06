<?php

namespace Tests\Support;

use Livewire\Component;
use Pixelworxio\LivewireWorkflows\Livewire\Concerns\InteractsWithWorkflows;

class TestComponentWithEncryptionHelper extends Component
{
    use InteractsWithWorkflows;

    public function boot(): void
    {
        $this->setWorkflowName('secure-flow');
    }

    public function saveEncrypted($value)
    {
        $encrypted = \Illuminate\Support\Facades\Crypt::encrypt($value);
        $this->putWorkflowState('manual_encrypted', $encrypted);
    }

    public function loadEncrypted()
    {
        $encrypted = $this->getWorkflowState('manual_encrypted');

        return $encrypted ? \Illuminate\Support\Facades\Crypt::decrypt($encrypted) : null;
    }

    public function render()
    {
        return '<div>Helper Component</div>';
    }
}
