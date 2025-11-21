# Changelog

All notable changes to `livewire-workflows` will be documented in this file.

## 0.5.1b - 2025-11-21

### What's Changed

* Update README.md by @whoisthisstud in https://github.com/pixelworxio/livewire-workflows/pull/9
* Fix workflow trait property dehydration issue by @whoisthisstud in https://github.com/pixelworxio/livewire-workflows/pull/11
* feat: Add WorkflowName attribute for explicit workflow declaration by @whoisthisstud in https://github.com/pixelworxio/livewire-workflows/pull/12

**Full Changelog**: https://github.com/pixelworxio/livewire-workflows/compare/0.5b...0.5.1b

## [Unreleased]

### Added

- **Selective Auth Middleware**: Comprehensive middleware customization at workflow and step levels
  - **WorkflowStep Attribute** (Preferred): `#[WorkflowStep(name: 'workflow', middleware: ['web', 'auth'])]` - all-in-one attribute
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
- **WorkflowStep Attribute** (Breaking): Signature changed from `WorkflowStep($flow, $key, $order)` to `WorkflowStep($name, $middleware = [])`
  - `$name` parameter now sets the workflow name (previously `$flow`)
  - Removed `$key` and `$order` parameters (these were not used for auto-registration)
  - Added optional `$middleware` parameter for setting step middleware
  - Now combines `WorkflowName` and `StepMiddleware` functionality into one attribute
  - Attribute precedence: `WorkflowStep` > `WorkflowName` + `StepMiddleware`

### Removed

- **WorkflowMiddleware Attribute**: Removed because workflow-level middleware should only be set via DSL `->middleware()` method, not from step component classes

### Breaking Changes

- **WorkflowStep attribute signature changed**: Update from `#[WorkflowStep(flow: 'name', key: 'step', order: 10)]` to `#[WorkflowStep(name: 'workflow', middleware: ['web', 'auth'])]`
  - The `flow` parameter is now `name` and sets the workflow name
  - The `key` and `order` parameters have been removed
  - Optional `middleware` parameter added for step-level middleware
  - **Migration**: If you were using the old WorkflowStep attribute, update to the new signature or use `WorkflowName` + `StepMiddleware` attributes instead
- **WorkflowMiddleware attribute removed**: Use `->middleware()` method on `FlowBuilder` in your workflow DSL instead

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
