# CLAUDE.md - AI Assistant Guide for Livewire Workflows

This document provides AI assistants with comprehensive information about the Livewire Workflows codebase structure, development workflows, and key conventions.

## Table of Contents

1. [Project Overview](#project-overview)
2. [Architecture & Design Patterns](#architecture--design-patterns)
3. [Directory Structure](#directory-structure)
4. [Core Components](#core-components)
5. [Development Workflow](#development-workflow)
6. [Coding Standards](#coding-standards)
7. [Testing Conventions](#testing-conventions)
8. [Key Concepts](#key-concepts)
9. [Common Tasks](#common-tasks)
10. [Troubleshooting](#troubleshooting)

---

## Project Overview

**Livewire Workflows** is a Laravel package that enables developers to build multi-step workflows with zero boilerplate. It provides an expressive DSL for defining complex user journeys (onboarding, checkouts, surveys) with automatic route registration, guard-based navigation, state persistence, and full Livewire integration.

### Key Information

- **Package Name**: `pixelworxio/livewire-workflows`
- **Namespace**: `Pixelworxio\LivewireWorkflows`
- **Requirements**: PHP 8.3+, Laravel 11.x/12.x, Livewire 3.x/4.x
- **License**: MIT
- **Test Framework**: Pest v3/v4
- **Code Quality**: PHPStan (level 2), Laravel Pint

### Primary Features

1. **Auto-generated routes** from DSL definitions
2. **Guard-based navigation** with positive semantics
3. **State persistence** (session, database, or null)
4. **History tracking** for back navigation
5. **Progress tracking** API
6. **Event-driven extensibility**
7. **Livewire 3 native integration**

---

## Architecture & Design Patterns

### Design Patterns Used

#### 1. Fluent Builder Pattern
- **Classes**: `FlowBuilder`, `StepBuilder`
- **Purpose**: Provide expressive DSL for workflow definition
- **Result**: Immutable DTOs (`WorkflowDefinition`, `StepDefinition`)

```php
Workflow::flow('onboarding')
    ->entersAt(name: 'onboarding.start', path: '/onboarding')
    ->finishesAt('dashboard')
    ->step('verify-email')
        ->goTo(VerifyEmail::class)
        ->unlessPasses(EmailVerifiedGuard::class)
        ->order(10);
```

#### 2. Repository Pattern
- **Contract**: `WorkflowStateRepository`
- **Implementations**: `SessionWorkflowStateRepository`, `EloquentWorkflowStateRepository`, `NullStateRepository`
- **Purpose**: Abstract state persistence mechanism

#### 3. Strategy Pattern
- **Usage**: Swap state repositories via configuration
- **Binding**: Service container resolves correct implementation

#### 4. Pipeline Pattern
- **Location**: `WorkflowEngine`
- **Purpose**: Sequential guard evaluation using Laravel's Pipeline

#### 5. Facade Pattern
- **Class**: `Workflow` facade
- **Target**: `WorkflowRegistrar`
- **Purpose**: Static access to workflow registration

#### 6. Registry Pattern
- **Class**: `WorkflowRegistrar`
- **Purpose**: Central registry of all workflows with lazy finalization

#### 7. DTO Pattern
- **Classes**: `WorkflowDefinition`, `StepDefinition`
- **Characteristics**: Immutable, validated on construction

#### 8. Trait Composition
- **Trait**: `InteractsWithWorkflows`
- **Purpose**: Reusable functionality for Livewire components

#### 9. Observer Pattern (Events)
- **Events**: `WorkflowAdvanced`, `WorkflowCompleted`, `WorkflowStateClearing`
- **Purpose**: Extensibility and analytics hooks

---

## Directory Structure

```
livewire-workflows/
├── config/
│   └── livewire-workflows.php          # Package configuration
├── database/
│   └── migrations/                     # Database migration stubs
├── resources/                          # Package resources (images, etc.)
├── src/
│   ├── Attributes/                     # PHP 8 Attributes
│   │   ├── WorkflowState.php          # State persistence attribute
│   │   └── WorkflowStep.php           # Step metadata attribute
│   ├── Commands/                       # Artisan commands
│   │   ├── MakeWorkflowCommand.php
│   │   ├── MakeWorkflowStepCommand.php
│   │   ├── MakeWorkflowGuardCommand.php
│   │   ├── WorkflowsInstallCommand.php
│   │   └── WorkflowsScanCommand.php
│   ├── Contracts/                      # Interfaces
│   │   ├── GuardContract.php
│   │   └── WorkflowStateRepository.php
│   ├── Events/                         # Laravel Events
│   │   ├── WorkflowAdvanced.php
│   │   ├── WorkflowCompleted.php
│   │   └── WorkflowStateClearing.php
│   ├── Exceptions/                     # Custom exceptions
│   │   ├── WorkflowNotFoundException.php
│   │   └── InvalidWorkflowConfigurationException.php
│   ├── Facades/                        # Laravel Facades
│   │   └── Workflow.php
│   ├── Http/Controllers/               # HTTP Controllers
│   │   └── WorkflowEntryController.php
│   ├── Livewire/Concerns/             # Livewire Traits
│   │   └── InteractsWithWorkflows.php
│   ├── Registrar/                      # DSL Implementation
│   │   ├── WorkflowRegistrar.php      # Central registry
│   │   ├── FlowBuilder.php            # Flow builder
│   │   └── StepBuilder.php            # Step builder
│   ├── StateRepositories/             # State Persistence
│   │   ├── NullStateRepository.php
│   │   ├── SessionWorkflowStateRepository.php
│   │   └── EloquentWorkflowStateRepository.php
│   ├── Support/                       # Core Support Classes
│   │   ├── WorkflowDefinition.php     # Workflow DTO
│   │   ├── StepDefinition.php         # Step DTO
│   │   ├── WorkflowEngine.php         # Core execution logic
│   │   ├── WorkflowResolver.php       # High-level API
│   │   ├── WorkflowInstance.php       # Fluent workflow interface
│   │   └── RouteRegistrar.php         # Auto-route registration
│   ├── LivewireWorkflowsServiceProvider.php
│   └── helpers.php                    # Global helper functions
├── stubs/                             # Code generation templates
│   └── workflows.php.stub
├── tests/
│   ├── Feature/                       # Integration tests
│   ├── Unit/                          # Unit tests
│   ├── Support/                       # Test fixtures
│   ├── TestCase.php                   # Base test case
│   ├── Pest.php                       # Pest configuration
│   └── ArchTest.php                   # Architecture tests
├── CHANGELOG.md                       # Version history
├── CONTRIBUTING.md                    # Contribution guidelines
├── README.md                          # User documentation
├── STATE_MANAGEMENT.md                # State management guide
├── UPGRADE.md                         # Upgrade guide
├── composer.json
├── phpunit.xml.dist
└── phpstan.neon.dist
```

---

## Core Components

### 1. Workflow Engine (`src/Support/WorkflowEngine.php`)

**Purpose**: Core workflow execution logic

**Responsibilities**:
- Evaluates guards using Laravel Pipeline
- Determines next/previous steps
- Handles workflow completion
- Calculates progress tracking
- Fires workflow events
- Manages state clearing

**Key Methods**:
- `nextStepFor(WorkflowDefinition $flow, Request $request): ?StepDefinition`
- `previousStepFor(WorkflowDefinition $flow, string $currentKey, Request $request): ?StepDefinition`
- `progressFor(WorkflowDefinition $flow, Request $request): array`
- `isComplete(WorkflowDefinition $flow, Request $request): bool`

### 2. Workflow Registrar (`src/Registrar/WorkflowRegistrar.php`)

**Purpose**: Central registry for all workflows

**Responsibilities**:
- Manages pending builders and finalized definitions
- Lazy finalization pattern
- Provides workflow lookup

**Key Methods**:
- `flow(string $name): FlowBuilder` - Start a new workflow definition
- `get(string $name): WorkflowDefinition` - Get finalized workflow
- `has(string $name): bool` - Check if workflow exists
- `all(): array` - Get all workflows

### 3. Flow Builder (`src/Registrar/FlowBuilder.php`)

**Purpose**: Fluent builder for workflow definitions

**Key Methods**:
- `entersAt(string $name, string $path): self` - Define entry route
- `finishesAt(string $routeName): self` - Define completion route
- `historyMode(string $mode): self` - Set history mode ('none' or 'stack')
- `step(string $key): StepBuilder` - Add a step and return StepBuilder
- `build(): WorkflowDefinition` - Finalize and validate

**Auto-Build**: Builder auto-builds in destructor if `finishesAt()` was called.

### 4. Step Builder (`src/Registrar/StepBuilder.php`)

**Purpose**: Fluent builder for individual steps

**Key Methods**:
- `goTo(string $componentClass): self` - Set Livewire component
- `unlessPasses(string $guardClass): self` - Set guard class
- `order(int $order): self` - Set execution order
- Proxies unknown methods to `FlowBuilder` for chaining

### 5. Workflow Definition (`src/Support/WorkflowDefinition.php`)

**Purpose**: Immutable DTO for workflow configuration

**Properties**:
- `flowName` - Workflow identifier
- `entryRouteName`, `entryRoutePath` - Entry route details
- `finishRouteName` - Completion route
- `historyMode` - History tracking mode
- `steps` - Array of StepDefinitions

**Key Methods**:
- `getOrderedSteps(): array` - Steps sorted by order
- `getPreviousStep(string $currentKey): ?StepDefinition`
- `getStepRouteName(string $stepKey): string`

### 6. InteractsWithWorkflows Trait (`src/Livewire/Concerns/InteractsWithWorkflows.php`)

**Purpose**: Core trait for Livewire components

**Lifecycle Hooks**:
- `bootInteractsWithWorkflows()` - Auto-detect workflow name from route
- `mountInteractsWithWorkflows()` - Hydrate state from repository
- `dehydrateInteractsWithWorkflows()` - Sync state to repository

**Navigation Methods**:
- `continue(string $flow): void` - Move to next step
- `back(string $flow, string $currentKey): void` - Return to previous step
- `syncState(): void` - Manually persist state

**State Methods**:
- `getWorkflowState(string $key, mixed $default = null): mixed`
- `putWorkflowState(string $key, mixed $value): void`
- `hasWorkflowState(string $key): bool`
- `forgetWorkflowState(string $key): void`
- `clearWorkflowState(?string $namespace = null): void`
- `allWorkflowState(): array`

**User Key Resolution**:
1. Authenticated user ID (if logged in)
2. Session ID (if session available)
3. Guest fingerprint: `guest-{md5(IP + UserAgent)}`

### 7. Guard Contract (`src/Contracts/GuardContract.php`)

**Purpose**: Interface for step guards

**Critical Understanding - Positive Semantics**:
- `passes() = true` → Step is SKIPPED
- `passes() = false` → Step is SHOWN
- Used with `unlessPasses()` in DSL: "Show step UNLESS guard passes"

**Methods**:
- `passes(Request $request): bool` - Determine if step can be skipped
- `onEnter(Request $request): void` - Hook when entering step
- `onExit(Request $request): void` - Hook when exiting step
- `onPass(Request $request): void` - Hook when guard passes
- `onFail(Request $request): void` - Hook when guard fails

### 8. State Repositories

#### WorkflowStateRepository Contract
**Methods**:
- `getCurrentStep(string $flow, string|int $userKey): ?string`
- `setCurrentStep(string $flow, string|int $userKey, ?string $stepKey): void`
- `getHistory(string $flow, string|int $userKey): array`
- `pushHistory(string $flow, string|int $userKey, string $stepKey): void`
- `getMetadata(string $flow, string|int $userKey, string $key, mixed $default = null): mixed`
- `setMetadata(string $flow, string|int $userKey, string $key, mixed $value): void`
- `getState(string $flow, string|int $userKey, string $key): mixed`
- `setState(string $flow, string|int $userKey, string $key, mixed $value): void`
- `hasState(string $flow, string|int $userKey, string $key): bool`
- `forgetState(string $flow, string|int $userKey, string $key): void`
- `clearState(string $flow, string|int $userKey, ?string $namespace = null): void`
- `getAllState(string $flow, string|int $userKey): array`

#### SessionWorkflowStateRepository
- Session-based storage
- Keys: `workflows.{flow}.{userKey}.{suffix}`
- Suitable for guests and simple flows

#### EloquentWorkflowStateRepository
- Database-backed (`workflow_states` table)
- Production-ready with persistence
- Unique constraint on `[workflow_name, user_key]`

#### NullStateRepository
- No-op implementation for stateless workflows

---

## Development Workflow

### Initial Setup

```bash
# Clone and install
git clone https://github.com/pixelworxio/livewire-workflows.git
cd livewire-workflows
composer install

# Run tests
composer test

# Static analysis
./vendor/bin/phpstan analyse

# Format code
./vendor/bin/pint
```

### Branch Strategy

1. **Fork** the repository
2. **Create feature branch**: `git checkout -b feature/amazing-feature`
3. **Make changes** following coding standards
4. **Write tests** for all changes
5. **Run test suite**: `composer test`
6. **Commit with clear messages**
7. **Submit PR** to `main` branch

### Pre-Commit Checklist

- [ ] All tests pass: `composer test`
- [ ] PHPStan analysis passes: `./vendor/bin/phpstan analyse`
- [ ] Code formatted: `./vendor/bin/pint`
- [ ] New tests added for changes
- [ ] Documentation updated (README, STATE_MANAGEMENT, etc.)
- [ ] CHANGELOG.md updated

---

## Coding Standards

### General Principles

1. **PSR-12 Compliance**: Follow PHP coding standards
2. **SOLID Principles**: Keep classes focused, follow SRP and DIP
3. **DRY**: Don't repeat yourself
4. **Type Safety**: Use strict types and proper type hints
5. **Immutability**: Favor immutable objects where appropriate
6. **Early Returns**: Use guard clauses

### File Structure

```php
<?php

declare(strict_types=1);  // ALWAYS include

namespace Pixelworxio\LivewireWorkflows\Example;

use Illuminate\Http\Request;  // Imports sorted alphabetically

/**
 * Clear class-level docblock explaining purpose.
 */
class ExampleClass
{
    // Constructor with promoted properties (PHP 8+)
    public function __construct(
        protected readonly SomeDependency $dependency,
    ) {}

    // Public methods with full type hints
    public function exampleMethod(Request $request): string
    {
        // Use early returns for guard clauses
        if (! $this->shouldProceed($request)) {
            return 'early';
        }

        // Favor readability over brevity
        $result = $this->dependency->process($request);

        return $result;
    }

    // Protected/private methods at bottom
    protected function shouldProceed(Request $request): bool
    {
        return $request->has('required_field');
    }
}
```

### Key Conventions

#### Strict Types
**ALWAYS** start files with:
```php
<?php

declare(strict_types=1);
```

#### Constructor Property Promotion
Use PHP 8 promoted properties:
```php
public function __construct(
    protected readonly WorkflowEngine $engine,
    protected readonly WorkflowStateRepository $repository,
) {}
```

#### Readonly Properties
Use `readonly` for immutability when possible:
```php
public function __construct(
    public readonly string $flowName,
    public readonly string $entryRouteName,
) {}
```

#### Named Arguments
Use named arguments for clarity (especially in DSL):
```php
->entersAt(name: 'onboarding.start', path: '/onboarding')
```

#### Validation at Construction
Validate in `__construct()` or `build()`:
```php
public function __construct(
    public readonly string $flowName,
    public readonly string $componentClass,
) {
    if (! class_exists($componentClass)) {
        throw new InvalidWorkflowConfigurationException(
            "Component class {$componentClass} does not exist."
        );
    }
}
```

### Naming Conventions

- **Classes**: PascalCase (`WorkflowEngine`)
- **Methods**: camelCase (`nextStepFor`)
- **Variables**: camelCase (`$currentStep`)
- **Constants**: UPPER_SNAKE_CASE (`HISTORY_MODE_STACK`)
- **Route Names**: snake.case with dots (`onboarding.verify-email`)
- **Step Keys**: kebab-case (`verify-email`)
- **Workflow Names**: kebab-case (`employee-onboarding`)

### Route Conventions

**Auto-Generated Pattern**:
- Entry: `{path}` → `{flow}.start`
- Steps: `{path}/{step-key}` → `{flow}.{step-key}`

**Example**:
```php
->entersAt(name: 'checkout.start', path: '/checkout')
->step('shipping')  // → /checkout/shipping → checkout.shipping
```

**Dynamic Routes with Parameters**:

Workflows support dynamic route parameters and route model binding:

```php
->entersAt(name: 'checkout.start', path: '/user/{user}/product/{product}')
->step('shipping')  // → /user/{user}/product/{product}/shipping
```

**Key Implementation Details**:
- Route parameters are parsed from `entryPath` and stored in `WorkflowDefinition::$routeParameters`
- Parameters are automatically extracted from the current request and passed through navigation
- `continue()` and `back()` methods preserve route parameters seamlessly
- Supports Laravel's route model binding syntax: `{user:id}`, `{product:slug}`, etc.
- `WorkflowDefinition::parseRouteParameters()` extracts parameter names using regex
- `WorkflowResolver::extractRouteParameters()` filters current route parameters to only include workflow-defined parameters
- `InteractsWithWorkflows::extractWorkflowRouteParameters()` provides the same functionality for Livewire components

**Example with Model Binding**:
```php
Workflow::flow('order-review')
    ->entersAt(name: 'order-review.start', path: '/organization/{organization:slug}/order/{order:id}')
    ->finishesAt('dashboard')
    ->step('review')
        ->goTo(ReviewOrder::class)
        ->unlessPasses(OrderNotReviewed::class)
        ->order(10);
```

---

## Testing Conventions

### Test Framework: Pest v3/v4

### Test Structure

```php
<?php

declare(strict_types=1);

use Pixelworxio\LivewireWorkflows\Facades\Workflow;

beforeEach(function () {
    // Setup code runs before each test
    Workflow::flow('test')
        ->entersAt(name: 'test.start', path: '/test')
        ->finishesAt('done')
        ->step('step-one')
            ->goTo(TestStepOneComponent::class)
            ->unlessPasses(TestStepOneGuard::class)
            ->order(10);
});

test('descriptive test name in sentence case', function () {
    // Arrange
    TestStepOneGuard::$shouldPass = false;

    // Act
    $result = workflow('test')->nextRouteNameFor(request());

    // Assert
    expect($result)->toBe('test.step-one');
});
```

### Test Organization

- **Feature Tests** (`tests/Feature/`): Integration and end-to-end tests
- **Unit Tests** (`tests/Unit/`): Isolated business logic tests
- **Support** (`tests/Support/`): Test fixtures, stubs, helpers

### Test Fixtures

**Test Components** (e.g., `TestStepOneComponent.php`):
```php
class TestStepOneComponent extends Component
{
    use InteractsWithWorkflows;

    public function render()
    {
        return '<div>Test Step</div>';
    }
}
```

**Test Guards** (e.g., `TestStepOneGuard.php`):
```php
class TestStepOneGuard implements GuardContract
{
    public static bool $shouldPass = false;

    public function passes(Request $request): bool
    {
        return self::$shouldPass;
    }

    public function onEnter(Request $request): void {}
    public function onExit(Request $request): void {}
    public function onPass(Request $request): void {}
    public function onFail(Request $request): void {}
}
```

### TestCase Configuration (`tests/TestCase.php`)

**Key Setup**:
- Extends `Orchestra\Testbench\TestCase`
- Registers `LivewireServiceProvider` and `LivewireWorkflowsServiceProvider`
- Configures in-memory SQLite for tests
- Provides `loadWorkflowMigrations()` helper

### Running Tests

```bash
# All tests
composer test
# or
./vendor/bin/pest

# Specific file
./vendor/bin/pest tests/Unit/WorkflowRegistrarTest.php

# With coverage
./vendor/bin/pest --coverage

# With coverage minimum
./vendor/bin/pest --coverage --min=80
```

### Test Coverage Goals

- Maintain **>80%** code coverage
- Test edge cases and error conditions
- Feature tests for integration scenarios
- Unit tests for business logic

### Architecture Tests (`tests/ArchTest.php`)

```php
arch('no debug functions in production code')
    ->expect('Pixelworxio\LivewireWorkflows')
    ->not->toUse(['dd', 'dump', 'ray']);
```

---

## Key Concepts

### 1. Positive Guard Semantics

**Critical Understanding**: Guards use **positive semantics** - opposite of what you might expect.

| Guard Result | Step Behavior |
|--------------|---------------|
| `passes() = true` | **Skip** this step |
| `passes() = false` | **Show** this step |

**Rationale**: Natural language with `unlessPasses()`:
```php
->unlessPasses(EmailVerifiedGuard::class)
// "Show step UNLESS guard passes"
```

**Example**:
```php
class ProfileCompleteGuard implements GuardContract
{
    public function passes(Request $request): bool
    {
        // Return true when profile IS complete → skip step
        return $request->user()->profile_completed;
    }
}
```

### 2. Workflow Navigation Flow

1. User visits **entry route** (`/onboarding`)
2. Package evaluates guards in **order**
3. Redirects to **first failing guard's step**
4. User completes step, calls `$this->continue('onboarding')`
5. Re-evaluates from entry → **next unmet step** or **finish**

### 3. History Modes

```php
->historyMode('none')   // No tracking (default)
->historyMode('stack')  // Full history stack for back navigation
```

**Stack Mode**: Enables `$this->back()` to return to previous steps.

### 4. State Scoping

**State is scoped per**:
- Workflow name
- User key (auth ID > session ID > guest fingerprint)

**No cross-workflow sharing** - by design for data isolation.

### 5. Auto-Route Registration

Routes are automatically registered from DSL:

```php
Workflow::flow('checkout')
    ->entersAt(name: 'checkout.start', path: '/checkout')
    ->step('shipping')  // Auto: /checkout/shipping
    ->step('payment')   // Auto: /checkout/payment
```

**Generated Routes**:
- `checkout.start` → `GET /checkout`
- `checkout.shipping` → `GET /checkout/shipping`
- `checkout.payment` → `GET /checkout/payment`

### 6. State Management with Attributes

**Automatic Persistence**:
```php
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowState;

class ProfileStep extends Component
{
    use InteractsWithWorkflows;

    protected ?string $workflowName = 'onboarding';

    #[WorkflowState]
    public ?string $email = null;

    #[WorkflowState(encrypt: true)]
    public ?string $password = null;

    #[WorkflowState(namespace: 'profile')]
    public ?string $name = null;
}
```

Properties are:
- **Hydrated** on mount
- **Synced** on dehydrate
- **Scoped** to workflow and user

### 7. Lazy Builder Finalization

Builders are stored as "pending" and finalized lazily:
- On explicit `get()` call
- In destructor if `finishesAt()` was called
- Allows flexible definition order

### 8. Convention Over Configuration

**Default Conventions**:
- Route names: `{flow}.{step-key}`
- Route paths: `{entryPath}/{step-key}`
- Session keys: `workflows.{flow}.{userKey}.{suffix}`
- Database table: `workflow_states` (fixed)
- Middleware: `['web']` (configurable)

---

## Common Tasks

### Adding a New Feature

1. **Create feature branch**: `git checkout -b feature/my-feature`
2. **Write failing tests first** (TDD approach)
3. **Implement feature** following coding standards
4. **Ensure tests pass**: `composer test`
5. **Run static analysis**: `./vendor/bin/phpstan analyse`
6. **Format code**: `./vendor/bin/pint`
7. **Update documentation** (README, this file, etc.)
8. **Update CHANGELOG.md**
9. **Commit and push**
10. **Submit PR with description**

### Adding a New Command

1. Create command class in `src/Commands/`
2. Register in `LivewireWorkflowsServiceProvider::bootCommands()`
3. Add stub template in `stubs/` if needed
4. Write tests in `tests/Feature/Commands/`
5. Update README with command documentation

### Adding a New Event

1. Create event class in `src/Events/`
2. Fire event in appropriate location (likely `WorkflowEngine`)
3. Document in README
4. Add test coverage

### Adding a New State Repository

1. Implement `WorkflowStateRepository` contract
2. Add to `StateRepositories/` directory
3. Register binding in service provider
4. Add configuration option
5. Write tests
6. Document in STATE_MANAGEMENT.md

### Modifying the DSL

1. Update builder classes (`FlowBuilder`, `StepBuilder`)
2. Update definition DTOs if needed
3. Ensure backward compatibility or document breaking change
4. Write comprehensive tests
5. Update README examples
6. Update UPGRADE.md if breaking

### Debugging Workflow Issues

**Enable Workflow Debugging**:
```php
// In routes/workflows.php or component
dd(workflow('onboarding')->progressFor(request()));

// Check current state
dd(app(WorkflowStateRepository::class)->getCurrentStep('onboarding', 'user-123'));

// See all state
dd($this->allWorkflowState());
```

**Common Issues**:
- Guard logic inverted (remember: `passes() = true` → skip)
- Missing `workflowName` property in component
- Workflow not registered in `routes/workflows.php`
- Route middleware blocking access

---

## Troubleshooting

### Guards Not Working

**Problem**: Steps always shown/skipped incorrectly

**Solution**:
- Remember positive semantics: `passes() = true` → SKIP
- Check guard class exists and implements `GuardContract`
- Verify guard is properly registered: `->unlessPasses(YourGuard::class)`
- Debug with `dd()` in guard's `passes()` method

### State Not Persisting

**Problem**: Component properties not saving

**Solution**:
- Ensure `workflowName` is set in component
- Verify `#[WorkflowState]` attribute is on public properties
- Check repository configuration in `config/livewire-workflows.php`
- For eloquent: ensure migration ran (`workflow_states` table exists)
- Verify `InteractsWithWorkflows` trait is used

### State Not Hydrating

**Problem**: Properties remain null on mount

**Solution**:
- Confirm `#[WorkflowState]` attribute exists
- Ensure `bootInteractsWithWorkflows()` is called (automatic with trait)
- Check property visibility (public properties work best)
- Verify data exists in repository

### Routes Not Registered

**Problem**: 404 errors on workflow routes

**Solution**:
- Ensure `routes/workflows.php` exists and is loaded
- Check `WorkflowRegistrar` is registering workflow
- Verify `RouteRegistrar` is being called in service provider
- Run `php artisan route:list` to see registered routes
- Check middleware isn't blocking

### Workflow Not Redirecting

**Problem**: Stays on entry route instead of redirecting to first step

**Solution**:
- Verify at least one guard returns `false` (step should be shown)
- Check all guards are returning `true` (all steps complete)
- Ensure `finishRouteName` exists in your routes
- Debug with: `dd(workflow('name')->nextRouteNameFor(request()))`

### Back Navigation Not Working

**Problem**: `$this->back()` does nothing

**Solution**:
- Ensure `historyMode('stack')` is set on workflow
- Verify history is being tracked (check repository)
- Confirm previous step exists in history
- Check you're passing correct flow name and current key

### Encryption Errors

**Problem**: Error when encrypting state

**Solution**:
- Ensure `APP_KEY` is set in `.env`
- Verify Laravel's encryption works: `Crypt::encrypt('test')`
- Check encrypted values aren't being double-encrypted

### Tests Failing After Changes

**Problem**: Tests fail after modifying code

**Solution**:
- Check breaking changes in DTOs (immutable properties)
- Verify test fixtures still valid (guards, components)
- Clear test database: `rm database/database.sqlite` (if using)
- Reset static properties in guards between tests
- Use `beforeEach()` to reset state

### PHPStan Errors

**Problem**: Static analysis failing

**Solution**:
- Add proper type hints to all methods
- Use PHPDoc comments for complex types
- Fix mixed types where possible
- Add to baseline if necessary: `./vendor/bin/phpstan analyse --generate-baseline`

---

## API Reference Quick Guide

### Workflow DSL

```php
Workflow::flow(string $name)
    ->entersAt(name: string, path: string)
    ->finishesAt(string $routeName)
    ->historyMode('none' | 'stack')
    ->step(string $key)
        ->goTo(string $componentClass)
        ->unlessPasses(string $guardClass)
        ->order(int $order);
```

### Livewire Component Methods

```php
use InteractsWithWorkflows;

// Navigation
$this->continue(string $flow): void
$this->back(string $flow, string $currentKey): void
$this->syncState(): void

// State management
$this->getWorkflowState(string $key, mixed $default = null): mixed
$this->putWorkflowState(string $key, mixed $value): void
$this->hasWorkflowState(string $key): bool
$this->forgetWorkflowState(string $key): void
$this->clearWorkflowState(?string $namespace = null): void
$this->allWorkflowState(): array
```

### Helper Functions

```php
// Get workflow instance
workflow(string $flow)->redirect(Request $request, ?string $doneRoute = null)
workflow(string $flow)->nextRouteNameFor(Request $request, ?string $doneRoute = null)
workflow(string $flow)->previousRouteNameFor(string $currentKey, Request $request)
workflow(string $flow)->progressFor(Request $request)
```

### Progress Tracking

```php
$progress = workflow('onboarding')->progressFor($request);
// Returns:
[
    'total' => 3,
    'completed' => 1,
    'remaining' => 2,
    'percentage' => 33.33,
    'current_step' => 'verify-email',
    'next_step' => 'profile',
    'is_complete' => false,
]
```

### Artisan Commands

```bash
# Installation
php artisan workflows:install [--with-db]

# Generation
php artisan make:workflow {name}
php artisan make:workflow-guard {name}
php artisan make:workflow-step {workflow} {step} [--component] [--guard] [--order]

# Validation
php artisan workflows:scan
```

---

## Configuration Reference

### Package Configuration (`config/livewire-workflows.php`)

```php
return [
    // State persistence: 'null', 'session', or 'eloquent'
    'repository' => env('WORKFLOWS_REPOSITORY', 'session'),

    // Middleware applied to all workflow routes
    'middleware' => ['web'],
];
```

### State Repository Options

| Repository | Use Case | Persistence |
|------------|----------|-------------|
| `null` | Stateless workflows | None |
| `session` | Guest users, simple flows | Session lifetime |
| `eloquent` | Authenticated users, production | Database |

---

## Important Architectural Decisions

### Why Positive Guard Semantics?

Natural language with `unlessPasses()`: "Show step UNLESS guard passes"

### Why Immutable Definitions?

Thread safety, predictability, prevents accidental mutations

### Why Lazy Builder Finalization?

Allows flexible definition order, auto-cleanup in destructor

### Why State Scoping?

Data isolation, security, simplicity (no cross-workflow contamination)

### Why Auto-Route Registration?

Zero boilerplate, DRY principle, convention over configuration

### Why Events?

Extensibility without modifying package (analytics, logging, etc.)

---

## Resources

- **README.md**: User-facing documentation
- **STATE_MANAGEMENT.md**: Comprehensive state management guide
- **CONTRIBUTING.md**: Contribution guidelines and standards
- **CHANGELOG.md**: Version history
- **UPGRADE.md**: Breaking changes and upgrade instructions
- **GitHub Issues**: Bug reports and feature requests
- **Tests**: Living documentation of expected behavior

---

## Quick Reference for AI Assistants

### When Making Changes:

1. ✅ **ALWAYS** include `declare(strict_types=1);`
2. ✅ **ALWAYS** write tests for new features
3. ✅ **ALWAYS** update relevant documentation
4. ✅ **ALWAYS** run `composer test` and `phpstan` before committing
5. ✅ **ALWAYS** use readonly properties for immutability
6. ✅ **ALWAYS** validate in constructors/builders
7. ✅ Use constructor property promotion
8. ✅ Use named arguments in DSL
9. ✅ Follow PSR-12 standards
10. ✅ Remember: `passes() = true` means **SKIP** the step

### When Adding Features:

1. Check if it breaks existing functionality
2. Ensure backward compatibility or document breaking change
3. Add to CHANGELOG.md
4. Update UPGRADE.md if breaking
5. Consider event-driven approach for extensibility
6. Prefer composition over inheritance
7. Keep classes focused (SRP)

### When Fixing Bugs:

1. Write failing test first (reproduce bug)
2. Fix the bug
3. Ensure test passes
4. Check for similar issues elsewhere
5. Add regression test
6. Document fix in CHANGELOG.md

---

## Version Information

This CLAUDE.md was created for version: **v1.x** (check CHANGELOG.md for current version)

Last updated: 2025-11-14

---

*This document is maintained for AI assistants working on the Livewire Workflows codebase. Keep it updated as the project evolves.*
