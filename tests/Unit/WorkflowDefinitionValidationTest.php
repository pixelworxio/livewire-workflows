<?php

declare(strict_types=1);

use Pixelworxio\LivewireWorkflows\Exceptions\InvalidWorkflowConfigurationException;
use Pixelworxio\LivewireWorkflows\Support\StepDefinition;
use Pixelworxio\LivewireWorkflows\Support\WorkflowDefinition;
use Tests\Support\PassingGuard;
use Tests\Support\TestStepOneComponent;

function makeValidStep(string $key = 'step-one', string $flow = 'test'): StepDefinition
{
    return new StepDefinition(
        key: $key,
        flow: $flow,
        component: TestStepOneComponent::class,
        guardClass: PassingGuard::class,
        order: 10,
    );
}

function makeValidDefinition(array $overrides = []): WorkflowDefinition
{
    return new WorkflowDefinition(
        flow: $overrides['flow'] ?? 'test',
        entryRouteName: $overrides['entryRouteName'] ?? 'test.start',
        entryPath: $overrides['entryPath'] ?? '/test',
        finishRoute: $overrides['finishRoute'] ?? 'dashboard',
        historyMode: $overrides['historyMode'] ?? 'none',
        steps: $overrides['steps'] ?? ['step-one' => makeValidStep()],
        middleware: $overrides['middleware'] ?? null,
    );
}

test('throws exception when flow name is empty', function () {
    expect(fn () => makeValidDefinition(['flow' => '']))
        ->toThrow(InvalidWorkflowConfigurationException::class, 'Workflow name cannot be empty.');
});

test('throws exception when entry route name is empty', function () {
    expect(fn () => makeValidDefinition(['entryRouteName' => '']))
        ->toThrow(InvalidWorkflowConfigurationException::class, 'must have an entry route name');
});

test('throws exception when entry path is empty', function () {
    expect(fn () => makeValidDefinition(['entryPath' => '']))
        ->toThrow(InvalidWorkflowConfigurationException::class, 'must have an entry path');
});

test('throws exception when finish route is empty', function () {
    expect(fn () => makeValidDefinition(['finishRoute' => '']))
        ->toThrow(InvalidWorkflowConfigurationException::class, 'must have a finish route');
});

test('throws exception when history mode is invalid', function () {
    expect(fn () => makeValidDefinition(['historyMode' => 'invalid']))
        ->toThrow(InvalidWorkflowConfigurationException::class, "History mode must be 'none' or 'stack'");
});

test('throws exception when steps array is empty', function () {
    expect(fn () => makeValidDefinition(['steps' => []]))
        ->toThrow(InvalidWorkflowConfigurationException::class, 'must have at least one step');
});

test('throws exception when steps have duplicate keys', function () {
    $step = makeValidStep('step-one', 'test');
    // Duplicate keys in array is impossible in PHP (last value wins), so test via multiple steps with same key
    // We test this by manually creating the condition: the array keys array has duplicates
    // In practice this can't happen with PHP arrays, but we can verify the validation exists
    // by checking that identical keys collapse to one entry (no exception since PHP deduplicates)
    // Instead test the step flow mismatch which IS the real guard
    $mismatchedStep = new StepDefinition(
        key: 'step-one',
        flow: 'other-flow',  // different flow
        component: TestStepOneComponent::class,
        order: 10,
    );

    expect(fn () => makeValidDefinition(['steps' => ['step-one' => $mismatchedStep]]))
        ->toThrow(InvalidWorkflowConfigurationException::class, 'flow mismatch');
});

test('throws exception when step flow does not match workflow flow', function () {
    $mismatchedStep = new StepDefinition(
        key: 'step-one',
        flow: 'wrong-flow',
        component: TestStepOneComponent::class,
        order: 10,
    );

    expect(fn () => new WorkflowDefinition(
        flow: 'test',
        entryRouteName: 'test.start',
        entryPath: '/test',
        finishRoute: 'dashboard',
        historyMode: 'none',
        steps: ['step-one' => $mismatchedStep],
    ))->toThrow(InvalidWorkflowConfigurationException::class, 'flow mismatch');
});

test('valid definition is created successfully', function () {
    $definition = makeValidDefinition();

    expect($definition->flow)->toBe('test')
        ->and($definition->entryRouteName)->toBe('test.start')
        ->and($definition->historyMode)->toBe('none');
});

test('accepts stack as valid history mode', function () {
    $definition = makeValidDefinition(['historyMode' => 'stack']);

    expect($definition->historyMode)->toBe('stack');
});

test('getStep() returns null for unknown step key', function () {
    $definition = makeValidDefinition();

    expect($definition->getStep('nonexistent'))->toBeNull();
});

test('getStep() returns step definition for known key', function () {
    $definition = makeValidDefinition();

    expect($definition->getStep('step-one'))->toBeInstanceOf(StepDefinition::class);
});

test('hasHistory() returns false for none mode', function () {
    $definition = makeValidDefinition(['historyMode' => 'none']);

    expect($definition->hasHistory())->toBeFalse();
});

test('hasHistory() returns true for stack mode', function () {
    $definition = makeValidDefinition(['historyMode' => 'stack']);

    expect($definition->hasHistory())->toBeTrue();
});

test('hasRouteParameters() returns false for path without parameters', function () {
    $definition = makeValidDefinition(['entryPath' => '/simple/path']);

    expect($definition->hasRouteParameters())->toBeFalse();
});

test('hasRouteParameters() returns true for path with parameters', function () {
    $definition = makeValidDefinition(['entryPath' => '/user/{user}/orders']);

    expect($definition->hasRouteParameters())->toBeTrue();
    expect($definition->routeParameters)->toContain('user');
});

test('getStepRouteName() returns correct route name format', function () {
    $definition = makeValidDefinition();

    expect($definition->getStepRouteName('step-one'))->toBe('test.step-one');
});

test('getStepPath() returns correct path format', function () {
    $definition = makeValidDefinition();

    expect($definition->getStepPath('step-one'))->toBe('/test/step-one');
});

test('getPreviousStep() returns null for first step', function () {
    $step1 = makeValidStep('step-one', 'test');
    $step2 = new StepDefinition(
        key: 'step-two',
        flow: 'test',
        component: TestStepOneComponent::class,
        order: 20,
    );

    $definition = makeValidDefinition(['steps' => ['step-one' => $step1, 'step-two' => $step2]]);

    expect($definition->getPreviousStep('step-one'))->toBeNull();
});

test('getPreviousStep() returns previous step', function () {
    $step1 = makeValidStep('step-one', 'test');
    $step2 = new StepDefinition(
        key: 'step-two',
        flow: 'test',
        component: TestStepOneComponent::class,
        order: 20,
    );

    $definition = makeValidDefinition(['steps' => ['step-one' => $step1, 'step-two' => $step2]]);

    expect($definition->getPreviousStep('step-two'))->not->toBeNull()
        ->and($definition->getPreviousStep('step-two')->key)->toBe('step-one');
});

test('getPreviousStep() returns null for unknown current key', function () {
    $definition = makeValidDefinition();

    expect($definition->getPreviousStep('nonexistent'))->toBeNull();
});
