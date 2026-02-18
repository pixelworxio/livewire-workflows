<?php

declare(strict_types=1);

use Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar;
use Pixelworxio\LivewireWorkflows\Support\StepDefinition;
use Pixelworxio\LivewireWorkflows\Support\WorkflowDefinition;
use Pixelworxio\LivewireWorkflows\Support\WorkflowEngine;
use Tests\Support\FailingGuard;
use Tests\Support\PassingGuard;
use Tests\Support\TestStepOneComponent;
use Tests\Support\TestStepTwoComponent;

beforeEach(function () {
    app(WorkflowRegistrar::class)->clear();
});

test('nextStep() skips steps without a guard', function () {
    // Step without a guard should be skipped (continues to next step)
    $definition = new WorkflowDefinition(
        flow: 'test',
        entryRouteName: 'test.start',
        entryPath: '/test',
        finishRoute: 'dashboard',
        historyMode: 'none',
        steps: [
            'step-no-guard' => new StepDefinition(
                key: 'step-no-guard',
                flow: 'test',
                component: TestStepOneComponent::class,
                guardClass: null,  // No guard
                order: 10,
            ),
            'step-with-guard' => new StepDefinition(
                key: 'step-with-guard',
                flow: 'test',
                component: TestStepTwoComponent::class,
                guardClass: FailingGuard::class,  // Failing guard - this step should be shown
                order: 20,
            ),
        ],
    );

    $engine = app(WorkflowEngine::class);
    $nextStep = $engine->nextStep($definition, request());

    // Should skip the no-guard step and return the failing guard step
    expect($nextStep)->toBe('step-with-guard');
});

test('nextStep() returns null when all steps either have no guard or passing guards', function () {
    $definition = new WorkflowDefinition(
        flow: 'test',
        entryRouteName: 'test.start',
        entryPath: '/test',
        finishRoute: 'dashboard',
        historyMode: 'none',
        steps: [
            'step-no-guard' => new StepDefinition(
                key: 'step-no-guard',
                flow: 'test',
                component: TestStepOneComponent::class,
                guardClass: null,  // No guard - skipped
                order: 10,
            ),
            'step-passing' => new StepDefinition(
                key: 'step-passing',
                flow: 'test',
                component: TestStepTwoComponent::class,
                guardClass: PassingGuard::class,  // Passing guard - step skipped
                order: 20,
            ),
        ],
    );

    $engine = app(WorkflowEngine::class);
    $nextStep = $engine->nextStep($definition, request());

    expect($nextStep)->toBeNull();
});

test('progress() counts steps without guard as completed', function () {
    $definition = new WorkflowDefinition(
        flow: 'test',
        entryRouteName: 'test.start',
        entryPath: '/test',
        finishRoute: 'dashboard',
        historyMode: 'none',
        steps: [
            'step-no-guard' => new StepDefinition(
                key: 'step-no-guard',
                flow: 'test',
                component: TestStepOneComponent::class,
                guardClass: null,  // No guard - counted as completed
                order: 10,
            ),
            'step-failing' => new StepDefinition(
                key: 'step-failing',
                flow: 'test',
                component: TestStepTwoComponent::class,
                guardClass: FailingGuard::class,
                order: 20,
            ),
        ],
    );

    $engine = app(WorkflowEngine::class);
    $progress = $engine->progress($definition, request());

    expect($progress['total'])->toBe(2)
        ->and($progress['completed'])->toBe(1)  // No-guard step is completed
        ->and($progress['remaining'])->toBe(1)
        ->and($progress['is_complete'])->toBeFalse();
});

test('progress() marks workflow complete when all steps have no guard or pass', function () {
    $definition = new WorkflowDefinition(
        flow: 'test',
        entryRouteName: 'test.start',
        entryPath: '/test',
        finishRoute: 'dashboard',
        historyMode: 'none',
        steps: [
            'step-no-guard' => new StepDefinition(
                key: 'step-no-guard',
                flow: 'test',
                component: TestStepOneComponent::class,
                guardClass: null,
                order: 10,
            ),
            'step-passing' => new StepDefinition(
                key: 'step-passing',
                flow: 'test',
                component: TestStepTwoComponent::class,
                guardClass: PassingGuard::class,
                order: 20,
            ),
        ],
    );

    $engine = app(WorkflowEngine::class);
    $progress = $engine->progress($definition, request());

    expect($progress['is_complete'])->toBeTrue()
        ->and($progress['completed'])->toBe(2);
});
