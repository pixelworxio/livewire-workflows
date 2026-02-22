<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\Facades\Workflow;
use Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar;
use Pixelworxio\LivewireWorkflows\StateRepositories\NullStateRepository;
use Pixelworxio\LivewireWorkflows\StateRepositories\SessionWorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\Support\WorkflowResolver;
use Tests\Support\FailingGuard;
use Tests\Support\TestStepOneComponent;

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
        ->order(10);

    Route::get('/dashboard', fn () => 'Dashboard')->name('dashboard');

    app(Pixelworxio\LivewireWorkflows\Support\RouteRegistrar::class)->register();
    app('router')->getRoutes()->refreshNameLookups();
});

test('resumeUrlFor generates a URL containing the workflow name and user key', function () {
    $resolver = app(WorkflowResolver::class);

    $url = $resolver->resumeUrlFor('onboarding', null, 'user-123');

    expect($url)
        ->toContain('onboarding')
        ->toContain('user-123')
        ->toContain('signature=');
});

test('resumeUrlFor accepts an Authenticatable and uses getAuthIdentifier', function () {
    $resolver = app(WorkflowResolver::class);

    $user = new class implements \Illuminate\Contracts\Auth\Authenticatable
    {
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): int
        {
            return 77;
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

    $url = $resolver->resumeUrlFor('onboarding', $user);

    expect($url)->toContain('77');
});

test('resumeUrlFor prefers user getAuthIdentifier over explicit userKey', function () {
    $resolver = app(WorkflowResolver::class);

    $user = new class implements \Illuminate\Contracts\Auth\Authenticatable
    {
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): int
        {
            return 10;
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

    $url = $resolver->resumeUrlFor('onboarding', $user, 'should-be-ignored');

    expect($url)
        ->toContain('/10')
        ->not->toContain('should-be-ignored');
});

test('resumeUrlFor respects custom expiry in minutes', function () {
    $resolver = app(WorkflowResolver::class);

    $url = $resolver->resumeUrlFor('onboarding', null, 'u1', 60);

    $query = parse_url($url, PHP_URL_QUERY);
    parse_str($query, $params);

    $expectedExpires = now()->addMinutes(60)->timestamp;

    expect((int) $params['expires'])
        ->toBeGreaterThan(now()->timestamp)
        ->toBeLessThanOrEqual($expectedExpires);
});

test('resumeUrlFor throws RuntimeException when using session repository', function () {
    // Override binding to use session repository
    app()->singleton(WorkflowStateRepository::class, function ($app) {
        return $app->make(SessionWorkflowStateRepository::class);
    });

    $resolver = app()->make(WorkflowResolver::class);

    expect(fn () => $resolver->resumeUrlFor('onboarding', null, 'u1'))
        ->toThrow(\RuntimeException::class, 'Resume links require the Eloquent state repository');
});

test('resumeUrlFor throws RuntimeException when using null repository', function () {
    app()->singleton(WorkflowStateRepository::class, function ($app) {
        return $app->make(NullStateRepository::class);
    });

    $resolver = app()->make(WorkflowResolver::class);

    expect(fn () => $resolver->resumeUrlFor('onboarding', null, 'u1'))
        ->toThrow(\RuntimeException::class, 'Resume links require the Eloquent state repository');
});

test('resumeUrlFor throws InvalidArgumentException when no user or userKey provided', function () {
    $resolver = app(WorkflowResolver::class);

    expect(fn () => $resolver->resumeUrlFor('onboarding'))
        ->toThrow(\InvalidArgumentException::class, 'You must provide either a $user');
});

test('resumeUrlFor with Eloquent repository does not throw', function () {
    $resolver = app(WorkflowResolver::class);

    expect(fn () => $resolver->resumeUrlFor('onboarding', null, 'u1'))
        ->not->toThrow(\RuntimeException::class);
});
