<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\Request;

class TestMethodController
{
    public function show(Request $request)
    {
        return view('test-controller-view', [
            'message' => 'Method Controller',
            'workflowState' => workflowState('test-flow')->forRequest($request)->all(),
        ]);
    }

    public function edit(Request $request)
    {
        return view('test-controller-view', [
            'message' => 'Edit Method',
            'workflowState' => workflowState('test-flow')->forRequest($request)->all(),
        ]);
    }
}
