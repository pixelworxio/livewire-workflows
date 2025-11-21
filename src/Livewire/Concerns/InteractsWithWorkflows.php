<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Livewire\Concerns;

use Illuminate\Support\Facades\Crypt;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowName;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowState;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use ReflectionClass;
use ReflectionProperty;

trait InteractsWithWorkflows
{
    protected ?string $workflowName = null;

    /**
     * Track properties that have changed during this request to optimize syncing.
     *
     * @var array<string, bool>
     */
    protected array $workflowStateDirtyProperties = [];

    public function bootInteractsWithWorkflows(): void
    {
        if ($this->workflowName === null) {
            // First, check if the component class has a WorkflowName attribute
            $reflection = new ReflectionClass($this);
            $attributes = $reflection->getAttributes(WorkflowName::class);

            if (! empty($attributes)) {
                $attribute = $attributes[0]->newInstance();
                $this->workflowName = $attribute->name;

                return;
            }

            // Fall back to auto-detection from route name
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

    /**
     * Hook into Livewire's updated lifecycle to proactively sync state
     * when properties change, ensuring persistence even if dehydration
     * doesn't complete normally.
     *
     * @param  mixed  $value
     */
    public function updatedInteractsWithWorkflows(string $propertyName, $value): void
    {
        if ($this->workflowName === null) {
            return;
        }

        // Check if this property has WorkflowState attribute
        $reflection = new ReflectionClass($this);

        try {
            $property = $reflection->getProperty($propertyName);
            $attributes = $property->getAttributes(WorkflowState::class);

            if (! empty($attributes)) {
                // Mark property as dirty
                $this->workflowStateDirtyProperties[$propertyName] = true;

                // Sync this specific property immediately
                $this->syncSpecificWorkflowProperty($property, $attributes[0]->newInstance());
            }
        } catch (\ReflectionException $e) {
            // Property might be nested (e.g., 'user.name')
            // In this case, let dehydration handle it
        }
    }

    /**
     * Dehydration hook as a fallback to ensure all state is persisted.
     * This catches any property changes that weren't tracked by the updated hook.
     */
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
        $registrar = app(\Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class);
        $workflow = $registrar->get($flow);
        $routeParameters = $this->extractWorkflowRouteParameters($workflow);

        $this->redirect(route($workflow->entryRouteName, $routeParameters), navigate: true);
    }

    public function back(string $flow, string $currentKey): void
    {
        $this->syncWorkflowState();
        $registrar = app(\Pixelworxio\LivewireWorkflows\Registrar\WorkflowRegistrar::class);
        $resolver = app(\Pixelworxio\LivewireWorkflows\Support\WorkflowResolver::class);
        $workflow = $registrar->get($flow);
        $previousRoute = $resolver->previousRouteNameFor($flow, $currentKey, request());

        if ($previousRoute === null) {
            return;
        }

        $routeParameters = $this->extractWorkflowRouteParameters($workflow);
        $this->redirect(route($previousRoute, $routeParameters), navigate: true);
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

        // Clear dirty tracking after full sync
        $this->workflowStateDirtyProperties = [];
    }

    /**
     * Sync a specific property to the workflow state repository.
     * Used for immediate persistence when properties change.
     */
    protected function syncSpecificWorkflowProperty(ReflectionProperty $property, WorkflowState $attribute): void
    {
        if (! $this->workflowName) {
            return;
        }

        $repository = app(WorkflowStateRepository::class);
        $userKey = $this->getUserKey();

        $stateKey = $this->getStateKey($property, $attribute);
        $value = $this->{$property->getName()};

        if ($attribute->encrypt && $value !== null) {
            $value = Crypt::encrypt($value);
        }

        $repository->setState($this->workflowName, $userKey, $stateKey, $value);
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

        // Check if session is available
        if ($request->hasSession()) {
            // Try to get user key from existing workflow session data
            if ($this->workflowName) {
                $session_workflow = $request->session()->get('workflows.'.$this->workflowName);

                if ($session_workflow) {
                    return array_key_first($session_workflow);
                }
            }

            return $request->session()->getId();
        }

        return 'guest-'.md5($request->ip().($request->userAgent() ?? 'unknown'));
    }

    /**
     * Extract route parameters for the workflow from the current request.
     *
     * @param  \Pixelworxio\LivewireWorkflows\Support\WorkflowDefinition  $workflow
     * @return array<string, mixed>
     */
    protected function extractWorkflowRouteParameters($workflow): array
    {
        if (! $workflow->hasRouteParameters()) {
            return [];
        }

        $repository = app(WorkflowStateRepository::class);
        $userKey = $this->getUserKey();

        // First, try to get stored parameters from workflow state
        $storedParameters = $repository->getState($workflow->flow, $userKey, '_route_parameters');
        if (! empty($storedParameters) && is_array($storedParameters)) {
            return $storedParameters;
        }

        $request = request();
        $currentRoute = $request->route();

        if (! $currentRoute) {
            return [];
        }

        $allParameters = $currentRoute->parameters();
        $workflowParameters = [];

        // Only include parameters that are defined in the workflow
        foreach ($workflow->routeParameters as $paramName) {
            if (array_key_exists($paramName, $allParameters)) {
                $workflowParameters[$paramName] = $allParameters[$paramName];
            }
        }

        // Store parameters for future use if we found any
        if (! empty($workflowParameters)) {
            $repository->setState($workflow->flow, $userKey, '_route_parameters', $workflowParameters);
        }

        return $workflowParameters;
    }
}
