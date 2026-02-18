<?php

declare(strict_types=1);

use Pixelworxio\LivewireWorkflows\Exceptions\InvalidWorkflowConfigurationException;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar;
use Pixelworxio\LivewireWorkflows\Support\WorkflowDefinition;
use Tests\Support\FailingGuard;
use Tests\Support\TestStepOneComponent;

beforeEach(function () {
    app(WorkflowRegistrar::class)->clear();
});

test('historyMode() throws exception for invalid mode', function () {
    expect(fn () => Workflow::flow('test')
        ->entersAt(name: 'test.start', path: '/test')
        ->historyMode('invalid-mode')
    )->toThrow(InvalidWorkflowConfigurationException::class, "History mode must be 'none' or 'stack'");
});

test('build() when already built returns existing workflow definition from registrar', function () {
    $flowBuilder = Workflow::flow('test')
        ->entersAt(name: 'test.start', path: '/test')
        ->finishesAt('dashboard');

    $stepBuilder = $flowBuilder->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    // Build via the flow builder (step chains back to flow builder's build)
    $definition1 = $flowBuilder->build();

    // Build again - should return same definition from registrar (isBuilt = true)
    $definition2 = $flowBuilder->build();

    expect($definition1)->toBeInstanceOf(WorkflowDefinition::class)
        ->and($definition2)->toBeInstanceOf(WorkflowDefinition::class)
        ->and($definition1->flow)->toBe($definition2->flow);
});

test('build() throws exception when entersAt has not been called', function () {
    $registrar = app(WorkflowRegistrar::class);
    $builder = new \Pixelworxio\LivewireWorkflows\Registrar\FlowBuilder('test2', $registrar);

    // No entersAt, no finishesAt - build should throw "must define an entry route"
    expect(fn () => $builder->build())
        ->toThrow(InvalidWorkflowConfigurationException::class, 'must define an entry route');
});

test('finishesAt() throws when entersAt not called first', function () {
    $registrar = app(WorkflowRegistrar::class);
    $builder = new \Pixelworxio\LivewireWorkflows\Registrar\FlowBuilder('test', $registrar);

    expect(fn () => $builder->finishesAt('dashboard'))
        ->toThrow(InvalidWorkflowConfigurationException::class, 'must define an entry route');
});

test('build() throws exception when finishesAt has not been called', function () {
    $registrar = app(WorkflowRegistrar::class);
    $builder = new \Pixelworxio\LivewireWorkflows\Registrar\FlowBuilder('test', $registrar);
    $builder->entersAt(name: 'test.start', path: '/test');

    expect(fn () => $builder->build())
        ->toThrow(InvalidWorkflowConfigurationException::class, 'must define a finish route');
});

test('middleware() can be set on flow builder', function () {
    $flowBuilder = Workflow::flow('test')
        ->entersAt(name: 'test.start', path: '/test')
        ->finishesAt('dashboard')
        ->middleware(['auth']);

    $flowBuilder->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    $definition = $flowBuilder->build();

    expect($definition->middleware)->toBe(['auth']);
});
