<?php

declare(strict_types=1);

use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Pixelworxio\LivewireWorkflows\Registrar\FlowBuilder;
use Pixelworxio\LivewireWorkflows\Registrar\StepBuilder;
use Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar;
use Tests\Support\FailingGuard;
use Tests\Support\TestStepOneComponent;

beforeEach(function () {
    app(WorkflowRegistrar::class)->clear();
});

test('__call() proxies known methods to FlowBuilder', function () {
    $builder = Workflow::flow('test')
        ->entersAt(name: 'test.start', path: '/test')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    // 'historyMode' is a method on FlowBuilder - proxied via StepBuilder.__call
    $result = $builder->historyMode('stack');

    // Should return the FlowBuilder (proxied call), not throw
    expect($result)->not->toBeNull();
});

test('__call() throws BadMethodCallException for unknown methods', function () {
    $registrar = app(WorkflowRegistrar::class);
    $flowBuilder = new FlowBuilder('test', $registrar);
    $stepBuilder = new StepBuilder('step-one', 'test', $flowBuilder);

    expect(fn () => $stepBuilder->nonExistentMethod())
        ->toThrow(BadMethodCallException::class, 'Method nonExistentMethod does not exist');
});

test('step() method chains back to flow builder for next step', function () {
    $flowBuilder = Workflow::flow('test')
        ->entersAt(name: 'test.start', path: '/test')
        ->finishesAt('dashboard');

    $flowBuilder->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10)
        ->step('step-two')  // This calls StepBuilder::step() which proxies to FlowBuilder::step()
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(20);

    $definition = $flowBuilder->build();

    expect($definition->steps)->toHaveCount(2)
        ->and(array_keys($definition->steps))->toContain('step-one')
        ->and(array_keys($definition->steps))->toContain('step-two');
});

test('middleware() can be set on step builder', function () {
    $flowBuilder = Workflow::flow('test')
        ->entersAt(name: 'test.start', path: '/test')
        ->finishesAt('dashboard');

    $flowBuilder->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->middleware(['auth'])
        ->order(10);

    $definition = $flowBuilder->build();
    $step = $definition->steps['step-one'];

    expect($step->middleware)->toBe(['auth']);
});
