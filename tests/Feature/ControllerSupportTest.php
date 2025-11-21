<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Tests\Support\FailingGuard;
use Tests\Support\PassingGuard;
use Tests\Support\TestInvokableController;
use Tests\Support\TestMethodController;
use Tests\Support\TestStepOneComponent;

beforeEach(function () {
    app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->clear();
});

test('can register workflow with invokable controller', function () {
    Workflow::flow('test-flow')
        ->entersAt(name: 'test-flow.start', path: '/test-flow')
        ->finishesAt('dashboard')
        ->step('controller-step')
        ->goTo(TestInvokableController::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    $registrar = app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class);
    $workflow = $registrar->get('test-flow');

    expect($workflow->steps)->toHaveCount(1)
        ->and($workflow->getStep('controller-step')->component)->toBe(TestInvokableController::class);
});

test('can register workflow with controller method array', function () {
    Workflow::flow('test-flow')
        ->entersAt(name: 'test-flow.start', path: '/test-flow')
        ->finishesAt('dashboard')
        ->step('controller-step')
        ->goTo([TestMethodController::class, 'show'])
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    $registrar = app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class);
    $workflow = $registrar->get('test-flow');

    expect($workflow->steps)->toHaveCount(1)
        ->and($workflow->getStep('controller-step')->component)->toBe([TestMethodController::class, 'show']);
});

test('can mix livewire components and controllers in same workflow', function () {
    Workflow::flow('mixed-flow')
        ->entersAt(name: 'mixed-flow.start', path: '/mixed')
        ->finishesAt('dashboard')
        ->step('livewire-step')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(PassingGuard::class)
        ->order(10)
        ->step('controller-step')
        ->goTo(TestInvokableController::class)
        ->unlessPasses(FailingGuard::class)
        ->order(20)
        ->step('method-controller-step')
        ->goTo([TestMethodController::class, 'show'])
        ->unlessPasses(FailingGuard::class)
        ->order(30);

    $registrar = app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class);
    $workflow = $registrar->get('mixed-flow');

    expect($workflow->steps)->toHaveCount(3)
        ->and($workflow->getStep('livewire-step')->component)->toBe(TestStepOneComponent::class)
        ->and($workflow->getStep('controller-step')->component)->toBe(TestInvokableController::class)
        ->and($workflow->getStep('method-controller-step')->component)->toBe([TestMethodController::class, 'show']);
});

test('routes are registered correctly for invokable controllers', function () {
    $builder = Workflow::flow('test-flow')
        ->entersAt(name: 'test-flow.start', path: '/test-flow')
        ->finishesAt('dashboard')
        ->step('controller-step')
        ->goTo(TestInvokableController::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    $builder->build();

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');

    $routeRegistrar = app(Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class);
    $routeRegistrar->register();

    app('router')->getRoutes()->refreshNameLookups();

    expect(Route::has('test-flow.controller-step'))->toBeTrue();

    $route = Route::getRoutes()->getByName('test-flow.controller-step');
    expect($route)->not->toBeNull()
        ->and($route->getActionName())->toContain('TestInvokableController');
});

test('routes are registered correctly for controller methods', function () {
    $builder = Workflow::flow('test-flow')
        ->entersAt(name: 'test-flow.start', path: '/test-flow')
        ->finishesAt('dashboard')
        ->step('method-step')
        ->goTo([TestMethodController::class, 'show'])
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    $builder->build();

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');

    $routeRegistrar = app(Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class);
    $routeRegistrar->register();

    app('router')->getRoutes()->refreshNameLookups();

    expect(Route::has('test-flow.method-step'))->toBeTrue();

    $route = Route::getRoutes()->getByName('test-flow.method-step');
    expect($route)->not->toBeNull()
        ->and($route->getActionName())->toContain('TestMethodController')
        ->and($route->getActionName())->toContain('show');
});

test('workflow navigation works with controllers', function () {
    Workflow::flow('test-flow')
        ->entersAt(name: 'test-flow.start', path: '/test-flow')
        ->finishesAt('dashboard')
        ->step('controller-step')
        ->goTo(TestInvokableController::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    $resolver = app(Pixelworxio\LivewireWorkflows\Support\WorkflowResolver::class);
    $nextRoute = $resolver->nextRouteNameFor('test-flow', request());

    expect($nextRoute)->toBe('test-flow.controller-step');
});

test('validates that controller class exists for invokable', function () {
    Workflow::flow('test-flow')
        ->entersAt(name: 'test-flow.start', path: '/test-flow')
        ->finishesAt('dashboard')
        ->step('controller-step')
        ->goTo('NonExistentController')
        ->order(10);

    $registrar = app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class);

    expect(fn () => $registrar->get('test-flow'))
        ->toThrow(Pixelworxio\LivewireWorkflows\Exceptions\InvalidWorkflowConfigurationException::class);
});

test('validates that controller class exists for method array', function () {
    Workflow::flow('test-flow')
        ->entersAt(name: 'test-flow.start', path: '/test-flow')
        ->finishesAt('dashboard')
        ->step('controller-step')
        ->goTo(['NonExistentController', 'show'])
        ->order(10);

    $registrar = app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class);

    expect(fn () => $registrar->get('test-flow'))
        ->toThrow(Pixelworxio\LivewireWorkflows\Exceptions\InvalidWorkflowConfigurationException::class);
});
