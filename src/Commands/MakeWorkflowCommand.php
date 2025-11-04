<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Command to scaffold a new workflow in routes/workflows.php.
 */
class MakeWorkflowCommand extends Command
{
    protected $signature = 'make:workflow {name : The workflow name}';

    protected $description = 'Create a new workflow definition';

    public function handle(): int
    {
        $name = Str::kebab($this->argument('name'));
        $studlyName = Str::studly($name);

        $workflowsPath = base_path('routes/workflows.php');

        if (! File::exists($workflowsPath)) {
            $this->error('routes/workflows.php not found. Run workflows:install first.');

            return self::FAILURE;
        }

        $stub = $this->getStub($name, $studlyName);

        // Append to workflows file
        File::append($workflowsPath, "\n".$stub);

        $this->info("Workflow '{$name}' created successfully!");
        $this->line('Edit routes/workflows.php to customize the workflow.');

        return self::SUCCESS;
    }

    protected function getStub(string $name, string $studlyName): string
    {
        return <<<PHP
// New workflow definition for {$name}
Workflow::flow('{$name}')
    ->entersAt(name: '{$name}.start', path: '/{$name}')
    ->finishesAt('dashboard')
    ->historyMode('stack')
    ->step('step-one')
        ->goTo(\App\Livewire\\{$studlyName}\{$studlyName}StepOne::class)
        ->unlessPasses(\App\Guards\\{$studlyName}\{$studlyName}StepOneGuard::class)
        ->order(10)
    ->step('step-two')
        ->goTo(\App\Livewire\\{$studlyName}\{$studlyName}StepTwo::class)
        ->unlessPasses(\App\Guards\\{$studlyName}\{$studlyName}StepTwoGuard::class)
        ->order(20);
PHP;
    }
}
