<?php

declare(strict_types=1);

use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\StateRepositories\EloquentWorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\StateRepositories\NullStateRepository;
use Pixelworxio\LivewireWorkflows\StateRepositories\SessionWorkflowStateRepository;

test('null repository returns empty values', function () {
    $repo = new NullStateRepository();

    expect($repo->getCurrentStep('test-flow', 'user-1'))->toBeNull()
        ->and($repo->getHistory('test-flow', 'user-1'))->toBe([])
        ->and($repo->getMetadata('test-flow', 'user-1'))->toBe([]);
});

test('session repository persists current step', function () {
    $repo = app(SessionWorkflowStateRepository::class);

    $repo->setCurrentStep('test-flow', 'user-1', 'step-one');

    expect($repo->getCurrentStep('test-flow', 'user-1'))->toBe('step-one');
});

test('session repository tracks history', function () {
    $repo = app(SessionWorkflowStateRepository::class);

    $repo->pushHistory('test-flow', 'user-1', 'step-one');
    $repo->pushHistory('test-flow', 'user-1', 'step-two');

    $history = $repo->getHistory('test-flow', 'user-1');

    expect($history)->toBe(['step-one', 'step-two']);
});

test('session repository pops history', function () {
    $repo = app(SessionWorkflowStateRepository::class);

    $repo->pushHistory('test-flow', 'user-1', 'step-one');
    $repo->pushHistory('test-flow', 'user-1', 'step-two');

    $popped = $repo->popHistory('test-flow', 'user-1');

    expect($popped)->toBe('step-two');

    $history = $repo->getHistory('test-flow', 'user-1');
    expect($history)->toBe(['step-one']);
});

test('session repository stores metadata', function () {
    $repo = app(SessionWorkflowStateRepository::class);

    $repo->setMetadata('test-flow', 'user-1', ['key' => 'value']);

    expect($repo->getMetadata('test-flow', 'user-1'))->toBe(['key' => 'value']);
});

test('eloquent repository persists current step', function () {
    $repo = app(EloquentWorkflowStateRepository::class);

    $repo->setCurrentStep('test-flow', 1, 'step-one');

    expect($repo->getCurrentStep('test-flow', 1))->toBe('step-one');

    $this->assertDatabaseHas('workflow_states', [
        'workflow_name' => 'test-flow',
        'user_key' => '1',
        'current_step' => 'step-one',
    ]);
});

test('eloquent repository tracks history', function () {
    $repo = app(EloquentWorkflowStateRepository::class);

    $repo->pushHistory('test-flow', 1, 'step-one');
    $repo->pushHistory('test-flow', 1, 'step-two');

    $history = $repo->getHistory('test-flow', 1);

    expect($history)->toBe(['step-one', 'step-two']);
});

test('eloquent repository clears state', function () {
    $repo = app(EloquentWorkflowStateRepository::class);

    $repo->setCurrentStep('test-flow', 1, 'step-one');
    $repo->pushHistory('test-flow', 1, 'step-one');

    $repo->clear('test-flow', 1);

    expect($repo->getCurrentStep('test-flow', 1))->toBeNull();

    $this->assertDatabaseMissing('workflow_states', [
        'workflow_name' => 'test-flow',
        'user_key' => '1',
    ]);
});
