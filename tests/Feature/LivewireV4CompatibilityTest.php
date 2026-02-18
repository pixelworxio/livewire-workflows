<?php

declare(strict_types=1);

/**
 * Livewire v4 Compatibility Certification Tests
 *
 * These tests formally certify that the InteractsWithWorkflows trait's
 * Livewire lifecycle integration points are compatible with Livewire v4.
 *
 * Lifecycle hooks verified:
 * - bootInteractsWithWorkflows()  — reads #[WorkflowName] and #[WorkflowStep] attributes
 * - mountInteractsWithWorkflows() — hydrates #[WorkflowState] properties from repository
 * - updatedInteractsWithWorkflows() — proactively persists state on property change
 * - dehydrateInteractsWithWorkflows() — fallback persistence for direct assignments
 *
 * Navigation verified:
 * - continue() — calls redirect() with navigate: true
 * - back()     — calls redirect() with navigate: true
 */

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Tests\Support\TestComponentWithWorkflowNameAttribute;
use Tests\Support\TestComponentWithWorkflowStepAttribute;

beforeEach(function () {
    app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->clear();

    Tests\Support\TestStepOneGuard::$shouldPass = false;
    Tests\Support\TestStepTwoGuard::$shouldPass = false;

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
    app('router')->getRoutes()->refreshNameLookups();
});

// v4 lifecycle: bootInteractsWithWorkflows() reads #[WorkflowStep] attribute
test('v4 boot hook: reads WorkflowStep attribute for flow name and step key', function () {
    $component = Livewire::test(TestComponentWithWorkflowStepAttribute::class);

    expect($component->instance()->getWorkflowName())->toBe('test-flow')
        ->and($component->instance()->getStepKey())->toBe('test-step');
});

// v4 lifecycle: bootInteractsWithWorkflows() reads #[WorkflowName] attribute
test('v4 boot hook: reads WorkflowName attribute for flow name', function () {
    $component = Livewire::test(TestComponentWithWorkflowNameAttribute::class);

    expect($component->instance()->getWorkflowName())->toBe('test-flow');
});

// v4 lifecycle: mountInteractsWithWorkflows() hydrates #[WorkflowState] properties from repository
test('v4 mount hook: hydrates WorkflowState properties from repository', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));
    $repository->setState('test-flow', $userKey, 'email', 'hydrated@example.com');

    $component = Livewire::test(TestComponentWithWorkflowNameAttribute::class);

    expect($component->email)->toBe('hydrated@example.com');
});

// v4 lifecycle: updatedInteractsWithWorkflows() proactively persists state on set()
test('v4 updated hook: proactively persists WorkflowState property on set()', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    Livewire::test(TestComponentWithWorkflowNameAttribute::class)
        ->set('email', 'proactive@example.com');

    expect($repository->getState('test-flow', $userKey, 'email'))
        ->toBe('proactive@example.com');
});

// v4 lifecycle: dehydrateInteractsWithWorkflows() persists state from direct property assignments
test('v4 dehydrate hook: persists state changed by direct assignment in action', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    Livewire::test(Tests\Support\TestComponentWithLifecycleTracking::class)
        ->call('directAssignment');

    expect($repository->getState('test-flow', $userKey, 'email'))
        ->toBe('direct@example.com');
});

// v4 navigation: continue() calls redirect() with navigate: true
test('v4 redirect: continue() redirects to workflow entry route', function () {
    $component = Livewire::test(TestComponentWithWorkflowStepAttribute::class);

    $component->call('continue');

    $component->assertRedirect(route('test.start'));
});

// v4 navigation: back() calls redirect() with navigate: true
test('v4 redirect: back() redirects to previous step route', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));
    $repository->pushHistory('test-flow', $userKey, 'another-step');
    $repository->pushHistory('test-flow', $userKey, 'test-step');

    $component = Livewire::test(TestComponentWithWorkflowStepAttribute::class);

    $component->call('back');

    $component->assertRedirect(route('test-flow.another-step'));
});
