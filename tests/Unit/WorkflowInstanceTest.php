<?php

declare(strict_types=1);

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar;
use Pixelworxio\LivewireWorkflows\Support\WorkflowInstance;
use Tests\Support\FailingGuard;
use Tests\Support\PassingGuard;
use Tests\Support\TestStepOneComponent;
use Tests\Support\TestStepTwoComponent;

beforeEach(function () {
    app(WorkflowRegistrar::class)->clear();

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');

    Workflow::flow('test-flow')
        ->entersAt(name: 'test-flow.start', path: '/test-flow')
        ->finishesAt('dashboard')
        ->historyMode('stack')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10)
        ->step('step-two')
        ->goTo(TestStepTwoComponent::class)
        ->unlessPasses(PassingGuard::class)
        ->order(20);

    app(Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class)->register();
    app('router')->getRoutes()->refreshNameLookups();
});

test('WorkflowInstance can be constructed', function () {
    $instance = workflow('test-flow');

    expect($instance)->toBeInstanceOf(WorkflowInstance::class);
});

test('redirect() returns a RedirectResponse', function () {
    $instance = workflow('test-flow');
    $response = $instance->redirect(request());

    expect($response)->toBeInstanceOf(RedirectResponse::class);
});

test('redirect() with doneRoute override uses that route when complete', function () {
    // Make all guards pass so workflow is complete
    app(WorkflowRegistrar::class)->clear();

    Route::get('/custom-done', fn () => 'Done')->name('custom.done');

    Workflow::flow('complete-flow')
        ->entersAt(name: 'complete-flow.start', path: '/complete-flow')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(PassingGuard::class)
        ->order(10);

    app(Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class)->register();
    app('router')->getRoutes()->refreshNameLookups();

    $instance = workflow('complete-flow');
    $response = $instance->redirect(request(), 'custom.done');

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->getTargetUrl())->toContain('custom-done');
});

test('nextRouteNameFor() returns next route name', function () {
    $instance = workflow('test-flow');
    $routeName = $instance->nextRouteNameFor(request());

    expect($routeName)->toBe('test-flow.step-one');
});

test('nextRouteNameFor() with doneRoute override returns custom route when complete', function () {
    app(WorkflowRegistrar::class)->clear();

    Workflow::flow('complete-flow')
        ->entersAt(name: 'complete-flow.start', path: '/complete-flow')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(PassingGuard::class)
        ->order(10);

    $instance = workflow('complete-flow');
    $routeName = $instance->nextRouteNameFor(request(), 'my.custom.done');

    expect($routeName)->toBe('my.custom.done');
});

test('previousRouteNameFor() returns null for first step', function () {
    $instance = workflow('test-flow');
    $routeName = $instance->previousRouteNameFor('step-one', request());

    expect($routeName)->toBeNull();
});

test('previousRouteNameFor() returns previous step route name', function () {
    // Push step-one to history first
    $repository = app(Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));
    $repository->pushHistory('test-flow', $userKey, 'step-one');
    $repository->setCurrentStep('test-flow', $userKey, 'step-two');

    $instance = workflow('test-flow');
    $routeName = $instance->previousRouteNameFor('step-two', request());

    expect($routeName)->toBe('test-flow.step-one');
});

test('progressFor() returns progress array', function () {
    $instance = workflow('test-flow');
    $progress = $instance->progressFor(request());

    expect($progress)->toBeArray()
        ->and($progress)->toHaveKeys(['total', 'completed', 'remaining', 'percentage', 'current_step', 'next_step', 'is_complete']);
});
