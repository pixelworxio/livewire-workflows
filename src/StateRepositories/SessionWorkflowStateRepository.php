<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\StateRepositories;

use Illuminate\Session\SessionManager;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;

/**
 * Session-based state repository.
 *
 * Suitable for guest users or simple persistence needs.
 * State is stored in Laravel's session and cleared when session expires.
 */
class SessionWorkflowStateRepository implements WorkflowStateRepository
{
    public function __construct(
        protected SessionManager $session
    ) {}

    public function getCurrentStep(string $flow, string|int|null $userKey): ?string
    {
        return $this->session->get($this->keyFor($flow, $userKey, 'current'));
    }

    public function setCurrentStep(string $flow, string|int|null $userKey, string $stepKey): void
    {
        $this->session->put($this->keyFor($flow, $userKey, 'current'), $stepKey);
    }

    public function getHistory(string $flow, string|int|null $userKey): array
    {
        return $this->session->get($this->keyFor($flow, $userKey, 'history'), []);
    }

    public function pushHistory(string $flow, string|int|null $userKey, string $stepKey): void
    {
        $history = $this->getHistory($flow, $userKey);
        $history[] = $stepKey;

        $this->session->put($this->keyFor($flow, $userKey, 'history'), $history);
    }

    public function popHistory(string $flow, string|int|null $userKey): ?string
    {
        $history = $this->getHistory($flow, $userKey);

        if (empty($history)) {
            return null;
        }

        $last = array_pop($history);
        $this->session->put($this->keyFor($flow, $userKey, 'history'), $history);

        return $last;
    }

    public function clear(string $flow, string|int|null $userKey): void
    {
        $this->session->forget($this->keyFor($flow, $userKey, 'current'));
        $this->session->forget($this->keyFor($flow, $userKey, 'history'));
        $this->session->forget($this->keyFor($flow, $userKey, 'metadata'));
        $this->session->forget($this->stateKeyFor($flow, $userKey));
    }

    public function getMetadata(string $flow, string|int|null $userKey): array
    {
        return $this->session->get($this->keyFor($flow, $userKey, 'metadata'), []);
    }

    public function setMetadata(string $flow, string|int|null $userKey, array $metadata): void
    {
        $this->session->put($this->keyFor($flow, $userKey, 'metadata'), $metadata);
    }

    public function getState(string $flow, string|int|null $userKey, string $key): mixed
    {
        $stateKey = $this->stateKeyFor($flow, $userKey);

        return $this->session->get("{$stateKey}.{$key}");
    }

    public function setState(string $flow, string|int|null $userKey, string $key, mixed $value): void
    {
        $stateKey = $this->stateKeyFor($flow, $userKey);

        $this->session->put("{$stateKey}.{$key}", $value);
    }

    public function hasState(string $flow, string|int|null $userKey, string $key): bool
    {
        $stateKey = $this->stateKeyFor($flow, $userKey);

        return $this->session->has("{$stateKey}.{$key}");
    }

    public function forgetState(string $flow, string|int|null $userKey, string $key): void
    {
        $stateKey = $this->stateKeyFor($flow, $userKey);

        $this->session->forget("{$stateKey}.{$key}");
    }

    public function clearState(string $flow, string|int|null $userKey, ?string $namespace = null): void
    {
        $stateKey = $this->stateKeyFor($flow, $userKey);

        if (! $namespace) {
            $this->session->forget($stateKey);

            return;
        }

        $allState = $this->getAllState($flow, $userKey);
        $keysToRemove = [];

        foreach (array_keys($allState) as $key) {
            if (str_starts_with($key, $namespace)) {
                $keysToRemove[] = $key;
            }
        }

        // Remove the keys from the state array
        foreach ($keysToRemove as $key) {
            unset($allState[$key]);
        }

        // Save the modified state back
        if (empty($allState)) {
            $this->session->forget($stateKey);
        } else {
            $this->session->put($stateKey, $allState);
        }
    }

    public function getAllState(string $flow, string|int|null $userKey): array
    {
        $stateKey = $this->stateKeyFor($flow, $userKey);

        return $this->session->get($stateKey, []);
    }

    /**
     * Generate a session key for workflow state.
     */
    protected function keyFor(string $flow, string|int|null $userKey, string $suffix): string
    {
        $userPart = $userKey ?? 'guest';

        return "workflows.{$flow}.{$userPart}.{$suffix}";
    }

    /**
     * Generate a session key for workflow state data.
     */
    protected function stateKeyFor(string $flow, string|int|null $userKey): string
    {
        $userPart = $userKey ?? 'guest';

        return "workflows.{$flow}.{$userPart}.data";
    }
}
