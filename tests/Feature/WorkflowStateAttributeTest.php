<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowState;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Tests\Support\TestComponentWithState;

beforeEach(function () {
    // Register a test component with WorkflowState attributes
    if (! class_exists(TestComponentWithState::class)) {
        eval(<<<'PHP'
namespace Tests\Support;

use Livewire\Component;
use Pixelworxio\LivewireWorkflows\Livewire\Concerns\InteractsWithWorkflows;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowState;

class TestComponentWithState extends Component
{
    use InteractsWithWorkflows;

    #[WorkflowState]
    public ?string $email = null;

    #[WorkflowState(encrypt: true)]
    public ?string $password = null;

    #[WorkflowState(namespace: 'profile')]
    public ?string $name = null;

    public function render()
    {
        return '<div>Test Component</div>';
    }
}
PHP
        );
    }

    app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->clear();

    $builder = Workflow::flow('test-flow')
        ->entersAt(name: 'test.start', path: '/onboarding')
        ->finishesAt('dashboard')
        ->historyMode('stack')
        ->step('verify-email')
        ->goTo(Tests\Support\TestComponentWithState::class)
        ->unlessPasses(Tests\Support\TestStepOneGuard::class)
        ->order(10);

    $builder->build();

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');

    app(Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class)->register();

    // Refresh route collection
    app('router')->getRoutes()->refreshNameLookups();
});

test('hydrates state from repository on mount', function () {
    $repository = app(WorkflowStateRepository::class);
    $sessionId = session()->getId();

    $repository->setState('test-flow', $sessionId, 'email', 'test@example.com');

    $component = Livewire::test(TestComponentWithState::class);

    expect($component->email)->toBe('test@example.com');
});

test('syncs state to repository on dehydrate', function () {
    $repository = app(WorkflowStateRepository::class);
    $sessionId = session()->getId();

    $component = Livewire::test(TestComponentWithState::class)
        ->set('email', 'new@example.com');

    $storedValue = $repository->getState('test-flow', $sessionId, 'email');

    expect($storedValue)->toBe('new@example.com');
});

test('encrypts state when encrypt flag is true', function () {
    $repository = app(WorkflowStateRepository::class);
    $sessionId = session()->getId();

    Livewire::test(TestComponentWithState::class)
        ->set('password', 'secret123');

    $storedValue = $repository->getState('test-flow', $sessionId, 'password');

    expect($storedValue)->not->toBe('secret123');

    $decrypted = Crypt::decrypt($storedValue);
    expect($decrypted)->toBe('secret123');
});

test('decrypts encrypted state on hydration', function () {
    $repository = app(WorkflowStateRepository::class);
    $sessionId = session()->getId();

    $encrypted = Crypt::encrypt('secret123');
    $repository->setState('test-flow', $sessionId, 'password', $encrypted);

    $component = Livewire::test(TestComponentWithState::class);

    expect($component->password)->toBe('secret123');
});

test('namespaces state keys correctly', function () {
    $repository = app(WorkflowStateRepository::class);
    $sessionId = session()->getId();

    Livewire::test(TestComponentWithState::class)
        ->set('name', 'John Doe');

    $storedValue = $repository->getState('test-flow', $sessionId, 'profile.name');

    expect($storedValue)->toBe('John Doe');
});

test('hydrates namespaced state correctly', function () {
    $repository = app(WorkflowStateRepository::class);
    $sessionId = session()->getId();

    $repository->setState('test-flow', $sessionId, 'profile.name', 'Jane Smith');

    $component = Livewire::test(TestComponentWithState::class);

    expect($component->name)->toBe('Jane Smith');
});
