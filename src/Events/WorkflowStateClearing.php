<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when workflow state is being cleared (usually on completion).
 *
 * Allows listeners to capture state data before it's removed.
 */
class WorkflowStateClearing
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  string  $flow  The workflow name
     * @param  string|int|null  $userKey  User identifier
     * @param  array  $stateData  The state data being cleared
     */
    public function __construct(
        public readonly string $flow,
        public readonly string|int|null $userKey,
        public readonly array $stateData,
    ) {}
}
