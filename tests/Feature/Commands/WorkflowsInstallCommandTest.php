<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Pixelworxio\LivewireWorkflows\Commands\WorkflowsInstallCommand;

beforeEach(function () {
    $this->workflowsPath = base_path('routes/workflows.php');
    $this->configPath = config_path('livewire-workflows.php');

    // Clean up before each test
    if (File::exists($this->workflowsPath)) {
        File::delete($this->workflowsPath);
    }
    if (File::exists($this->configPath)) {
        File::delete($this->configPath);
    }
});

afterEach(function () {
    if (File::exists($this->workflowsPath)) {
        File::delete($this->workflowsPath);
    }
    if (File::exists($this->configPath)) {
        File::delete($this->configPath);
    }
});

test('command runs successfully', function () {
    $this->artisan(WorkflowsInstallCommand::class)
        ->assertSuccessful();
});

test('creates routes/workflows.php from stub when it does not exist', function () {
    expect(File::exists($this->workflowsPath))->toBeFalse();

    $this->artisan(WorkflowsInstallCommand::class)
        ->assertSuccessful()
        ->expectsOutputToContain('Created routes/workflows.php');

    expect(File::exists($this->workflowsPath))->toBeTrue();
});

test('warns and skips when routes/workflows.php already exists', function () {
    File::put($this->workflowsPath, '<?php // existing content');

    $this->artisan(WorkflowsInstallCommand::class)
        ->assertSuccessful()
        ->expectsOutputToContain('routes/workflows.php already exists, skipping.');

    // Ensure existing content was NOT overwritten
    expect(File::get($this->workflowsPath))->toContain('existing content');
});

test('outputs success message after installation', function () {
    $this->artisan(WorkflowsInstallCommand::class)
        ->assertSuccessful()
        ->expectsOutputToContain('livewire-workflows installed successfully!');
});

test('outputs next steps after installation', function () {
    $this->artisan(WorkflowsInstallCommand::class)
        ->assertSuccessful()
        ->expectsOutputToContain('Define workflows in routes/workflows.php');
});

test('with-db option publishes migration', function () {
    // Clean up any migrations that might exist
    $migrationPattern = database_path('migrations/*create_workflow_states_table.php');
    $existing = glob($migrationPattern);
    foreach ($existing as $file) {
        File::delete($file);
    }

    $this->artisan(WorkflowsInstallCommand::class, ['--with-db' => true])
        ->assertSuccessful();

    $migrations = glob(database_path('migrations/*create_workflow_states_table.php'));

    expect($migrations)->not->toBeEmpty();

    // Cleanup
    foreach ($migrations as $file) {
        File::delete($file);
    }
});

test('with-db option outputs migration run instruction', function () {
    $this->artisan(WorkflowsInstallCommand::class, ['--with-db' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('php artisan migrate');

    // Cleanup migrations
    $migrations = glob(database_path('migrations/*create_workflow_states_table.php'));
    foreach ($migrations as $file) {
        File::delete($file);
    }
});

test('with-db option updates config to use eloquent repository', function () {
    // First run without --with-db to publish config with session
    $this->artisan(WorkflowsInstallCommand::class)
        ->assertSuccessful();

    // Now run with --with-db
    $this->artisan(WorkflowsInstallCommand::class, ['--with-db' => true])
        ->assertSuccessful();

    if (File::exists($this->configPath)) {
        $config = File::get($this->configPath);
        expect($config)->toContain("env('WORKFLOWS_REPOSITORY', 'eloquent')");
    }

    // Cleanup migrations
    $migrations = glob(database_path('migrations/*create_workflow_states_table.php'));
    foreach ($migrations as $file) {
        File::delete($file);
    }
});
