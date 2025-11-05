<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Contracts;

/**
 * Repository contract for persisting workflow state.
 *
 * Supports three implementations:
 * - NullStateRepository: No persistence (stateless)
 * - SessionWorkflowStateRepository: Session-based (guests/simple)
 * - EloquentWorkflowStateRepository: Database-backed (per-user)
 */
interface WorkflowStateRepository
{
    /**
     * Get the current step key for a workflow.
     *
     * @param  string  $flow  The workflow name
     * @param  string|int|null  $userKey  User identifier (ID, session ID, or null)
     * @return string|null The current step key or null
     */
    public function getCurrentStep(string $flow, string|int|null $userKey): ?string;

    /**
     * Set the current step for a workflow.
     *
     * @param  string  $flow  The workflow name
     * @param  string|int|null  $userKey  User identifier
     * @param  string  $stepKey  The step key to set
     */
    public function setCurrentStep(string $flow, string|int|null $userKey, string $stepKey): void;

    /**
     * Get the complete history stack for a workflow.
     *
     * @param  string  $flow  The workflow name
     * @param  string|int|null  $userKey  User identifier
     * @return array Array of step keys in chronological order
     */
    public function getHistory(string $flow, string|int|null $userKey): array;

    /**
     * Push a step onto the history stack.
     *
     * @param  string  $flow  The workflow name
     * @param  string|int|null  $userKey  User identifier
     * @param  string  $stepKey  The step key to add
     */
    public function pushHistory(string $flow, string|int|null $userKey, string $stepKey): void;

    /**
     * Pop the last step from the history stack.
     *
     * @param  string  $flow  The workflow name
     * @param  string|int|null  $userKey  User identifier
     * @return string|null The popped step key or null
     */
    public function popHistory(string $flow, string|int|null $userKey): ?string;

    /**
     * Clear all state for a workflow.
     *
     * @param  string  $flow  The workflow name
     * @param  string|int|null  $userKey  User identifier
     */
    public function clear(string $flow, string|int|null $userKey): void;

    /**
     * Get metadata for a workflow (completion status, progress, etc.).
     *
     * @param  string  $flow  The workflow name
     * @param  string|int|null  $userKey  User identifier
     * @return array Associative array of metadata
     */
    public function getMetadata(string $flow, string|int|null $userKey): array;

    /**
     * Set metadata for a workflow.
     *
     * @param  string  $flow  The workflow name
     * @param  string|int|null  $userKey  User identifier
     * @param  array  $metadata  Metadata to store
     */
    public function setMetadata(string $flow, string|int|null $userKey, array $metadata): void;

    /**
     * Get a specific state value.
     *
     * @param  string  $flow  The workflow name
     * @param  string|int|null  $userKey  User identifier
     * @param  string  $key  The state key
     * @return mixed The state value or null
     */
    public function getState(string $flow, string|int|null $userKey, string $key): mixed;

    /**
     * Set a specific state value.
     *
     * @param  string  $flow  The workflow name
     * @param  string|int|null  $userKey  User identifier
     * @param  string  $key  The state key
     * @param  mixed  $value  The state value
     */
    public function setState(string $flow, string|int|null $userKey, string $key, mixed $value): void;

    /**
     * Check if a state key exists.
     *
     * @param  string  $flow  The workflow name
     * @param  string|int|null  $userKey  User identifier
     * @param  string  $key  The state key
     * @return bool True if the key exists
     */
    public function hasState(string $flow, string|int|null $userKey, string $key): bool;

    /**
     * Remove a specific state key.
     *
     * @param  string  $flow  The workflow name
     * @param  string|int|null  $userKey  User identifier
     * @param  string  $key  The state key
     */
    public function forgetState(string $flow, string|int|null $userKey, string $key): void;

    /**
     * Clear state data for a workflow.
     *
     * @param  string  $flow  The workflow name
     * @param  string|int|null  $userKey  User identifier
     * @param  string|null  $namespace  Optional namespace to clear (clears all if null)
     */
    public function clearState(string $flow, string|int|null $userKey, ?string $namespace = null): void;

    /**
     * Get all state data for a workflow.
     *
     * @param  string  $flow  The workflow name
     * @param  string|int|null  $userKey  User identifier
     * @return array All state data
     */
    public function getAllState(string $flow, string|int|null $userKey): array;
}
