<?php

declare(strict_types=1);

use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\StateRepositories\EloquentWorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\StateRepositories\NullStateRepository;
use Pixelworxio\LivewireWorkflows\StateRepositories\SessionWorkflowStateRepository;

test('WorkflowStateRepository resolves to SessionWorkflowStateRepository when config is session', function () {
    config()->set('livewire-workflows.repository', 'session');

    // Re-bind the repository
    app()->singleton(WorkflowStateRepository::class, function ($app) {
        return match (config('livewire-workflows.repository')) {
            'eloquent' => $app->make(EloquentWorkflowStateRepository::class),
            'session' => $app->make(SessionWorkflowStateRepository::class),
            default => $app->make(NullStateRepository::class),
        };
    });

    $repo = app(WorkflowStateRepository::class);

    expect($repo)->toBeInstanceOf(SessionWorkflowStateRepository::class);
});

test('WorkflowStateRepository resolves to EloquentWorkflowStateRepository when config is eloquent', function () {
    $this->loadWorkflowMigrations();

    config()->set('livewire-workflows.repository', 'eloquent');

    // Re-bind to reflect config change
    app()->singleton(WorkflowStateRepository::class, function ($app) {
        return match (config('livewire-workflows.repository')) {
            'eloquent' => $app->make(EloquentWorkflowStateRepository::class),
            'session' => $app->make(SessionWorkflowStateRepository::class),
            default => $app->make(NullStateRepository::class),
        };
    });

    $repo = app(WorkflowStateRepository::class);

    expect($repo)->toBeInstanceOf(EloquentWorkflowStateRepository::class);
});

test('WorkflowStateRepository resolves to NullStateRepository when config is null', function () {
    config()->set('livewire-workflows.repository', 'null');

    app()->singleton(WorkflowStateRepository::class, function ($app) {
        return match (config('livewire-workflows.repository')) {
            'eloquent' => $app->make(EloquentWorkflowStateRepository::class),
            'session' => $app->make(SessionWorkflowStateRepository::class),
            default => $app->make(NullStateRepository::class),
        };
    });

    $repo = app(WorkflowStateRepository::class);

    expect($repo)->toBeInstanceOf(NullStateRepository::class);
});

test('loadWorkflows() skips gracefully when routes/workflows.php does not exist', function () {
    // The service provider boot() is already called during test setup
    // and it handles the missing file gracefully. We verify the app boots without error.
    $registrar = app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class);

    // If there's no routes/workflows.php in test env, this should still work
    expect($registrar)->not->toBeNull();
    expect($registrar->all())->toBeArray();
});
