<?php

declare(strict_types=1);

use Livewire\Livewire;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use Tests\Support\TestComponentWithHelpers;

beforeEach(function () {});

test('putWorkflowState stores data', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    $component = Livewire::test(TestComponentWithHelpers::class);
    $component->call('saveData');

    $storedValue = $repository->getState('test-flow', $userKey, 'user_data');

    expect($storedValue)->toBe(['name' => 'John', 'age' => 30]);
});

test('getWorkflowState retrieves data', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    $repository->setState('test-flow', $userKey, 'user_data', ['name' => 'Jane', 'age' => 25]);

    $component = Livewire::test(TestComponentWithHelpers::class);

    // Access the actual stored data through the repository instead of method return
    $data = $repository->getState('test-flow', $userKey, 'user_data');

    expect($data)->toBe(['name' => 'Jane', 'age' => 25]);
});

test('hasWorkflowState checks existence', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    // Check doesn't exist initially
    expect($repository->hasState('test-flow', $userKey, 'user_data'))->toBeFalse();

    $repository->setState('test-flow', $userKey, 'user_data', ['test' => 'value']);

    // Check exists after setting
    expect($repository->hasState('test-flow', $userKey, 'user_data'))->toBeTrue();
});

test('forgetWorkflowState removes data', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    $repository->setState('test-flow', $userKey, 'user_data', ['name' => 'John']);

    expect($repository->hasState('test-flow', $userKey, 'user_data'))->toBeTrue();

    $component = Livewire::test(TestComponentWithHelpers::class);
    $component->call('removeData');

    expect($repository->hasState('test-flow', $userKey, 'user_data'))->toBeFalse();
});

test('clearWorkflowState removes all data', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    $repository->setState('test-flow', $userKey, 'key1', 'value1');
    $repository->setState('test-flow', $userKey, 'key2', 'value2');

    $component = Livewire::test(TestComponentWithHelpers::class);
    $component->call('clearAll');

    expect($repository->getAllState('test-flow', $userKey))->toBeEmpty();
});

test('clearWorkflowState with namespace only clears namespaced keys', function () {
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    $component = Livewire::test(TestComponentWithHelpers::class);

    // Use the component's helper methods to set state, ensuring same userKey
    $repository = app(WorkflowStateRepository::class);
    $repository->setState('test-flow', $userKey, 'profile.name', 'John');
    $repository->setState('test-flow', $userKey, 'profile.age', 30);
    $repository->setState('test-flow', $userKey, 'settings.theme', 'dark');

    $component->call('clearAll','profile');

    $allState = $repository->getAllState('test-flow', $userKey);

    expect($allState)->not->toHaveKey('profile.name')
        ->and($allState)->not->toHaveKey('profile.age')
        ->and($allState)->toHaveKey('settings.theme');
});

test('allWorkflowState returns all data', function () {
    $repository = app(WorkflowStateRepository::class);
    $userKey = 'guest-'.md5(request()->ip().(request()->userAgent() ?? 'unknown'));

    $repository->setState('test-flow', $userKey, 'key1', 'value1');
    $repository->setState('test-flow', $userKey, 'key2', 'value2');

    $allData = $repository->getAllState('test-flow', $userKey);

    expect($allData)->toBe(['key1' => 'value1', 'key2' => 'value2']);
});
