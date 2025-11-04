<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Facades;

use Illuminate\Support\Facades\Facade;
use Pixelworxio\LivewireWorkflows\Registrar\FlowBuilder;
use Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar;

/**
 * @method static FlowBuilder flow(string $flow)
 * @method static \Pixelworxio\LivewireWorkflows\Support\WorkflowDefinition get(string $flow)
 * @method static bool has(string $flow)
 * @method static array all()
 *
 * @see WorkflowRegistrar
 */
class Workflow extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WorkflowRegistrar::class;
    }
}
