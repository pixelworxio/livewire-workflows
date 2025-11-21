<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Tests\Support\TestComponentWithoutWorkflowName;
use Tests\Support\TestComponentWithProtectedProperty;
use Tests\Support\TestComponentWithoutAttribute;

beforeEach(function () {
    app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->clear();

    $builder = Workflow::flow('test-flow')
        ->entersAt(name: 'test.start', path: '/onboarding')
        ->finishesAt('dashboard')
        ->historyMode('stack')
        ->step('verify-email')
        ->goTo(Tests\Support\TestComponentWithState::class)
        ->unlessPasses(Tests\Support\TestStepOneGuard::class)
        ->order(10);

    $builder->build();

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');

    app(Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class)->register();

    // Refresh route collection
    app('router')->getRoutes()->refreshNameLookups();
});

test('properties without WorkflowState attribute are not synced', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    Livewire::test(TestComponentWithoutAttribute::class)
        ->call('updateProperties');

    // The property without attribute should not be in state
    expect($repository->hasState('test-flow', $userKey, 'notTracked'))->toBeFalse()
        // But the property with attribute should be
        ->and($repository->getState('test-flow', $userKey, 'email'))->toBe('tracked@example.com');
});

test('protected properties are not synced even with attribute', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    Livewire::test(TestComponentWithProtectedProperty::class)
        ->call('updateProtectedProperty');

    // Protected properties should not be synced (reflection filters by IS_PUBLIC)
    expect($repository->hasState('test-flow', $userKey, 'protectedProp'))->toBeFalse();
});

test('dehydration skipped when workflow name is null', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    // Component without workflow name set
    Livewire::test(TestComponentWithoutWorkflowName::class)
        ->set('email', 'null-workflow@example.com');

    // State should not be saved since workflow name is null
    expect($repository->hasState('test-flow', $userKey, 'email'))->toBeFalse();
});
