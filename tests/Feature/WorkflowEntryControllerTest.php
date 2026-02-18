<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar;
use Tests\Support\FailingGuard;
use Tests\Support\TestStepOneComponent;

beforeEach(function () {
    app(WorkflowRegistrar::class)->clear();
});

test('redirects to next step when workflow has a failing guard', function () {
    Workflow::flow('test-flow')
        ->entersAt(name: 'test-flow.start', path: '/test-flow-entry')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');

    app(Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class)->register();
    app('router')->getRoutes()->refreshNameLookups();

    $response = $this->get('/test-flow-entry');

    $response->assertRedirect(route('test-flow.step-one'));
});
