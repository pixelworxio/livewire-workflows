<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Livewire\Livewire;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Pixelworxio\LivewireWorkflows\Livewire\Concerns\InteractsWithWorkflows;

// Test Components
class DynamicStepOneComponent extends Component
{
    use InteractsWithWorkflows;

    public $user;
    public $product;

    public function mount($user = null, $product = null)
    {
        $this->user = $user;
        $this->product = $product;
    }

    public function render()
    {
        return '<div>Step One</div>';
    }
}

class DynamicStepTwoComponent extends Component
{
    use InteractsWithWorkflows;

    public $user;
    public $product;

    public function mount($user = null, $product = null)
    {
        $this->user = $user;
        $this->product = $product;
    }

    public function render()
    {
        return '<div>Step Two</div>';
    }
}

// Test Guards
class DynamicStepOneGuard implements \Pixelworxio\LivewireWorkflows\Contracts\GuardContract
{
    public static bool $shouldPass = false;

    public function passes(\Illuminate\Http\Request $request): bool
    {
        return self::$shouldPass;
    }

    public function onEnter(\Illuminate\Http\Request $request): void {}
    public function onExit(\Illuminate\Http\Request $request): void {}
    public function onPass(\Illuminate\Http\Request $request): void {}
    public function onFail(\Illuminate\Http\Request $request): void {}
}

class DynamicStepTwoGuard implements \Pixelworxio\LivewireWorkflows\Contracts\GuardContract
{
    public static bool $shouldPass = false;

    public function passes(\Illuminate\Http\Request $request): bool
    {
        return self::$shouldPass;
    }

    public function onEnter(\Illuminate\Http\Request $request): void {}
    public function onExit(\Illuminate\Http\Request $request): void {}
    public function onPass(\Illuminate\Http\Request $request): void {}
    public function onFail(\Illuminate\Http\Request $request): void {}
}

