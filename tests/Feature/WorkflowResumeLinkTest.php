<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar;
use Pixelworxio\LivewireWorkflows\StateRepositories\SessionWorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\Support\RouteRegistrar;
use Pixelworxio\LivewireWorkflows\Support\WorkflowEngine;
use Pixelworxio\LivewireWorkflows\Support\WorkflowInstance;
use Pixelworxio\LivewireWorkflows\Support\WorkflowResolver;
use Tests\Support\FailingGuard;
use Tests\Support\TestStepOneComponent;
use Tests\Support\TestStepTwoComponent;

beforeEach(function () {
    $this->loadWorkflowMigrations();
    config()->set('livewire-workflows.repository', 'eloquent');

    app(WorkflowRegistrar::class)->clear();

    Workflow::flow('onboarding')
        ->entersAt(name: 'onboarding.start', path: '/onboarding')
        ->finishesAt('dashboard')
        ->step('step-one')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10)
        ->step('step-two')
        ->goTo(TestStepTwoComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(20);

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');

    app(RouteRegistrar::class)->register();
    app('router')->getRoutes()->refreshNameLookups();

    $this->resolver = app(WorkflowResolver::class);
    $this->repo = app(WorkflowStateRepository::class);
});

test('generates a signed resume URL for an authenticated user', function () {
    $user = new class implements Authenticatable
    {
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): int
        {
            return 42;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return 'secret';
        }

        public function getRememberToken(): string
        {
            return '';
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }
    };

    $url = workflow('onboarding')->resumeUrlFor(user: $user);

    expect($url)
        ->toBeString()
        ->toContain('/workflow/resume/onboarding/42')
        ->toContain('expires=')
        ->toContain('signature=');
});

test('generates a signed resume URL with an explicit user key', function () {
    $url = workflow('onboarding')->resumeUrlFor(userKey: 'guest-abc123');

    expect($url)
        ->toBeString()
        ->toContain('/workflow/resume/onboarding/guest-abc123')
        ->toContain('signature=');
});

test('generated URL has default 24 hour expiry', function () {
    $url = workflow('onboarding')->resumeUrlFor(userKey: '1');

    $query = parse_url($url, PHP_URL_QUERY);
    parse_str($query, $params);

    expect($params)->toHaveKey('expires');
    expect((int) $params['expires'])->toBeGreaterThan(now()->timestamp)
        ->toBeLessThanOrEqual(now()->addMinutes(1440)->timestamp);
});

test('generated URL uses the configured default expiry', function () {
    config()->set('livewire-workflows.resume.default_expires_minutes', 60);

    $url = workflow('onboarding')->resumeUrlFor(userKey: '1');

    $query = parse_url($url, PHP_URL_QUERY);
    parse_str($query, $params);

    expect((int) $params['expires'])
        ->toBeGreaterThan(now()->timestamp)
        ->toBeLessThanOrEqual(now()->addMinutes(60)->timestamp);
});

test('generated URL respects custom expiry', function () {
    $url = workflow('onboarding')->resumeUrlFor(userKey: '1', expiresInMinutes: 60);

    $query = parse_url($url, PHP_URL_QUERY);
    parse_str($query, $params);

    expect((int) $params['expires'])->toBeLessThanOrEqual(now()->addMinutes(60)->timestamp);
});

test('resume URL redirects to current step when one is recorded', function () {
    $this->repo->setCurrentStep('onboarding', '99', 'step-two');

    $url = workflow('onboarding')->resumeUrlFor(userKey: '99');
    $response = $this->get($url);

    $response->assertRedirect(route('onboarding.step-two'));
});

test('resume URL redirects to entry route when no step is recorded', function () {
    // No current step set for user 88
    $url = workflow('onboarding')->resumeUrlFor(userKey: '88');
    $response = $this->get($url);

    $response->assertRedirect(route('onboarding.start'));
});

test('resume URL preserves dynamic workflow route parameters', function () {
    Workflow::flow('checkout')
        ->entersAt(name: 'checkout.start', path: '/users/{user}/products/{product}')
        ->finishesAt('dashboard')
        ->step('shipping')
        ->goTo(TestStepOneComponent::class)
        ->unlessPasses(FailingGuard::class)
        ->order(10);

    app(RouteRegistrar::class)->register();
    app('router')->getRoutes()->refreshNameLookups();

    $parameters = ['user' => '123', 'product' => '456'];
    $this->repo->setCurrentStep('checkout', '99', 'shipping');
    $this->repo->setState('checkout', '99', '_route_parameters', $parameters);

    $url = workflow('checkout')->resumeUrlFor(userKey: '99');
    $response = $this->get($url);

    $response->assertRedirect(route('checkout.shipping', $parameters));
});

test('resume URL returns 403 when signature is invalid', function () {
    $url = workflow('onboarding')->resumeUrlFor(userKey: '1');

    // Tamper with the signature
    $tamperedUrl = $url.'tampered';
    $response = $this->get($tamperedUrl);

    $response->assertStatus(403);
});

test('resume URL returns 403 when signature is expired', function () {
    $url = workflow('onboarding')->resumeUrlFor(userKey: '1', expiresInMinutes: -1);

    $response = $this->get($url);

    $response->assertStatus(403);
});

test('throws RuntimeException when not using Eloquent repository', function () {
    config()->set('livewire-workflows.repository', 'session');

    // Rebind repository and resolver so new bindings take effect
    app()->singleton(WorkflowStateRepository::class, function ($app) {
        return $app->make(SessionWorkflowStateRepository::class);
    });
    app()->singleton(WorkflowResolver::class, function ($app) {
        return new WorkflowResolver(
            $app->make(WorkflowRegistrar::class),
            $app->make(WorkflowEngine::class),
            $app->make(WorkflowStateRepository::class),
        );
    });

    $resolver = app()->make(WorkflowResolver::class);

    expect(fn () => $resolver->resumeUrlFor('onboarding', null, '1'))
        ->toThrow(RuntimeException::class, 'Resume links require the Eloquent state repository');
});

test('throws InvalidArgumentException when neither user nor userKey is provided', function () {
    expect(fn () => workflow('onboarding')->resumeUrlFor())
        ->toThrow(InvalidArgumentException::class, 'You must provide either a $user');
});

test('helper function returns WorkflowInstance with resumeUrlFor method', function () {
    $instance = workflow('onboarding');

    expect($instance)->toBeInstanceOf(WorkflowInstance::class);
    expect(method_exists($instance, 'resumeUrlFor'))->toBeTrue();
});
