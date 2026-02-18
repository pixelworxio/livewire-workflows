<?php

declare(strict_types=1);

use Pixelworxio\LivewireWorkflows\StateRepositories\NullStateRepository;

beforeEach(function () {
    $this->repo = new NullStateRepository;
});

test('getCurrentStep() returns null', function () {
    expect($this->repo->getCurrentStep('flow', 'user-1'))->toBeNull();
});

test('setCurrentStep() is a no-op and returns void', function () {
    // Should not throw
    $this->repo->setCurrentStep('flow', 'user-1', 'step-one');
    expect(true)->toBeTrue();
});

test('getHistory() returns empty array', function () {
    expect($this->repo->getHistory('flow', 'user-1'))->toBe([]);
});

test('pushHistory() is a no-op', function () {
    $this->repo->pushHistory('flow', 'user-1', 'step-one');
    expect($this->repo->getHistory('flow', 'user-1'))->toBe([]);
});

test('popHistory() returns null', function () {
    expect($this->repo->popHistory('flow', 'user-1'))->toBeNull();
});

test('clear() is a no-op', function () {
    $this->repo->clear('flow', 'user-1');
    expect(true)->toBeTrue();
});

test('getMetadata() returns empty array', function () {
    expect($this->repo->getMetadata('flow', 'user-1'))->toBe([]);
});

test('setMetadata() is a no-op', function () {
    $this->repo->setMetadata('flow', 'user-1', ['key' => 'value']);
    expect($this->repo->getMetadata('flow', 'user-1'))->toBe([]);
});

test('getState() returns null', function () {
    expect($this->repo->getState('flow', 'user-1', 'key'))->toBeNull();
});

test('setState() is a no-op', function () {
    $this->repo->setState('flow', 'user-1', 'key', 'value');
    expect($this->repo->getState('flow', 'user-1', 'key'))->toBeNull();
});

test('hasState() returns false', function () {
    expect($this->repo->hasState('flow', 'user-1', 'key'))->toBeFalse();
});

test('forgetState() is a no-op', function () {
    $this->repo->forgetState('flow', 'user-1', 'key');
    expect(true)->toBeTrue();
});

test('clearState() without namespace is a no-op', function () {
    $this->repo->clearState('flow', 'user-1');
    expect(true)->toBeTrue();
});

test('clearState() with namespace is a no-op', function () {
    $this->repo->clearState('flow', 'user-1', 'profile');
    expect(true)->toBeTrue();
});

test('getAllState() returns empty array', function () {
    expect($this->repo->getAllState('flow', 'user-1'))->toBe([]);
});
