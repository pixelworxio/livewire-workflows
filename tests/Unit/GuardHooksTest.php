<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Pixelworxio\LivewireWorkflows\Support\WorkflowEngine;
use Tests\Support\TrackableGuard;

beforeEach(function () {
    app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->clear();

    TrackableGuard::reset();

    $builder = Workflow::flow('guard-hook-test')
        ->entersAt(name: 'guard-test.start', path: '/guard-test')
        ->finishesAt('dashboard')
        ->historyMode('stack')
        ->step('step-one')
        ->goTo(Tests\Support\TestStepOneComponent::class)
        ->unlessPasses(TrackableGuard::class)
        ->order(10);

    $builder->build();

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');

    app(Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class)->register();

    // Refresh route collection
    app('router')->getRoutes()->refreshNameLookups();

    $this->workflow = app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->get('guard-hook-test');
    $this->engine = app(WorkflowEngine::class);

    // Properly initialize request with session
    $this->request = Request::create('/guard-test');

    // Start a session and attach it to the request
    $session = app('session')->driver();
    $session->setId(\Illuminate\Support\Str::random(40));
    $session->start();
    $this->request->setLaravelSession($session);
});

afterEach(function () {
    TrackableGuard::reset();
});

test('onPass is called when guard passes in nextStep', function () {
    TrackableGuard::$shouldPass = true;

    $this->engine->nextStep($this->workflow, $this->request);

    expect(TrackableGuard::$calls)
        ->toContain('passes')
        ->toContain('onPass');
});

test('onFail is called when guard fails in nextStep', function () {
    TrackableGuard::$shouldPass = false;

    $this->engine->nextStep($this->workflow, $this->request);

    expect(TrackableGuard::$calls)
        ->toContain('passes')
        ->toContain('onFail');
});

test('onPass is not called when guard fails in nextStep', function () {
    TrackableGuard::$shouldPass = false;

    $this->engine->nextStep($this->workflow, $this->request);

    expect(TrackableGuard::$calls)
        ->not->toContain('onPass');
});

test('onFail is not called when guard passes in nextStep', function () {
    TrackableGuard::$shouldPass = true;

    $this->engine->nextStep($this->workflow, $this->request);

    expect(TrackableGuard::$calls)
        ->not->toContain('onFail');
});

test('onEnter is called when advancing to a step', function () {
    TrackableGuard::reset();

    $this->engine->advanceTo($this->workflow, 'step-one', $this->request);

    expect(TrackableGuard::$calls)->toContain('onEnter');
});

test('onExit is called when leaving a step', function () {
    // First, advance to step-one
    $this->engine->advanceTo($this->workflow, 'step-one', $this->request);

    // Reset calls to track only the exit
    TrackableGuard::$calls = [];

    // Now create a second guard and step to advance to
    app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->clear();

    $builder = Workflow::flow('guard-hook-test')
        ->entersAt(name: 'guard-test.start', path: '/guard-test')
        ->finishesAt('dashboard')
        ->historyMode('stack')
        ->step('step-one')
        ->goTo(Tests\Support\TestStepOneComponent::class)
        ->unlessPasses(TrackableGuard::class)
        ->order(10)
        ->step('step-two')
        ->goTo(Tests\Support\TestStepTwoComponent::class)
        ->unlessPasses(Tests\Support\TestStepTwoGuard::class)
        ->order(20);

    $builder->build();

    app(Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class)->register();
    app('router')->getRoutes()->refreshNameLookups();

    $this->workflow = app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->get('guard-hook-test');

    // Advance to step-two (leaving step-one)
    $this->engine->advanceTo($this->workflow, 'step-two', $this->request);

    expect(TrackableGuard::$calls)->toContain('onExit');
});

test('onEnter and onExit are called in correct order when advancing between steps', function () {
    // Create workflow with two trackable guards
    app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->clear();

    // Create a second trackable guard class for step-two
    $secondGuard = new class implements Pixelworxio\LivewireWorkflows\Contracts\GuardContract
    {
        public static array $calls = [];

        public function passes(Request $request): bool
        {
            return false;
        }

        public function onEnter(Request $request): void
        {
            self::$calls[] = 'step-two-onEnter';
        }

        public function onExit(Request $request): void
        {
            self::$calls[] = 'step-two-onExit';
        }

        public function onPass(Request $request): void {}

        public function onFail(Request $request): void {}
    };

    $builder = Workflow::flow('guard-hook-test')
        ->entersAt(name: 'guard-test.start', path: '/guard-test')
        ->finishesAt('dashboard')
        ->historyMode('stack')
        ->step('step-one')
        ->goTo(Tests\Support\TestStepOneComponent::class)
        ->unlessPasses(TrackableGuard::class)
        ->order(10)
        ->step('step-two')
        ->goTo(Tests\Support\TestStepTwoComponent::class)
        ->unlessPasses($secondGuard::class)
        ->order(20);

    $builder->build();

    app(Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class)->register();
    app('router')->getRoutes()->refreshNameLookups();

    $this->workflow = app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->get('guard-hook-test');

    // Advance to step-one
    TrackableGuard::reset();
    $this->engine->advanceTo($this->workflow, 'step-one', $this->request);

    expect(TrackableGuard::$calls)->toContain('onEnter');

    // Advance to step-two
    TrackableGuard::$calls = [];
    $secondGuard::$calls = [];
    $this->engine->advanceTo($this->workflow, 'step-two', $this->request);

    // step-one's onExit should be called, then step-two's onEnter
    expect(TrackableGuard::$calls)->toContain('onExit')
        ->and($secondGuard::$calls)->toContain('step-two-onEnter');
});

test('hooks are called with the correct request parameter', function () {
    $customRequest = Request::create('/guard-test', 'GET', ['test_param' => 'test_value']);

    // Start a session and attach it to the request
    $session = app('session')->driver();
    $session->setId(\Illuminate\Support\Str::random(40));
    $session->start();
    $customRequest->setLaravelSession($session);

    TrackableGuard::reset();

    // Create a guard that validates the request
    $requestValidatingGuard = new class implements Pixelworxio\LivewireWorkflows\Contracts\GuardContract
    {
        public static ?Request $receivedRequest = null;

        public function passes(Request $request): bool
        {
            self::$receivedRequest = $request;

            return false;
        }

        public function onEnter(Request $request): void
        {
            self::$receivedRequest = $request;
        }

        public function onExit(Request $request): void {}

        public function onPass(Request $request): void {}

        public function onFail(Request $request): void {}
    };

    app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->clear();

    $builder = Workflow::flow('guard-hook-test')
        ->entersAt(name: 'guard-test.start', path: '/guard-test')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(Tests\Support\TestStepOneComponent::class)
        ->unlessPasses($requestValidatingGuard::class)
        ->order(10);

    $builder->build();

    app(Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class)->register();
    app('router')->getRoutes()->refreshNameLookups();

    $this->workflow = app(Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->get('guard-hook-test');

    $this->engine->advanceTo($this->workflow, 'step-one', $customRequest);

    expect($requestValidatingGuard::$receivedRequest)
        ->not->toBeNull()
        ->and($requestValidatingGuard::$receivedRequest->input('test_param'))
        ->toBe('test_value');
});
