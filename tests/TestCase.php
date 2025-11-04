<?php

namespace Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Pixelworxio\LivewireWorkflows\LivewireWorkflowsServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Pixelworx\\LivewireWorkflows\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            LivewireWorkflowsServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        config()->set('session.driver', 'array');

        config()->set('livewire-workflows.repository', 'session');
        config()->set('livewire-workflows.middleware', ['web']);

        config()->set('view.paths', [
            __DIR__.'/views',
            resource_path('views'),
        ]);
    }

    protected function loadWorkflowMigrations(): void
    {
        Schema::create('workflow_states', function (Blueprint $table) {
            $table->id();
            $table->string('workflow_name')->index();
            $table->string('user_key')->index();
            $table->string('current_step')->nullable();
            $table->json('history')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['workflow_name', 'user_key']);
        });
    }
}
