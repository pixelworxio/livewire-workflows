<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Livewire\Concerns;

use Illuminate\Support\Facades\Crypt;
use Livewire\Features\SupportRedirects\Redirector;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowState;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use ReflectionClass;
use ReflectionProperty;

/**
 * Livewire trait for workflow navigation and state management.
 *
 * Provides convenient methods for Livewire components:
 * - continue(): Advance to next step
 * - back(): Return to previous step
 * - State management: Automatic persistence of properties marked with #[WorkflowState]
 */
trait InteractsWithWorkflows
{
    /**
     * The workflow name for state management.
     */
    protected ?string $workflowName = null;

    /**
     * Boot the trait.
     */
    public function bootInteractsWithWorkflows(): void
    {
        $this->hydrateWorkflowState();
    }

    /**
     * Dehydrate hook to sync state.
     */
    public function dehydrateInteractsWithWorkflows(): void
    {
        $this->syncWorkflowState();
    }

    /**
     * Set workflow name
     */
    public function setWorkflowName(string $workflowName): void
    {
        $this->workflowName = $workflowName;
    }

    /**
     * Get workflow name
     */
    public function getWorkflowName(): string
    {
        return $this->workflowName;
    }

    /**
     * Continue to the next workflow step.
     *
     * Redirects to the workflow entry route which will evaluate guards
     * and redirect to the appropriate next step or finish route.
     *
     * @param  string  $flow  The workflow identifier
     */
    public function continue(string $flow): Redirector
    {
        $workflow = app(\Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class)->get($flow);

        return $this->redirect(route($workflow->entryRouteName), navigate: true);
    }

    /**
     * Go back to the previous workflow step.
     *
     * @param  string  $flow  The workflow identifier
     * @param  string  $currentKey  The current step key
     */
    public function back(string $flow, string $currentKey): ?Redirector
    {
        $resolver = app(\Pixelworxio\LivewireWorkflows\Support\WorkflowResolver::class);
        $request = request();

        $previousRoute = $resolver->previousRouteNameFor($flow, $currentKey, $request);

        if ($previousRoute === null) {
            return null;
        }

        return $this->redirect(route($previousRoute), navigate: true);
    }

    /**
     * Hydrate workflow state from repository.
     */
    protected function hydrateWorkflowState(): void
    {
        if ($this->workflowName === null) {
            return;
        }

        $repository = app(WorkflowStateRepository::class);
        $userKey = $this->getUserKey();

        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties() as $property) {
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

                $property->setAccessible(true);
                $property->setValue($this, $value);
            }
        }
    }

    /**
     * Sync workflow state to repository.
     */
    protected function syncWorkflowState(): void
    {
        if ($this->workflowName === null) {
            return;
        }

        $repository = app(WorkflowStateRepository::class);
        $userKey = $this->getUserKey();

        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(WorkflowState::class);

            if (empty($attributes)) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();
            $stateKey = $this->getStateKey($property, $attribute);

            $property->setAccessible(true);
            $value = $property->getValue($this);

            if ($attribute->encrypt && $value !== null) {
                $value = Crypt::encrypt($value);
            }

            $repository->setState($this->workflowName, $userKey, $stateKey, $value);
        }
    }

    /**
     * Get a workflow state value.
     */
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

    /**
     * Set a workflow state value.
     */
    protected function putWorkflowState(string $key, mixed $value): void
    {
        if ($this->workflowName === null) {
            return;
        }

        $repository = app(WorkflowStateRepository::class);
        $userKey = $this->getUserKey();

        $repository->setState($this->workflowName, $userKey, $key, $value);
    }

    /**
     * Check if a workflow state key exists.
     */
    protected function hasWorkflowState(string $key): bool
    {
        if ($this->workflowName === null) {
            return false;
        }

        $repository = app(WorkflowStateRepository::class);
        $userKey = $this->getUserKey();

        return $repository->hasState($this->workflowName, $userKey, $key);
    }

    /**
     * Remove a workflow state key.
     */
    protected function forgetWorkflowState(string $key): void
    {
        if ($this->workflowName === null) {
            return;
        }

        $repository = app(WorkflowStateRepository::class);
        $userKey = $this->getUserKey();

        $repository->forgetState($this->workflowName, $userKey, $key);
    }

    /**
     * Clear workflow state.
     */
    protected function clearWorkflowState(?string $namespace = null): void
    {
        if ($this->workflowName === null) {
            return;
        }

        $repository = app(WorkflowStateRepository::class);
        $userKey = $this->getUserKey();

        $repository->clearState($this->workflowName, $userKey, $namespace);
    }

    /**
     * Get all workflow state.
     */
    protected function allWorkflowState(): array
    {
        if ($this->workflowName === null) {
            return [];
        }

        $repository = app(WorkflowStateRepository::class);
        $userKey = $this->getUserKey();

        return $repository->getAllState($this->workflowName, $userKey);
    }

    /**
     * Get the state key for a property.
     */
    protected function getStateKey(ReflectionProperty $property, WorkflowState $attribute): string
    {
        $key = $property->getName();

        if ($attribute->namespace !== null) {
            $key = $attribute->namespace . '.' . $key;
        }

        return $key;
    }

    /**
     * Get the user key for state persistence.
     */
    protected function getUserKey(): string|int
    {
        $request = request();

        if ($request->user()) {
            return $request->user()->getAuthIdentifier();
        }

        if ($request->hasSession()) {
            return $request->session()->getId();
        }

        return 'guest-' . md5($request->ip() . ($request->userAgent() ?? 'unknown'));
    }
}
