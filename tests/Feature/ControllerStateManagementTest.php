<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Tests\Support\FailingGuard;
use Tests\Support\PassingGuard;
use Tests\Support\TestControllerWithState;
use Tests\Support\TestStepOneComponent;

beforeEach(function () {
    app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->clear();
});

test('workflowState helper can set state in controller', function () {
    $request = Request::create('/test', 'GET');

    workflowState('test-flow')
        ->forRequest($request)
        ->set('name', 'John Doe')
        ->set('email', 'john@example.com');

    $repository = app(Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository::class);
    $userKey = workflowState('test-flow')->forRequest($request)->getUserKey();

    expect($repository->getState('test-flow', $userKey, 'name'))->toBe('John Doe')
        ->and($repository->getState('test-flow', $userKey, 'email'))->toBe('john@example.com');
});

test('workflowState helper can get state in controller', function () {
    $request = Request::create('/test', 'GET');

    $repository = app(Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository::class);
    $userKey = workflowState('test-flow')->forRequest($request)->getUserKey();

    $repository->setState('test-flow', $userKey, 'name', 'Jane Doe');
    $repository->setState('test-flow', $userKey, 'age', 30);

    $name = workflowState('test-flow')->forRequest($request)->get('name');
    $age = workflowState('test-flow')->forRequest($request)->get('age');

    expect($name)->toBe('Jane Doe')
        ->and($age)->toBe(30);
});

test('workflowState helper can get with default value', function () {
    $request = Request::create('/test', 'GET');

    $value = workflowState('test-flow')
        ->forRequest($request)
        ->get('nonexistent', 'default-value');

    expect($value)->toBe('default-value');
});

test('workflowState helper can check if state exists', function () {
    $request = Request::create('/test', 'GET');

    workflowState('test-flow')
        ->forRequest($request)
        ->set('exists', 'yes');

    $exists = workflowState('test-flow')->forRequest($request)->has('exists');
    $notExists = workflowState('test-flow')->forRequest($request)->has('notexists');

    expect($exists)->toBeTrue()
        ->and($notExists)->toBeFalse();
});

test('workflowState helper can forget state', function () {
    $request = Request::create('/test', 'GET');

    workflowState('test-flow')
        ->forRequest($request)
        ->set('temp', 'value');

    expect(workflowState('test-flow')->forRequest($request)->has('temp'))->toBeTrue();

    workflowState('test-flow')
        ->forRequest($request)
        ->forget('temp');

    expect(workflowState('test-flow')->forRequest($request)->has('temp'))->toBeFalse();
});

test('workflowState helper can get all state', function () {
    $request = Request::create('/test', 'GET');

    workflowState('test-flow')
        ->forRequest($request)
        ->set('key1', 'value1')
        ->set('key2', 'value2')
        ->set('key3', 'value3');

    $allState = workflowState('test-flow')->forRequest($request)->all();

    expect($allState)->toHaveCount(3)
        ->and($allState['key1'])->toBe('value1')
        ->and($allState['key2'])->toBe('value2')
        ->and($allState['key3'])->toBe('value3');
});

test('workflowState helper can clear all state', function () {
    $request = Request::create('/test', 'GET');

    workflowState('test-flow')
        ->forRequest($request)
        ->set('key1', 'value1')
        ->set('key2', 'value2');

    expect(workflowState('test-flow')->forRequest($request)->all())->toHaveCount(2);

    workflowState('test-flow')
        ->forRequest($request)
        ->clear();

    expect(workflowState('test-flow')->forRequest($request)->all())->toHaveCount(0);
});

test('workflowState helper can clear namespaced state', function () {
    $request = Request::create('/test', 'GET');

    workflowState('test-flow')
        ->forRequest($request)
        ->set('profile.name', 'John')
        ->set('profile.email', 'john@example.com')
        ->set('settings.theme', 'dark');

    workflowState('test-flow')
        ->forRequest($request)
        ->clear('profile');

    $allState = workflowState('test-flow')->forRequest($request)->all();

    expect($allState)->not->toHaveKey('profile.name')
        ->and($allState)->not->toHaveKey('profile.email')
        ->and($allState)->toHaveKey('settings.theme');
});

test('workflowState helper supports method chaining', function () {
    $request = Request::create('/test', 'GET');

    workflowState('test-flow')
        ->forRequest($request)
        ->set('a', 1)
        ->set('b', 2)
        ->set('c', 3)
        ->forget('b');

    $allState = workflowState('test-flow')->forRequest($request)->all();

    expect($allState)->toHaveCount(2)
        ->and($allState['a'])->toBe(1)
        ->and($allState['c'])->toBe(3);
});

test('state set in controller is available in livewire component', function () {
    $request = Request::create('/test', 'GET');

    // Simulate controller setting state
    workflowState('onboarding')
        ->forRequest($request)
        ->set('controller_name', 'From Controller')
        ->set('controller_email', 'controller@example.com');

    // Now check if a Livewire component can access it
    $repository = app(Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository::class);
    $userKey = workflowState('onboarding')->forRequest($request)->getUserKey();

    $name = $repository->getState('onboarding', $userKey, 'controller_name');
    $email = $repository->getState('onboarding', $userKey, 'controller_email');

    expect($name)->toBe('From Controller')
        ->and($email)->toBe('controller@example.com');
});

test('state is scoped to workflow name', function () {
    $request = Request::create('/test', 'GET');

    workflowState('workflow-one')
        ->forRequest($request)
        ->set('shared-key', 'value-one');

    workflowState('workflow-two')
        ->forRequest($request)
        ->set('shared-key', 'value-two');

    $valueOne = workflowState('workflow-one')->forRequest($request)->get('shared-key');
    $valueTwo = workflowState('workflow-two')->forRequest($request)->get('shared-key');

    expect($valueOne)->toBe('value-one')
        ->and($valueTwo)->toBe('value-two');
});

test('state is scoped to user key using guest fingerprints', function () {
    // Create requests with different IPs to generate different guest fingerprints
    $request1 = Request::create('/test', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.1', 'HTTP_USER_AGENT' => 'Browser1']);
    $request2 = Request::create('/test', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.2', 'HTTP_USER_AGENT' => 'Browser2']);

    workflowState('test-flow')
        ->forRequest($request1)
        ->set('name', 'User 1');

    workflowState('test-flow')
        ->forRequest($request2)
        ->set('name', 'User 2');

    $name1 = workflowState('test-flow')->forRequest($request1)->get('name');
    $name2 = workflowState('test-flow')->forRequest($request2)->get('name');

    expect($name1)->toBe('User 1')
        ->and($name2)->toBe('User 2');
});

test('workflowState helper resolves user key from authenticated user', function () {
    $request = Request::create('/test', 'GET');

    // Mock authenticated user
    $user = new class {
        public function getAuthIdentifier()
        {
            return 123;
        }
    };

    $request->setUserResolver(fn () => $user);

    workflowState('test-flow')
        ->forRequest($request)
        ->set('auth-test', 'authenticated');

    $repository = app(Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository::class);
    $value = $repository->getState('test-flow', 123, 'auth-test');

    expect($value)->toBe('authenticated');
});
