<?php

namespace Pixelworx\LivewireWorkflows\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Pixelworx\LivewireWorkflows\LivewireWorkflows
 */
class LivewireWorkflows extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Pixelworx\LivewireWorkflows\LivewireWorkflows::class;
    }
}
