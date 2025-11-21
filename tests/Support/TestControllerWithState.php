<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TestControllerWithState
{
    public function __invoke(Request $request)
    {
        // Set some state
        workflowState('onboarding')
            ->forRequest($request)
            ->set('controller_name', 'Test Controller')
            ->set('controller_step', 'step-one');

        return view('test-controller-view', [
            'message' => 'Controller with State',
        ]);
    }

    public function edit(Request $request)
    {
        // Get state and set new state
        $previousName = workflowState('onboarding')->forRequest($request)->get('controller_name');

        workflowState('onboarding')
            ->forRequest($request)
            ->set('previous_name', $previousName)
            ->set('edit_step', 'completed');

        return view('test-controller-view', [
            'message' => 'Edit with State',
            'previous_name' => $previousName,
        ]);
    }

    public function redirect(Request $request): RedirectResponse
    {
        // Test that we can redirect to the workflow entry
        workflowState('onboarding')
            ->forRequest($request)
            ->set('redirect_called', true);

        return redirect()->route('onboarding.start');
    }
}
