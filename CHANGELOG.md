# Changelog

All notable changes to `livewire-workflows` will be documented in this file.

## [Unreleased]

### Added

- **Resume Links**: Generate signed URLs that drop users directly into their current workflow step
  - `workflow('flow')->resumeUrlFor(user: $user)` — for authenticated users
  - `workflow('flow')->resumeUrlFor(userKey: 'guest-key')` — for explicit guest keys
  - Configurable expiry via `expiresInMinutes` parameter (default: 1440 = 24 hours)
  - `WORKFLOWS_RESUME_EXPIRES` env variable for global default
  - New `WorkflowResumeController` handles signed URL validation and step redirect
  - Requires Eloquent state repository; throws `\RuntimeException` if not configured

- **`workflows:upgrade` command**: Guides existing installations from session to Eloquent state
  - Detects current repository configuration
  - Confirmation prompt before making any changes (safe to cancel)
  - Publishes migration, runs `migrate`, updates published config on confirm

### Changed

- **Eloquent is now the default repository**: `WORKFLOWS_REPOSITORY` defaults to `eloquent` instead of `session`
- **`workflows:install` always sets up the database**: The `--with-db` flag has been removed; migration is always published and `php artisan migrate` is prompted in next steps

### Deprecated

- **Session state repository**: `SessionWorkflowStateRepository` is no longer documented or recommended. It will be removed in a future release. Existing code using it will continue to work.

### Breaking Changes

- Default `WORKFLOWS_REPOSITORY` changed from `session` to `eloquent`. Run `php artisan workflows:upgrade` to migrate.
- `workflows:install --with-db` flag removed. Database setup is now always included.

See [UPGRADE.md](UPGRADE.md) for full migration instructions.

## 0.5.6b - 2026-03-23

### Added

- **Laravel 13 Support**: Added compatibility with Laravel 13.x (released March 17, 2026).
  Updated `illuminate/contracts`, `illuminate/pipeline`, and `illuminate/support` constraints
  to include `^13.0`. Updated `orchestra/testbench` to include `^11.0.0` for testing.
  No source code changes required — all lifecycle hooks and APIs remain fully compatible.

### Changed

- Updated CI matrix to test against Laravel 13.* with Testbench 11.*
- Updated README.md and CLAUDE.md to document Laravel 13.x support

**Full Changelog**: https://github.com/pixelworxio/livewire-workflows/compare/0.5.5b...0.5.6b

## 0.5.5b - 2026-02-18

### What's Changed

* Add workflows:audit command and tests by @whoisthisstud in https://github.com/pixelworxio/livewire-workflows/pull/19

**Full Changelog**: https://github.com/pixelworxio/livewire-workflows/compare/0.5.4.b...0.5.5b

## 0.5.4.b - 2026-02-18

### What's Changed

* Bump dependabot/fetch-metadata from 2.4.0 to 2.5.0 by @dependabot[bot] in https://github.com/pixelworxio/livewire-workflows/pull/16
* Certify Livewire v4 support and update documentation by @whoisthisstud in https://github.com/pixelworxio/livewire-workflows/pull/17
* Add more tests by @whoisthisstud in https://github.com/pixelworxio/livewire-workflows/pull/18

**Full Changelog**: https://github.com/pixelworxio/livewire-workflows/compare/0.5.3b...0.5.4.b

## 0.6.0b - 2026-02-17

### Added

- **Livewire v4 Certification**: Formally verified compatibility with Livewire v4. All lifecycle
  hooks used by `InteractsWithWorkflows` (`boot`, `mount`, `updated`, `dehydrate`) and the
  `redirect()` navigation API (`navigate: true`) are confirmed unchanged in Livewire v4.
- Added `tests/Feature/LivewireV4CompatibilityTest.php` with 7 explicit v4 compatibility tests
  documenting each lifecycle integration point.

### Changed

- Updated `README.md` requirements to list Livewire 3.x or 4.x.
- Updated `README.md` FAQ to reflect certified Livewire v4 compatibility.

## 0.5.3b - 2025-12-21

### What's Changed

* feat: Improve navigation helpers to not require repeated flow info by @whoisthisstud in https://github.com/pixelworxio/livewire-workflows/pull/14

**Full Changelog**: https://github.com/pixelworxio/livewire-workflows/compare/0.5.2b...0.5.3b

## 0.5.2b - 2025-11-26

### What's Changed

* feat: Add selective auth middleware to workflows by @whoisthisstud in https://github.com/pixelworxio/livewire-workflows/pull/13
* Bump actions/checkout from 5 to 6 by @dependabot[bot] in https://github.com/pixelworxio/livewire-workflows/pull/15

**Full Changelog**: https://github.com/pixelworxio/livewire-workflows/compare/0.5.1b...0.5.2b

## 0.5.1b - 2025-11-21

### What's Changed

* Update README.md by @whoisthisstud in https://github.com/pixelworxio/livewire-workflows/pull/9
* Fix workflow trait property dehydration issue by @whoisthisstud in https://github.com/pixelworxio/livewire-workflows/pull/11
* feat: Add WorkflowName attribute for explicit workflow declaration by @whoisthisstud in https://github.com/pixelworxio/livewire-workflows/pull/12

**Full Changelog**: https://github.com/pixelworxio/livewire-workflows/compare/0.5b...0.5.1b

## [Unreleased]

### Added

