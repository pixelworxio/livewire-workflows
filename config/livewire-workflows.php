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
    | Middleware applied to all workflow routes by default.
    | Individual workflows and steps can override this using the middleware()
    | method or #[StepMiddleware] attribute.
    |
    */
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Middleware Precedence
    |--------------------------------------------------------------------------
    |
    | How middleware should be combined when multiple levels are defined.
    |
    | Supported: "override", "merge"
    |
    | - override: Step middleware completely replaces workflow/global middleware
    | - merge: Step, workflow, and global middleware are all combined
    |
    */
    'middleware_precedence' => env('WORKFLOWS_MIDDLEWARE_PRECEDENCE', 'override'),
];
