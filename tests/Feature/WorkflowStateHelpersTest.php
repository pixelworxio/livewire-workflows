<?php

declare(strict_types=1);

use Livewire\Livewire;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use Tests\Support\TestComponentWithHelpers;

beforeEach(function () {
    if (! class_exists(TestComponentWithHelpers::class)) {
        eval(<<<'PHP'
namespace Tests\Support;

use Livewire\Component;
use Pixelworxio\LivewireWorkflows\Livewire\Concerns\InteractsWithWorkflows;

class TestComponentWithHelpers extends Component
{
    use InteractsWithWorkflows;

    public function mount()
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
PHP
        );
    }
});

test('putWorkflowState stores data', function () {
    $component = Livewire::test(TestComponentWithHelpers::class);

    $component->call('saveData');

    $repository = app(WorkflowStateRepository::class);
    $sessionId = session()->getId();

    $storedValue = $repository->getState('test-flow', $sessionId, 'user_data');

    expect($storedValue)->toBe(['name' => 'John', 'age' => 30]);
});

test('getWorkflowState retrieves data', function () {
    $repository = app(WorkflowStateRepository::class);
    $sessionId = session()->getId();

    $repository->setState('test-flow', $sessionId, 'user_data', ['name' => 'Jane', 'age' => 25]);

    $component = Livewire::test(TestComponentWithHelpers::class);

    $data = $component->call('loadData')->json();

    expect($data)->toBe(['name' => 'Jane', 'age' => 25]);
});

test('hasWorkflowState checks existence', function () {
    $repository = app(WorkflowStateRepository::class);
    $sessionId = session()->getId();

    $component = Livewire::test(TestComponentWithHelpers::class);

    expect($component->call('checkData')->json())->toBeFalse();

    $repository->setState('test-flow', $sessionId, 'user_data', ['test' => 'value']);

    expect($component->call('checkData')->json())->toBeTrue();
});

test('forgetWorkflowState removes data', function () {
    $repository = app(WorkflowStateRepository::class);
    $sessionId = session()->getId();

    $repository->setState('test-flow', $sessionId, 'user_data', ['name' => 'John']);

    $component = Livewire::test(TestComponentWithHelpers::class);

    expect($component->call('checkData')->json())->toBeTrue();

    $component->call('removeData');

    expect($component->call('checkData')->json())->toBeFalse();
});

test('clearWorkflowState removes all data', function () {
    $repository = app(WorkflowStateRepository::class);
    $sessionId = session()->getId();

    $repository->setState('test-flow', $sessionId, 'key1', 'value1');
    $repository->setState('test-flow', $sessionId, 'key2', 'value2');

    $component = Livewire::test(TestComponentWithHelpers::class);

    $component->call('clearAll');

    expect($repository->getAllState('test-flow', $sessionId))->toBeEmpty();
});

test('clearWorkflowState with namespace only clears namespaced keys', function () {
    $repository = app(WorkflowStateRepository::class);
    $sessionId = session()->getId();

    $repository->setState('test-flow', $sessionId, 'profile.name', 'John');
    $repository->setState('test-flow', $sessionId, 'profile.age', 30);
    $repository->setState('test-flow', $sessionId, 'settings.theme', 'dark');

    $component = Livewire::test(TestComponentWithHelpers::class);

    $component->call('clearAll', 'profile');

    $allState = $repository->getAllState('test-flow', $sessionId);

    expect($allState)->not->toHaveKey('profile.name')
        ->and($allState)->not->toHaveKey('profile.age')
        ->and($allState)->toHaveKey('settings.theme');
});

test('allWorkflowState returns all data', function () {
    $repository = app(WorkflowStateRepository::class);
    $sessionId = session()->getId();

    $repository->setState('test-flow', $sessionId, 'key1', 'value1');
    $repository->setState('test-flow', $sessionId, 'key2', 'value2');

    $component = Livewire::test(TestComponentWithHelpers::class);

    $allData = $component->call('getAllData')->json();

    expect($allData)->toBe(['key1' => 'value1', 'key2' => 'value2']);
});
