<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Tests\Support\TestComponentWithWorkflowNameAttribute;
use Tests\Support\TestComponentWithWorkflowStepAttribute;

beforeEach(function () {
    app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->clear();

    $builder = Workflow::flow('test-flow')
        ->entersAt(name: 'test.start', path: '/test')
        ->finishesAt('dashboard')
        ->historyMode('stack')
        ->step('test-step')
        ->goTo(TestComponentWithWorkflowStepAttribute::class)
        ->unlessPasses(Tests\Support\TestStepOneGuard::class)
        ->order(10)
        ->step('another-step')
        ->goTo(TestComponentWithWorkflowNameAttribute::class)
        ->unlessPasses(Tests\Support\TestStepTwoGuard::class)
        ->order(20);

    $builder->build();

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');

    app(Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class)->register();

    // Refresh route collection
    app('router')->getRoutes()->refreshNameLookups();
});

test('continue without parameters uses workflow name from WorkflowStep attribute', function () {
    $component = Livewire::test(TestComponentWithWorkflowStepAttribute::class);

    $component->call('continue');

    // Should redirect to the workflow entry point
    $component->assertRedirect(route('test.start'));
});

test('continue without parameters uses workflow name from WorkflowName attribute', function () {
    $component = Livewire::test(TestComponentWithWorkflowNameAttribute::class);

    $component->call('continue');

    // Should redirect to the workflow entry point
    $component->assertRedirect(route('test.start'));
});

test('continue with explicit parameter overrides detected workflow name', function () {
    $component = Livewire::test(TestComponentWithWorkflowStepAttribute::class);

    $component->call('continue', 'test-flow');

    // Should redirect to the workflow entry point
    $component->assertRedirect(route('test.start'));
});

test('back without parameters uses workflow name and step key from WorkflowStep attribute', function () {
    $component = Livewire::test(TestComponentWithWorkflowStepAttribute::class);

    // Set up history
    $repository = app(\Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));
    $repository->pushHistory('test-flow', $userKey, 'another-step');
    $repository->pushHistory('test-flow', $userKey, 'test-step');

    $component->call('back');

    // Should redirect to the previous step (route names are flow.step-key)
    $component->assertRedirect(route('test-flow.another-step'));
});

test('back with explicit parameters overrides detected values', function () {
    $component = Livewire::test(TestComponentWithWorkflowStepAttribute::class);

    // Set up history
    $repository = app(\Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));
    $repository->pushHistory('test-flow', $userKey, 'another-step');
    $repository->pushHistory('test-flow', $userKey, 'test-step');

    $component->call('back', 'test-flow', 'test-step');

    // Should redirect to the previous step (route names are flow.step-key)
    $component->assertRedirect(route('test-flow.another-step'));
});

test('continue throws exception when workflow name not available', function () {
    // Create a component without workflow name attribute
    $component = Livewire::test(Tests\Support\TestComponentWithoutWorkflowName::class);

    $component->call('continue');
})->throws(\InvalidArgumentException::class, 'Workflow name must be provided');

test('back throws exception when workflow name not available', function () {
    // Create a component without workflow name attribute
    $component = Livewire::test(Tests\Support\TestComponentWithoutWorkflowName::class);

    $component->call('back');
})->throws(\InvalidArgumentException::class, 'Workflow name must be provided');

test('back throws exception when step key not available', function () {
    // Create a component with workflow name but no step key
    $component = Livewire::test(TestComponentWithWorkflowNameAttribute::class);

    $component->call('back');
})->throws(\InvalidArgumentException::class, 'Current step key must be provided');

test('component detects step key from WorkflowStep attribute', function () {
    $component = Livewire::test(TestComponentWithWorkflowStepAttribute::class);

    expect($component->instance()->getStepKey())->toBe('test-step');
});

test('component detects workflow name from WorkflowStep attribute', function () {
    $component = Livewire::test(TestComponentWithWorkflowStepAttribute::class);

    expect($component->instance()->getWorkflowName())->toBe('test-flow');
});

test('backward compatibility: continue still works with explicit flow parameter', function () {
    $component = Livewire::test(TestComponentWithWorkflowNameAttribute::class);

    // Old API: explicit parameter
    $component->call('continue', 'test-flow');

    $component->assertRedirect(route('test.start'));
});

test('backward compatibility: back still works with explicit parameters', function () {
    $component = Livewire::test(TestComponentWithWorkflowStepAttribute::class);

    // Set up history
    $repository = app(\Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));
    $repository->pushHistory('test-flow', $userKey, 'another-step');
    $repository->pushHistory('test-flow', $userKey, 'test-step');

    // Old API: explicit parameters
    $component->call('back', 'test-flow', 'test-step');

    $component->assertRedirect(route('test-flow.another-step'));
});
