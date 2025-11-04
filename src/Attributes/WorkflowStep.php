<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Attributes;

use Attribute;

/**
 * Attribute for marking Livewire components as workflow steps.
 *
 * This is NOT used for auto-registration (DSL is source of truth),
 * but can be used by the workflows:scan command for validation.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class WorkflowStep
{
    public function __construct(
        public readonly string $flow,
        public readonly string $key,
        public readonly int $order = 0,
    ) {
    }
}
