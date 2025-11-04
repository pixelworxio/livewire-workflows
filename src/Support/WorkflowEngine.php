<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Support;

use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Pixelworxio\LivewireWorkflows\Contracts\GuardContract;
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;
use Pixelworxio\LivewireWorkflows\Events\WorkflowAdvanced;
use Pixelworxio\LivewireWorkflows\Events\WorkflowCompleted;

/**
 * Core workflow execution engine.
 *
 * Uses Laravel's Pipeline to evaluate guards in order and determine
 * the next step or completion state. Handles state persistence and events.
 */
class WorkflowEngine
{
    public function __construct(
        protected WorkflowStateRepository $stateRepository,
        protected Pipeline $pipeline,
    ) {}

    /**
     * Determine the next step for the user in the workflow.
     *
     * @return string|null Step key or null if workflow is complete
     */
    public function nextStep(WorkflowDefinition $workflow, Request $request): ?string
    {
        $orderedSteps = $workflow->getOrderedSteps();

        foreach ($orderedSteps as $step) {
            if (! $step->hasGuard()) {
                continue;
            }

            $guard = app($step->guardClass);

            if (! $guard instanceof GuardContract) {
                continue;
            }

            // If guard fails (returns false), this is the next unmet step
            if (! $guard->passes($request)) {
                return $step->key;
            }
        }

        // All guards passed - workflow is complete
        return null;
    }

    /**
     * Get the previous step key based on current step.
     */
    public function previousStep(WorkflowDefinition $workflow, string $currentKey, Request $request): ?string
    {
        $userKey = $this->getUserKey($request);

        // If using history stack, pop from history
        if ($workflow->hasHistory()) {
            $history = $this->stateRepository->getHistory($workflow->flow, $userKey);

            // Remove current step from history if it's the last item
            if (! empty($history) && end($history) === $currentKey) {
                array_pop($history);
            }

            // Get the previous step from history
            if (! empty($history)) {
                return end($history);
            }
        }

        // Fall back to ordered previous step
        $previousStep = $workflow->getPreviousStep($currentKey);

        return $previousStep?->key;
    }

    /**
     * Advance the workflow to a specific step.
     */
    public function advanceTo(WorkflowDefinition $workflow, string $toKey, Request $request): void
    {
        $userKey = $this->getUserKey($request);
        $fromKey = $this->stateRepository->getCurrentStep($workflow->flow, $userKey);

        // Call onExit for previous step's guard
        if ($fromKey !== null) {
            $fromStep = $workflow->getStep($fromKey);
            if ($fromStep?->hasGuard()) {
                $guard = app($fromStep->guardClass);
                if ($guard instanceof GuardContract) {
                    $guard->onExit($request);
                }
            }
        }

        // Update current step
        $this->stateRepository->setCurrentStep($workflow->flow, $userKey, $toKey);

        // Add to history if enabled
        if ($workflow->hasHistory()) {
            $this->stateRepository->pushHistory($workflow->flow, $userKey, $toKey);
        }

        // Call onEnter for new step's guard
        $toStep = $workflow->getStep($toKey);
        if ($toStep?->hasGuard()) {
            $guard = app($toStep->guardClass);
            if ($guard instanceof GuardContract) {
                $guard->onEnter($request);
            }
        }

        // Fire event
        WorkflowAdvanced::dispatch($workflow->flow, $userKey, $fromKey, $toKey);
    }

    /**
     * Mark a workflow as completed.
     */
    public function complete(WorkflowDefinition $workflow, Request $request): void
    {
        $userKey = $this->getUserKey($request);

        // Clear workflow state
        $this->stateRepository->clear($workflow->flow, $userKey);

        // Fire completion event
        WorkflowCompleted::dispatch($workflow->flow, $userKey);
    }

    /**
     * Get progress information for a workflow.
     */
    public function progress(WorkflowDefinition $workflow, Request $request): array
    {
        $orderedSteps = $workflow->getOrderedSteps();
        $totalSteps = count($orderedSteps);
        $completedSteps = 0;
        $currentStepKey = null;
        $nextStepKey = null;

        foreach ($orderedSteps as $step) {
            if (! $step->hasGuard()) {
                $completedSteps++;

                continue;
            }

            $guard = app($step->guardClass);

            if (! $guard instanceof GuardContract) {
                continue;
            }

            if ($guard->passes($request)) {
                $completedSteps++;
            } else {
                if ($nextStepKey === null) {
                    $nextStepKey = $step->key;
                }
                break;
            }
        }

        $userKey = $this->getUserKey($request);
        $currentStepKey = $this->stateRepository->getCurrentStep($workflow->flow, $userKey);

        return [
            'total' => $totalSteps,
            'completed' => $completedSteps,
            'remaining' => $totalSteps - $completedSteps,
            'percentage' => $totalSteps > 0 ? round(($completedSteps / $totalSteps) * 100, 2) : 0,
            'current_step' => $currentStepKey,
            'next_step' => $nextStepKey,
            'is_complete' => $nextStepKey === null,
        ];
    }

    /**
     * Get the user key for state persistence.
     */
    protected function getUserKey(Request $request): string|int|null
    {
        if ($request->user()) {
            return $request->user()->getAuthIdentifier();
        }

        return $request->session()->getId();
    }
}
