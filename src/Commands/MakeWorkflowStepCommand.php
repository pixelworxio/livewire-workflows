<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Command to add a step to an existing workflow.
 */
class MakeWorkflowStepCommand extends Command
{
    protected $signature = 'make:workflow-step
                            {flow : The workflow name}
                            {key : The step key}
                            {--guard= : The guard class name}
                            {--component= : The Livewire component class}
                            {--order=10 : The step order}';

    protected $description = 'Add a step to an existing workflow';

    public function handle(): int
    {
        $flow = $this->argument('flow');
        $key = $this->argument('key');
        $guardClass = $this->option('guard');
        $componentClass = $this->option('component');
        $order = (int) $this->option('order');

        // Generate component if provided and doesn't exist
        if ($componentClass && !class_exists($componentClass)) {
            $componentName = str_replace(['App\\Livewire\\', '\\'], ['', '.'], $componentClass);

            $this->info("Component {$componentClass} not found. Creating...");

            Artisan::call('make:livewire', ['name' => $componentName]);

            $this->info(Artisan::output());
        }

        // Build DSL snippet
        $snippet = $this->buildSnippet($key, $componentClass, $guardClass, $order);

        $this->newLine();
        $this->info("Add this step to your '{$flow}' workflow in routes/workflows.php:");
        $this->newLine();
        $this->line($snippet);
        $this->newLine();

        return self::SUCCESS;
    }

    protected function buildSnippet(string $key, ?string $component, ?string $guard, int $order): string
    {
        $lines = [];
        $lines[] = "    ->step('{$key}')";

        if ($component) {
            $lines[] = "        ->goTo({$component}::class)";
        }

        if ($guard) {
            $lines[] = "        ->unlessPasses({$guard}::class)";
        }

        $lines[] = "        ->order({$order})";

        return implode("\n", $lines);
    }
}
