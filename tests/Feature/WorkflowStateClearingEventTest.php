<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\Events\WorkflowCompleted;
use Pixelworxio\LivewireWorkflows\Events\WorkflowStateClearing;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;

beforeEach(function () {
    Event::fake();

    app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->clear();

    $builder = Workflow::flow('onboarding')
        ->entersAt(name: 'onboarding.start', path: '/onboarding')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(Tests\Support\TestStepOneComponent::class)
        ->unlessPasses(Tests\Support\TestStepOneGuard::class)
        ->order(10);

    $builder->build();

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');

    app(Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class)->register();

    // Refresh route collection
    app('router')->getRoutes()->refreshNameLookups();
});

test('fires WorkflowStateClearing event when workflow completes with state data', function () {
    $repository = app(WorkflowStateRepository::class);
    $sessionId = session()->getId();

    dump('sessionId: '.$sessionId); // TEMPORARY

    // Set some state data
    $repository->setState('onboarding', $sessionId, 'email', 'test@example.com');
    $repository->setState('onboarding', $sessionId, 'name', 'John Doe');

    // Complete the workflow (all guards pass)
    Tests\Support\TestStepOneGuard::$shouldPass = true;

    $this->get('/onboarding');

    dd($repository);

    Event::assertDispatched(WorkflowStateClearing::class, function ($event) use ($sessionId) {
        return $event->flow === 'onboarding'
            && $event->userKey === $sessionId
            && isset($event->stateData['email'])
            && $event->stateData['email'] === 'test@example.com'
            && isset($event->stateData['name'])
            && $event->stateData['name'] === 'John Doe';
    });
});

test('does not fire WorkflowStateClearing event when no state data exists', function () {
    // Complete the workflow without any state data
    Tests\Support\TestStepOneGuard::$shouldPass = true;

    $this->get('/onboarding');

    Event::assertNotDispatched(WorkflowStateClearing::class);
    Event::assertDispatched(WorkflowCompleted::class);
});

test('clears state data after firing WorkflowStateClearing event', function () {
    $repository = app(WorkflowStateRepository::class);
    $sessionId = session()->getId();

    // Set some state data
    $repository->setState('onboarding', $sessionId, 'email', 'test@example.com');

    expect($repository->getAllState('onboarding', $sessionId))->not->toBeEmpty();

    // Complete the workflow
    Tests\Support\TestStepOneGuard::$shouldPass = true;

    $this->get('/onboarding');

    // State should be cleared
    expect($repository->getAllState('onboarding', $sessionId))->toBeEmpty();
});

test('event contains complete state snapshot', function () {
    $repository = app(WorkflowStateRepository::class);
    $sessionId = session()->getId();

    // Set complex state data
    $repository->setState('onboarding', $sessionId, 'user.email', 'test@example.com');
    $repository->setState('onboarding', $sessionId, 'user.name', 'John Doe');
    $repository->setState('onboarding', $sessionId, 'preferences.theme', 'dark');
    $repository->setState('onboarding', $sessionId, 'preferences.notifications', true);

    Tests\Support\TestStepOneGuard::$shouldPass = true;

    $this->get('/onboarding');

    Event::assertDispatched(WorkflowStateClearing::class, function ($event) {
        return count($event->stateData) === 4
            && isset($event->stateData['user.email'])
            && isset($event->stateData['user.name'])
            && isset($event->stateData['preferences.theme'])
            && isset($event->stateData['preferences.notifications']);
    });
});

test('listener can capture state before clearing', function () {
    $capturedState = null;

    Event::listen(WorkflowStateClearing::class, function ($event) use (&$capturedState) {
        $capturedState = $event->stateData;
    });

    $repository = app(WorkflowStateRepository::class);
    $sessionId = session()->getId();

    $repository->setState('onboarding', $sessionId, 'important_data', 'must_be_saved');

    Tests\Support\TestStepOneGuard::$shouldPass = true;

    $this->get('/onboarding');

    expect($capturedState)->not->toBeNull()
        ->and($capturedState)->toHaveKey('important_data')
        ->and($capturedState['important_data'])->toBe('must_be_saved');

    // State should still be cleared
    expect($repository->getAllState('onboarding', $sessionId))->toBeEmpty();
});
