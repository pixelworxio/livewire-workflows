<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;

beforeEach(function () {
    app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->clear();

    Workflow::flow('onboarding')
        ->entersAt(name: 'onboarding.start', path: '/onboarding')
        ->finishesAt('dashboard')
        ->historyMode('stack')
        ->step('verify-email')
        ->goTo(Tests\Support\TestStepOneComponent::class)
        ->unlessPasses(Tests\Support\TestStepOneGuard::class)
        ->order(10)
        ->step('profile')
        ->goTo(Tests\Support\TestStepTwoComponent::class)
        ->unlessPasses(Tests\Support\TestStepTwoGuard::class)
        ->order(20);

    // Register routes
    app(Pixelworxio\LivewireWorkflows\LivewireWorkflowsServiceProvider::class)->registerRoutes();

    // Create dashboard route for finish
    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');
});

test('entry route is registered', function () {
    expect(Route::has('onboarding.start'))->toBeTrue();
});

test('step routes are registered', function () {
    expect(Route::has('onboarding.verify-email'))->toBeTrue()
        ->and(Route::has('onboarding.profile'))->toBeTrue();
});

test('entry route redirects to first unmet step', function () {
    Tests\Support\TestStepOneGuard::$shouldPass = false;
    Tests\Support\TestStepTwoGuard::$shouldPass = true;

    $response = $this->get('/onboarding');

    $response->assertRedirect(route('onboarding.verify-email'));
});

test('entry route redirects to finish when complete', function () {
    Tests\Support\TestStepOneGuard::$shouldPass = true;
    Tests\Support\TestStepTwoGuard::$shouldPass = true;

    $response = $this->get('/onboarding');

    $response->assertRedirect(route('dashboard'));
});

test('step routes render livewire components', function () {
    $response = $this->get(route('onboarding.verify-email'));

    $response->assertOk();
});
