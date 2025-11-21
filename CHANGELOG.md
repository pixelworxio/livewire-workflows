# Changelog

All notable changes to `livewire-workflows` will be documented in this file.

## [Unreleased]

### Added

- **WorkflowName Attribute**: New `#[WorkflowName]` attribute for declaring workflow names on Livewire component classes
  - Eliminates boilerplate of manually setting `$workflowName` property
  - Makes workflow association explicit and declarative
  - Auto-detected during `bootInteractsWithWorkflows()`
  - Falls back to route-based auto-detection if not present
- Comprehensive tests for WorkflowName attribute functionality
- Documentation updates in README.md and CLAUDE.md

### Changed

- Updated `InteractsWithWorkflows` trait to check for `WorkflowName` attribute before falling back to route-based detection

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
