<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Tests\Support\TestComponentWithState;

beforeEach(function () {
    app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->clear();

    $builder = Workflow::flow('test-flow')
        ->entersAt(name: 'test.start', path: '/onboarding')
        ->finishesAt('dashboard')
        ->historyMode('stack')
        ->step('verify-email')
        ->goTo(TestComponentWithState::class)
        ->unlessPasses(Tests\Support\TestStepOneGuard::class)
        ->order(10);

    $builder->build();

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');

    app(Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class)->register();

    // Refresh route collection
    app('router')->getRoutes()->refreshNameLookups();
});

test('state persists across multiple component instantiations', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    // First request - set property
    Livewire::test(TestComponentWithState::class)
        ->set('email', 'first@example.com');

    // Second request - component should hydrate with saved value
    $secondComponent = Livewire::test(TestComponentWithState::class);

    expect($secondComponent->email)->toBe('first@example.com');

    // Modify in second request
    $secondComponent->set('email', 'second@example.com');

    // Third request - should have the updated value
    $thirdComponent = Livewire::test(TestComponentWithState::class);

    expect($thirdComponent->email)->toBe('second@example.com');
});

test('manual syncState persists changes immediately', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    $component = Livewire::test(TestComponentWithState::class);

    // Set property and manually sync
    $component->set('email', 'synced@example.com')
        ->call('syncState');

    // Check it was persisted immediately
    $storedValue = $repository->getState('test-flow', $userKey, 'email');
    expect($storedValue)->toBe('synced@example.com');
});

test('state survives component remount', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    // Set state directly in repository
    $repository->setState('test-flow', $userKey, 'email', 'preexisting@example.com');

    // Mount component - should hydrate from repository
    $component = Livewire::test(TestComponentWithState::class);

    expect($component->email)->toBe('preexisting@example.com');
});
