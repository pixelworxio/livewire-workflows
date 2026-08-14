<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Pixelworxio\LivewireWorkflows\Commands\WorkflowsUpgradeCommand;

beforeEach(function () {
    $this->configPath = config_path('livewire-workflows.php');

    // Clean up config and migrations before each test
    if (File::exists($this->configPath)) {
        File::delete($this->configPath);
    }

    $migrations = glob(database_path('migrations/*create_workflow_states_table.php'));
    foreach ($migrations as $file) {
        File::delete($file);
    }
});

afterEach(function () {
    if (File::exists($this->configPath)) {
        File::delete($this->configPath);
    }

    $migrations = glob(database_path('migrations/*create_workflow_states_table.php'));
    foreach ($migrations as $file) {
        File::delete($file);
    }
});

test('command runs successfully', function () {
    config()->set('livewire-workflows.repository', 'eloquent');

    $this->artisan(WorkflowsUpgradeCommand::class)
        ->assertSuccessful();
});

test('informs user and exits when already using Eloquent', function () {
    config()->set('livewire-workflows.repository', 'eloquent');

    $this->artisan(WorkflowsUpgradeCommand::class)
        ->assertSuccessful()
        ->expectsOutputToContain('Already using the Eloquent state repository. Nothing to do.');
});

test('shows warning message when using session repository', function () {
    config()->set('livewire-workflows.repository', 'session');

    $this->artisan(WorkflowsUpgradeCommand::class)
        ->expectsQuestion('Do you wish to continue?', false)
        ->assertSuccessful()
        ->expectsOutputToContain('session state repository')
        ->expectsOutputToContain('cannot be automatically migrated');
});

test('exits cleanly with no changes when user declines', function () {
    config()->set('livewire-workflows.repository', 'session');

    $this->artisan(WorkflowsUpgradeCommand::class)
        ->expectsQuestion('Do you wish to continue?', false)
        ->assertSuccessful()
        ->expectsOutputToContain('Upgrade cancelled. No changes were made.');

    // Config should not have been published
    expect(File::exists($this->configPath))->toBeFalse();

    // No migration should have been published
    $migrations = glob(database_path('migrations/*create_workflow_states_table.php'));
    expect($migrations)->toBeEmpty();
});

test('publishes migration when user confirms', function () {
    config()->set('livewire-workflows.repository', 'session');

    $this->artisan(WorkflowsUpgradeCommand::class)
        ->expectsQuestion('Do you wish to continue?', true)
        ->assertSuccessful();

    $migrations = glob(database_path('migrations/*create_workflow_states_table.php'));
    expect($migrations)->not->toBeEmpty();
});

test('updates published config to eloquent when user confirms', function () {
    config()->set('livewire-workflows.repository', 'session');

    // Publish config with session default first
    File::put($this->configPath, "<?php\nreturn ['repository' => env('WORKFLOWS_REPOSITORY', 'session')];");

    $this->artisan(WorkflowsUpgradeCommand::class)
        ->expectsQuestion('Do you wish to continue?', true)
        ->assertSuccessful();

    if (File::exists($this->configPath)) {
        $config = File::get($this->configPath);
        expect($config)->toContain("env('WORKFLOWS_REPOSITORY', 'eloquent')");
    }
});

test('skips publishing migration if one already exists', function () {
    config()->set('livewire-workflows.repository', 'session');

    // Create a valid no-op migration file so artisan migrate succeeds
    $existingMigration = database_path('migrations/2020_01_01_000000_create_workflow_states_table.php');
    File::put($existingMigration, '<?php
use Illuminate\Database\Migrations\Migration;
return new class extends Migration {
    public function up(): void {}
    public function down(): void {}
};');

    $this->artisan(WorkflowsUpgradeCommand::class)
        ->expectsQuestion('Do you wish to continue?', true)
        ->assertSuccessful()
        ->expectsOutputToContain('Migration already exists, skipping publish.');

    // Should only have the one we created (not a duplicate)
    $migrations = glob(database_path('migrations/*create_workflow_states_table.php'));
    expect(count($migrations))->toBe(1);

    File::delete($existingMigration);
});

test('outputs success summary after upgrade', function () {
    config()->set('livewire-workflows.repository', 'session');

    $this->artisan(WorkflowsUpgradeCommand::class)
        ->expectsQuestion('Do you wish to continue?', true)
        ->assertSuccessful()
        ->expectsOutputToContain('Upgrade complete!');
});

test('reminds user to set env var after upgrade', function () {
    config()->set('livewire-workflows.repository', 'session');

    $this->artisan(WorkflowsUpgradeCommand::class)
        ->expectsQuestion('Do you wish to continue?', true)
        ->assertSuccessful()
        ->expectsOutputToContain('WORKFLOWS_REPOSITORY=eloquent');
});

test('handles null repository same as session', function () {
    config()->set('livewire-workflows.repository', 'null');

    $this->artisan(WorkflowsUpgradeCommand::class)
        ->expectsQuestion('Do you wish to continue?', false)
        ->assertSuccessful()
        ->expectsOutputToContain('null state repository');
});
