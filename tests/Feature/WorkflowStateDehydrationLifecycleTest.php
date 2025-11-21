<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Tests\Support\TestComponentWithLifecycleTracking;

beforeEach(function () {
    app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->clear();

    $builder = Workflow::flow('test-flow')
        ->entersAt(name: 'test.start', path: '/onboarding')
        ->finishesAt('dashboard')
        ->historyMode('stack')
        ->step('verify-email')
        ->goTo(TestComponentWithLifecycleTracking::class)
        ->unlessPasses(Tests\Support\TestStepOneGuard::class)
        ->order(10);

    $builder->build();

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');

    app(Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class)->register();

    // Refresh route collection
    app('router')->getRoutes()->refreshNameLookups();
});

test('dehydration captures property changes made in updated() hook', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    Livewire::test(TestComponentWithLifecycleTracking::class)
        ->set('email', 'test@example.com');

    // The updated() hook should have modified the name
    $storedName = $repository->getState('test-flow', $userKey, 'profile.name');

    expect($storedName)->toBe('Updated from hook');
});

test('dehydration captures direct property assignment not via set()', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    Livewire::test(TestComponentWithLifecycleTracking::class)
        ->call('directAssignment');

    $storedValue = $repository->getState('test-flow', $userKey, 'email');

    expect($storedValue)->toBe('direct@example.com');
});

test('dehydration happens after all property modifications in action', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    Livewire::test(TestComponentWithLifecycleTracking::class)
        ->call('multipleModifications');

    expect($repository->getState('test-flow', $userKey, 'email'))->toBe('final@example.com')
        ->and($repository->getState('test-flow', $userKey, 'profile.name'))->toBe('Final Name');
});
