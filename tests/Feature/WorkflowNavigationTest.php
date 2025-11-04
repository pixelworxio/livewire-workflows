<?php

declare(strict_types=1);

use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Pixelworxio\LivewireWorkflows\Support\WorkflowResolver;

beforeEach(function () {
    app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->clear();

    Workflow::flow('onboarding')
        ->entersAt(name: 'onboarding.start', path: '/onboarding')
        ->finishesAt('dashboard')
        ->historyMode('stack')
        ->step('step-one')
            ->goTo(Tests\Support\TestStepOneComponent::class)
            ->unlessPasses(Tests\Support\TestStepOneGuard::class)
            ->order(10)
        ->step('step-two')
            ->goTo(Tests\Support\TestStepTwoComponent::class)
            ->unlessPasses(Tests\Support\TestStepTwoGuard::class)
            ->order(20)
        ->step('step-three')
            ->goTo(Tests\Support\TestStepOneComponent::class)
            ->unlessPasses(Tests\Support\TestStepThreeGuard::class)
            ->order(30);

    $this->resolver = app(WorkflowResolver::class);
});

test('can get next route name', function () {
    Tests\Support\TestStepOneGuard::$shouldPass = false;

    $nextRoute = $this->resolver->nextRouteNameFor('onboarding', request());

    expect($nextRoute)->toBe('onboarding.step-one');
});

test('can get previous route name', function () {
    $previousRoute = $this->resolver->previousRouteNameFor('onboarding', 'step-two', request());

    expect($previousRoute)->toBe('onboarding.step-one');
});

test('previous route returns null for first step', function () {
    $previousRoute = $this->resolver->previousRouteNameFor('onboarding', 'step-one', request());

    expect($previousRoute)->toBeNull();
});

test('can get workflow progress', function () {
    Tests\Support\TestStepOneGuard::$shouldPass = true;
    Tests\Support\TestStepTwoGuard::$shouldPass = false;
    Tests\Support\TestStepThreeGuard::$shouldPass = false;

    $progress = $this->resolver->progressFor('onboarding', request());

    expect($progress['total'])->toBe(3)
        ->and($progress['completed'])->toBe(1)
        ->and($progress['remaining'])->toBe(2)
        ->and($progress['percentage'])->toBe(33.33);
});

test('helper function works', function () {
    Tests\Support\TestStepOneGuard::$shouldPass = false;

    $nextRoute = workflow('onboarding')->nextRouteNameFor(request());

    expect($nextRoute)->toBe('onboarding.step-one');
});
