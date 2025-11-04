<?php

declare(strict_types=1);

use Pixelworxio\LivewireWorkflows\Exceptions\InvalidWorkflowConfigurationException;
use Pixelworxio\LivewireWorkflows\Exceptions\WorkflowNotFoundException;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar;

beforeEach(function () {
    $this->registrar = app(WorkflowRegistrar::class);
    $this->registrar->clear();
});

test('can register a workflow with DSL', function () {
    Workflow::flow('onboarding')
        ->entersAt(name: 'onboarding.start', path: '/onboarding')
        ->finishesAt('dashboard')
        ->historyMode('stack')
        ->step('verify-email')
            ->goTo(Tests\Support\TestStepOneComponent::class)
            ->unlessPasses(Tests\Support\TestStepOneGuard::class)
            ->order(10);

    expect($this->registrar->has('onboarding'))->toBeTrue();

    $workflow = $this->registrar->get('onboarding');

    expect($workflow->flow)->toBe('onboarding')
        ->and($workflow->entryRouteName)->toBe('onboarding.start')
        ->and($workflow->entryPath)->toBe('/onboarding')
        ->and($workflow->finishRoute)->toBe('dashboard')
        ->and($workflow->historyMode)->toBe('stack')
        ->and($workflow->steps)->toHaveKey('verify-email');
});

test('workflow generates correct step route names', function () {
    Workflow::flow('onboarding')
        ->entersAt(name: 'onboarding.start', path: '/onboarding')
        ->finishesAt('dashboard')
        ->step('verify-email')
            ->goTo(Tests\Support\TestStepOneComponent::class)
            ->order(10);

    $workflow = $this->registrar->get('onboarding');

    expect($workflow->getStepRouteName('verify-email'))->toBe('onboarding.verify-email');
});

test('workflow generates correct step paths', function () {
    Workflow::flow('onboarding')
        ->entersAt(name: 'onboarding.start', path: '/onboarding')
        ->finishesAt('dashboard')
        ->step('verify-email')
            ->goTo(Tests\Support\TestStepOneComponent::class)
            ->order(10);

    $workflow = $this->registrar->get('onboarding');

    expect($workflow->getStepPath('verify-email'))->toBe('/onboarding/verify-email');
});

test('throws exception for missing workflow', function () {
    $this->registrar->get('nonexistent');
})->throws(WorkflowNotFoundException::class);

test('validates workflow configuration', function () {
    Workflow::flow('invalid')
        ->finishesAt('dashboard');
})->throws(InvalidWorkflowConfigurationException::class);

test('orders steps correctly', function () {
    Workflow::flow('test')
        ->entersAt(name: 'test.start', path: '/test')
        ->finishesAt('dashboard')
        ->step('step-three')
            ->goTo(Tests\Support\TestStepOneComponent::class)
            ->order(30)
        ->step('step-one')
            ->goTo(Tests\Support\TestStepOneComponent::class)
            ->order(10)
        ->step('step-two')
            ->goTo(Tests\Support\TestStepOneComponent::class)
            ->order(20);

    $workflow = $this->registrar->get('test');
    $orderedSteps = $workflow->getOrderedSteps();

    expect($orderedSteps[0]->key)->toBe('step-one')
        ->and($orderedSteps[1]->key)->toBe('step-two')
        ->and($orderedSteps[2]->key)->toBe('step-three');
});

test('finds previous step correctly', function () {
    Workflow::flow('test')
        ->entersAt(name: 'test.start', path: '/test')
        ->finishesAt('dashboard')
        ->step('step-one')
            ->goTo(Tests\Support\TestStepOneComponent::class)
            ->order(10)
        ->step('step-two')
            ->goTo(Tests\Support\TestStepOneComponent::class)
            ->order(20)
        ->step('step-three')
            ->goTo(Tests\Support\TestStepOneComponent::class)
            ->order(30);

    $workflow = $this->registrar->get('test');

    $previous = $workflow->getPreviousStep('step-two');
    expect($previous->key)->toBe('step-one');

    $noPrevious = $workflow->getPreviousStep('step-one');
    expect($noPrevious)->toBeNull();
});
