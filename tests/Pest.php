<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(
    Tests\TestCase::class,
    RefreshDatabase::class,
)->in('Feature');

uses(Tests\TestCase::class)->in('Unit');

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
        ->goTo(Tests\Support\TestStepOneComponent::class)
        ->unlessPasses(Tests\Support\TestStepOneGuard::class)
        ->order(10)
        ->step('step-two')
        ->goTo(Tests\Support\TestStepTwoComponent::class)
        ->unlessPasses(Tests\Support\TestStepTwoGuard::class)
        ->order(20);
}
