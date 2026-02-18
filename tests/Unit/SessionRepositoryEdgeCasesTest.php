<?php

declare(strict_types=1);

use Pixelworxio\LivewireWorkflows\StateRepositories\SessionWorkflowStateRepository;

beforeEach(function () {
    $this->repo = app(SessionWorkflowStateRepository::class);
});

test('popHistory() returns null when history is empty', function () {
    $result = $this->repo->popHistory('flow', 'user-1');

    expect($result)->toBeNull();
});

test('clearState() with namespace removes only matching keys and forgets entire state when emptied', function () {
    // The session repository uses str_starts_with($key, $namespace) where namespace is the raw string
    // So passing 'profile' will remove any key starting with 'profile'
    $this->repo->setState('flow', 'user-1', 'profile.name', 'John');
    $this->repo->setState('flow', 'user-1', 'profile.email', 'john@example.com');

    $this->repo->clearState('flow', 'user-1', 'profile');

    // All profile keys should be removed
    expect($this->repo->getAllState('flow', 'user-1'))->toBe([]);
});

test('clearState() with namespace keeps non-matching keys when emptied', function () {
    $this->repo->setState('flow', 'user-1', 'profile.name', 'John');
    $this->repo->setState('flow', 'user-1', 'other.key', 'value');

    $this->repo->clearState('flow', 'user-1', 'profile');

    $remaining = $this->repo->getAllState('flow', 'user-1');

    expect($remaining)->toHaveKey('other.key')
        ->and($remaining)->not->toHaveKey('profile.name');
});
