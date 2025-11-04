<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Livewire\Livewire;

beforeEach(function () {
    app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->clear();

    $builder = Workflow::flow('onboarding')
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

    $builder->build();

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');

    app(Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class)->register();

    // Refresh route collection
    app('router')->getRoutes()->refreshNameLookups();
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
    Livewire::test(Tests\Support\TestStepOneComponent::class)
        ->assertOk();
});
