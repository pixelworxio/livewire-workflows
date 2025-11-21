<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Attributes;

use Attribute;

/**
 * Attribute for declaring workflow step metadata on a component.
 *
 * This is a comprehensive "god attribute" that can set the workflow name,
 * step key, and middleware all in one place. It combines the functionality
 * of WorkflowName and StepMiddleware attributes.
 *
 * @example
 * ```php
 * use Pixelworxio\LivewireWorkflows\Attributes\WorkflowStep;
 *
 * #[WorkflowStep(flow: 'checkout', key: 'payment', middleware: ['web', 'auth', 'verified'])]
 * class PaymentStep extends Component
 * {
 *     use InteractsWithWorkflows;
 *     // Automatically sets workflow name and middleware
 * }
 * ```
 *
 * Note: The `key` parameter is for documentation/validation purposes.
 * Steps are registered via DSL, not auto-discovered from this attribute.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class WorkflowStep
{
    /**
     * @param  string  $flow  The workflow name this step belongs to
     * @param  string  $key  The step key/identifier (for documentation)
     * @param  array  $middleware  Optional middleware to apply to this step route
     */
    public function __construct(
        public readonly string $flow,
        public readonly string $key,
        public readonly array $middleware = [],
    ) {}
}
