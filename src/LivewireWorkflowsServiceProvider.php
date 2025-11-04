<?php

namespace Pixelworx\LivewireWorkflows;

use Pixelworx\LivewireWorkflows\Commands\LivewireWorkflowsCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LivewireWorkflowsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('livewire-workflows')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_livewire_workflows_table')
            ->hasCommand(LivewireWorkflowsCommand::class);
    }
}
