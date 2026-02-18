<?php

declare(strict_types=1);

use Pixelworxio\LivewireWorkflows\Contracts\GuardContract;
use Pixelworxio\LivewireWorkflows\Exceptions\InvalidWorkflowConfigurationException;
use Pixelworxio\LivewireWorkflows\Support\StepDefinition;
use Tests\Support\PassingGuard;
use Tests\Support\TestMethodController;
use Tests\Support\TestStepOneComponent;

test('throws exception when component is null', function () {
    expect(fn () => new StepDefinition(
        key: 'step-one',
        flow: 'test',
        component: null,
    ))->toThrow(InvalidWorkflowConfigurationException::class, 'must have a component');
});

test('throws exception when array component has wrong count', function () {
    expect(fn () => new StepDefinition(
        key: 'step-one',
        flow: 'test',
        component: [TestMethodController::class],  // Only 1 item, needs 2
    ))->toThrow(InvalidWorkflowConfigurationException::class, "must be [ClassName, 'methodName']");
});

test('throws exception when array component has non-string class name', function () {
    expect(fn () => new StepDefinition(
        key: 'step-one',
        flow: 'test',
        component: [123, 'show'],  // Non-string class name
    ))->toThrow(InvalidWorkflowConfigurationException::class, "must be [ClassName, 'methodName']");
});

test('throws exception when array component has non-string method name', function () {
    expect(fn () => new StepDefinition(
        key: 'step-one',
        flow: 'test',
        component: [TestMethodController::class, 123],  // Non-string method name
    ))->toThrow(InvalidWorkflowConfigurationException::class, "must be [ClassName, 'methodName']");
});

test('throws exception when array component class does not exist', function () {
    expect(fn () => new StepDefinition(
        key: 'step-one',
        flow: 'test',
        component: ['NonExistentClass', 'show'],
    ))->toThrow(InvalidWorkflowConfigurationException::class, 'does not exist');
});

test('throws exception when array component method does not exist', function () {
    expect(fn () => new StepDefinition(
        key: 'step-one',
        flow: 'test',
        component: [TestMethodController::class, 'nonExistentMethod'],
    ))->toThrow(InvalidWorkflowConfigurationException::class, 'does not exist on class');
});

test('throws exception when string component class does not exist', function () {
    expect(fn () => new StepDefinition(
        key: 'step-one',
        flow: 'test',
        component: 'NonExistentComponent',
    ))->toThrow(InvalidWorkflowConfigurationException::class, 'does not exist');
});

test('throws exception when guard class does not exist', function () {
    expect(fn () => new StepDefinition(
        key: 'step-one',
        flow: 'test',
        component: TestStepOneComponent::class,
        guardClass: 'NonExistentGuard',
    ))->toThrow(InvalidWorkflowConfigurationException::class, 'does not exist');
});

test('throws exception when guard class does not implement GuardContract', function () {
    // Use a class that exists but doesn't implement GuardContract
    expect(fn () => new StepDefinition(
        key: 'step-one',
        flow: 'test',
        component: TestStepOneComponent::class,
        guardClass: TestStepOneComponent::class,  // Component doesn't implement GuardContract
    ))->toThrow(InvalidWorkflowConfigurationException::class, 'must implement GuardContract');
});

test('valid step with string component is created successfully', function () {
    $step = new StepDefinition(
        key: 'step-one',
        flow: 'test',
        component: TestStepOneComponent::class,
        order: 10,
    );

    expect($step->key)->toBe('step-one')
        ->and($step->flow)->toBe('test')
        ->and($step->component)->toBe(TestStepOneComponent::class)
        ->and($step->order)->toBe(10);
});

test('valid step with array component is created successfully', function () {
    $step = new StepDefinition(
        key: 'step-one',
        flow: 'test',
        component: [TestMethodController::class, 'show'],
        order: 10,
    );

    expect($step->component)->toBe([TestMethodController::class, 'show']);
});

test('hasGuard() returns false when no guard class', function () {
    $step = new StepDefinition(
        key: 'step-one',
        flow: 'test',
        component: TestStepOneComponent::class,
    );

    expect($step->hasGuard())->toBeFalse();
});

test('hasGuard() returns true when guard class is set', function () {
    $step = new StepDefinition(
        key: 'step-one',
        flow: 'test',
        component: TestStepOneComponent::class,
        guardClass: PassingGuard::class,
    );

    expect($step->hasGuard())->toBeTrue();
});

test('getGuard() returns null when no guard class', function () {
    $step = new StepDefinition(
        key: 'step-one',
        flow: 'test',
        component: TestStepOneComponent::class,
    );

    expect($step->getGuard())->toBeNull();
});

test('getGuard() returns guard instance when guard class is set', function () {
    $step = new StepDefinition(
        key: 'step-one',
        flow: 'test',
        component: TestStepOneComponent::class,
        guardClass: PassingGuard::class,
    );

    $guard = $step->getGuard();

    expect($guard)->toBeInstanceOf(GuardContract::class)
        ->and($guard)->toBeInstanceOf(PassingGuard::class);
});
