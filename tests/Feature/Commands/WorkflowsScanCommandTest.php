<?php

declare(strict_types=1);

use Pixelworxio\LivewireWorkflows\Commands\WorkflowsScanCommand;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar;
use Tests\Support\FailingGuard;
use Tests\Support\PassingGuard;
use Tests\Support\TestStepOneComponent;
use Tests\Support\TestStepTwoComponent;

beforeEach(function () {
    app(WorkflowRegistrar::class)->clear();
});

test('outputs warning when no components with WorkflowStep attribute are found', function () {
    // No app/Livewire directory in test environment
    $this->artisan(WorkflowsScanCommand::class)
        ->assertSuccessful()
        ->expectsOutputToContain('No components with #[WorkflowStep] attribute found');
});

test('shows no workflows registered message when none are registered', function () {
    $this->artisan(WorkflowsScanCommand::class)
        ->assertSuccessful()
        ->expectsOutputToContain('No workflows registered.');
});

test('displays registered workflows with their details', function () {
    Workflow::flow('onboarding')
        ->entersAt(name: 'onboarding.start', path: '/onboarding')
        ->finishesAt('dashboard')
        ->historyMode('stack')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10)
        ->step('step-two')
        ->goTo(TestStepTwoComponent::class)
        ->unlessPasses(PassingGuard::class)
        ->order(20);

    // Force registration
    app(WorkflowRegistrar::class)->get('onboarding');

    $this->artisan(WorkflowsScanCommand::class)
        ->assertSuccessful()
        ->expectsOutputToContain('onboarding')
        ->expectsOutputToContain('dashboard');
});

test('displays step details in workflow map', function () {
    Workflow::flow('test-flow')
        ->entersAt(name: 'test-flow.start', path: '/test-flow')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    app(WorkflowRegistrar::class)->get('test-flow');

    $this->artisan(WorkflowsScanCommand::class)
        ->assertSuccessful()
        ->expectsOutputToContain('step-one');
});

test('displays history mode in workflow map', function () {
    Workflow::flow('test-flow')
        ->entersAt(name: 'test-flow.start', path: '/test-flow')
        ->finishesAt('dashboard')
        ->historyMode('stack')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    app(WorkflowRegistrar::class)->get('test-flow');

    $this->artisan(WorkflowsScanCommand::class)
        ->assertSuccessful()
        ->expectsOutputToContain('stack');
});

test('displays guard info for steps with guards', function () {
    Workflow::flow('test-flow')
        ->entersAt(name: 'test-flow.start', path: '/test-flow')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    app(WorkflowRegistrar::class)->get('test-flow');

    $this->artisan(WorkflowsScanCommand::class)
        ->assertSuccessful()
        ->expectsOutputToContain('FailingGuard');
});

test('displays No guard for steps without guards', function () {
    Workflow::flow('test-flow')
        ->entersAt(name: 'test-flow.start', path: '/test-flow')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->order(10);

    app(WorkflowRegistrar::class)->get('test-flow');

    $this->artisan(WorkflowsScanCommand::class)
        ->assertSuccessful()
        ->expectsOutputToContain('No guard');
});

test('scanning outputs route names for steps', function () {
    Workflow::flow('checkout')
        ->entersAt(name: 'checkout.start', path: '/checkout')
        ->finishesAt('dashboard')
        ->step('payment')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    app(WorkflowRegistrar::class)->get('checkout');

    $this->artisan(WorkflowsScanCommand::class)
        ->assertSuccessful()
        ->expectsOutputToContain('checkout.payment');
});
