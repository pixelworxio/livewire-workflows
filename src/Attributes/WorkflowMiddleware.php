<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Attributes;

use Attribute;

/**
 * Attribute for declaring default middleware for all steps in a workflow.
 *
 * This attribute can be placed on a workflow provider class or used as
 * a reference for workflow-level middleware configuration. In most cases,
 * you'll use the DSL middleware() method instead.
 *
 * @example
 * ```php
 * use Pixelworxio\LivewireWorkflows\Attributes\WorkflowMiddleware;
 *
 * #[WorkflowMiddleware(['web', 'auth'])]
 * class CheckoutWorkflowProvider
 * {
 *     // Workflow provider class with default middleware
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
class WorkflowMiddleware
{
    /**
     * @param  array  $middleware  Array of middleware to apply to all workflow steps
     */
    public function __construct(
        public readonly array $middleware,
    ) {}
}
