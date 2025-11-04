<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a user completes all steps in a workflow.
 */
class WorkflowCompleted
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  string  $flow  The workflow name
     * @param  string|int|null  $userKey  User identifier
     */
    public function __construct(
        public readonly string $flow,
        public readonly string|int|null $userKey,
    ) {}
}
