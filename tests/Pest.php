<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

function createTestWorkflow(): void
{
    \Pixelworxio\LivewireWorkflows\Facades\Workflow::flow('test-flow')
        ->entersAt(name: 'test.start', path: '/test-flow')
        ->finishesAt('dashboard')
        ->historyMode('stack')
        ->step('step-one')
            ->goTo(\Pixelworx\LivewireWorkflows\Tests\Support\TestStepOneComponent::class)
            ->unlessPasses(\Pixelworx\LivewireWorkflows\Tests\Support\TestStepOneGuard::class)
            ->order(10)
        ->step('step-two')
            ->goTo(\Pixelworx\LivewireWorkflows\Tests\Support\TestStepTwoComponent::class)
            ->unlessPasses(\Pixelworx\LivewireWorkflows\Tests\Support\TestStepTwoGuard::class)
            ->order(20);
}
