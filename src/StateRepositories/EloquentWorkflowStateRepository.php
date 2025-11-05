<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\StateRepositories;

use Illuminate\Database\ConnectionInterface;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;

/**
 * Eloquent/Database state repository.
 *
 * Provides persistent storage of workflow state in the database.
 * Suitable for authenticated users and production use.
 */
class EloquentWorkflowStateRepository implements WorkflowStateRepository
{
    protected string $table = 'workflow_states';

    public function __construct(
        protected ConnectionInterface $db
    ) {}

    public function getCurrentStep(string $flow, string|int|null $userKey): ?string
    {
        $record = $this->findRecord($flow, $userKey);

        return $record?->current_step;
    }

    public function setCurrentStep(string $flow, string|int|null $userKey, string $stepKey): void
    {
        $this->db->table($this->table)->updateOrInsert(
            [
                'workflow_name' => $flow,
                'user_key' => (string) ($userKey ?? 'guest'),
            ],
            [
                'current_step' => $stepKey,
                'updated_at' => now(),
            ]
        );
    }

    public function getHistory(string $flow, string|int|null $userKey): array
    {
        $record = $this->findRecord($flow, $userKey);

        if (! $record || ! $record->history) {
            return [];
        }

        $decoded = json_decode($record->history, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function pushHistory(string $flow, string|int|null $userKey, string $stepKey): void
    {
        $history = $this->getHistory($flow, $userKey);
        $history[] = $stepKey;

        $this->db->table($this->table)->updateOrInsert(
            [
                'workflow_name' => $flow,
                'user_key' => (string) ($userKey ?? 'guest'),
            ],
            [
                'history' => json_encode($history),
                'updated_at' => now(),
            ]
        );
    }

    public function popHistory(string $flow, string|int|null $userKey): ?string
    {
        $history = $this->getHistory($flow, $userKey);

        if (empty($history)) {
            return null;
        }

        $last = array_pop($history);

        $this->db->table($this->table)->updateOrInsert(
            [
                'workflow_name' => $flow,
                'user_key' => (string) ($userKey ?? 'guest'),
            ],
            [
                'history' => json_encode($history),
                'updated_at' => now(),
            ]
        );

        return $last;
    }

    public function clear(string $flow, string|int|null $userKey): void
    {
        $this->db->table($this->table)
            ->where('workflow_name', $flow)
            ->where('user_key', (string) ($userKey ?? 'guest'))
            ->delete();
    }

    public function getMetadata(string $flow, string|int|null $userKey): array
    {
        $record = $this->findRecord($flow, $userKey);

        if (! $record || ! $record->metadata) {
            return [];
        }

        $decoded = json_decode($record->metadata, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function setMetadata(string $flow, string|int|null $userKey, array $metadata): void
    {
        $this->db->table($this->table)->updateOrInsert(
            [
                'workflow_name' => $flow,
                'user_key' => (string) ($userKey ?? 'guest'),
            ],
            [
                'metadata' => json_encode($metadata),
                'updated_at' => now(),
            ]
        );
    }

    public function getState(string $flow, string|int|null $userKey, string $key): mixed
    {
        $allState = $this->getAllState($flow, $userKey);

        return $allState[$key] ?? null;
    }

    public function setState(string $flow, string|int|null $userKey, string $key, mixed $value): void
    {
        $allState = $this->getAllState($flow, $userKey);
        $allState[$key] = $value;

        $this->db->table($this->table)->updateOrInsert(
            [
                'workflow_name' => $flow,
                'user_key' => (string) ($userKey ?? 'guest'),
            ],
            [
                'data' => json_encode($allState),
                'updated_at' => now(),
            ]
        );
    }

    public function hasState(string $flow, string|int|null $userKey, string $key): bool
    {
        $allState = $this->getAllState($flow, $userKey);

        return array_key_exists($key, $allState);
    }

    public function forgetState(string $flow, string|int|null $userKey, string $key): void
    {
        $allState = $this->getAllState($flow, $userKey);
        unset($allState[$key]);

        $this->db->table($this->table)->updateOrInsert(
            [
                'workflow_name' => $flow,
                'user_key' => (string) ($userKey ?? 'guest'),
            ],
            [
                'data' => empty($allState) ? null : json_encode($allState),
                'updated_at' => now(),
            ]
        );
    }

    public function clearState(string $flow, string|int|null $userKey, ?string $namespace = null): void
    {
        if ($namespace === null) {
            $this->db->table($this->table)->updateOrInsert(
                [
                    'workflow_name' => $flow,
                    'user_key' => (string) ($userKey ?? 'guest'),
                ],
                [
                    'data' => null,
                    'updated_at' => now(),
                ]
            );
            return;
        }

        $allState = $this->getAllState($flow, $userKey);

        foreach (array_keys($allState) as $key) {
            if (str_starts_with($key, $namespace . '.')) {
                unset($allState[$key]);
            }
        }

        $this->db->table($this->table)->updateOrInsert(
            [
                'workflow_name' => $flow,
                'user_key' => (string) ($userKey ?? 'guest'),
            ],
            [
                'data' => empty($allState) ? null : json_encode($allState),
                'updated_at' => now(),
            ]
        );
    }

    public function getAllState(string $flow, string|int|null $userKey): array
    {
        $record = $this->findRecord($flow, $userKey);

        if (! $record || ! $record->data) {
            return [];
        }

        $decoded = json_decode($record->data, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Find a workflow state record.
     */
    protected function findRecord(string $flow, string|int|null $userKey): ?object
    {
        return $this->db->table($this->table)
            ->where('workflow_name', $flow)
            ->where('user_key', (string) ($userKey ?? 'guest'))
            ->first();
    }
}
