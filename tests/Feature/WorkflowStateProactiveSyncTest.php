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

test('proactive sync persists state when property changes via set', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    // Set property - should trigger proactive sync via updated() hook
    Livewire::test(TestComponentWithState::class)
        ->set('email', 'proactive@example.com');

    // State should be immediately persisted, not just on dehydrate
    $storedValue = $repository->getState('test-flow', $userKey, 'email');

    expect($storedValue)->toBe('proactive@example.com');
});

test('proactive sync works with encrypted properties', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    Livewire::test(TestComponentWithState::class)
        ->set('password', 'secret-password');

    // Should be encrypted and immediately persisted
    $storedValue = $repository->getState('test-flow', $userKey, 'password');

    expect($storedValue)->not->toBe('secret-password')
        ->and($storedValue)->not->toBeNull();
});

test('proactive sync works with namespaced properties', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    Livewire::test(TestComponentWithState::class)
        ->set('name', 'John Doe');

    // Should be persisted with namespace prefix
    $storedValue = $repository->getState('test-flow', $userKey, 'profile.name');

    expect($storedValue)->toBe('John Doe');
});

test('dirty tracking prevents redundant syncs', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    $component = Livewire::test(TestComponentWithState::class)
        ->set('email', 'first@example.com')
        ->set('email', 'second@example.com')
        ->set('email', 'third@example.com');

    // Final value should be persisted
    $storedValue = $repository->getState('test-flow', $userKey, 'email');

    expect($storedValue)->toBe('third@example.com');
});

test('dehydration still works as fallback for direct property assignments', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    // Direct assignment in method (not via set()) should still be caught by dehydration
    Livewire::test(Tests\Support\TestComponentWithEventHandler::class)
        ->call('updateEmail', 'fallback@example.com');

    $storedValue = $repository->getState('test-flow', $userKey, 'email');

    expect($storedValue)->toBe('fallback@example.com');
});

test('proactive sync and dehydration work together harmoniously', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    Livewire::test(Tests\Support\TestComponentWithLifecycleTracking::class)
        ->set('email', 'proactive@example.com')  // Triggers proactive sync
        ->call('multipleModifications');         // Direct assignments caught by dehydration

    // Both should be persisted correctly
    expect($repository->getState('test-flow', $userKey, 'email'))->toBe('final@example.com')
        ->and($repository->getState('test-flow', $userKey, 'profile.name'))->toBe('Final Name');
});
