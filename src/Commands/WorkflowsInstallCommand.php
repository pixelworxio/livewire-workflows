<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Install command for livewire-workflows package.
 *
 * Publishes configuration, stub files, and optionally the database migration.
 */
class WorkflowsInstallCommand extends Command
{
    protected $signature = 'workflows:install {--with-db : Publish database migration and bind Eloquent repository}';

    protected $description = 'Install pixelworxio/livewire-workflows package';

    public function handle(): int
    {
        $this->info('Installing pixelworxio/livewire-workflows...');

        // Publish config
        $this->call('vendor:publish', [
            '--tag' => 'livewire-workflows-config',
            '--force' => true,
        ]);

        // Create routes/workflows.php if it doesn't exist
        $workflowsPath = base_path('routes/workflows.php');

        if (! File::exists($workflowsPath)) {
            $stub = File::get(__DIR__.'/../../stubs/workflows.php.stub');
            File::put($workflowsPath, $stub);
            $this->info('Created routes/workflows.php');
        } else {
            $this->warn('routes/workflows.php already exists, skipping.');
        }

        // Handle database option
        if ($this->option('with-db')) {
            $this->installDatabase();
        }

        $this->newLine();
        $this->info('✓ livewire-workflows installed successfully!');
        $this->newLine();
        $this->line('Next steps:');
        $this->line('  1. Define workflows in routes/workflows.php');
        $this->line('  2. Create guards and Livewire components');
        $this->line('  3. Use the workflows:scan command to validate');
        $this->newLine();

        return self::SUCCESS;
    }

    protected function installDatabase(): void
    {
        // Publish migration
        $migrationStub = __DIR__.'/../../database/migrations/create_workflow_states_table.php.stub';
        $migrationName = date('Y_m_d_His').'_create_workflow_states_table.php';
        $migrationPath = database_path('migrations/'.$migrationName);

        if (! File::exists($migrationPath)) {
            File::copy($migrationStub, $migrationPath);
            $this->info('Published migration: '.$migrationName);
        }

        // Update config to use Eloquent repository
        $configPath = config_path('livewire-workflows.php');

        if (File::exists($configPath)) {
            $config = File::get($configPath);
            $config = str_replace(
                "'repository' => 'session'",
                "'repository' => 'eloquent'",
                $config
            );
            File::put($configPath, $config);
            $this->info('Updated config to use Eloquent repository');
        }

        $this->newLine();
        $this->info('Run "php artisan migrate" to create the workflow_states table.');
    }
}
