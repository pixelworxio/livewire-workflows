<?php

return [
    /*
    |--------------------------------------------------------------------------
    | State Repository
    |--------------------------------------------------------------------------
    |
    | The repository used to persist workflow state.
    |
    | Supported: "null", "session", "eloquent"
    |
    | - null: No persistence (stateless)
    | - session: Store in Laravel session (good for guests)
    | - eloquent: Store in database (requires migration)
    |
    */
    'repository' => env('WORKFLOWS_REPOSITORY', 'session'),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware applied to all workflow routes.
    | You can add 'auth' here to protect all workflows.
    |
    */
    'middleware' => ['web'],
];