- **Selective Auth Middleware**: Comprehensive middleware customization at workflow and step levels
  
  - **WorkflowStep Attribute** (Preferred): `#[WorkflowStep(flow: 'checkout', key: 'payment', middleware: ['web', 'auth'])]` - all-in-one attribute
    
  - **StepMiddleware Attribute**: `#[StepMiddleware(['web', 'auth'])]` for declarative step middleware only
    
  - **DSL Middleware Methods**: `->middleware(['web', 'auth'])` on FlowBuilder and StepBuilder
    
  - **Callable Middleware**: Support for dynamic middleware resolution: `->middleware(fn() => ['web', 'auth'])`
    
  - **Middleware Precedence Configuration**: New `middleware_precedence` config option ('override' | 'merge')
    
    - **Override mode** (default): Step > Workflow > Global
    - **Merge mode**: Combines all middleware layers (deduplicated)
    
  - Enables mixed public/authenticated steps in same workflow
    
  - Fine-grained access control per step
    
  - 17 comprehensive tests covering all middleware scenarios
    
  
- **WorkflowName Attribute**: New `#[WorkflowName]` attribute for declaring workflow names on Livewire component classes
  
  - Eliminates boilerplate of manually setting `$workflowName` property
  - Makes workflow association explicit and declarative
  - Auto-detected during `bootInteractsWithWorkflows()`
  - Falls back to route-based auto-detection if not present
  
- Comprehensive tests for WorkflowName attribute functionality
  
- Documentation updates in README.md, CLAUDE.md, and new MIDDLEWARE.md guide
  

### Changed

- Updated `InteractsWithWorkflows` trait to check for `WorkflowStep` and `WorkflowName` attributes before falling back to route-based detection
- **WorkflowDefinition**: Added optional `middleware` property (array|Closure|null)
- **StepDefinition**: Added optional `middleware` property (array|Closure|null)
- **RouteRegistrar**: Enhanced to resolve and apply middleware with configurable precedence logic
- **StepBuilder**: Automatically reads `WorkflowStep` or `StepMiddleware` attributes from component classes
- **Config**: Updated middleware documentation to reflect per-workflow/step customization
- **WorkflowStep Attribute** (Enhanced): Added optional `middleware` parameter while preserving existing signature
  - Signature: `WorkflowStep($flow, $key, $middleware = [])`
  - `$flow` parameter sets the workflow name on component
  - `$key` parameter is for documentation/validation purposes
  - Added optional `$middleware` parameter for setting step middleware
  - Now combines `WorkflowName` and `StepMiddleware` functionality into one attribute
  - Attribute precedence: `WorkflowStep` > `WorkflowName` + `StepMiddleware`
  

### Removed

- **WorkflowMiddleware Attribute**: Removed because workflow-level middleware should only be set via DSL `->middleware()` method, not from step component classes

### Breaking Changes

- **WorkflowMiddleware attribute removed**: If you were using the `#[WorkflowMiddleware]` attribute, use `->middleware()` method on `FlowBuilder` in your workflow DSL instead

### Notes

- **WorkflowStep attribute enhanced**: The `WorkflowStep` attribute now accepts an optional third parameter for middleware: `#[WorkflowStep(flow: 'checkout', key: 'payment', middleware: ['web', 'auth'])]`
  - This is **not a breaking change** - existing usage without the middleware parameter continues to work
  - The attribute can now be used as a "god attribute" combining workflow name, step identification, and middleware configuration
  

## 0.5b - 2025-11-17

### What's Changed

* Write a CLAUDE.md by @whoisthisstud in https://github.com/pixelworxio/livewire-workflows/pull/4
* feat: Add support for dynamic workflow routes with route parameters by @whoisthisstud in https://github.com/pixelworxio/livewire-workflows/pull/5
* fix: Guard hook methods not firing in WorkflowEngine by @whoisthisstud in https://github.com/pixelworxio/livewire-workflows/pull/6
* feat: add support for using controllers for steps by @whoisthisstud in https://github.com/pixelworxio/livewire-workflows/pull/7

**Full Changelog**: https://github.com/pixelworxio/livewire-workflows/compare/0.4a...0.5b

## 0.4a - 2025-11-07

### What's Changed

* Feature: Add make:workflow-guard command by @whoisthisstud in https://github.com/pixelworxio/livewire-workflows/pull/2
* Feature: Add pass/fail Guard hooks by @whoisthisstud in https://github.com/pixelworxio/livewire-workflows/pull/3

### New Contributors

* @whoisthisstud made their first contribution in https://github.com/pixelworxio/livewire-workflows/pull/2

**Full Changelog**: https://github.com/pixelworxio/livewire-workflows/compare/0.3a...0.4a

## 0.3a - 2025-11-06

Reduce PHP requirement to 8.3 or greater.

**Full Changelog**: https://github.com/pixelworxio/livewire-workflows/compare/0.2a...0.3a

## 0.2a - 2025-11-06

### What's Changed

* Bump stefanzweifel/git-auto-commit-action from 6 to 7 by @dependabot[bot] in https://github.com/pixelworxio/livewire-workflows/pull/1

### New Contributors

* @dependabot[bot] made their first contribution in https://github.com/pixelworxio/livewire-workflows/pull/1

**Full Changelog**: https://github.com/pixelworxio/livewire-workflows/compare/0.1a...0.2a

## 0.1a - 2025-11-05

**Full Changelog**: https://github.com/pixelworxio/livewire-workflows/commits/0.1a
