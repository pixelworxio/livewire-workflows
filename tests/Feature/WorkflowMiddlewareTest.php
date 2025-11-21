<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Pixelworxio\LivewireWorkflows\Attributes\StepMiddleware;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowName;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowStep;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Pixelworxio\LivewireWorkflows\Livewire\Concerns\InteractsWithWorkflows;
use Pixelworxio\LivewireWorkflows\Support\RouteRegistrar;
use Tests\Support\TestStepOneGuard;

beforeEach(function () {
    app(\Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->clear();
    TestStepOneGuard::$shouldPass = false;
});

// Test Components for middleware tests
class PublicStepComponent extends Component
{
    use InteractsWithWorkflows;

    public function render()
    {
        return '<div>Public Step</div>';
    }
}

#[WorkflowName('checkout')]
#[StepMiddleware(['web', 'auth'])]
class AuthRequiredStepComponent extends Component
{
    use InteractsWithWorkflows;

    public function render()
    {
        return '<div>Auth Required Step</div>';
    }
}

#[WorkflowName('checkout')]
#[StepMiddleware(['web', 'auth', 'verified'])]
class VerifiedStepComponent extends Component
{
    use InteractsWithWorkflows;

    public function render()
    {
        return '<div>Verified Step</div>';
    }
}

// Test Components using WorkflowStep attribute
#[WorkflowStep(name: 'checkout', middleware: ['web', 'auth'])]
class WorkflowStepTestComponent extends Component
{
    use InteractsWithWorkflows;

    public function render()
    {
        return '<div>Workflow Step Test</div>';
    }
}

#[WorkflowStep(name: 'product-review')]
class WorkflowStepNoMiddlewareComponent extends Component
{
    use InteractsWithWorkflows;

    public function render()
    {
        return '<div>Workflow Step No Middleware</div>';
    }
}

test('workflow can have custom middleware via DSL', function () {
    Workflow::flow('admin-panel')
        ->middleware(['web', 'auth', 'admin'])
        ->entersAt(name: 'admin.start', path: '/admin')
        ->finishesAt('dashboard')
        ->step('settings')
            ->goTo(PublicStepComponent::class)
            ->order(10);

    $registrar = app(\Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class);
    $workflow = $registrar->get('admin-panel');

    expect($workflow->middleware)->toBe(['web', 'auth', 'admin']);
});

test('step can have custom middleware via DSL', function () {
    Workflow::flow('checkout')
        ->entersAt(name: 'checkout.start', path: '/checkout')
        ->finishesAt('dashboard')
        ->step('payment')
            ->goTo(PublicStepComponent::class)
            ->middleware(['web', 'auth', 'verified'])
            ->order(10);

    $registrar = app(\Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class);
    $workflow = $registrar->get('checkout');
    $step = $workflow->getStep('payment');

    expect($step->middleware)->toBe(['web', 'auth', 'verified']);
});

test('step reads middleware from StepMiddleware attribute', function () {
    Workflow::flow('checkout')
        ->entersAt(name: 'checkout.start', path: '/checkout')
        ->finishesAt('dashboard')
        ->step('payment')
            ->goTo(AuthRequiredStepComponent::class)
            ->order(10);

    $registrar = app(\Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class);
    $workflow = $registrar->get('checkout');
    $step = $workflow->getStep('payment');

    expect($step->middleware)->toBe(['web', 'auth']);
});

test('DSL middleware overrides attribute middleware', function () {
    Workflow::flow('checkout')
        ->entersAt(name: 'checkout.start', path: '/checkout')
        ->finishesAt('dashboard')
        ->step('payment')
            ->goTo(AuthRequiredStepComponent::class)
            ->middleware(['web', 'custom'])
            ->order(10);

    $registrar = app(\Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class);
    $workflow = $registrar->get('checkout');
    $step = $workflow->getStep('payment');

    // DSL middleware should override attribute middleware
    expect($step->middleware)->toBe(['web', 'custom']);
});

test('workflow supports callable middleware', function () {
    Workflow::flow('dynamic-auth')
        ->middleware(fn () => ['web', 'auth'])
        ->entersAt(name: 'dynamic.start', path: '/dynamic')
        ->finishesAt('dashboard')
        ->step('step1')
            ->goTo(PublicStepComponent::class)
            ->order(10);

    $registrar = app(\Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class);
    $workflow = $registrar->get('dynamic-auth');

    expect($workflow->middleware)->toBeInstanceOf(Closure::class);
});

test('step supports callable middleware', function () {
    Workflow::flow('checkout')
        ->entersAt(name: 'checkout.start', path: '/checkout')
        ->finishesAt('dashboard')
        ->step('payment')
            ->goTo(PublicStepComponent::class)
            ->middleware(fn () => ['web', 'auth'])
            ->order(10);

    $registrar = app(\Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class);
    $workflow = $registrar->get('checkout');
    $step = $workflow->getStep('payment');

    expect($step->middleware)->toBeInstanceOf(Closure::class);
});

test('routes are registered with correct middleware in override mode', function () {
    config(['livewire-workflows.middleware_precedence' => 'override']);
    config(['livewire-workflows.middleware' => ['web']]);

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');

    Workflow::flow('checkout')
        ->middleware(['web', 'auth'])
        ->entersAt(name: 'checkout.start', path: '/checkout')
        ->finishesAt('dashboard')
        ->step('public-view')
            ->goTo(PublicStepComponent::class)
            ->order(10)
        ->step('payment')
            ->goTo(PublicStepComponent::class)
            ->middleware(['web', 'auth', 'verified'])
            ->order(20);

    app(RouteRegistrar::class)->register();
    app('router')->getRoutes()->refreshNameLookups();

    $entryRoute = Route::getRoutes()->getByName('checkout.start');
    $publicRoute = Route::getRoutes()->getByName('checkout.public-view');
    $paymentRoute = Route::getRoutes()->getByName('checkout.payment');

    // Entry route should use workflow middleware
    expect($entryRoute->middleware())->toContain('web', 'auth');

    // Public view should inherit workflow middleware (no step middleware)
    expect($publicRoute->middleware())->toContain('web', 'auth');

    // Payment should override with its own middleware
    expect($paymentRoute->middleware())->toContain('web', 'auth', 'verified');
});

test('routes are registered with correct middleware in merge mode', function () {
    config(['livewire-workflows.middleware_precedence' => 'merge']);
    config(['livewire-workflows.middleware' => ['web']]);

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');

    Workflow::flow('checkout')
        ->middleware(['auth'])
        ->entersAt(name: 'checkout.start', path: '/checkout')
        ->finishesAt('dashboard')
        ->step('payment')
            ->goTo(PublicStepComponent::class)
            ->middleware(['verified'])
            ->order(10);

    app(RouteRegistrar::class)->register();
    app('router')->getRoutes()->refreshNameLookups();

    $paymentRoute = Route::getRoutes()->getByName('checkout.payment');

    // In merge mode, all middleware should be combined: global + workflow + step
    $middleware = $paymentRoute->middleware();
    expect($middleware)->toContain('web', 'auth', 'verified');
});

test('routes use global middleware when no workflow or step middleware is defined', function () {
    config(['livewire-workflows.middleware' => ['web', 'custom']]);

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');

    Workflow::flow('simple')
        ->entersAt(name: 'simple.start', path: '/simple')
        ->finishesAt('dashboard')
        ->step('step1')
            ->goTo(PublicStepComponent::class)
            ->order(10);

    app(RouteRegistrar::class)->register();
    app('router')->getRoutes()->refreshNameLookups();

    $route = Route::getRoutes()->getByName('simple.step1');

    expect($route->middleware())->toContain('web', 'custom');
});

test('callable middleware is evaluated at route registration', function () {
    config(['livewire-workflows.middleware_precedence' => 'override']);

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');

    $dynamicMiddleware = ['web', 'dynamic'];

    Workflow::flow('dynamic')
        ->middleware(fn () => $dynamicMiddleware)
        ->entersAt(name: 'dynamic.start', path: '/dynamic')
        ->finishesAt('dashboard')
        ->step('step1')
            ->goTo(PublicStepComponent::class)
            ->order(10);

    app(RouteRegistrar::class)->register();
    app('router')->getRoutes()->refreshNameLookups();

    $route = Route::getRoutes()->getByName('dynamic.step1');

    expect($route->middleware())->toContain('web', 'dynamic');
});

test('middleware precedence respects step > workflow > global in override mode', function () {
    config(['livewire-workflows.middleware_precedence' => 'override']);
    config(['livewire-workflows.middleware' => ['web']]);

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');

    Workflow::flow('precedence-test')
        ->middleware(['workflow-middleware'])
        ->entersAt(name: 'precedence.start', path: '/precedence')
        ->finishesAt('dashboard')
        ->step('no-step-middleware')
            ->goTo(PublicStepComponent::class)
            ->order(10)
        ->step('with-step-middleware')
            ->goTo(PublicStepComponent::class)
            ->middleware(['step-middleware'])
            ->order(20);

    app(RouteRegistrar::class)->register();
    app('router')->getRoutes()->refreshNameLookups();

    $noStepMiddlewareRoute = Route::getRoutes()->getByName('precedence-test.no-step-middleware');
    $withStepMiddlewareRoute = Route::getRoutes()->getByName('precedence-test.with-step-middleware');

    // Step without middleware should inherit workflow middleware
    expect($noStepMiddlewareRoute->middleware())->toContain('workflow-middleware')
        ->and($noStepMiddlewareRoute->middleware())->not()->toContain('web');

    // Step with middleware should override workflow middleware
    expect($withStepMiddlewareRoute->middleware())->toContain('step-middleware')
        ->and($withStepMiddlewareRoute->middleware())->not()->toContain('workflow-middleware')
        ->and($withStepMiddlewareRoute->middleware())->not()->toContain('web');
});

test('merge mode combines and deduplicates middleware', function () {
    config(['livewire-workflows.middleware_precedence' => 'merge']);
    config(['livewire-workflows.middleware' => ['web', 'shared']]);

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');

    Workflow::flow('merge-test')
        ->middleware(['auth', 'shared'])
        ->entersAt(name: 'merge.start', path: '/merge')
        ->finishesAt('dashboard')
        ->step('step1')
            ->goTo(PublicStepComponent::class)
            ->middleware(['verified', 'shared'])
            ->order(10);

    app(RouteRegistrar::class)->register();
    app('router')->getRoutes()->refreshNameLookups();

    $route = Route::getRoutes()->getByName('merge-test.step1');
    $middleware = $route->middleware();

    // Should contain all middleware: web, auth, verified
    expect($middleware)->toContain('web', 'auth', 'verified');

    // 'shared' should appear only once (deduplicated)
    $sharedCount = count(array_filter($middleware, fn ($m) => $m === 'shared'));
    expect($sharedCount)->toBe(1);
});

test('mixed public and authenticated steps in same workflow', function () {
    config(['livewire-workflows.middleware_precedence' => 'override']);
    config(['livewire-workflows.middleware' => ['web']]);

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');

    Workflow::flow('product-review')
        ->entersAt(name: 'review.start', path: '/review')
        ->finishesAt('dashboard')
        ->step('view')
            ->goTo(PublicStepComponent::class)
            ->middleware(['web'])
            ->order(10)
        ->step('write')
            ->goTo(AuthRequiredStepComponent::class)
            ->order(20)
        ->step('submit')
            ->goTo(VerifiedStepComponent::class)
            ->order(30);

    app(RouteRegistrar::class)->register();
    app('router')->getRoutes()->refreshNameLookups();

    $viewRoute = Route::getRoutes()->getByName('product-review.view');
    $writeRoute = Route::getRoutes()->getByName('product-review.write');
    $submitRoute = Route::getRoutes()->getByName('product-review.submit');

    // View step should be public (only web middleware)
    expect($viewRoute->middleware())->toContain('web')
        ->and($viewRoute->middleware())->not()->toContain('auth');

    // Write step should require auth (from attribute)
    expect($writeRoute->middleware())->toContain('web', 'auth');

    // Submit step should require auth + verified (from attribute)
    expect($submitRoute->middleware())->toContain('web', 'auth', 'verified');
});

test('WorkflowStep attribute sets both workflow name and middleware', function () {
    Workflow::flow('checkout')
        ->entersAt(name: 'checkout.start', path: '/checkout')
        ->finishesAt('dashboard')
        ->step('test')
            ->goTo(WorkflowStepTestComponent::class)
            ->order(10);

    $registrar = app(\Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class);
    $workflow = $registrar->get('checkout');
    $step = $workflow->getStep('test');

    // Should read middleware from WorkflowStep attribute
    expect($step->middleware)->toBe(['web', 'auth']);
});

test('WorkflowStep attribute with empty middleware returns null', function () {
    Workflow::flow('product-review')
        ->entersAt(name: 'review.start', path: '/review')
        ->finishesAt('dashboard')
        ->step('view')
            ->goTo(WorkflowStepNoMiddlewareComponent::class)
            ->order(10);

    $registrar = app(\Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class);
    $workflow = $registrar->get('product-review');
    $step = $workflow->getStep('view');

    // Empty middleware array should return null
    expect($step->middleware)->toBeNull();
});

test('WorkflowStep attribute is preferred over StepMiddleware', function () {
    // This test verifies that WorkflowStep takes precedence over StepMiddleware
    // when both are present. We're using WorkflowStepTestComponent which has
    // WorkflowStep attribute with ['web', 'auth'] middleware.

    Workflow::flow('checkout')
        ->entersAt(name: 'checkout.start', path: '/checkout')
        ->finishesAt('dashboard')
        ->step('test')
            ->goTo(WorkflowStepTestComponent::class)
            ->order(10);

    $registrar = app(\Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class);
    $workflow = $registrar->get('checkout');
    $step = $workflow->getStep('test');

    // Should use middleware from WorkflowStep attribute
    expect($step->middleware)->toBe(['web', 'auth']);
});

test('DSL middleware still overrides WorkflowStep attribute', function () {
    Workflow::flow('checkout')
        ->entersAt(name: 'checkout.start', path: '/checkout')
        ->finishesAt('dashboard')
        ->step('test')
            ->goTo(WorkflowStepTestComponent::class)
            ->middleware(['web', 'custom']) // DSL middleware
            ->order(10);

    $registrar = app(\Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class);
    $workflow = $registrar->get('checkout');
    $step = $workflow->getStep('test');

    // DSL middleware should override WorkflowStep attribute
    expect($step->middleware)->toBe(['web', 'custom']);
});
