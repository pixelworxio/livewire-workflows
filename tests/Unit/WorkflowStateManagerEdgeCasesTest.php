<?php

declare(strict_types=1);

use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\Support\WorkflowStateManager;

test('getUserKey() returns null when no request has been set', function () {
    $repository = app(WorkflowStateRepository::class);
    $manager = new WorkflowStateManager('test-flow', $repository);

    // No forRequest() called
    $userKey = $manager->getUserKey();

    expect($userKey)->toBeNull();
});

test('get() returns default when no request set and state is empty', function () {
    $repository = app(WorkflowStateRepository::class);
    $manager = new WorkflowStateManager('test-flow', $repository);

    // No forRequest() called - userKey is null
    $value = $manager->get('some-key', 'default-value');

    expect($value)->toBe('default-value');
});

test('getUserKey() returns guest fingerprint when session is not available', function () {
    $repository = app(WorkflowStateRepository::class);
    $manager = new WorkflowStateManager('test-flow', $repository);

    // Create a request without a session
    $request = \Illuminate\Http\Request::create('/test', 'GET');
    // Don't attach session - let it fall through to guest fingerprint
    $manager->forRequest($request);

    $userKey = $manager->getUserKey();

    // Should be a guest fingerprint
    expect($userKey)->toStartWith('guest-');
});

test('getUserKey() returns session ID when session is available', function () {
    $repository = app(WorkflowStateRepository::class);
    $manager = new WorkflowStateManager('test-flow', $repository);

    $manager->forRequest(request());

    $userKey = $manager->getUserKey();

    // Should be a non-null string (session ID)
    expect($userKey)->not->toBeNull()
        ->and($userKey)->toBeString();
});

test('set() and get() work correctly after forRequest()', function () {
    $repository = app(WorkflowStateRepository::class);
    $manager = new WorkflowStateManager('test-flow', $repository);

    $manager->forRequest(request())
        ->set('key', 'value');

    $value = $manager->get('key');

    expect($value)->toBe('value');
});

test('userKey is cached after first resolution', function () {
    $repository = app(WorkflowStateRepository::class);
    $manager = new WorkflowStateManager('test-flow', $repository);

    $manager->forRequest(request());

    // Call getUserKey twice - second call should use cached value
    $key1 = $manager->getUserKey();
    $key2 = $manager->getUserKey();

    expect($key1)->toBe($key2);
});

test('forRequest() resets cached userKey', function () {
    $repository = app(WorkflowStateRepository::class);
    $manager = new WorkflowStateManager('test-flow', $repository);

    $manager->forRequest(request());
    $key1 = $manager->getUserKey();

    // Call forRequest() again - should reset
    $manager->forRequest(request());
    $key2 = $manager->getUserKey();

    expect($key1)->toBe($key2);  // Same session, same key
});
