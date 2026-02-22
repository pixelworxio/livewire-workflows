<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Upgrade command for migrating from session state to Eloquent.
 *
 * Guides existing users through switching to the Eloquent state repository,
 * including publishing and running migrations and updating published config.
 */
class WorkflowsUpgradeCommand extends Command
{
    protected $signature = 'workflows:upgrade';

    protected $description = 'Upgrade livewire-workflows to use the Eloquent state repository';

    public function handle(): int
    {
        $repository = config('livewire-workflows.repository', 'eloquent');

        if ($repository === 'eloquent') {
            $this->info('Already using the Eloquent state repository. Nothing to do.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('You are currently using the '.$repository.' state repository.');
        $this->newLine();
        $this->line('  <fg=yellow>Session state cannot be automatically migrated to the database.</>');
        $this->line('  <fg=yellow>Any users with in-progress workflows will restart from the beginning</>');
        $this->line('  <fg=yellow>after this change. There is no way to recover their current progress.</>');
        $this->newLine();
        $this->line('  This command will:');
        $this->line('    1. Publish the workflow_states database migration (if not already published)');
        $this->line('    2. Run php artisan migrate');
        $this->line('    3. Update the published config to default to the Eloquent repository');
        $this->newLine();

        if (! $this->confirm('Do you wish to continue?', false)) {
            $this->info('Upgrade cancelled. No changes were made.');

            return self::SUCCESS;
        }

        $this->newLine();

        // Step 1: Publish migration if not already present
        $existingMigrations = glob(database_path('migrations/*_create_workflow_states_table.php'));

        if (! empty($existingMigrations)) {
            $this->line('  ✓ Migration already exists, skipping publish.');
        } else {
            $migrationStub = __DIR__.'/../../database/migrations/create_workflow_states_table.php.stub';
            $migrationName = date('Y_m_d_His').'_create_workflow_states_table.php';
            $migrationPath = database_path('migrations/'.$migrationName);

            File::copy($migrationStub, $migrationPath);
            $this->line('  ✓ Published migration: '.$migrationName);
        }

        // Step 2: Run migrations
        $this->call('migrate');
        $this->line('  ✓ Migrations complete.');

        // Step 3: Update published config
        $configPath = config_path('livewire-workflows.php');

        if (File::exists($configPath)) {
            $config = File::get($configPath);
            $updated = str_replace(
                "'repository' => env('WORKFLOWS_REPOSITORY', 'session')",
                "'repository' => env('WORKFLOWS_REPOSITORY', 'eloquent')",
                $config
            );

            // Also handle null default
            $updated = str_replace(
                "'repository' => env('WORKFLOWS_REPOSITORY', 'null')",
                "'repository' => env('WORKFLOWS_REPOSITORY', 'eloquent')",
                $updated
            );

            File::put($configPath, $updated);
            $this->line('  ✓ Updated config/livewire-workflows.php to default to Eloquent.');
        } else {
            $this->warn('  config/livewire-workflows.php not found. Run workflows:install first.');
        }

        $this->newLine();
        $this->info('✓ Upgrade complete!');
        $this->newLine();
        $this->line('  <fg=yellow>Remember to set WORKFLOWS_REPOSITORY=eloquent in your .env file.</>');
        $this->newLine();

        return self::SUCCESS;
    }
}
