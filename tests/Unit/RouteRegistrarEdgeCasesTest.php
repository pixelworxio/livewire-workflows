<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar;
use Pixelworxio\LivewireWorkflows\Support\RouteRegistrar;
use Tests\Support\FailingGuard;
use Tests\Support\TestStepOneComponent;

beforeEach(function () {
    app(WorkflowRegistrar::class)->clear();
});

test('invalid middleware_precedence mode falls back to global middleware', function () {
    config()->set('livewire-workflows.middleware_precedence', 'invalid-mode');
    config()->set('livewire-workflows.middleware', ['web']);

    Workflow::flow('test')
        ->entersAt(name: 'test.start', path: '/test')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    // Build the workflow
    app(WorkflowRegistrar::class)->get('test');

    // Register routes - should not throw even with invalid precedence mode
    $registrar = app(RouteRegistrar::class);
    $registrar->register();

    app('router')->getRoutes()->refreshNameLookups();

    // Route should be registered (falling back to global middleware)
    expect(Route::has('test.start'))->toBeTrue();
});

test('merge middleware_precedence mode combines middleware', function () {
    config()->set('livewire-workflows.middleware_precedence', 'merge');
    config()->set('livewire-workflows.middleware', ['web']);

    Workflow::flow('test-merge')
        ->entersAt(name: 'test-merge.start', path: '/test-merge')
        ->finishesAt('dashboard')
        ->middleware(['throttle:60,1'])
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->middleware(['auth'])
        ->order(10);

    app(WorkflowRegistrar::class)->get('test-merge');

    $registrar = app(RouteRegistrar::class);
    $registrar->register();

    app('router')->getRoutes()->refreshNameLookups();

    expect(Route::has('test-merge.start'))->toBeTrue();
    expect(Route::has('test-merge.step-one'))->toBeTrue();
});
