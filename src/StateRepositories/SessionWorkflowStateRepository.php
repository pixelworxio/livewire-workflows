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
    ) {
    }

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
    }

    public function getMetadata(string $flow, string|int|null $userKey): array
    {
        return $this->session->get($this->keyFor($flow, $userKey, 'metadata'), []);
    }

    public function setMetadata(string $flow, string|int|null $userKey, array $metadata): void
    {
        $this->session->put($this->keyFor($flow, $userKey, 'metadata'), $metadata);
    }

    /**
     * Generate a session key for workflow state.
     *
     * @param string $flow
     * @param string|int|null $userKey
     * @param string $suffix
     * @return string
     */
    protected function keyFor(string $flow, string|int|null $userKey, string $suffix): string
    {
        $userPart = $userKey ?? 'guest';
        return "workflows.{$flow}.{$userPart}.{$suffix}";
    }
}
