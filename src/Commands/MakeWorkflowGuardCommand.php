<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Command to generate a workflow guard class.
 */
class MakeWorkflowGuardCommand extends Command
{
    protected $signature = 'make:workflow-guard {name : The guard class name}';

    protected $description = 'Create a new workflow guard class';

    public function handle(): int
    {
        $name = $this->argument('name');

        // Parse subdirectory structure (e.g., "Onboarding/EmailVerified")
        $parts = explode('/', str_replace('\\', '/', $name));
        $className = array_pop($parts);

        // Ensure Guard suffix
        if (! str_ends_with($className, 'Guard')) {
            $className .= 'Guard';
        }

        $className = Str::studly($className);

        // Build namespace and path with subdirectories
        $subdirectory = implode('/', array_map([Str::class, 'studly'], $parts));
        $namespace = 'App\\Guards';
        $path = app_path('Guards');

        if (! empty($subdirectory)) {
            $namespace .= '\\'.str_replace('/', '\\', $subdirectory);
            $path .= '/'.$subdirectory;
        }

        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }

        $filePath = $path.'/'.$className.'.php';

        if (File::exists($filePath)) {
            $this->error("Guard already exists: {$filePath}");

            return self::FAILURE;
        }

        $stub = $this->getStub($namespace, $className);
        File::put($filePath, $stub);

        $this->info("Guard created successfully: {$filePath}");

        return self::SUCCESS;
    }

    protected function getStub(string $namespace, string $className): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Illuminate\Http\Request;
use Pixelworxio\LivewireWorkflows\Contracts\GuardContract;

class {$className} implements GuardContract
{
    /**
     * Determine if the guard passes.
     *
     * @param  Request  \$request  The current request
     * @return bool True if the step can be skipped, false if the step should be shown
     */
    public function passes(Request \$request): bool
    {
        return true;
    }

    /**
     * Hook called when entering this step.
     *
     * @param  Request  \$request  The current request
     */
    public function onEnter(Request \$request): void {}

    /**
     * Hook called when exiting this step.
     *
     * @param  Request  \$request  The current request
     */
    public function onExit(Request \$request): void {}

    /**
     * Hook called when this step passes.
     *
     * @param  Request  \$request  The current request
     */
    public function onPass(Request \$request): void {}

    /**
     * Hook called when this step fails.
     *
     * @param  Request  \$request  The current request
     */
    public function onFail(Request \$request): void {}
}

PHP;
    }
}
