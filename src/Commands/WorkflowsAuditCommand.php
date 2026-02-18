<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowStep;
use Pixelworxio\LivewireWorkflows\Contracts\GuardContract;
use Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar;
use Pixelworxio\LivewireWorkflows\Support\StepDefinition;
use Pixelworxio\LivewireWorkflows\Support\WorkflowDefinition;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

/**
 * Audit command to validate workflow DSL definitions against the runtime application.
 */
class WorkflowsAuditCommand extends Command
{
    protected $signature = 'workflows:audit';

    protected $description = 'Audit workflow DSL definitions against the runtime application';

    public function handle(WorkflowRegistrar $registrar): int
    {
        $this->info('Auditing workflow definitions...');
        $this->newLine();

        $workflows = $registrar->all();

        if (empty($workflows)) {
            $this->warn('No workflows registered. Nothing to audit.');
            $this->newLine();
            $this->line('Define workflows in routes/workflows.php and run this command again.');

            return self::SUCCESS;
        }

        $totalIssues = 0;

        foreach ($workflows as $workflow) {
            $issues = $this->auditWorkflow($workflow);
            $totalIssues += count($issues);
        }

        $orphanIssues = $this->auditOrphanedSteps($workflows);
        $totalIssues += count($orphanIssues);

        $this->newLine();
        $this->printSummary(count($workflows), $totalIssues);

        return $totalIssues > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function auditWorkflow(WorkflowDefinition $workflow): array
    {
        $this->line("## Workflow: <fg=cyan>{$workflow->flow}</>");
        $this->line("   Entry: {$workflow->entryPath} (route: {$workflow->entryRouteName})");
        $this->line("   Finish: {$workflow->finishRoute}");
        $this->newLine();

        $issues = [];

        $routeIssues = $this->auditFinishRoute($workflow);
        $issues = array_merge($issues, $routeIssues);

        foreach ($workflow->getOrderedSteps() as $step) {
            $stepIssues = $this->auditStep($step);
            $issues = array_merge($issues, $stepIssues);
        }

        if (empty($issues)) {
            $this->line('   <fg=green>✓</> All checks passed.');
        }

        $this->newLine();

        return $issues;
    }

    private function auditFinishRoute(WorkflowDefinition $workflow): array
    {
        $issues = [];

        if (Route::has($workflow->finishRoute)) {
            $this->line("   <fg=green>✓</> Finish route '{$workflow->finishRoute}' is registered.");
        } else {
            $message = "Finish route '{$workflow->finishRoute}' is NOT registered.";
            $this->line("   <fg=red>✗</> {$message}");
            $issues[] = $message;
        }

        return $issues;
    }

    private function auditStep(StepDefinition $step): array
    {
        $this->line("   Step: <fg=cyan>{$step->key}</> (order: {$step->order})");

        $issues = [];

        $componentIssues = $this->auditStepComponent($step);
        $issues = array_merge($issues, $componentIssues);

        $guardIssues = $this->auditStepGuard($step);
        $issues = array_merge($issues, $guardIssues);

        return $issues;
    }

    private function auditStepComponent(StepDefinition $step): array
    {
        $issues = [];
        $component = $step->component;

        if (is_string($component)) {
            if (class_exists($component)) {
                $this->line("      <fg=green>✓</> Component: {$component}");
            } else {
                $message = "Component class '{$component}' does not exist.";
                $this->line("      <fg=red>✗</> {$message}");
                $issues[] = "[{$step->flow}.{$step->key}] {$message}";
            }

            return $issues;
        }

        if (is_array($component)) {
            [$className, $methodName] = $component;

            if (! class_exists($className)) {
                $message = "Component class '{$className}' does not exist.";
                $this->line("      <fg=red>✗</> {$message}");
                $issues[] = "[{$step->flow}.{$step->key}] {$message}";

                return $issues;
            }

            if (! method_exists($className, $methodName)) {
                $message = "Method '{$methodName}' does not exist on '{$className}'.";
                $this->line("      <fg=red>✗</> {$message}");
                $issues[] = "[{$step->flow}.{$step->key}] {$message}";
            } else {
                $this->line("      <fg=green>✓</> Component: {$className}@{$methodName}");
            }

            return $issues;
        }

        $message = "Component is null or invalid for step '{$step->key}'.";
        $this->line("      <fg=red>✗</> {$message}");
        $issues[] = "[{$step->flow}.{$step->key}] {$message}";

        return $issues;
    }

    private function auditStepGuard(StepDefinition $step): array
    {
        $issues = [];

        if (! $step->hasGuard()) {
            $this->line('      <fg=yellow>-</> Guard: none configured');

            return $issues;
        }

        $guardClass = $step->guardClass;

        if (! class_exists($guardClass)) {
            $message = "Guard class '{$guardClass}' does not exist.";
            $this->line("      <fg=red>✗</> Guard: {$message}");
            $issues[] = "[{$step->flow}.{$step->key}] {$message}";

            return $issues;
        }

        $implements = class_implements($guardClass);

        if (! in_array(GuardContract::class, $implements ?: [], true)) {
            $message = "Guard '{$guardClass}' does not implement GuardContract.";
            $this->line("      <fg=red>✗</> Guard: {$message}");
            $issues[] = "[{$step->flow}.{$step->key}] {$message}";
        } else {
            $this->line("      <fg=green>✓</> Guard: {$guardClass}");
        }

        return $issues;
    }

    private function auditOrphanedSteps(array $workflows): array
    {
        $this->line('## Orphaned #[WorkflowStep] Attributes');
        $this->newLine();

        $attributedComponents = $this->findAttributedComponents();

        if (empty($attributedComponents)) {
            $this->line('   <fg=green>✓</> No components with #[WorkflowStep] found (nothing to check).');
            $this->newLine();

            return [];
        }

        $issues = [];

        foreach ($attributedComponents as $component) {
            $flow = $component['flow'];
            $key = $component['key'];

            $inDsl = isset($workflows[$flow]) && isset($workflows[$flow]->steps[$key]);

            if ($inDsl) {
                $this->line("   <fg=green>✓</> {$component['class']} ({$flow}.{$key}) is referenced in DSL.");
            } else {
                $reason = ! isset($workflows[$flow])
                    ? "workflow '{$flow}' is not registered"
                    : "step '{$key}' not found in workflow '{$flow}'";

                $message = "{$component['class']} has #[WorkflowStep] but {$reason}.";
                $this->line("   <fg=yellow>!</> {$message}");
                $issues[] = $message;
            }
        }

        $this->newLine();

        return $issues;
    }

    private function findAttributedComponents(): array
    {
        $components = [];
        $appPath = app_path('Livewire');

        if (! is_dir($appPath)) {
            return [];
        }

        $finder = new Finder;
        $finder->files()->in($appPath)->name('*.php');

        foreach ($finder as $file) {
            $className = 'App\\Livewire\\'.str_replace(
                ['/', '.php'],
                ['\\', ''],
                $file->getRelativePathname()
            );

            if (! class_exists($className)) {
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
                ];
            }
        }

        return $components;
    }

    private function printSummary(int $workflowCount, int $issueCount): void
    {
        if ($issueCount === 0) {
            $this->info("Audit complete: {$workflowCount} workflow(s) audited, no issues found.");
        } else {
            $issueWord = $issueCount === 1 ? 'issue' : 'issues';
            $this->error("Audit complete: {$workflowCount} workflow(s) audited, {$issueCount} {$issueWord} found.");
        }
    }
}
