<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Support;

use Illuminate\Http\Request;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;

/**
 * Fluent API for managing workflow state in controllers.
 *
 * Provides a clean interface for controllers to interact with workflow state
 * that is compatible with Livewire components using #[WorkflowState] attributes.
 */
class WorkflowStateManager
{
    protected ?Request $request = null;

    protected string|int|null $userKey = null;

    public function __construct(
        protected string $workflowName,
        protected WorkflowStateRepository $repository,
    ) {}

    /**
     * Set the request context for resolving the user key.
     */
    public function forRequest(Request $request): self
    {
        $this->request = $request;
        $this->userKey = null; // Reset cached user key

        return $this;
    }

    /**
     * Set a workflow state value.
     */
    public function set(string $key, mixed $value): self
    {
        $this->repository->setState(
            $this->workflowName,
            $this->resolveUserKey(),
            $key,
            $value
        );

        return $this;
    }

    /**
     * Get a workflow state value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->repository->getState(
            $this->workflowName,
            $this->resolveUserKey(),
            $key
        );

        return $value ?? $default;
    }

    /**
     * Check if a workflow state key exists.
     */
    public function has(string $key): bool
    {
        return $this->repository->hasState(
            $this->workflowName,
            $this->resolveUserKey(),
            $key
        );
    }

    /**
     * Forget a workflow state key.
     */
    public function forget(string $key): self
    {
        $this->repository->forgetState(
            $this->workflowName,
            $this->resolveUserKey(),
            $key
        );

        return $this;
    }

    /**
     * Get all workflow state.
     */
    public function all(): array
    {
        return $this->repository->getAllState(
            $this->workflowName,
            $this->resolveUserKey()
        );
    }

    /**
     * Clear workflow state (optionally by namespace).
     */
    public function clear(?string $namespace = null): self
    {
        $this->repository->clearState(
            $this->workflowName,
            $this->resolveUserKey(),
            $namespace
        );

        return $this;
    }

    /**
     * Get the resolved user key (for testing/debugging).
     */
    public function getUserKey(): string|int|null
    {
        return $this->resolveUserKey();
    }

    /**
     * Resolve the user key from the request.
     *
     * Priority:
     * 1. Authenticated user ID
     * 2. Session ID
     * 3. Guest fingerprint (IP + User Agent hash)
     */
    protected function resolveUserKey(): string|int|null
    {
        if ($this->userKey !== null) {
            return $this->userKey;
        }

        if ($this->request === null) {
            return null;
        }

        // 1. Try authenticated user
        $user = $this->request->user();
        if ($user !== null) {
            $this->userKey = $user->getAuthIdentifier();

            return $this->userKey;
        }

        // 2. Try session ID
        if ($this->request->hasSession()) {
            $this->userKey = $this->request->session()->getId();

            return $this->userKey;
        }

        // 3. Fall back to guest fingerprint
        $ip = $this->request->ip() ?? 'unknown';
        $userAgent = $this->request->userAgent() ?? 'unknown';
        $this->userKey = 'guest-'.md5($ip.$userAgent);

        return $this->userKey;
    }
}
