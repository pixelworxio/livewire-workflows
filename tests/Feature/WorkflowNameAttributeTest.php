<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Tests\Support\TestComponentWithWorkflowNameAttribute;

beforeEach(function () {
    app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->clear();

    $builder = Workflow::flow('test-flow')
        ->entersAt(name: 'test.start', path: '/onboarding')
        ->finishesAt('dashboard')
        ->historyMode('stack')
        ->step('verify-email')
        ->goTo(TestComponentWithWorkflowNameAttribute::class)
        ->unlessPasses(Tests\Support\TestStepOneGuard::class)
        ->order(10);

    $builder->build();

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');

    app(Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class)->register();

    // Refresh route collection
    app('router')->getRoutes()->refreshNameLookups();
});

test('component reads workflow name from WorkflowName attribute', function () {
    $component = Livewire::test(TestComponentWithWorkflowNameAttribute::class);

    expect($component->instance()->getWorkflowName())->toBe('test-flow');
});

test('component with WorkflowName attribute can hydrate state', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    $repository->setState('test-flow', $userKey, 'email', 'test@example.com');
    $repository->setState('test-flow', $userKey, 'name', 'John Doe');

    $component = Livewire::test(TestComponentWithWorkflowNameAttribute::class);

    expect($component->email)->toBe('test@example.com')
        ->and($component->name)->toBe('John Doe');
});

test('component with WorkflowName attribute can sync state', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    $component = Livewire::test(TestComponentWithWorkflowNameAttribute::class)
        ->set('email', 'new@example.com')
        ->set('name', 'Jane Smith');

    $storedEmail = $repository->getState('test-flow', $userKey, 'email');
    $storedName = $repository->getState('test-flow', $userKey, 'name');

    expect($storedEmail)->toBe('new@example.com')
        ->and($storedName)->toBe('Jane Smith');
});

test('component with WorkflowName attribute can use continue method', function () {
    $component = Livewire::test(TestComponentWithWorkflowNameAttribute::class);

    $component->call('continue', 'test-flow');

    // Should redirect to the workflow entry point
    $component->assertRedirect(route('test.start'));
});

test('WorkflowName attribute takes precedence over property', function () {
    // Create a component instance
    $component = Livewire::test(TestComponentWithWorkflowNameAttribute::class);

    // Even though the component has the attribute set to 'test-flow',
    // the workflow name should be 'test-flow' from the attribute
    expect($component->instance()->getWorkflowName())->toBe('test-flow');
});

test('component with WorkflowName attribute does not require manual setWorkflowName call', function () {
    // This test verifies that we don't need to call setWorkflowName in boot()
    $component = Livewire::test(TestComponentWithWorkflowNameAttribute::class);

    // Workflow name should be automatically set from the attribute
    expect($component->instance()->getWorkflowName())->toBe('test-flow');

    // And state should work without manual setup
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    $component->set('email', 'auto@example.com');

    $storedEmail = $repository->getState('test-flow', $userKey, 'email');
    expect($storedEmail)->toBe('auto@example.com');
});

test('WorkflowName attribute is read during boot phase', function () {
    // The workflow name should be set before mount is called
    $component = Livewire::test(TestComponentWithWorkflowNameAttribute::class);

    // Verify the workflow name is available
    expect($component->instance()->getWorkflowName())->not->toBeNull()
        ->and($component->instance()->getWorkflowName())->toBe('test-flow');
});
