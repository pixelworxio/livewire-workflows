<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use Tests\Support\TestComponentWithEncryption;

beforeEach(function () {

});

test('encrypted property is stored encrypted', function () {
    $repository = app(WorkflowStateRepository::class);
    $sessionId = session()->getId();

    Livewire::test(TestComponentWithEncryption::class)
        ->set('privateData', 'sensitive_information');

    $storedValue = $repository->getState('secure-flow', $sessionId, 'privateData');

    expect($storedValue)->not->toBe('sensitive_information');

    $decrypted = Crypt::decrypt($storedValue);
    expect($decrypted)->toBe('sensitive_information');
});

test('non-encrypted property is stored as plain text', function () {
    $repository = app(WorkflowStateRepository::class);
    $sessionId = session()->getId();

    Livewire::test(TestComponentWithEncryption::class)
        ->set('publicData', 'public_information');

    $storedValue = $repository->getState('secure-flow', $sessionId, 'publicData');

    expect($storedValue)->toBe('public_information');
});

test('encrypted array is stored and retrieved correctly', function () {
    $repository = app(WorkflowStateRepository::class);
    $sessionId = session()->getId();

    $sensitiveData = [
        'ssn' => '123-45-6789',
        'credit_card' => '4111-1111-1111-1111',
    ];

    $component = Livewire::test(TestComponentWithEncryption::class)
        ->set('sensitiveArray', $sensitiveData);

    $storedValue = $repository->getState('secure-flow', $sessionId, 'sensitiveArray');

    expect($storedValue)->not->toBe($sensitiveData);

    $decrypted = Crypt::decrypt($storedValue);
    expect($decrypted)->toBe($sensitiveData);

    // Test hydration
    $newComponent = Livewire::test(TestComponentWithEncryption::class);
    expect($newComponent->sensitiveArray)->toBe($sensitiveData);
});

test('null encrypted value is handled correctly', function () {
    $repository = app(WorkflowStateRepository::class);
    $sessionId = session()->getId();

    $component = Livewire::test(TestComponentWithEncryption::class)
        ->set('privateData', null);

    $storedValue = $repository->getState('secure-flow', $sessionId, 'privateData');

    expect($storedValue)->toBeNull();
});

test('encrypted value survives multiple updates', function () {
    $repository = app(WorkflowStateRepository::class);
    $sessionId = session()->getId();

    $component = Livewire::test(TestComponentWithEncryption::class);

    $component->set('privateData', 'first_secret');
    $stored1 = $repository->getState('secure-flow', $sessionId, 'privateData');
    expect(Crypt::decrypt($stored1))->toBe('first_secret');

    $component->set('privateData', 'second_secret');
    $stored2 = $repository->getState('secure-flow', $sessionId, 'privateData');
    expect(Crypt::decrypt($stored2))->toBe('second_secret');

    $component->set('privateData', 'third_secret');
    $stored3 = $repository->getState('secure-flow', $sessionId, 'privateData');
    expect(Crypt::decrypt($stored3))->toBe('third_secret');
});

test('mixing encrypted and plain properties works correctly', function () {
    $repository = app(WorkflowStateRepository::class);
    $sessionId = session()->getId();

    Livewire::test(TestComponentWithEncryption::class)
        ->set('publicData', 'everyone_can_see')
        ->set('privateData', 'only_encrypted');

    $publicStored = $repository->getState('secure-flow', $sessionId, 'publicData');
    $privateStored = $repository->getState('secure-flow', $sessionId, 'privateData');

    expect($publicStored)->toBe('everyone_can_see');
    expect($privateStored)->not->toBe('only_encrypted');
    expect(Crypt::decrypt($privateStored))->toBe('only_encrypted');
});

test('encrypted data can be manually retrieved using helper', function () {
    $component = Livewire::test(Tests\Support\TestComponentWithEncryptionHelper::class);

    $component->call('saveEncrypted', 'secret_value');
    $retrieved = $component->call('loadEncrypted')->json();

    expect($retrieved)->toBe('secret_value');
});
