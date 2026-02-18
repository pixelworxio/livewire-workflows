<?php

declare(strict_types=1);

use Pixelworxio\LivewireWorkflows\StateRepositories\EloquentWorkflowStateRepository;

beforeEach(function () {
    $this->loadWorkflowMigrations();
    config()->set('livewire-workflows.repository', 'eloquent');
    $this->repo = app(EloquentWorkflowStateRepository::class);
});

test('getHistory() returns empty array when no record exists', function () {
    expect($this->repo->getHistory('flow', 'user-1'))->toBe([]);
});

test('getHistory() returns empty array when history column is null', function () {
    // Create a record with null history by setting current step
    $this->repo->setCurrentStep('flow', 'user-1', 'step-one');

    // History should still be empty
    expect($this->repo->getHistory('flow', 'user-1'))->toBe([]);
});

test('pushHistory() creates record when none exists', function () {
    $this->repo->pushHistory('flow', 'user-1', 'step-one');

    $history = $this->repo->getHistory('flow', 'user-1');

    expect($history)->toBe(['step-one']);
});

test('getMetadata() returns empty array when no record exists', function () {
    expect($this->repo->getMetadata('flow', 'user-1'))->toBe([]);
});

test('getMetadata() returns empty array when metadata column is null', function () {
    // Create a record without metadata
    $this->repo->setCurrentStep('flow', 'user-1', 'step-one');

    expect($this->repo->getMetadata('flow', 'user-1'))->toBe([]);
});

test('setMetadata() creates record when none exists', function () {
    $this->repo->setMetadata('flow', 'user-1', ['key' => 'value']);

    expect($this->repo->getMetadata('flow', 'user-1'))->toBe(['key' => 'value']);
});

test('clearState() without namespace nulls data column', function () {
    $this->repo->setState('flow', 'user-1', 'name', 'John');
    $this->repo->clearState('flow', 'user-1');

    expect($this->repo->getAllState('flow', 'user-1'))->toBe([]);
});

test('clearState() with namespace removes matching keys', function () {
    $this->repo->setState('flow', 'user-1', 'profile.name', 'John');
    $this->repo->setState('flow', 'user-1', 'profile.email', 'john@test.com');
    $this->repo->setState('flow', 'user-1', 'other.key', 'value');

    // The Eloquent repository uses str_starts_with($key, $namespace.'.') so 'profile' matches 'profile.name'
    $this->repo->clearState('flow', 'user-1', 'profile');

    $remaining = $this->repo->getAllState('flow', 'user-1');

    expect($remaining)->toHaveKey('other.key')
        ->and($remaining)->not->toHaveKey('profile.name')
        ->and($remaining)->not->toHaveKey('profile.email');
});

test('clearState() with namespace sets data to null when all keys removed', function () {
    $this->repo->setState('flow', 'user-1', 'profile.name', 'John');
    $this->repo->clearState('flow', 'user-1', 'profile');

    expect($this->repo->getAllState('flow', 'user-1'))->toBe([]);
});

test('forgetState() sets data to null when last key removed', function () {
    $this->repo->setState('flow', 'user-1', 'name', 'John');
    $this->repo->forgetState('flow', 'user-1', 'name');

    expect($this->repo->getAllState('flow', 'user-1'))->toBe([]);
    expect($this->repo->hasState('flow', 'user-1', 'name'))->toBeFalse();
});

test('popHistory() removes last history item and updates record', function () {
    $this->repo->pushHistory('flow', 'user-1', 'step-one');
    $this->repo->pushHistory('flow', 'user-1', 'step-two');

    $popped = $this->repo->popHistory('flow', 'user-1');

    expect($popped)->toBe('step-two')
        ->and($this->repo->getHistory('flow', 'user-1'))->toBe(['step-one']);
});

test('popHistory() returns null when history is empty', function () {
    expect($this->repo->popHistory('flow', 'user-1'))->toBeNull();
});

test('getAllState() returns empty array when data column is null', function () {
    $this->repo->setCurrentStep('flow', 'user-1', 'step-one');

    expect($this->repo->getAllState('flow', 'user-1'))->toBe([]);
});
