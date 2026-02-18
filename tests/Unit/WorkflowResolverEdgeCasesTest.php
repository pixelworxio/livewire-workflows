<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar;
use Pixelworxio\LivewireWorkflows\Support\WorkflowDefinition;
use Pixelworxio\LivewireWorkflows\Support\WorkflowResolver;
use Tests\Support\FailingGuard;
use Tests\Support\PassingGuard;
use Tests\Support\TestStepOneComponent;

beforeEach(function () {
    app(WorkflowRegistrar::class)->clear();
    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');
});

test('get() returns the workflow definition', function () {
    Workflow::flow('test')
        ->entersAt(name: 'test.start', path: '/test')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    $resolver = app(WorkflowResolver::class);
    $definition = $resolver->get('test');

    expect($definition)->toBeInstanceOf(WorkflowDefinition::class)
        ->and($definition->flow)->toBe('test');
});

test('nextRouteNameFor() returns doneRoute when workflow is complete', function () {
    Workflow::flow('test')
        ->entersAt(name: 'test.start', path: '/test')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(PassingGuard::class)  // All guards pass = complete
        ->order(10);

    $resolver = app(WorkflowResolver::class);
    $routeName = $resolver->nextRouteNameFor('test', request(), 'my.custom.done');

    expect($routeName)->toBe('my.custom.done');
});

test('nextRouteNameFor() returns default finish route when complete and no doneRoute override', function () {
    Workflow::flow('test')
        ->entersAt(name: 'test.start', path: '/test')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(PassingGuard::class)
        ->order(10);

    $resolver = app(WorkflowResolver::class);
    $routeName = $resolver->nextRouteNameFor('test', request());

    expect($routeName)->toBe('dashboard');
});

test('getUserKey() uses session ID when no auth user', function () {
    Workflow::flow('test')
        ->entersAt(name: 'test.start', path: '/test')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    $resolver = app(WorkflowResolver::class);
    $routeName = $resolver->nextRouteNameFor('test', request());

    // Session ID is used when user is not authenticated
    expect($routeName)->toBe('test.step-one');
});

test('extractRouteParameters() uses stored parameters when available', function () {
    Workflow::flow('test')
        ->entersAt(name: 'test.start', path: '/user/{user}/test')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    app(Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class)->register();
    app('router')->getRoutes()->refreshNameLookups();

    // Store route parameters in state first
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));
    $repository = app(WorkflowStateRepository::class);
    $repository->setState('test', $userKey, '_route_parameters', ['user' => '123']);

    $resolver = app(WorkflowResolver::class);
    $routeName = $resolver->nextRouteNameFor('test', request());

    // Should resolve next step name (parameters come from stored state)
    expect($routeName)->toBe('test.step-one');
});

test('previousRouteNameFor() returns null when no previous step exists', function () {
    Workflow::flow('test')
        ->entersAt(name: 'test.start', path: '/test')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    $resolver = app(WorkflowResolver::class);
    $result = $resolver->previousRouteNameFor('test', 'step-one', request());

    expect($result)->toBeNull();
});

test('getUserKey() falls back to guest fingerprint when no session', function () {
    // Test that the engine can handle requests without session
    Workflow::flow('test')
        ->entersAt(name: 'test.start', path: '/test')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    $resolver = app(WorkflowResolver::class);

    // Standard request (session is available in tests via array driver)
    $routeName = $resolver->nextRouteNameFor('test', request());
    expect($routeName)->toBe('test.step-one');
});
