<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows;

use Illuminate\Support\ServiceProvider;
use Pixelworxio\LivewireWorkflows\Commands\MakeWorkflowCommand;
use Pixelworxio\LivewireWorkflows\Commands\MakeWorkflowGuardCommand;
use Pixelworxio\LivewireWorkflows\Commands\MakeWorkflowStepCommand;
use Pixelworxio\LivewireWorkflows\Commands\WorkflowsInstallCommand;
use Pixelworxio\LivewireWorkflows\Commands\WorkflowsScanCommand;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar;
use Pixelworxio\LivewireWorkflows\StateRepositories\EloquentWorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\StateRepositories\NullStateRepository;
use Pixelworxio\LivewireWorkflows\StateRepositories\SessionWorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\Support\RouteRegistrar;
use Pixelworxio\LivewireWorkflows\Support\WorkflowEngine;
use Pixelworxio\LivewireWorkflows\Support\WorkflowResolver;

class LivewireWorkflowsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/livewire-workflows.php', 'livewire-workflows');

        // Register core services
        $this->app->singleton(WorkflowRegistrar::class);

        $this->app->singleton(WorkflowEngine::class, function ($app) {
            return new WorkflowEngine(
                $app->make(WorkflowStateRepository::class),
                $app->make(\Illuminate\Pipeline\Pipeline::class)
            );
        });

        $this->app->singleton(WorkflowResolver::class, function ($app) {
            return new WorkflowResolver(
                $app->make(WorkflowRegistrar::class),
                $app->make(WorkflowEngine::class),
                $app->make(WorkflowStateRepository::class)
            );
        });

        $this->app->singleton(RouteRegistrar::class, function ($app) {
            return new RouteRegistrar(
                $app->make(WorkflowRegistrar::class)
            );
        });

        // Bind state repository based on config
        $this->app->singleton(WorkflowStateRepository::class, function ($app) {
            return match (config('livewire-workflows.repository')) {
                'eloquent' => $app->make(EloquentWorkflowStateRepository::class),
                'session' => $app->make(SessionWorkflowStateRepository::class),
                default => $app->make(NullStateRepository::class),
            };
        });
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerCommands();
        $this->loadWorkflows();
        $this->registerRoutes();
    }

    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/livewire-workflows.php' => config_path('livewire-workflows.php'),
            ], 'livewire-workflows-config');

            $this->publishes([
                __DIR__.'/../database/migrations/create_workflow_states_table.php.stub' => database_path('migrations/'.date('Y_m_d_His').'_create_workflow_states_table.php'),
            ], 'livewire-workflows-migrations');
        }
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                WorkflowsInstallCommand::class,
                MakeWorkflowCommand::class,
                MakeWorkflowStepCommand::class,
                MakeWorkflowGuardCommand::class,
                WorkflowsScanCommand::class,
            ]);
        }
    }

    protected function loadWorkflows(): void
    {
        $workflowsPath = base_path('routes/workflows.php');

        if (file_exists($workflowsPath)) {
            $registrar = $this->app->make(WorkflowRegistrar::class);

            // Make Workflow facade available in the file
            $workflow = $registrar;

            require $workflowsPath;

            // Build any pending workflows
            foreach ($registrar->all() as $definition) {
                // Already built and registered during require
            }
        }
    }

    protected function registerRoutes(): void
    {
        $this->app->make(RouteRegistrar::class)->register();
    }
}
