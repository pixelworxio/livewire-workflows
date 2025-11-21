<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Attributes;

use Attribute;

/**
 * Attribute for declaring middleware on a workflow step component.
 *
 * This is the preferred method for assigning middleware to workflow steps.
 * The middleware will be applied to the route for this step, and can be
 * overridden by DSL-based middleware if needed.
 *
 * @example
 * ```php
 * use Pixelworxio\LivewireWorkflows\Attributes\StepMiddleware;
 *
 * #[StepMiddleware(['web', 'auth', 'verified'])]
 * class PaymentStep extends Component
 * {
 *     use InteractsWithWorkflows;
 *     // This step will require authentication and email verification
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
class StepMiddleware
{
    /**
     * @param  array  $middleware  Array of middleware to apply to this step
     */
    public function __construct(
        public readonly array $middleware,
    ) {}
}
