<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Pixelworxio\LivewireWorkflows\Support\WorkflowEngine;

beforeEach(function () {
    app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->clear();

    Workflow::flow('test-flow')
        ->entersAt(name: 'test.start', path: '/test-flow')
        ->finishesAt('dashboard')
        ->historyMode('stack')
        ->step('step-one')
            ->goTo(Tests\Support\TestStepOneComponent::class)
            ->unlessPasses(Tests\Support\TestStepOneGuard::class)
            ->order(10)
        ->step('step-two')
            ->goTo(Tests\Support\TestStepTwoComponent::class)
            ->unlessPasses(Tests\Support\TestStepTwoGuard::class)
            ->order(20);

    $this->workflow = app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->get('test-flow');
    $this->engine = app(WorkflowEngine::class);
    $this->request = Request::create('/test');
});

test('determines next step when first guard fails', function () {
    Tests\Support\TestStepOneGuard::$shouldPass = false;
    Tests\Support\TestStepTwoGuard::$shouldPass = true;

    $nextStep = $this->engine->nextStep($this->workflow, $this->request);

    expect($nextStep)->toBe('step-one');
});

test('determines next step when second guard fails', function () {
    Tests\Support\TestStepOneGuard::$shouldPass = true;
    Tests\Support\TestStepTwoGuard::$shouldPass = false;

    $nextStep = $this->engine->nextStep($this->workflow, $this->request);

    expect($nextStep)->toBe('step-two');
});

test('returns null when all guards pass', function () {
    Tests\Support\TestStepOneGuard::$shouldPass = true;
    Tests\Support\TestStepTwoGuard::$shouldPass = true;

    $nextStep = $this->engine->nextStep($this->workflow, $this->request);

    expect($nextStep)->toBeNull();
});

test('calculates progress correctly', function () {
    Tests\Support\TestStepOneGuard::$shouldPass = true;
    Tests\Support\TestStepTwoGuard::$shouldPass = false;

    $progress = $this->engine->progress($this->workflow, $this->request);

    expect($progress['total'])->toBe(2)
        ->and($progress['completed'])->toBe(1)
        ->and($progress['remaining'])->toBe(1)
        ->and($progress['percentage'])->toBe(50.0)
        ->and($progress['next_step'])->toBe('step-two')
        ->and($progress['is_complete'])->toBeFalse();
});

test('tracks history when advancing steps', function () {
    $repository = app(WorkflowStateRepository::class);

    $this->engine->advanceTo($this->workflow, 'step-one', $this->request);
    $history = $repository->getHistory('test-flow', session()->getId());

    expect($history)->toContain('step-one');

    $this->engine->advanceTo($this->workflow, 'step-two', $this->request);
    $history = $repository->getHistory('test-flow', session()->getId());

    expect($history)->toContain('step-one')
        ->and($history)->toContain('step-two');
});
