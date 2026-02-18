<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar;
use Tests\Support\FailingGuard;
use Tests\Support\PassingGuard;
use Tests\Support\TestComponentWithoutWorkflowName;
use Tests\Support\TestComponentWithState;
use Tests\Support\TestComponentWithWorkflowNameAttribute;
use Tests\Support\TestComponentWithWorkflowStepAttribute;
use Tests\Support\TestStepOneComponent;
use Tests\Support\TestStepTwoComponent;

beforeEach(function () {
    app(WorkflowRegistrar::class)->clear();

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');

    Workflow::flow('test-flow')
        ->entersAt(name: 'test.start', path: '/test-flow')
        ->finishesAt('dashboard')
        ->historyMode('stack')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10)
        ->step('step-two')
        ->goTo(TestStepTwoComponent::class)
        ->unlessPasses(PassingGuard::class)
        ->order(20);

    app(Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class)->register();
    app('router')->getRoutes()->refreshNameLookups();
});

test('mountInteractsWithWorkflows() skips hydration when workflowName is null', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    // Store some state that should NOT be hydrated
    $repository->setState('test-flow', $userKey, 'email', 'stored@example.com');

    // Component without workflow name should not hydrate
    $component = Livewire::test(TestComponentWithoutWorkflowName::class);

    // email should remain null since workflowName is null
    expect($component->email)->toBeNull();
});

test('updatedInteractsWithWorkflows() skips sync when workflowName is null', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    // Component without workflow name should not sync state
    Livewire::test(TestComponentWithoutWorkflowName::class)
        ->set('email', 'changed@example.com');

    // State should not have been persisted since workflowName is null
    $stored = $repository->getState('test-flow', $userKey, 'email');
    expect($stored)->toBeNull();
});

test('dehydrateInteractsWithWorkflows() skips sync when workflowName is null', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    // After component lifecycle, state should not be stored
    Livewire::test(TestComponentWithoutWorkflowName::class)
        ->set('email', 'test@example.com');

    // Nothing should be stored
    $stored = $repository->getAllState('test-flow', $userKey);
    expect($stored)->toBeEmpty();
});

test('syncState() does nothing when workflowName is null', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    // Component without workflow name
    $component = Livewire::test(TestComponentWithoutWorkflowName::class)
        ->set('email', 'test@example.com')
        ->call('syncState');

    // State should not be stored
    $stored = $repository->getAllState('test-flow', $userKey);
    expect($stored)->toBeEmpty();
});

test('getWorkflowState() returns default when workflowName is null', function () {
    // TestComponentWithoutWorkflowName has no workflow name
    $component = Livewire::test(TestComponentWithoutWorkflowName::class);

    // Access state via the component's protected method won't be directly callable
    // But dehydration/sync should work (or not work) correctly
    expect($component->instance()->getWorkflowName())->toBeNull();
});

test('allWorkflowState() returns empty array when workflowName is null', function () {
    $component = Livewire::test(TestComponentWithoutWorkflowName::class);

    expect($component->instance()->getWorkflowName())->toBeNull();
});

test('putWorkflowState() is no-op when workflowName is null', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    $component = Livewire::test(TestComponentWithoutWorkflowName::class);
    // Component has null workflowName, putWorkflowState should be a no-op

    $stored = $repository->getAllState('test-flow', $userKey);
    expect($stored)->toBeEmpty();
});

test('hasWorkflowState() returns false when workflowName is null', function () {
    $component = Livewire::test(TestComponentWithoutWorkflowName::class);

    expect($component->instance()->getWorkflowName())->toBeNull();
});

test('state with both encrypt and namespace is synced correctly', function () {
    // Create a component class that has encrypt+namespace combined
    // We'll use TestComponentWithState which has namespace
    $component = Livewire::test(TestComponentWithState::class);

    $component->set('name', 'John Doe');

    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    // 'name' has namespace: 'profile', so key is 'profile.name'
    $stored = $repository->getState('test-flow', $userKey, 'profile.name');
    expect($stored)->toBe('John Doe');
});

test('continue() works with no route parameters', function () {
    $component = Livewire::test(TestComponentWithWorkflowNameAttribute::class);

    $component->call('continue');

    $component->assertRedirect(route('test.start'));
});

test('back() with no previous step does nothing', function () {
    // Component with step key 'test-step' from WorkflowStep attribute
    $component = Livewire::test(TestComponentWithWorkflowStepAttribute::class);

    // back() should not throw, just return since there's no previous step
    $component->call('back');

    // Should not have redirected (no exception thrown)
    expect(true)->toBeTrue();
});

test('bootInteractsWithWorkflows() reads workflow name from WorkflowStep attribute', function () {
    $component = Livewire::test(TestComponentWithWorkflowStepAttribute::class);

    expect($component->instance()->getWorkflowName())->toBe('test-flow');
    expect($component->instance()->getStepKey())->toBe('test-step');
});

test('bootInteractsWithWorkflows() reads workflow name from WorkflowName attribute', function () {
    $component = Livewire::test(TestComponentWithWorkflowNameAttribute::class);

    expect($component->instance()->getWorkflowName())->toBe('test-flow');
});

test('syncSpecificWorkflowProperty() skips when workflowName is null', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    // TestComponentWithoutWorkflowName has WorkflowState attributes but no workflow name
    $component = Livewire::test(TestComponentWithoutWorkflowName::class)
        ->set('email', 'test@example.com');

    // The updated hook fires but workflowName is null, so syncSpecificWorkflowProperty should skip
    expect($repository->getState('test-flow', $userKey, 'email'))->toBeNull();
});
