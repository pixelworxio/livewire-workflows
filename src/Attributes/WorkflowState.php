<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Attributes;

use Attribute;

/**
 * Attribute for marking Livewire component properties for workflow state persistence.
 *
 * Properties marked with this attribute will automatically be persisted
 * to the workflow state repository and hydrated on component load.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class WorkflowState
{
    /**
     * @param  bool  $encrypt  Whether to encrypt the value in storage
     * @param  string|null  $namespace  Optional namespace for grouping state keys
     */
    public function __construct(
        public readonly bool $encrypt = false,
        public readonly ?string $namespace = null,
    ) {}
}
