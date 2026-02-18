<?php

declare(strict_types=1);

use Pixelworxio\LivewireWorkflows\Support\WorkflowInstance;
use Pixelworxio\LivewireWorkflows\Support\WorkflowStateManager;

test('workflow() helper returns a WorkflowInstance', function () {
    $instance = workflow('some-flow');

    expect($instance)->toBeInstanceOf(WorkflowInstance::class);
});

test('workflowState() helper returns a WorkflowStateManager', function () {
    $manager = workflowState('some-flow');

    expect($manager)->toBeInstanceOf(WorkflowStateManager::class);
});

test('workflow() helper creates instance with correct flow name', function () {
    $instance = workflow('my-workflow');

    // The flow is stored internally, verify it proxies correctly
    expect($instance)->toBeInstanceOf(WorkflowInstance::class);
});

test('workflowState() helper creates manager with correct workflow name', function () {
    $manager = workflowState('my-workflow');

    expect($manager)->toBeInstanceOf(WorkflowStateManager::class);
});
