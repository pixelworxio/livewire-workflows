<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Attributes;

use Attribute;

/**
 * Attribute for declaring workflow name and middleware on a step component.
 *
 * This is the preferred all-in-one attribute for workflow step components.
 * It combines the functionality of WorkflowName and StepMiddleware attributes.
 *
 * @example
 * ```php
 * use Pixelworxio\LivewireWorkflows\Attributes\WorkflowStep;
 *
 * #[WorkflowStep(name: 'checkout', middleware: ['web', 'auth', 'verified'])]
 * class PaymentStep extends Component
 * {
 *     use InteractsWithWorkflows;
 *     // Automatically sets workflow name and middleware
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
class WorkflowStep
{
    /**
     * @param  string  $name  The workflow name this step belongs to
     * @param  array  $middleware  Optional middleware to apply to this step route
     */
    public function __construct(
        public readonly string $name,
        public readonly array $middleware = [],
    ) {}
}
