<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Pixelworxio\LivewireWorkflows\Events\WorkflowAdvanced;
use Pixelworxio\LivewireWorkflows\Events\WorkflowCompleted;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;

beforeEach(function () {
    Event::fake();

    app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->clear();

    Workflow::flow('onboarding')
        ->entersAt(name: 'onboarding.start', path: '/onboarding')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(Tests\Support\TestStepOneComponent::class)
        ->unlessPasses(Tests\Support\TestStepOneGuard::class)
        ->order(10);

    app(Pixelworxio\LivewireWorkflows\LivewireWorkflowsServiceProvider::class)->registerRoutes();
    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');
});

test('fires advanced event when moving to step', function () {
    Tests\Support\TestStepOneGuard::$shouldPass = false;

    $this->get('/onboarding');

    Event::assertDispatched(WorkflowAdvanced::class, function ($event) {
        return $event->flow === 'onboarding'
            && $event->toKey === 'step-one';
    });
});

test('fires completed event when workflow finishes', function () {
    Tests\Support\TestStepOneGuard::$shouldPass = true;

    $this->get('/onboarding');

    Event::assertDispatched(WorkflowCompleted::class, function ($event) {
        return $event->flow === 'onboarding';
    });
});
