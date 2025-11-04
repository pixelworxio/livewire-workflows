<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a user advances from one workflow step to another.
 */
class WorkflowAdvanced
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  string  $flow  The workflow name
     * @param  string|int|null  $userKey  User identifier
     * @param  string|null  $fromKey  The step being exited
     * @param  string  $toKey  The step being entered
     */
    public function __construct(
        public readonly string $flow,
        public readonly string|int|null $userKey,
        public readonly ?string $fromKey,
        public readonly string $toKey,
    ) {}
}
