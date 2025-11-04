<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Exceptions;

use Exception;

/**
 * Exception thrown when a workflow cannot be found.
 */
class WorkflowNotFoundException extends Exception
{
    /**
     * Create a new exception for a missing workflow.
     *
     * @param string $flow The workflow name that was not found
     * @return static
     */
    public static function forFlow(string $flow): static
    {
        return new static("Workflow '{$flow}' not found. Did you register it in routes/workflows.php?");
    }
}
