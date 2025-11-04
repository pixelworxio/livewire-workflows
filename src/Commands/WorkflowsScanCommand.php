<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Commands;

use Illuminate\Console\Command;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowStep;
use Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar;

/**
 * Scan command to validate DSL against attributes and generate documentation.
 */
class WorkflowsScanCommand extends Command
{
    protected $signature = 'workflows:scan';

    protected $description = 'Scan for WorkflowStep attributes and validate against DSL';

    public function handle(WorkflowRegistrar $registrar): int
    {
        $this->info('Scanning for WorkflowStep attributes...');
        $this->newLine();

        $attributes = $this->findAttributedComponents();
        $workflows = $registrar->all();

        if (empty($attributes)) {
            $this->warn('No components with #[WorkflowStep] attribute found.');
        } else {
            $this->displayAttributeValidation($attributes, $workflows);
        }

        $this->newLine();
        $this->displayWorkflowMap($workflows);

        return self::SUCCESS;
    }

    protected function findAttributedComponents(): array
    {
        $components = [];
        $appPath = app_path('Livewire');

        if (!is_dir($appPath)) {
            return [];
        }

        $finder = new Finder();
        $finder->files()->in($appPath)->name('*.php');

        foreach ($finder as $file) {
            $className = 'App\\Livewire\\' . str_replace(
                    ['/', '.php'],
                    ['\\', ''],
                    $file->getRelativePathname()
                );

            if (!class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            $attributes = $reflection->getAttributes(WorkflowStep::class);

            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $components[] = [
                    'class' => $className,
                    'flow' => $instance->flow,
                    'key' => $instance->key,
                    'order' => $instance->order,
                ];
            }
        }

        return $components;
    }

    protected function displayAttributeValidation(array $attributes, array $workflows): void
    {
        $this->info('Found ' . count($attributes) . ' component(s) with #[WorkflowStep] attribute:');
        $this->newLine();

        foreach ($attributes as $attr) {
            $flowExists = isset($workflows[$attr['flow']]);
            $stepExists = $flowExists && isset($workflows[$attr['flow']]->steps[$attr['key']]);

            $status = match (true) {
                !$flowExists => '<fg=red>✗</> Flow not registered',
                !$stepExists => '<fg=yellow>!</> Step not in DSL',
                default => '<fg=green>✓</> Registered',
            };

            $this->line("  {$status} {$attr['class']}");
            $this->line("    Flow: {$attr['flow']}, Key: {$attr['key']}, Order: {$attr['order']}");

            if (!$stepExists && $flowExists) {
                $this->line('    <fg=yellow>Suggested DSL:</>');
                $this->line("    ->step('{$attr['key']}')");
                $this->line("        ->goTo({$attr['class']}::class)");
                $this->line("        ->unlessPasses(YourGuardClass::class)");
                $this->line("        ->order({$attr['order']})");
            }

            $this->newLine();
        }
    }

    protected function displayWorkflowMap(array $workflows): void
    {
        $this->info('Registered Workflows:');
        $this->newLine();

        if (empty($workflows)) {
            $this->warn('No workflows registered.');
            return;
        }

        foreach ($workflows as $workflow) {
            $this->line("## Workflow: <fg=cyan>{$workflow->flow}</>");
            $this->line("   Entry: {$workflow->entryPath} (route: {$workflow->entryRouteName})");
            $this->line("   Finish: {$workflow->finishRoute}");
            $this->line("   History: {$workflow->historyMode}");
            $this->line("   Steps:");

            foreach ($workflow->getOrderedSteps() as $step) {
                $guardInfo = $step->hasGuard() ? $step->guardClass : 'No guard';
                $routeName = $workflow->getStepRouteName($step->key);
                $path = $workflow->getStepPath($step->key);

                $this->line("     [{$step->order}] {$step->key}");
                $this->line("         Route: {$routeName} → {$path}");
                $this->line("         Component: {$step->component}");
                $this->line("         Guard: {$guardInfo}");
            }

            $this->newLine();
        }
    }
}
