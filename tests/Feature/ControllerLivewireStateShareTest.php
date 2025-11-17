<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Livewire\Livewire;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Tests\Support\FailingGuard;
use Tests\Support\PassingGuard;
use Tests\Support\TestControllerWithState;
use Tests\Support\TestComponentWithControllerState;
use Tests\Support\TestInvokableController;

beforeEach(function () {
    app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->clear();

    Workflow::flow('onboarding')
        ->entersAt(name: 'onboarding.start', path: '/onboarding')
        ->finishesAt('dashboard')
        ->step('controller-step')
        ->goTo(TestControllerWithState::class)
        ->unlessPasses(PassingGuard::class)
        ->order(10)
        ->step('component-step')
        ->goTo(TestComponentWithControllerState::class)
        ->unlessPasses(FailingGuard::class)
        ->order(20);
});

test('state set in controller is accessible in livewire component via WorkflowState attribute', function () {
    $request = Request::create('/test', 'GET');

    // Controller sets state
    workflowState('onboarding')
        ->forRequest($request)
        ->set('controller_name', 'John Doe')
        ->set('controller_email', 'john@example.com');

    // Simulate Livewire component mount
    $component = Livewire::test(TestComponentWithControllerState::class);

    // Component should have hydrated the state
    expect($component->controller_name)->toBe('John Doe')
        ->and($component->controller_email)->toBe('john@example.com');
});

test('repository state is accessible from both contexts', function () {
    // Direct repository access (simulating both controller and component)
    $repository = app(Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository::class);
    $userKey = 'test-user-456';

    // Simulate component setting state
    $repository->setState('onboarding', $userKey, 'controller_name', 'Jane Smith');
    $repository->setState('onboarding', $userKey, 'controller_email', 'jane@example.com');
    $repository->setState('onboarding', $userKey, 'component_data', 'test data');

    // Simulate controller reading state
    $name = $repository->getState('onboarding', $userKey, 'controller_name');
    $email = $repository->getState('onboarding', $userKey, 'controller_email');
    $data = $repository->getState('onboarding', $userKey, 'component_data');

    expect($name)->toBe('Jane Smith')
        ->and($email)->toBe('jane@example.com')
        ->and($data)->toBe('test data');
});

test('state persists via repository across different access patterns', function () {
    $repository = app(Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository::class);
    $userKey = 'test-user-123';

    // Step 1: Set initial state (simulating controller)
    $repository->setState('onboarding', $userKey, 'step', '1');
    $repository->setState('onboarding', $userKey, 'controller_name', 'Initial Name');

    // Step 2: Read and verify (simulating component read)
    $name = $repository->getState('onboarding', $userKey, 'controller_name');
    expect($name)->toBe('Initial Name');

    // Step 3: Update state (simulating component write)
    $repository->setState('onboarding', $userKey, 'controller_name', 'Updated by Component');
    $repository->setState('onboarding', $userKey, 'component_data', 'Component Step 2');

    // Step 4: Verify updates (simulating controller read)
    $name = $repository->getState('onboarding', $userKey, 'controller_name');
    $data = $repository->getState('onboarding', $userKey, 'component_data');

    expect($name)->toBe('Updated by Component')
        ->and($data)->toBe('Component Step 2');

    // Step 5: Final update
    $repository->setState('onboarding', $userKey, 'controller_name', 'Final Update');
    $repository->setState('onboarding', $userKey, 'step', '3');

    // Step 6: Verify final state
    $finalName = $repository->getState('onboarding', $userKey, 'controller_name');
    $finalData = $repository->getState('onboarding', $userKey, 'component_data');

    expect($finalName)->toBe('Final Update')
        ->and($finalData)->toBe('Component Step 2');
});

test('multiple controllers can share state in workflow', function () {
    $request = Request::create('/test', 'GET');

    // First controller sets state
    workflowState('onboarding')
        ->forRequest($request)
        ->set('shared_key', 'value1')
        ->set('controller1_data', 'from controller 1');

    // Second controller reads and updates
    $sharedValue = workflowState('onboarding')->forRequest($request)->get('shared_key');
    expect($sharedValue)->toBe('value1');

    workflowState('onboarding')
        ->forRequest($request)
        ->set('shared_key', 'value2')
        ->set('controller2_data', 'from controller 2');

    // Verify both updates exist
    $allState = workflowState('onboarding')->forRequest($request)->all();

    expect($allState['shared_key'])->toBe('value2')
        ->and($allState['controller1_data'])->toBe('from controller 1')
        ->and($allState['controller2_data'])->toBe('from controller 2');
});

test('namespaced state works between controllers and components', function () {
    $request = Request::create('/test', 'GET');

    // Controller sets namespaced state
    workflowState('onboarding')
        ->forRequest($request)
        ->set('profile.name', 'John')
        ->set('profile.email', 'john@example.com')
        ->set('settings.theme', 'dark');

    // Verify all namespaced keys are accessible
    $name = workflowState('onboarding')->forRequest($request)->get('profile.name');
    $theme = workflowState('onboarding')->forRequest($request)->get('settings.theme');

    expect($name)->toBe('John')
        ->and($theme)->toBe('dark');

    // Clear namespace
    workflowState('onboarding')->forRequest($request)->clear('profile');

    // Verify profile namespace is cleared but settings remains
    $allState = workflowState('onboarding')->forRequest($request)->all();
    expect($allState)->not->toHaveKey('profile.name')
        ->and($allState)->toHaveKey('settings.theme');
});

test('state isolation between different workflows', function () {
    $request = Request::create('/test', 'GET');

    // Set state in onboarding workflow
    workflowState('onboarding')
        ->forRequest($request)
        ->set('workflow_specific', 'onboarding value');

    // Set state in checkout workflow
    workflowState('checkout')
        ->forRequest($request)
        ->set('workflow_specific', 'checkout value');

    // Verify isolation
    $onboardingValue = workflowState('onboarding')->forRequest($request)->get('workflow_specific');
    $checkoutValue = workflowState('checkout')->forRequest($request)->get('workflow_specific');

    expect($onboardingValue)->toBe('onboarding value')
        ->and($checkoutValue)->toBe('checkout value');
});
