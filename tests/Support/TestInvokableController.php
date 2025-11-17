<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\Request;

class TestInvokableController
{
    public function __invoke(Request $request)
    {
        return view('test-controller-view', [
            'message' => 'Invokable Controller',
            'workflowState' => workflowState('test-flow')->forRequest($request)->all(),
        ]);
    }
}
