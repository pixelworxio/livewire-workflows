<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Install command for livewire-workflows package.
 *
 * Publishes configuration, stub files, and the database migration.
 */
class WorkflowsInstallCommand extends Command
{
    protected $signature = 'workflows:install';

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
            $stubPath = dirname(__DIR__, 2).'/stubs/workflows.php.stub';
            if (! is_file($stubPath)) {
                throw new \RuntimeException('Missing stub: '.$stubPath);
            }
            $stub = File::get($stubPath);

            File::put($workflowsPath, $stub);
            $this->info('Created routes/workflows.php');
        } else {
            $this->warn('routes/workflows.php already exists, skipping.');
        }

        // Publish and set up the database migration
        $this->publishMigration();

        $this->newLine();
        $this->info('✓ livewire-workflows installed successfully!');
        $this->newLine();
        $this->line('Next steps:');
        $this->line('  1. Run: php artisan migrate');
        $this->line('  2. Define workflows in routes/workflows.php');
        $this->line('  3. Create guards and Livewire components');
        $this->line('  4. Use the workflows:scan command to validate');
        $this->newLine();

        return self::SUCCESS;
    }

    protected function publishMigration(): void
    {
        $migrationStub = __DIR__.'/../../database/migrations/create_workflow_states_table.php.stub';
        $migrationName = date('Y_m_d_His').'_create_workflow_states_table.php';
        $migrationPath = database_path('migrations/'.$migrationName);

        // Check if migration already exists (any version)
        $existingMigrations = glob(database_path('migrations/*_create_workflow_states_table.php'));

        if (! empty($existingMigrations)) {
            $this->warn('Migration for workflow_states table already exists, skipping.');

            return;
        }

        File::copy($migrationStub, $migrationPath);
        $this->info('Published migration: '.$migrationName);
    }
}
