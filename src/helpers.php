<?php

declare(strict_types=1);

use Pixelworxio\LivewireWorkflows\Support\WorkflowInstance;
use Pixelworxio\LivewireWorkflows\Support\WorkflowResolver;

if (! function_exists('workflow')) {
    /**
     * Get a workflow instance for a specific flow.
     *
     * @param  string  $flow  The workflow name
     */
    function workflow(string $flow): WorkflowInstance
    {
        $resolver = app(WorkflowResolver::class);

        return new WorkflowInstance($flow, $resolver);
    }
}
