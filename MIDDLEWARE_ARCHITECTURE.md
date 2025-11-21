# Livewire Workflows - Complete Middleware Architecture Analysis

**Date:** 2025-11-21  
**Codebase Location:** `/home/user/livewire-workflows`  
**Current Branch:** claude/selective-auth-middleware-012cNNARJwDfFi5wbMAJTVPE

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Current Architecture Overview](#current-architecture-overview)
3. [File-by-File Analysis](#file-by-file-analysis)
4. [Configuration System](#configuration-system)
5. [Middleware Application Flow](#middleware-application-flow)
6. [DTO Structures & Immutability](#dto-structures--immutability)
7. [Builder Pattern Implementation](#builder-pattern-implementation)
8. [Route Registration & Generation](#route-registration--generation)
9. [Extensibility Points](#extensibility-points)
10. [Architectural Diagrams](#architectural-diagrams)
11. [Code Examples & Use Cases](#code-examples--use-cases)
12. [Implementation Gaps](#implementation-gaps)
13. [Recommendations](#recommendations)

---

## Executive Summary

### Current State

The Livewire Workflows package uses a **global, single-layer middleware architecture** where:
- All workflow routes share identical middleware from `config/livewire-workflows.php`
- Middleware is applied uniformly at HTTP route level, before controller/component execution
- No per-workflow or per-step middleware differentiation is possible
- Default configuration: `'middleware' => ['web']`

### Key Characteristics

✓ **Strengths:**
- Simple, predictable, easy to understand
- Consistent middleware application across all routes
- Configurable globally
- Clean separation of concerns

✗ **Limitations:**
- Cannot apply different auth strategies to different steps
- Cannot mix public and authenticated steps in same workflow
- Cannot conditionally apply middleware based on route parameters
- Not extensible for per-step customization

### Architecture Style

```
Configuration Layer (config/livewire-workflows.php)
         ↓
Service Provider (LivewireWorkflowsServiceProvider)
         ↓
Workflow/Step Builders (DSL - FlowBuilder, StepBuilder)
         ↓
DTOs (Immutable - WorkflowDefinition, StepDefinition)
         ↓
Route Registrar (RouteRegistrar)
         ↓
HTTP Routes (Laravel Route::middleware())
```

---

## Current Architecture Overview

### 1. Middleware Configuration Hierarchy

```
config/livewire-workflows.php
│
├─ 'middleware' => ['web']  ← Global Default
│
└─ Applied by: RouteRegistrar->register()
   └─ All routes use this global middleware
      ├─ Entry routes
      └─ Step routes
```

### 2. Global Configuration File

**File:** `/home/user/livewire-workflows/config/livewire-workflows.php`

```php
return [
    'repository' => env('WORKFLOWS_REPOSITORY', 'session'),
    'middleware' => ['web'],  // <-- SINGLE GLOBAL MIDDLEWARE SETTING
];
```

**Current Properties:**
- `repository`: State persistence driver ('null', 'session', 'eloquent')
- `middleware`: Array of middleware to apply to ALL workflow routes

**Extensibility:**
- Users can override in published config
- Service provider reads this on boot
- No per-workflow or per-step customization

### 3. Bootstrap Flow (Service Provider)

**File:** `/home/user/livewire-workflows/src/LivewireWorkflowsServiceProvider.php`

**Service Registration Phase (`register()` method):**
```php
public function register(): void
{
    $this->mergeConfigFrom(__DIR__.'/../config/livewire-workflows.php', 'livewire-workflows');
    
    // Register core services as singletons
    $this->app->singleton(WorkflowRegistrar::class);
    $this->app->singleton(WorkflowEngine::class);
    $this->app->singleton(RouteRegistrar::class);
    
    // Bind state repository based on config
    $this->app->singleton(WorkflowStateRepository::class, function ($app) {
        return match (config('livewire-workflows.repository')) {
            'eloquent' => $app->make(EloquentWorkflowStateRepository::class),
            'session' => $app->make(SessionWorkflowStateRepository::class),
            default => $app->make(NullStateRepository::class),
        };
    });
}
```

**Bootstrap Phase (`boot()` method):**
```php
public function boot(): void
{
    // 1. Publish config & migrations
    $this->registerPublishing();
    
    // 2. Register artisan commands
    $this->registerCommands();
    
    // 3. Load workflow definitions from routes/workflows.php
    $this->loadWorkflows();        // <-- Builds WorkflowDefinition objects
    
    // 4. Register HTTP routes
    $this->registerRoutes();       // <-- RouteRegistrar applies middleware
}
```

**Key Insight:** Workflow definitions are built BEFORE routes are registered, so middleware cannot be applied during definition phase.

---

## File-by-File Analysis

### 1. Configuration File
**Path:** `config/livewire-workflows.php`

```php
<?php
return [
    'repository' => env('WORKFLOWS_REPOSITORY', 'session'),
    'middleware' => ['web'],
];
```

**Analysis:**
- Minimal configuration
- No nested middleware options
- No per-workflow customization points
- No validation of middleware classes/aliases

---

### 2. WorkflowDefinition (Core DTO)
**Path:** `src/Support/WorkflowDefinition.php`

```php
class WorkflowDefinition
{
    public readonly array $routeParameters;

    public function __construct(
        public readonly string $flow,              // Workflow name
        public readonly string $entryRouteName,    // Entry route name
        public readonly string $entryPath,         // Entry path (may have params)
        public readonly string $finishRoute,       // Completion route name
        public readonly string $historyMode,       // 'none' or 'stack'
        public readonly array $steps,              // StepDefinition[]
    ) {
        // Parses {param} syntax from entryPath
        $this->routeParameters = $this->parseRouteParameters($this->entryPath);
        $this->validate();
    }
}
```

**Readonly Properties (Immutable):**
- `flow`: 'checkout', 'onboarding', etc.
- `entryRouteName`: 'checkout.start', 'onboarding.start'
- `entryPath`: '/checkout', '/user/{user}/onboarding'
- `finishRoute`: 'dashboard', 'completion'
- `historyMode`: Navigation tracking mode
- `steps`: Collection of StepDefinition objects
- `routeParameters`: Auto-extracted from entryPath

**Missing:** No `middleware` property

**Validation Rules:**
- Workflow name required and non-empty
- Entry route name required
- Entry path required
- Finish route required
- History mode must be 'none' or 'stack'
- Must have at least one step
- Step keys must be unique
- Each step's flow must match workflow's flow

**Helper Methods:**
```php
getStep(string $key): ?StepDefinition
getOrderedSteps(): array
getPreviousStep(string $currentKey): ?StepDefinition
getStepRouteName(string $stepKey): string
getStepPath(string $stepKey): string
hasHistory(): bool
hasRouteParameters(): bool
```

---

### 3. StepDefinition (Core DTO)
**Path:** `src/Support/StepDefinition.php`

```php
class StepDefinition
{
    public function __construct(
        public readonly string $key,               // Step identifier
        public readonly string $flow,              // Parent workflow name
        public readonly string|array|null $component = null,  // Component/Controller
        public readonly ?string $guardClass = null,           // Guard class
        public readonly int $order = 0,                       // Execution order
    ) {
        $this->validate();
    }
}
```

**Readonly Properties (Immutable):**
- `key`: 'verify-email', 'shipping', 'payment'
- `flow`: Parent workflow identifier
- `component`: Livewire component class or controller array
- `guardClass`: Guard implementation class
- `order`: Integer for step ordering

**Missing:** No `middleware` property

**Validation Rules:**
- Component must exist and be valid
- Guard class (if present) must exist and implement GuardContract
- Component can be:
  - String: Full class name (Livewire component or invokable controller)
  - Array: [ControllerClass, 'methodName']

**Helper Methods:**
```php
hasGuard(): bool
getGuard(): ?GuardContract
```

---

### 4. FlowBuilder (DSL - Fluent)
**Path:** `src/Registrar/FlowBuilder.php`

```php
class FlowBuilder
{
    protected ?string $entryName = null;
    protected ?string $entryPath = null;
    protected ?string $finishRoute = null;
    protected string $historyMode = 'none';
    protected ?StepBuilder $currentStep = null;
    public array $steps = [];
    protected bool $isBuilt = false;

    public function __construct(
        protected string $flow,
        protected WorkflowRegistrar $registrar
    ) {}

    // Fluent DSL methods:
    public function entersAt(string $name, string $path): static
    public function finishesAt(string $route): static
    public function historyMode(string $mode): static
    public function step(string $key): StepBuilder
    public function build(): WorkflowDefinition
    public function __destruct()  // Auto-builds in destructor
}
```

**DSL Usage:**
```php
Workflow::flow('checkout')                           // FlowBuilder created
    ->entersAt(name: 'checkout.start', path: '/checkout')
    ->finishesAt('dashboard')
    ->historyMode('stack')
    ->step('shipping')                               // StepBuilder created
        ->goTo(ShippingStep::class)
        ->unlessPasses(ShippingGuard::class)
        ->order(10)
    ->step('payment')                                // New StepBuilder
        ->goTo(PaymentStep::class)
        ->unlessPasses(PaymentGuard::class)
        ->order(20);
    // Auto-builds in destructor
```

**Key Features:**
- Lazy builder pattern with destructor auto-build
- Validation on `finishesAt()` call (eager)
- Full validation on `build()` call
- Pending builders tracked by registrar

**Missing Builder Methods:**
- No `middleware()` method
- No extensibility for custom configuration

---

### 5. StepBuilder (DSL - Fluent)
**Path:** `src/Registrar/StepBuilder.php`

```php
class StepBuilder
{
    protected string|array|null $component = null;
    protected ?string $guard = null;
    protected int $order = 0;

    public function __construct(
        protected string $key,
        protected string $flow,
        protected FlowBuilder $flowBuilder
    ) {}

    public function goTo(string|array $component): static
    public function unlessPasses(string $guard): static
    public function order(int $order): static
    public function step(string $key): self          // Proxy to FlowBuilder->step()
    public function build(): StepDefinition
    public function __call(string $method, array $arguments)  // Proxy unknown methods
}
```

**Features:**
- Only 3 direct builder methods
- Proxies unknown method calls to parent FlowBuilder
- Allows DSL chaining back to FlowBuilder

**Missing:**
- No `middleware()` method
- No extensibility hooks

---

### 6. RouteRegistrar (Route Registration)
**Path:** `src/Support/RouteRegistrar.php`

```php
class RouteRegistrar
{
    public function __construct(
        protected WorkflowRegistrar $workflowRegistrar,
    ) {}

    /**
     * Register all workflow routes.
     */
    public function register(?array $middleware = null): void
    {
        // Step 1: Get middleware (parameter or config)
        $middleware = $middleware ?? config('livewire-workflows.middleware', ['web']);

        // Step 2: Iterate all finalized workflows
        foreach ($this->workflowRegistrar->all() as $workflow) {
            
            // Step 3: Register entry route
            Route::middleware($middleware)
                ->get($workflow->entryPath, [WorkflowEntryController::class, '__invoke'])
                ->defaults('flow', $workflow->flow)
                ->name($workflow->entryRouteName);

            // Step 4: Register all step routes
            foreach ($workflow->steps as $step) {
                $routeName = $workflow->getStepRouteName($step->key);
                $routePath = $workflow->getStepPath($step->key);

                Route::middleware($middleware)  // <-- Same global middleware
                    ->get($routePath, $step->component)
                    ->name($routeName);
            }
        }
    }
}
```

**Analysis:**
- Single `$middleware` parameter (optional)
- Applied identically to ALL routes
- Called once in service provider boot
- No per-workflow or per-step differentiation
- Simple, flat application

**Route Generation Logic:**
- Entry route: `GET {entryPath} → entryRouteName`
- Step routes: `GET {entryPath}/{stepKey} → {flow}.{stepKey}`

**Example Generated Routes:**
```
GET /checkout                    → WorkflowEntryController   (checkout.start)
GET /checkout/shipping           → ShippingStep component    (checkout.shipping)
GET /checkout/payment            → PaymentStep component     (checkout.payment)
```

---

### 7. WorkflowRegistrar (Registry/Registry)
**Path:** `src/Registrar/WorkflowRegistrar.php`

```php
class WorkflowRegistrar
{
    protected array $workflows = [];              // Built workflows
    protected array $pendingBuilders = [];        // Pending FlowBuilders

    public function flow(string $name): FlowBuilder
    {
        $builder = new FlowBuilder($name, $this);
        $this->pendingBuilders[$name] = $builder;
        return $builder;
    }

    public function register(WorkflowDefinition $workflow): void
    {
        $this->workflows[$workflow->flow] = $workflow;
        unset($this->pendingBuilders[$workflow->flow]);
    }

    public function get(string $flow): WorkflowDefinition
    {
        $this->finalizePending($flow);
        if (! $this->has($flow)) {
            throw WorkflowNotFoundException::forFlow($flow);
        }
        return $this->workflows[$flow];
    }

    public function has(string $flow): bool
    {
        $this->finalizePending($flow);
        return isset($this->workflows[$flow]);
    }

    public function all(): array
    {
        $this->finalizeAllPending();
        return $this->workflows;
    }
}
```

**Pattern:** Registry with lazy finalization
**Role:** Central collection point for workflow definitions

---

## Configuration System

### Current Configuration

```
config/livewire-workflows.php
├── repository (enum: 'null' | 'session' | 'eloquent')
└── middleware (array: ['web'])
```

### Configuration Loading

**In ServiceProvider::register():**
```php
$this->mergeConfigFrom(__DIR__.'/../config/livewire-workflows.php', 'livewire-workflows');
```

**Publishing Configuration:**
```php
$this->publishes([
    __DIR__.'/../config/livewire-workflows.php' => config_path('livewire-workflows.php'),
], 'livewire-workflows-config');
```

Users can customize by publishing:
```bash
php artisan vendor:publish --tag=livewire-workflows-config
```

Then editing `config/livewire-workflows.php`:
```php
'middleware' => ['web', 'auth'],  // Add auth to all workflows
```

---

## Middleware Application Flow

### Sequence Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│ Application Boot (ServiceProvider)                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1. register() phase                                            │
│     └─ mergeConfigFrom(config/livewire-workflows.php)           │
│        └─ Reads: 'middleware' => ['web']                        │
│                                                                 │
│  2. boot() phase                                                │
│     ├─ loadWorkflows()                                          │
│     │  ├─ require routes/workflows.php                          │
│     │  └─ Build WorkflowDefinition objects                      │
│     │     └─ Store in WorkflowRegistrar::$workflows             │
│     │                                                            │
│     └─ registerRoutes()                                          │
│        └─ app(RouteRegistrar::class)->register()                │
│           ├─ Get middleware from config: ['web']                │
│           ├─ For each WorkflowDefinition:                       │
│           │  ├─ Register entry route with ['web']               │
│           │  └─ Register all steps with ['web']                 │
│           └─ Laravel Route registry finalized                   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
                             │
                             ▼
        ┌────────────────────────────────────────────┐
        │  HTTP Request to Workflow Route            │
        ├────────────────────────────────────────────┤
        │                                            │
        │  GET /checkout                             │
        │       │                                    │
        │       ▼                                    │
        │  Middleware Stack: ['web']                 │
        │       │                                    │
        │       ├─ SessionMiddleware                 │
        │       ├─ EncryptCookies                    │
        │       └─ ... other web middleware          │
        │       │                                    │
        │       ▼                                    │
        │  WorkflowEntryController::__invoke()       │
        │       │                                    │
        │       ├─ Get flow from route defaults      │
        │       ├─ WorkflowResolver->redirect()      │
        │       ├─ Evaluate guards                   │
        │       └─ Redirect to next step             │
        │                                            │
        └────────────────────────────────────────────┘
```

### Middleware Application Points

1. **Configuration Time:**
   - Read from `config/livewire-workflows.php`
   - Single array: `['web']`

2. **Registration Time:**
   - RouteRegistrar calls `Route::middleware(['web'])`
   - Applied to ALL workflow routes uniformly

3. **Request Time:**
   - Laravel Pipeline executes middleware stack
   - Runs BEFORE controller/component code

### Current Middleware Stack Example

For any workflow route:
```
Request
  ↓
StartSession (from 'web' middleware)
  ↓
EncryptCookies (from 'web' middleware)
  ↓
... other web middleware ...
  ↓
Controller/Component
  ↓
Response
```

**Critical Point:** All routes execute identical middleware. No differentiation possible.

---

## DTO Structures & Immutability

### Immutability Design

```php
// WorkflowDefinition - ALL properties readonly
class WorkflowDefinition
{
    public function __construct(
        public readonly string $flow,
        public readonly string $entryRouteName,
        public readonly string $entryPath,
        public readonly string $finishRoute,
        public readonly string $historyMode,
        public readonly array $steps,
    ) {}
}

// StepDefinition - ALL properties readonly
class StepDefinition
{
    public function __construct(
        public readonly string $key,
        public readonly string $flow,
        public readonly string|array|null $component = null,
        public readonly ?string $guardClass = null,
        public readonly int $order = 0,
    ) {}
}
```

### Design Rationale

**Why Immutable?**
1. Thread safety for concurrent requests
2. Predictability (no accidental mutations)
3. Cache-friendly (can be reused across requests)
4. Validation at construction time
5. Clear intent in code

**Impact on Middleware Extension:**
- Cannot add `public middleware: array` as property without breaking immutability
- New properties must be `readonly` and required/optional with defaults
- Addition is backward compatible (new optional property)

### Validation Pattern

```php
public function __construct(
    public readonly string $flow,
    // ... other properties ...
) {
    $this->validate();  // Called in constructor
}

protected function validate(): void
{
    // Throws InvalidWorkflowConfigurationException on invalid data
    if (empty($this->flow)) {
        throw new InvalidWorkflowConfigurationException('...');
    }
}
```

---

## Builder Pattern Implementation

### Builder Lifecycle

```
1. FlowBuilder Created
   Workflow::flow('checkout')  ← FlowBuilder instantiated
   
   2. Configuration Methods Called
   ->entersAt(name: '...', path: '...')  ← Set entry
   ->finishesAt('dashboard')             ← Set finish + validate entry
   ->historyMode('stack')                ← Set history mode
   
   3. Step Building
   ->step('shipping')                    ← StepBuilder created
      ->goTo(ShippingStep::class)        ← Set component
      ->unlessPasses(Guard::class)       ← Set guard
      ->order(10)                        ← Set order
   ->step('payment')                     ← New StepBuilder
      ->goTo(PaymentStep::class)
      ->order(20)
   
   4. Auto-Build in Destructor
   ~FlowBuilder()  ← Destructor calls build()
   └─ WorkflowDefinition created & registered
```

### Key Pattern: Lazy Finalization

```php
public function __destruct()
{
    // Only build if finishesAt() was called (indicating intentional definition)
    if (! $this->isBuilt && $this->finishRoute !== null) {
        try {
            $this->build();
        } catch (\Throwable $e) {
            // Suppress errors in destructor
        }
    }
}
```

### Proxy Pattern (StepBuilder → FlowBuilder)

```php
public function __call(string $method, array $arguments)
{
    // Unknown methods on StepBuilder proxy to FlowBuilder
    if (method_exists($this->flowBuilder, $method)) {
        return $this->flowBuilder->$method(...$arguments);
    }
    throw new \BadMethodCallException("Method {$method} does not exist...");
}
```

**Example:**
```php
->step('verify')
   ->goTo(VerifyStep::class)
   ->order(10)
   ->step('profile')          // Unknown method → proxies to FlowBuilder
      ->goTo(ProfileStep::class)
```

---

## Route Registration & Generation

### Route Generation Algorithm

**Entry Route:**
```
{entryPath} → GET {entryPath}
             ↓
    Route name: {entryRouteName}
    Handler: WorkflowEntryController::__invoke()
    Middleware: ['web'] (from config)
```

**Step Routes:**
```
For each step in workflow:
  {entryPath}/{stepKey} → GET {entryPath}/{stepKey}
                          ↓
                Step route name: {flow}.{stepKey}
                Handler: {step->component}
                Middleware: ['web'] (from config)
```

### Example: Checkout Workflow

```php
Workflow::flow('checkout')
    ->entersAt(name: 'checkout.start', path: '/checkout')
    ->finishesAt('dashboard')
    ->step('shipping')
        ->goTo(ShippingStep::class)
        ->order(10)
    ->step('payment')
        ->goTo(PaymentStep::class)
        ->order(20);
```

**Generated Routes:**
```
Route Name              URI                 Handler                  Middleware
─────────────────────────────────────────────────────────────────────────────
checkout.start          /checkout           WorkflowEntryController  ['web']
checkout.shipping       /checkout/shipping  ShippingStep component   ['web']
checkout.payment        /checkout/payment   PaymentStep component    ['web']
```

### Dynamic Routes with Parameters

**Entry Path with Parameters:**
```php
->entersAt(name: 'checkout.start', path: '/user/{user}/product/{product}')
```

**Parsed Route Parameters:**
- RouteParameters extracted: `['user', 'product']`
- Regex pattern: `/\{([^}:]+)/` extracts parameter names

**Generated Dynamic Routes:**
```
checkout.start          /user/{user}/product/{product}
checkout.shipping       /user/{user}/product/{product}/shipping
checkout.payment        /user/{user}/product/{product}/payment
```

**Parameter Preservation:**
- Parameters stored in workflow state
- Passed through navigation calls
- Preserved in URL generation

---

## Extensibility Points

### 1. Service Provider Extension

**Current:**
```php
// In AppServiceProvider
app(RouteRegistrar::class)->register(middleware: ['web', 'auth']);
```

**Limitation:** Single global override only, no per-workflow/step control

### 2. Configuration Extension

**Current:**
```bash
php artisan vendor:publish --tag=livewire-workflows-config
```

Edit `config/livewire-workflows.php`:
```php
'middleware' => ['web', 'auth', 'verified'],
```

**Limitation:** Global only, cannot differentiate

### 3. Guard System (Indirect)

**Current:**
Guards control execution flow, not auth level:
```php
class AdminOnlyGuard implements GuardContract
{
    public function passes(Request $request): bool
    {
        return $request->user()?->is_admin ?? false;
    }
    
    // ... other methods ...
}
```

**Limitation:** Does not apply middleware, only redirects/skips steps

### 4. Controller/Component-Level Middleware

**Possible Workaround:**
```php
class PaymentStep extends Component
{
    use InteractsWithWorkflows;
    
    protected $middleware = ['auth', 'verified'];  // Component property
    
    // But this is ignored by Livewire in workflow context
}
```

**Limitation:** Livewire components don't use this in workflow routes

---

## Architectural Diagrams

### Complete System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Livewire Workflows                       │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌────────────────────────────────────────────────────┐   │
│  │  Configuration Layer                               │   │
│  │  ┌──────────────────────────────────────────────┐  │   │
│  │  │  config/livewire-workflows.php               │  │   │
│  │  │  {                                           │  │   │
│  │  │    'repository' => 'session',                │  │   │
│  │  │    'middleware' => ['web']    ◄── GLOBAL    │  │   │
│  │  │  }                                           │  │   │
│  │  └──────────────────────────────────────────────┘  │   │
│  │                                                     │   │
│  └────────────────────┬────────────────────────────────┘   │
│                       │                                    │
│                       ▼                                    │
│  ┌────────────────────────────────────────────────────┐   │
│  │  Service Provider (Boot Phase)                      │   │
│  │  ┌──────────────────────────────────────────────┐  │   │
│  │  │  1. loadWorkflows()                          │  │   │
│  │  │     require routes/workflows.php             │  │   │
│  │  │     └─ DSL builds WorkflowDefinitions        │  │   │
│  │  │                                              │  │   │
│  │  │  2. registerRoutes()                         │  │   │
│  │  │     RouteRegistrar->register()               │  │   │
│  │  │     └─ Applies middleware to routes          │  │   │
│  │  └──────────────────────────────────────────────┘  │   │
│  │                                                     │   │
│  └────────────────────┬────────────────────────────────┘   │
│                       │                                    │
│        ┌──────────────┴──────────────┐                    │
│        ▼                             ▼                    │
│  ┌──────────────┐          ┌──────────────────────┐      │
│  │ FlowBuilder  │          │  StepBuilder         │      │
│  ├──────────────┤          ├──────────────────────┤      │
│  │entersAt()    │          │goTo()                │      │
│  │finishesAt()  │──┐  ┌────│unlessPasses()        │      │
│  │historyMode() │  │  │    │order()               │      │
│  │step()        │  │  │    │step()  (proxy)       │      │
│  │build()       │  │  │    │build()               │      │
│  └──────┬───────┘  └──┼────┘                      │      │
│         │             │                           │      │
│         └─────────┬───┘                           │      │
│                   ▼                               │      │
│  ┌────────────────────────┐                      │      │
│  │ WorkflowDefinition DTO │                      │      │
│  │ (Immutable)            │                      │      │
│  ├────────────────────────┤                      │      │
│  │ - flow                 │                      │      │
│  │ - entryRouteName       │                      │      │
│  │ - entryPath            │                      │      │
│  │ - finishRoute          │                      │      │
│  │ - historyMode          │                      │      │
│  │ - steps[] ─────────────┼──────────────┐       │      │
│  │ - routeParameters      │              │       │      │
│  └────────────┬───────────┘              │       │      │
│               │                          │       │      │
│               │              ┌───────────▼────┐  │      │
│               │              │ StepDefinition │  │      │
│               │              │ (Immutable)    │  │      │
│               │              ├────────────────┤  │      │
│               │              │ - key          │  │      │
│               │              │ - flow         │  │      │
│               │              │ - component    │  │      │
│               │              │ - guardClass   │  │      │
│               │              │ - order        │  │      │
│               │              └────────────────┘  │      │
│               │                                  │      │
│               └──────────────┬───────────────────┘      │
│                              ▼                         │
│  ┌────────────────────────────────────────────────┐   │
│  │  WorkflowRegistrar (Registry)                  │   │
│  ├────────────────────────────────────────────────┤   │
│  │ - $workflows (finalized definitions)           │   │
│  │ - $pendingBuilders (unfinalized builders)      │   │
│  └────────────────────┬───────────────────────────┘   │
│                       ▼                               │
│  ┌────────────────────────────────────────────────┐   │
│  │  RouteRegistrar (Route Registration)           │   │
│  │  ┌──────────────────────────────────────────┐  │   │
│  │  │ register(?$middleware = null)             │  │   │
│  │  │ 1. Get middleware from param or config    │  │   │
│  │  │ 2. For each workflow:                     │  │   │
│  │  │    ├─ Route::middleware($middleware)      │  │   │
│  │  │    │   ->get(entryPath, controller)       │  │   │
│  │  │    │   ->name(entryRouteName)             │  │   │
│  │  │    │                                      │  │   │
│  │  │    └─ For each step:                      │  │   │
│  │  │        Route::middleware($middleware)     │  │   │
│  │  │        ->get(stepPath, component)         │  │   │
│  │  │        ->name(routeName)                  │  │   │
│  │  └──────────────────────────────────────────┘  │   │
│  │                                                 │   │
│  └────────────────────┬────────────────────────────┘   │
│                       ▼                               │
│  ┌────────────────────────────────────────────────┐   │
│  │  Laravel HTTP Routes                           │   │
│  │  ┌──────────────────────────────────────────┐  │   │
│  │  │ GET /checkout [middleware: web]          │  │   │
│  │  │ GET /checkout/shipping [middleware: web] │  │   │
│  │  │ GET /checkout/payment [middleware: web]  │  │   │
│  │  │                                          │  │   │
│  │  │ ◄── ALL ROUTES IDENTICAL MIDDLEWARE     │  │   │
│  │  └──────────────────────────────────────────┘  │   │
│  │                                                 │   │
│  └────────────────────────────────────────────────┘   │
│                                                       │
└─────────────────────────────────────────────────────────┘
```

### Request-Response Flow

```
Browser Request: GET /checkout
        │
        ▼
Route Matching
        │
        ├─ Route: checkout.start
        ├─ Middleware: ['web']
        └─ Handler: WorkflowEntryController::__invoke
        │
        ▼
Middleware Pipeline (Laravel Pipeline)
        │
        ├─ SessionMiddleware
        ├─ EncryptCookiesMiddleware
        ├─ ... other web middleware ...
        │
        ▼
WorkflowEntryController::__invoke()
        │
        ├─ Extract flow name from route defaults
        ├─ Get WorkflowResolver
        └─ Call resolver->redirect()
           │
           ├─ Get WorkflowDefinition('checkout')
           ├─ Evaluate guards in order
           │  ├─ Guard 1: passes() ?
           │  ├─ Guard 2: passes() ?
           │  └─ ... continue until guard fails
           ├─ Determine next step or finish
           │
           ▼
        Redirect Response
        │
        ├─ If step unmet: redirect to step route
        │  └─ HTTP 302 to /checkout/shipping
        │
        ├─ If all complete: redirect to finish route
        │  └─ HTTP 302 to /dashboard
        │
        ▼
Browser Follows Redirect (new request)
```

### Middleware Stack Visualization

```
REQUEST
   │
   ├─────────────────────────────────────────┐
   │  Laravel Middleware Stack (web group)    │
   │                                          │
   ├─ StartSessionMiddleware                  │
   │  └─ Read session from cookie            │
   │                                          │
   ├─ EncryptCookiesMiddleware                │
   │  └─ Decrypt incoming cookies            │
   │                                          │
   ├─ VerifyCsrfToken (web group)             │
   │  └─ Check CSRF token for POST/PUT/DELETE│
   │                                          │
   ├─ ... other middleware ...                │
   │                                          │
   └──────────────────────────────────────────┘
   │
   ▼
Controller/Component Handler
   │
   ├─ WorkflowEntryController::__invoke()
   │  or
   │  Livewire Component
   │
   ▼
Response
   │
   └─ Middleware "unwinding" (reverse order)
      └─ EncryptCookiesMiddleware again
      └─ Response sent
```

---

## Code Examples & Use Cases

### Example 1: Simple Workflow Definition

```php
// routes/workflows.php
use Pixelworxio\LivewireWorkflows\Facades\Workflow;

Workflow::flow('onboarding')
    ->entersAt(name: 'onboarding.start', path: '/onboarding')
    ->finishesAt('dashboard')
    ->historyMode('stack')
    ->step('verify-email')
        ->goTo(VerifyEmail::class)
        ->unlessPasses(EmailVerifiedGuard::class)
        ->order(10)
    ->step('profile')
        ->goTo(ProfileSetup::class)
        ->unlessPasses(ProfileCompleteGuard::class)
        ->order(20);
```

**Generated Routes (all with middleware: ['web']):**
```
GET /onboarding              → onboarding.start
GET /onboarding/verify-email → onboarding.verify-email
GET /onboarding/profile      → onboarding.profile
```

### Example 2: Workflow with Dynamic Parameters

```php
Workflow::flow('order-checkout')
    ->entersAt(
        name: 'checkout.start',
        path: '/user/{user:id}/order/{order:id}'
    )
    ->finishesAt('dashboard')
    ->step('address')
        ->goTo(AddressStep::class)
        ->unlessPasses(AddressCompleteGuard::class)
        ->order(10)
    ->step('shipping')
        ->goTo(ShippingStep::class)
        ->unlessPasses(ShippingSelectedGuard::class)
        ->order(20)
    ->step('payment')
        ->goTo(PaymentStep::class)
        ->unlessPasses(PaymentCompleteGuard::class)
        ->order(30);
```

**Generated Routes (all with middleware: ['web']):**
```
GET /user/{user:id}/order/{order:id}                    → checkout.start
GET /user/{user:id}/order/{order:id}/address            → checkout.address
GET /user/{user:id}/order/{order:id}/shipping           → checkout.shipping
GET /user/{user:id}/order/{order:id}/payment            → checkout.payment
```

### Example 3: Component Using Workflow State

```php
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowName;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowState;
use Pixelworxio\LivewireWorkflows\Livewire\Concerns\InteractsWithWorkflows;
use Livewire\Component;

#[WorkflowName('onboarding')]
class ProfileSetup extends Component
{
    use InteractsWithWorkflows;

    #[WorkflowState]
    public ?string $name = null;

    #[WorkflowState]
    public ?string $email = null;

    #[WorkflowState(encrypt: true)]
    public ?string $password = null;

    public function save()
    {
        // State auto-persists via WorkflowState attribute
        $this->putWorkflowState('name', $this->name);
        $this->putWorkflowState('email', $this->email);

        // Continue to next step
        $this->continue('onboarding');
    }

    public function render()
    {
        return view('livewire.profile-setup');
    }
}
```

---

## Implementation Gaps

### Gap 1: Per-Workflow Middleware

**Current Limitation:**
```php
'middleware' => ['web']  // Global only
```

**Desired Capability:**
```php
Workflow::flow('admin-workflow')
    ->middleware(['web', 'auth', 'admin'])  // ◄── Not possible
    ->entersAt(...)
```

### Gap 2: Per-Step Middleware

**Current Limitation:**
```php
->step('payment')
    ->goTo(PaymentStep::class)
    // No middleware option
    ->order(30);
```

**Desired Capability:**
```php
->step('payment')
    ->goTo(PaymentStep::class)
    ->middleware(['web', 'auth', 'verified'])  // ◄── Not possible
    ->order(30);
```

### Gap 3: Mixed Access Levels

**Current Limitation:**
Cannot have first N steps public, then require auth for remaining steps.

**Desired Capability:**
```php
Workflow::flow('product-review')
    ->entersAt(name: 'review.start', path: '/product/{product}/review')
    ->finishesAt('review.complete')
    ->step('view-details')
        ->goTo(ProductDetailsView::class)
        ->middleware(['web'])  // Public
        ->order(10)
    ->step('write-review')
        ->goTo(WriteReview::class)
        ->middleware(['web', 'auth'])  // Requires auth
        ->order(20)
    ->step('submit')
        ->goTo(SubmitReview::class)
        ->middleware(['web', 'auth', 'verified'])  // Requires auth + verified
        ->order(30);
```

### Gap 4: Step-Level Auth Attributes

**Current Limitation:**
No way to declare auth requirements on components.

**Desired Capability:**
```php
use Pixelworxio\LivewireWorkflows\Attributes\StepMiddleware;

#[StepMiddleware(['auth', 'verified'])]
class PaymentStep extends Component
{
    use InteractsWithWorkflows;
    // ...
}
```

### Gap 5: Conditional Middleware

**Current Limitation:**
Cannot apply middleware conditionally based on request state/parameters.

**Desired Capability:**
```php
->step('review')
    ->goTo(OrderReview::class)
    ->middleware(function(Request $request) {
        // Check if user is order owner
        return $request->user()->can('view-order', $request->order);
    })
    ->order(10);
```

---

## Recommendations

### For Selective Auth Middleware Implementation

#### 1. Extend WorkflowDefinition

Add optional middleware property (backward compatible):
```php
public function __construct(
    public readonly string $flow,
    public readonly string $entryRouteName,
    public readonly string $entryPath,
    public readonly string $finishRoute,
    public readonly string $historyMode,
    public readonly array $steps,
    public readonly ?array $middleware = null,  // NEW: Optional workflow-level
) {
    // ...
}
```

#### 2. Extend StepDefinition

Add optional middleware property:
```php
public function __construct(
    public readonly string $key,
    public readonly string $flow,
    public readonly string|array|null $component = null,
    public readonly ?string $guardClass = null,
    public readonly int $order = 0,
    public readonly ?array $middleware = null,  // NEW: Optional step-level
) {
    // ...
}
```

#### 3. Extend FlowBuilder

Add middleware() method:
```php
protected ?array $middleware = null;

public function middleware(array $middleware): static
{
    $this->middleware = $middleware;
    return $this;
}
```

#### 4. Extend StepBuilder

Add middleware() method:
```php
protected ?array $middleware = null;

public function middleware(array $middleware): static
{
    $this->middleware = $middleware;
    return $this;
}
```

#### 5. Modify RouteRegistrar

Implement middleware precedence:
```php
public function register(?array $globalMiddleware = null): void
{
    $globalMiddleware = $globalMiddleware ?? config('livewire-workflows.middleware', ['web']);

    foreach ($this->workflowRegistrar->all() as $workflow) {
        // Entry route uses workflow or global middleware
        $entryMiddleware = $workflow->middleware ?? $globalMiddleware;
        
        Route::middleware($entryMiddleware)
            ->get($workflow->entryPath, ...)
            ->name($workflow->entryRouteName);

        // Step routes use step > workflow > global middleware
        foreach ($workflow->steps as $step) {
            $stepMiddleware = $step->middleware 
                ?? $workflow->middleware 
                ?? $globalMiddleware;
            
            Route::middleware($stepMiddleware)
                ->get($workflow->getStepPath($step->key), ...)
                ->name($workflow->getStepRouteName($step->key));
        }
    }
}
```

#### 6. Update DSL Documentation

```php
// Example usage
Workflow::flow('checkout')
    ->middleware(['web'])  // Workflow-level (optional, defaults to config)
    ->entersAt(name: 'checkout.start', path: '/checkout')
    ->finishesAt('dashboard')
    ->step('shipping')
        ->goTo(ShippingStep::class)
        ->middleware(['web'])  // Step-level (optional, inherits from workflow)
        ->order(10)
    ->step('payment')
        ->goTo(PaymentStep::class)
        ->middleware(['web', 'auth'])  // Overrides workflow middleware
        ->order(20);
```

### Implementation Benefits

✓ **Backward Compatible**
- New properties are optional
- Existing workflows continue to work

✓ **Flexible**
- Can be combined: global + workflow + step
- Clear precedence: step > workflow > global

✓ **Follows Laravel Conventions**
- Route middleware application pattern
- Familiar to Laravel developers

✓ **Minimal Code Changes**
- Additive to existing architecture
- No breaking changes to DTOs
- Builder pattern already in place

✓ **Type-Safe**
- Maintains `readonly` properties
- Validation in constructors

---

## Summary

The current Livewire Workflows middleware system is **simple and global-focused**, applying identical middleware to all workflow routes. This is suitable for basic scenarios but lacks the flexibility needed for:

1. Mixed authentication levels (public + authenticated steps)
2. Role-based access (some steps require admin, others are public)
3. Complex authorization rules per step
4. Selective guest access to specific steps

The architecture is well-designed for extension, with clear separation of concerns and immutable DTOs. Adding selective per-step middleware requires:

1. Small additions to `WorkflowDefinition` and `StepDefinition` (optional properties)
2. Simple builder methods in `FlowBuilder` and `StepBuilder`
3. Enhanced logic in `RouteRegistrar` to respect step-level middleware precedence
4. Documentation of new DSL methods

The implementation is straightforward, backward compatible, and follows established Laravel and package conventions.

