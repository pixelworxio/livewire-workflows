<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Attributes;

use Attribute;

/**
 * Attribute for declaring the workflow name on a Livewire component class.
 *
 * This eliminates the need to manually set the $workflowName property
 * in every component. Simply add this attribute to the component class:
 *
 * @example
 * ```php
 * use Pixelworxio\LivewireWorkflows\Attributes\WorkflowName;
 *
 * #[WorkflowName('onboarding')]
 * class VerifyEmailStep extends Component
 * {
 *     use InteractsWithWorkflows;
 *     // No need to set protected ?string $workflowName = 'onboarding';
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
class WorkflowName
{
    /**
     * @param  string  $name  The workflow identifier
     */
    public function __construct(
        public readonly string $name,
    ) {}
}
