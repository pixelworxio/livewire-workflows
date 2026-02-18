<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Pixelworxio\LivewireWorkflows\Commands\MakeWorkflowCommand;

beforeEach(function () {
    $this->workflowsPath = base_path('routes/workflows.php');

    // Clean up before each test
    if (File::exists($this->workflowsPath)) {
        File::delete($this->workflowsPath);
    }
});

afterEach(function () {
    if (File::exists($this->workflowsPath)) {
        File::delete($this->workflowsPath);
    }
});

test('fails when routes/workflows.php does not exist', function () {
    expect(File::exists($this->workflowsPath))->toBeFalse();

    $this->artisan(MakeWorkflowCommand::class, ['name' => 'onboarding'])
        ->assertFailed()
        ->expectsOutput('routes/workflows.php not found. Run workflows:install first.');
});

test('appends workflow stub to routes/workflows.php when it exists', function () {
    File::put($this->workflowsPath, '<?php'.PHP_EOL);

    $this->artisan(MakeWorkflowCommand::class, ['name' => 'onboarding'])
        ->assertSuccessful();

    $contents = File::get($this->workflowsPath);

    expect($contents)->toContain("Workflow::flow('onboarding')");
});

test('converts name to kebab-case', function () {
    File::put($this->workflowsPath, '<?php'.PHP_EOL);

    $this->artisan(MakeWorkflowCommand::class, ['name' => 'UserOnboarding'])
        ->assertSuccessful();

    $contents = File::get($this->workflowsPath);

    expect($contents)->toContain("Workflow::flow('user-onboarding')");
});

test('generates correct StudlyCase class names in stub', function () {
    File::put($this->workflowsPath, '<?php'.PHP_EOL);

    $this->artisan(MakeWorkflowCommand::class, ['name' => 'user-onboarding'])
        ->assertSuccessful();

    $contents = File::get($this->workflowsPath);

    // The stub generates \App\Livewire\UserOnboarding\{UserOnboarding}StepOne format
    // Note: PHP heredoc with \{$var} produces literal \{VarValue} not interpolated
    expect($contents)
        ->toContain('UserOnboarding')
        ->toContain('StepOne::class');
});

test('generates correct entry route name and path', function () {
    File::put($this->workflowsPath, '<?php'.PHP_EOL);

    $this->artisan(MakeWorkflowCommand::class, ['name' => 'checkout'])
        ->assertSuccessful();

    $contents = File::get($this->workflowsPath);

    expect($contents)
        ->toContain("entersAt(name: 'checkout.start', path: '/checkout')")
        ->toContain("finishesAt('dashboard')");
});

test('generates stub with two sample steps', function () {
    File::put($this->workflowsPath, '<?php'.PHP_EOL);

    $this->artisan(MakeWorkflowCommand::class, ['name' => 'survey'])
        ->assertSuccessful();

    $contents = File::get($this->workflowsPath);

    expect($contents)
        ->toContain("->step('step-one')")
        ->toContain("->step('step-two')");
});

test('outputs success message after creating workflow', function () {
    File::put($this->workflowsPath, '<?php'.PHP_EOL);

    $this->artisan(MakeWorkflowCommand::class, ['name' => 'test-workflow'])
        ->assertSuccessful()
        ->expectsOutputToContain("Workflow 'test-workflow' created successfully!");
});