beforeEach(function () {
    app(\Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->clear();

    // Reset guards
    DynamicStepOneGuard::$shouldPass = false;
    DynamicStepTwoGuard::$shouldPass = false;
});

test('workflow definition parses simple route parameter', function () {
    Workflow::flow('user-onboarding')
        ->entersAt(name: 'user-onboarding.start', path: '/user/{user}')
        ->finishesAt('dashboard')
        ->step('profile')
        ->goTo(DynamicStepOneComponent::class)
        ->unlessPasses(DynamicStepOneGuard::class)
        ->order(10);

    $workflow = app(\Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->get('user-onboarding');

    expect($workflow->hasRouteParameters())->toBeTrue()
        ->and($workflow->routeParameters)->toBe(['user']);
});

test('workflow definition parses multiple route parameters', function () {
    Workflow::flow('checkout')
        ->entersAt(name: 'checkout.start', path: '/user/{user}/product/{product}')
        ->finishesAt('dashboard')
        ->step('shipping')
        ->goTo(DynamicStepOneComponent::class)
        ->unlessPasses(DynamicStepOneGuard::class)
        ->order(10);

    $workflow = app(\Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->get('checkout');

    expect($workflow->hasRouteParameters())->toBeTrue()
        ->and($workflow->routeParameters)->toBe(['user', 'product']);
});

test('workflow definition parses route parameters with model binding syntax', function () {
    Workflow::flow('checkout')
        ->entersAt(name: 'checkout.start', path: '/user/{user:id}/product/{product:id}')
        ->finishesAt('dashboard')
        ->step('shipping')
        ->goTo(DynamicStepOneComponent::class)
        ->unlessPasses(DynamicStepOneGuard::class)
        ->order(10);

    $workflow = app(\Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->get('checkout');

    expect($workflow->hasRouteParameters())->toBeTrue()
        ->and($workflow->routeParameters)->toBe(['user', 'product']);
});

test('workflow without route parameters returns empty array', function () {
    Workflow::flow('simple')
        ->entersAt(name: 'simple.start', path: '/simple')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(DynamicStepOneComponent::class)
        ->unlessPasses(DynamicStepOneGuard::class)
        ->order(10);

    $workflow = app(\Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->get('simple');

    expect($workflow->hasRouteParameters())->toBeFalse()
        ->and($workflow->routeParameters)->toBe([]);
});

test('dynamic routes are registered with parameters', function () {
    Workflow::flow('checkout')
        ->entersAt(name: 'checkout.start', path: '/user/{user}/product/{product}')
        ->finishesAt('dashboard')
        ->step('shipping')
        ->goTo(DynamicStepOneComponent::class)
        ->unlessPasses(DynamicStepOneGuard::class)
        ->order(10)
        ->step('payment')
        ->goTo(DynamicStepTwoComponent::class)
        ->unlessPasses(DynamicStepTwoGuard::class)
        ->order(20);

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');
    app(\Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class)->register();
    app('router')->getRoutes()->refreshNameLookups();

    expect(Route::has('checkout.start'))->toBeTrue()
        ->and(Route::has('checkout.shipping'))->toBeTrue()
        ->and(Route::has('checkout.payment'))->toBeTrue();

    $entryRoute = Route::getRoutes()->getByName('checkout.start');
    expect($entryRoute->uri())->toBe('user/{user}/product/{product}');

    $shippingRoute = Route::getRoutes()->getByName('checkout.shipping');
    expect($shippingRoute->uri())->toBe('user/{user}/product/{product}/shipping');
});

test('entry route with parameters redirects to first step with parameters preserved', function () {
    Workflow::flow('checkout')
        ->entersAt(name: 'checkout.start', path: '/user/{user}/product/{product}')
        ->finishesAt('dashboard')
        ->step('shipping')
        ->goTo(DynamicStepOneComponent::class)
        ->unlessPasses(DynamicStepOneGuard::class)
        ->order(10);

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');
    app(\Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class)->register();
    app('router')->getRoutes()->refreshNameLookups();

    DynamicStepOneGuard::$shouldPass = false;

    $response = $this->get('/user/123/product/456');

    $response->assertRedirect(route('checkout.shipping', ['user' => '123', 'product' => '456']));
});

test('entry route with parameters redirects to finish route when complete', function () {
    Workflow::flow('checkout')
        ->entersAt(name: 'checkout.start', path: '/user/{user}/product/{product}')
        ->finishesAt('dashboard')
        ->step('shipping')
        ->goTo(DynamicStepOneComponent::class)
        ->unlessPasses(DynamicStepOneGuard::class)
        ->order(10);

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');
    app(\Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class)->register();
    app('router')->getRoutes()->refreshNameLookups();

    DynamicStepOneGuard::$shouldPass = true;

    $response = $this->get('/user/123/product/456');

    $response->assertRedirect(route('dashboard', ['user' => '123', 'product' => '456']));
});

test('workflow routes work with numeric parameter values', function () {
    Workflow::flow('checkout')
        ->entersAt(name: 'checkout.start', path: '/user/{user}/product/{product}')
        ->finishesAt('dashboard')
        ->step('shipping')
        ->goTo(DynamicStepOneComponent::class)
        ->unlessPasses(DynamicStepOneGuard::class)
        ->order(10);

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');
    app(\Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class)->register();
    app('router')->getRoutes()->refreshNameLookups();

    DynamicStepOneGuard::$shouldPass = false;

    $response = $this->get('/user/1/product/5');

    $response->assertRedirect(route('checkout.shipping', [
        'user' => '1',
        'product' => '5',
    ]));
});

test('step route URL is constructed correctly with parameters', function () {
    Workflow::flow('checkout')
        ->entersAt(name: 'checkout.start', path: '/user/{user}/product/{product}')
        ->finishesAt('dashboard')
        ->step('shipping')
        ->goTo(DynamicStepOneComponent::class)
        ->unlessPasses(DynamicStepOneGuard::class)
        ->order(10);

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');
    app(\Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class)->register();
    app('router')->getRoutes()->refreshNameLookups();

    $url = route('checkout.shipping', ['user' => '123', 'product' => '456']);

    expect($url)->toContain('/user/123/product/456/shipping');
});

test('complex route with multiple parameters works end-to-end', function () {
    Workflow::flow('order-workflow')
        ->entersAt(name: 'order-workflow.start', path: '/organization/{org}/user/{user}/order/{order}')
        ->finishesAt('dashboard')
        ->step('review')
        ->goTo(DynamicStepOneComponent::class)
        ->unlessPasses(DynamicStepOneGuard::class)
        ->order(10)
        ->step('confirm')
        ->goTo(DynamicStepTwoComponent::class)
        ->unlessPasses(DynamicStepTwoGuard::class)
        ->order(20);

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');
    app(\Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class)->register();
    app('router')->getRoutes()->refreshNameLookups();

    $workflow = app(\Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->get('order-workflow');

    expect($workflow->routeParameters)->toBe(['org', 'user', 'order']);

    DynamicStepOneGuard::$shouldPass = false;

    $response = $this->get('/organization/acme/user/42/order/999');
    $response->assertRedirect(route('order-workflow.review', ['org' => 'acme', 'user' => '42', 'order' => '999']));
});
