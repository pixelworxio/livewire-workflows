<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Livewire\Concerns;

use Illuminate\Support\Facades\Crypt;
use Livewire\Features\SupportRedirects\Redirector;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowState;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use ReflectionClass;
use ReflectionProperty;

trait InteractsWithWorkflows
{
    protected ?string $workflowName = null;

    public function bootInteractsWithWorkflows(): void
    {
        if ($this->workflowName === null) {
            $routeName = request()->route()?->getName();

            if ($routeName && str_contains($routeName, '.')) {
                $parts = explode('.', $routeName);
                if (count($parts) >= 2) {
                    $possibleFlow = $parts[0];

                    try {
                        $registrar = app(\Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class);
                        if ($registrar->has($possibleFlow)) {
                            $this->workflowName = $possibleFlow;
                        }
                    } catch (\Throwable $e) {
                        // Skip
                    }
                }
            }
        }
    }

    public function mountInteractsWithWorkflows(): void
    {
        if ($this->workflowName !== null) {
            $this->hydrateWorkflowState();
        }
    }

    public function dehydrateInteractsWithWorkflows(): void
    {
        $this->syncWorkflowState();
    }

    public function setWorkflowName(string $workflowName): void
    {
        $this->workflowName = $workflowName;
        $this->hydrateWorkflowState();
    }

    public function getWorkflowName(): ?string
    {
        return $this->workflowName;
    }

    public function continue(string $flow): void
    {
        $this->syncWorkflowState();
        $workflow = app(\Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->get($flow);

        $this->redirect(route($workflow->entryRouteName), navigate: true);
    }

    public function back(string $flow, string $currentKey): void
    {
        $this->syncWorkflowState();
        $resolver = app(\Pixelworxio\LivewireWorkflows\Support\WorkflowResolver::class);
        $previousRoute = $resolver->previousRouteNameFor($flow, $currentKey, request());

        if ($previousRoute === null) {
            return;
        }

        $this->redirect(route($previousRoute), navigate: true);
    }

    /**
     * Manually sync current state to repository
     * Call this before navigation or when you want to persist state
     */
    public function syncState(): void
    {
        $this->syncWorkflowState();
    }

    protected function hydrateWorkflowState(): void
    {
        if ($this->workflowName === null) {
            return;
        }

        $repository = app(WorkflowStateRepository::class);
        $userKey = $this->getUserKey();
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $attributes = $property->getAttributes(WorkflowState::class);

            if (empty($attributes)) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();
            $stateKey = $this->getStateKey($property, $attribute);

            if ($repository->hasState($this->workflowName, $userKey, $stateKey)) {
                $value = $repository->getState($this->workflowName, $userKey, $stateKey);

                if ($attribute->encrypt && $value !== null) {
                    $value = Crypt::decrypt($value);
                }

                $this->{$property->getName()} = $value;
            }
        }
    }

    protected function syncWorkflowState(): void
    {
        if (! $this->workflowName) {
            return;
        }

        $repository = app(WorkflowStateRepository::class);
        $userKey = $this->getUserKey();
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $attributes = $property->getAttributes(WorkflowState::class);

            if (empty($attributes)) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();
            $stateKey = $this->getStateKey($property, $attribute);
            $value = $this->{$property->getName()};

            if ($attribute->encrypt && $value !== null) {
                $value = Crypt::encrypt($value);
            }

            $repository->setState($this->workflowName, $userKey, $stateKey, $value);
        }
    }

    protected function getWorkflowState(string $key, mixed $default = null): mixed
    {
        if ($this->workflowName === null) {
            return $default;
        }

        $repository = app(WorkflowStateRepository::class);
        $userKey = $this->getUserKey();
        $value = $repository->getState($this->workflowName, $userKey, $key);

        return $value ?? $default;
    }

    protected function putWorkflowState(string $key, mixed $value): void
    {
        if ($this->workflowName === null) {
            return;
        }

        $repository = app(WorkflowStateRepository::class);
        $userKey = $this->getUserKey();
        $repository->setState($this->workflowName, $userKey, $key, $value);
    }

    protected function hasWorkflowState(string $key): bool
    {
        if ($this->workflowName === null) {
            return false;
        }

        $repository = app(WorkflowStateRepository::class);
        $userKey = $this->getUserKey();

        return $repository->hasState($this->workflowName, $userKey, $key);
    }

    protected function forgetWorkflowState(string $key): void
    {
        if ($this->workflowName === null) {
            return;
        }

        $repository = app(WorkflowStateRepository::class);
        $userKey = $this->getUserKey();
        $repository->forgetState($this->workflowName, $userKey, $key);
    }

    protected function clearWorkflowState(?string $namespace = null): void
    {
        if ($this->workflowName === null) {
            return;
        }

        $repository = app(WorkflowStateRepository::class);
        $userKey = $this->getUserKey();

        $repository->clearState($this->workflowName, $userKey, $namespace);
    }

    protected function allWorkflowState(): array
    {
        if ($this->workflowName === null) {
            return [];
        }

        $repository = app(WorkflowStateRepository::class);
        $userKey = $this->getUserKey();

        return $repository->getAllState($this->workflowName, $userKey);
    }

    protected function getStateKey(ReflectionProperty $property, WorkflowState $attribute): string
    {
        $key = $property->getName();

        if ($attribute->namespace !== null) {
            $key = $attribute->namespace.'.'.$key;
        }

        return $key;
    }

    protected function getUserKey(): string|int
    {
        $request = request();

        if ($request->user()) {
            return $request->user()->getAuthIdentifier();
        }

        if ($request->hasSession()) {
            return $request->session()->getId();
        }

        return 'guest-'.md5($request->ip().($request->userAgent() ?? 'unknown'));
    }
}
