<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Pixelworxio\LivewireWorkflows\Commands\WorkflowsAuditCommand;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar;
use Tests\Support\FailingGuard;
use Tests\Support\PassingGuard;
use Tests\Support\TestMethodController;
use Tests\Support\TestStepOneComponent;
use Tests\Support\TestStepTwoComponent;

beforeEach(function () {
    app(WorkflowRegistrar::class)->clear();
    Route::get('/dashboard', fn () => 'ok')->name('dashboard');
    app('router')->getRoutes()->refreshNameLookups();
});

// ─── No workflows ───────────────────────────────────────────────────────────

test('outputs warning and succeeds when no workflows are registered', function () {
    $this->artisan(WorkflowsAuditCommand::class)
        ->assertSuccessful()
        ->expectsOutputToContain('No workflows registered');
});

// ─── Finish route checks ─────────────────────────────────────────────────────

test('fails when finish route is not registered', function () {
    Workflow::flow('checkout')
        ->entersAt(name: 'checkout.start', path: '/checkout')
        ->finishesAt('nonexistent.route')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    app(WorkflowRegistrar::class)->get('checkout');

    $exitCode = Artisan::call(WorkflowsAuditCommand::class);
    $output = Artisan::output();

    expect($exitCode)->toBe(1);
    expect($output)->toContain('nonexistent.route');
    expect($output)->toContain('NOT registered');
});

test('passes finish route check when route is registered', function () {
    Workflow::flow('checkout')
        ->entersAt(name: 'checkout.start', path: '/checkout')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    app(WorkflowRegistrar::class)->get('checkout');

    $this->artisan(WorkflowsAuditCommand::class)
        ->assertSuccessful()
        ->expectsOutputToContain("'dashboard' is registered");
});

// ─── Component checks ────────────────────────────────────────────────────────

test('passes component check for valid string component class', function () {
    Workflow::flow('onboarding')
        ->entersAt(name: 'onboarding.start', path: '/onboarding')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    app(WorkflowRegistrar::class)->get('onboarding');

    $this->artisan(WorkflowsAuditCommand::class)
        ->assertSuccessful()
        ->expectsOutputToContain('TestStepOneComponent');
});

test('passes component check for valid array component with class and method', function () {
    Workflow::flow('onboarding')
        ->entersAt(name: 'onboarding.start', path: '/onboarding')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo([TestMethodController::class, 'show'])
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    app(WorkflowRegistrar::class)->get('onboarding');

    $this->artisan(WorkflowsAuditCommand::class)
        ->assertSuccessful()
        ->expectsOutputToContain('TestMethodController@show');
});

// ─── Guard checks ────────────────────────────────────────────────────────────

test('shows no guard configured for steps without a guard', function () {
    Workflow::flow('onboarding')
        ->entersAt(name: 'onboarding.start', path: '/onboarding')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->order(10);

    app(WorkflowRegistrar::class)->get('onboarding');

    $this->artisan(WorkflowsAuditCommand::class)
        ->assertSuccessful()
        ->expectsOutputToContain('none configured');
});

test('passes guard check for valid guard class implementing GuardContract', function () {
    Workflow::flow('onboarding')
        ->entersAt(name: 'onboarding.start', path: '/onboarding')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    app(WorkflowRegistrar::class)->get('onboarding');

    $this->artisan(WorkflowsAuditCommand::class)
        ->assertSuccessful()
        ->expectsOutputToContain('FailingGuard');
});

// ─── All checks pass ─────────────────────────────────────────────────────────

test('succeeds and reports no issues when all checks pass', function () {
    Workflow::flow('onboarding')
        ->entersAt(name: 'onboarding.start', path: '/onboarding')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10)
        ->step('step-two')
        ->goTo(TestStepTwoComponent::class)
        ->unlessPasses(PassingGuard::class)
        ->order(20);

    app(WorkflowRegistrar::class)->get('onboarding');

    $exitCode = Artisan::call(WorkflowsAuditCommand::class);
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('All checks passed');
    expect($output)->toContain('no issues found');
});

// ─── Orphaned steps ──────────────────────────────────────────────────────────

test('orphan section reports no components found when app/Livewire does not exist', function () {
    // Test environment has no app/Livewire directory by default
    Workflow::flow('onboarding')
        ->entersAt(name: 'onboarding.start', path: '/onboarding')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    app(WorkflowRegistrar::class)->get('onboarding');

    $this->artisan(WorkflowsAuditCommand::class)
        ->assertSuccessful()
        ->expectsOutputToContain('No components with #[WorkflowStep] found');
});

// ─── Summary ─────────────────────────────────────────────────────────────────

test('summary shows correct workflow count', function () {
    Workflow::flow('onboarding')
        ->entersAt(name: 'onboarding.start', path: '/onboarding')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    app(WorkflowRegistrar::class)->get('onboarding');

    $this->artisan(WorkflowsAuditCommand::class)
        ->assertSuccessful()
        ->expectsOutputToContain('1 workflow(s) audited');
});

test('summary uses singular issue for exactly one issue', function () {
    Workflow::flow('checkout')
        ->entersAt(name: 'checkout.start', path: '/checkout')
        ->finishesAt('nonexistent.route')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    app(WorkflowRegistrar::class)->get('checkout');

    $this->artisan(WorkflowsAuditCommand::class)
        ->assertFailed()
        ->expectsOutputToContain('1 issue found');
});

test('summary uses plural issues for more than one issue', function () {
    Workflow::flow('checkout')
        ->entersAt(name: 'checkout.start', path: '/checkout')
        ->finishesAt('nonexistent.route')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    Workflow::flow('onboarding')
        ->entersAt(name: 'onboarding.start', path: '/onboarding')
        ->finishesAt('another.missing.route')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    app(WorkflowRegistrar::class)->get('checkout');
    app(WorkflowRegistrar::class)->get('onboarding');

    $exitCode = Artisan::call(WorkflowsAuditCommand::class);
    $output = Artisan::output();

    expect($exitCode)->toBe(1);
    expect($output)->toContain('2 workflow(s) audited');
    expect($output)->toContain('2 issues found');
});

test('multiple issues within a single workflow are all reported', function () {
    Workflow::flow('checkout')
        ->entersAt(name: 'checkout.start', path: '/checkout')
        ->finishesAt('missing.one')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->order(10)
        ->step('step-two')
        ->goTo(TestStepTwoComponent::class)
        ->order(20);

    app(WorkflowRegistrar::class)->get('checkout');

    $this->artisan(WorkflowsAuditCommand::class)
        ->assertFailed()
        ->expectsOutputToContain('missing.one');
});

// ─── Output structure ────────────────────────────────────────────────────────

test('displays workflow entry and finish route in output', function () {
    Workflow::flow('onboarding')
        ->entersAt(name: 'onboarding.start', path: '/onboarding')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    app(WorkflowRegistrar::class)->get('onboarding');

    $exitCode = Artisan::call(WorkflowsAuditCommand::class);
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('onboarding');
    expect($output)->toContain('/onboarding');
    expect($output)->toContain('dashboard');
});

test('displays step key and order in output', function () {
    Workflow::flow('onboarding')
        ->entersAt(name: 'onboarding.start', path: '/onboarding')
        ->finishesAt('dashboard')
        ->step('verify-email')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(15);

    app(WorkflowRegistrar::class)->get('onboarding');

    $exitCode = Artisan::call(WorkflowsAuditCommand::class);
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('verify-email');
    expect($output)->toContain('15');
});
