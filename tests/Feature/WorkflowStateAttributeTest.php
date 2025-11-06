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

    // Use the same userKey that the component will use
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    $repository->setState('test-flow', $userKey, 'email', 'test@example.com');

    $component = Livewire::test(TestComponentWithState::class);

    expect($component->email)->toBe('test@example.com');
});

test('syncs state to repository on dehydrate', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    $component = Livewire::test(TestComponentWithState::class)
        ->set('email', 'new@example.com');

    $storedValue = $repository->getState('test-flow', $userKey, 'email');

    expect($storedValue)->toBe('new@example.com');
});

test('encrypts state when encrypt flag is true', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    Livewire::test(TestComponentWithState::class)
        ->set('password', 'secret123');

    $storedValue = $repository->getState('test-flow', $userKey, 'password');

    expect($storedValue)->not->toBe('secret123');

    $decrypted = Crypt::decrypt($storedValue);
    expect($decrypted)->toBe('secret123');
});

test('decrypts encrypted state on hydration', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    $encrypted = Crypt::encrypt('secret123');
    $repository->setState('test-flow', $userKey, 'password', $encrypted);

    $component = Livewire::test(TestComponentWithState::class);

    expect($component->password)->toBe('secret123');
});

test('namespaces state keys correctly', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    Livewire::test(TestComponentWithState::class)
        ->set('name', 'John Doe')
        ->call('syncState');

    $storedValue = $repository->getState('test-flow', $userKey, 'profile.name');

    expect($storedValue)->toBe('John Doe');
});

test('hydrates namespaced state correctly', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    $repository->setState('test-flow', $userKey, 'profile.name', 'Jane Smith');

    $component = Livewire::test(TestComponentWithState::class);

    expect($component->name)->toBe('Jane Smith');
});
