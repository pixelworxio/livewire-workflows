<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\StateRepositories;

use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;

/**
 * Null state repository - no persistence.
 *
 * Useful for stateless workflows or testing.
 * All operations are no-ops and return empty/null values.
 */
class NullStateRepository implements WorkflowStateRepository
{
    public function getCurrentStep(string $flow, string|int|null $userKey): ?string
    {
        return null;
    }

    public function setCurrentStep(string $flow, string|int|null $userKey, string $stepKey): void
    {
        // No-op
    }

    public function getHistory(string $flow, string|int|null $userKey): array
    {
        return [];
    }

    public function pushHistory(string $flow, string|int|null $userKey, string $stepKey): void
    {
        // No-op
    }

    public function popHistory(string $flow, string|int|null $userKey): ?string
    {
        return null;
    }

    public function clear(string $flow, string|int|null $userKey): void
    {
        // No-op
    }

    public function getMetadata(string $flow, string|int|null $userKey): array
    {
        return [];
    }

    public function setMetadata(string $flow, string|int|null $userKey, array $metadata): void
    {
        // No-op
    }

    public function getState(string $flow, string|int|null $userKey, string $key): mixed
    {
        return null;
    }

    public function setState(string $flow, string|int|null $userKey, string $key, mixed $value): void
    {
        // No-op
    }

    public function hasState(string $flow, string|int|null $userKey, string $key): bool
    {
        return false;
    }

    public function forgetState(string $flow, string|int|null $userKey, string $key): void
    {
        // No-op
    }

    public function clearState(string $flow, string|int|null $userKey, ?string $namespace = null): void
    {
        // No-op
    }

    public function getAllState(string $flow, string|int|null $userKey): array
    {
        return [];
    }
}
