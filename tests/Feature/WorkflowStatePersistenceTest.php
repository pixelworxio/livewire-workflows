<?php

declare(strict_types=1);

use Pixelworxio\LivewireWorkflows\StateRepositories\EloquentWorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\StateRepositories\NullStateRepository;
use Pixelworxio\LivewireWorkflows\StateRepositories\SessionWorkflowStateRepository;

test('null repository returns null for all state operations', function () {
    $repo = new NullStateRepository;

    $repo->setState('test-flow', 'user-1', 'key', 'value');

    expect($repo->getState('test-flow', 'user-1', 'key'))->toBeNull()
        ->and($repo->hasState('test-flow', 'user-1', 'key'))->toBeFalse()
        ->and($repo->getAllState('test-flow', 'user-1'))->toBeEmpty();
});

test('session repository persists state', function () {
    $repo = app(SessionWorkflowStateRepository::class);

    $repo->setState('test-flow', 'user-1', 'name', 'John Doe');
    $repo->setState('test-flow', 'user-1', 'age', 30);

    expect($repo->getState('test-flow', 'user-1', 'name'))->toBe('John Doe')
        ->and($repo->getState('test-flow', 'user-1', 'age'))->toBe(30)
        ->and($repo->hasState('test-flow', 'user-1', 'name'))->toBeTrue();
});

test('session repository forgets state', function () {
    $repo = app(SessionWorkflowStateRepository::class);

    $repo->setState('test-flow', 'user-1', 'key', 'value');

    expect($repo->hasState('test-flow', 'user-1', 'key'))->toBeTrue();

    $repo->forgetState('test-flow', 'user-1', 'key');

    expect($repo->hasState('test-flow', 'user-1', 'key'))->toBeFalse();
});

test('session repository clears all state', function () {
    $repo = app(SessionWorkflowStateRepository::class);

    $repo->setState('test-flow', 'user-1', 'key1', 'value1');
    $repo->setState('test-flow', 'user-1', 'key2', 'value2');

    $repo->clearState('test-flow', 'user-1');

    expect($repo->getAllState('test-flow', 'user-1'))->toBeEmpty();
});

test('session repository clears namespaced state', function () {
    $repo = app(SessionWorkflowStateRepository::class);

    $repo->setState('test-flow', 'user-1', 'profile.name', 'John');
    $repo->setState('test-flow', 'user-1', 'profile.age', 30);
    $repo->setState('test-flow', 'user-1', 'settings.theme', 'dark');

    $repo->clearState('test-flow', 'user-1', 'profile');

    $allState = $repo->getAllState('test-flow', 'user-1');

    expect($allState)->not->toHaveKey('profile.name')
        ->and($allState)->not->toHaveKey('profile.age')
        ->and($allState)->toHaveKey('settings.theme');
});

test('session repository returns all state', function () {
    $repo = app(SessionWorkflowStateRepository::class);

    $repo->setState('test-flow', 'user-1', 'key1', 'value1');
    $repo->setState('test-flow', 'user-1', 'key2', 'value2');

    $allState = $repo->getAllState('test-flow', 'user-1');

    expect($allState)->toBe(['key1' => 'value1', 'key2' => 'value2']);
});

test('eloquent repository persists state', function () {
    $this->loadWorkflowMigrations();

    $repo = app(EloquentWorkflowStateRepository::class);

    $repo->setState('test-flow', 1, 'name', 'John Doe');
    $repo->setState('test-flow', 1, 'age', 30);

    expect($repo->getState('test-flow', 1, 'name'))->toBe('John Doe')
        ->and($repo->getState('test-flow', 1, 'age'))->toBe(30)
        ->and($repo->hasState('test-flow', 1, 'name'))->toBeTrue();

    $this->assertDatabaseHas('workflow_states', [
        'workflow_name' => 'test-flow',
        'user_key' => '1',
    ]);
});

test('eloquent repository forgets state', function () {
    $this->loadWorkflowMigrations();

    $repo = app(EloquentWorkflowStateRepository::class);

    $repo->setState('test-flow', 1, 'key', 'value');

    expect($repo->hasState('test-flow', 1, 'key'))->toBeTrue();

    $repo->forgetState('test-flow', 1, 'key');

    expect($repo->hasState('test-flow', 1, 'key'))->toBeFalse();
});

test('eloquent repository clears all state', function () {
    $this->loadWorkflowMigrations();

    $repo = app(EloquentWorkflowStateRepository::class);

    $repo->setState('test-flow', 1, 'key1', 'value1');
    $repo->setState('test-flow', 1, 'key2', 'value2');

    $repo->clearState('test-flow', 1);

    expect($repo->getAllState('test-flow', 1))->toBeEmpty();
});

test('eloquent repository clears namespaced state', function () {
    $this->loadWorkflowMigrations();

    $repo = app(EloquentWorkflowStateRepository::class);

    $repo->setState('test-flow', 1, 'profile.name', 'John');
    $repo->setState('test-flow', 1, 'profile.age', 30);
    $repo->setState('test-flow', 1, 'settings.theme', 'dark');

    $repo->clearState('test-flow', 1, 'profile');

    $allState = $repo->getAllState('test-flow', 1);

    expect($allState)->not->toHaveKey('profile.name')
        ->and($allState)->not->toHaveKey('profile.age')
        ->and($allState)->toHaveKey('settings.theme');
});

test('eloquent repository supports guest user keys', function () {
    $this->loadWorkflowMigrations();

    $repo = app(EloquentWorkflowStateRepository::class);

    $guestKey = 'guest-abc123';
    $repo->setState('test-flow', $guestKey, 'key', 'value');

    expect($repo->getState('test-flow', $guestKey, 'key'))->toBe('value');

    $this->assertDatabaseHas('workflow_states', [
        'workflow_name' => 'test-flow',
        'user_key' => $guestKey,
    ]);
});
