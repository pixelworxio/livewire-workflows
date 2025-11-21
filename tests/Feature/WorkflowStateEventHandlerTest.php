<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Tests\Support\TestComponentWithEventHandler;

beforeEach(function () {
    app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->clear();

    $builder = Workflow::flow('test-flow')
        ->entersAt(name: 'test.start', path: '/onboarding')
        ->finishesAt('dashboard')
        ->historyMode('stack')
        ->step('verify-email')
        ->goTo(TestComponentWithEventHandler::class)
        ->unlessPasses(Tests\Support\TestStepOneGuard::class)
        ->order(10);

    $builder->build();

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');

    app(Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class)->register();

    // Refresh route collection
    app('router')->getRoutes()->refreshNameLookups();
});

test('syncs state to repository after calling event handler method', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    $component = Livewire::test(TestComponentWithEventHandler::class)
        ->call('updateEmail', 'handler@example.com');

    $storedValue = $repository->getState('test-flow', $userKey, 'email');

    expect($storedValue)->toBe('handler@example.com');
});

test('syncs state to repository after calling multiple event handler methods', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    Livewire::test(TestComponentWithEventHandler::class)
        ->call('updateEmail', 'first@example.com')
        ->call('updateName', 'John Doe');

    expect($repository->getState('test-flow', $userKey, 'email'))->toBe('first@example.com')
        ->and($repository->getState('test-flow', $userKey, 'profile.name'))->toBe('John Doe');
});

test('syncs state after modifying property in event handler and then navigating', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    Livewire::test(TestComponentWithEventHandler::class)
        ->call('updateEmail', 'navigate@example.com')
        ->call('continue', 'test-flow');

    // After navigation, state should be persisted
    expect($repository->getState('test-flow', $userKey, 'email'))->toBe('navigate@example.com');
});
