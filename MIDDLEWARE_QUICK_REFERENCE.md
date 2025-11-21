# Middleware Architecture - Quick Reference Guide

## Current State at a Glance

```
Global Middleware: config/livewire-workflows.php
         ↓
Applied uniformly to ALL workflow routes
         ↓
No per-workflow or per-step differentiation
```

## Key Files

| File | Purpose | Middleware Role |
|------|---------|-----------------|
| `config/livewire-workflows.php` | Global config | Defines default middleware |
| `src/LivewireWorkflowsServiceProvider.php` | Service provider | Reads config, triggers route registration |
| `src/Support/RouteRegistrar.php` | Route registration | Applies middleware to routes |
| `src/Support/WorkflowDefinition.php` | Workflow DTO | Contains workflow config (no middleware prop) |
| `src/Support/StepDefinition.php` | Step DTO | Contains step config (no middleware prop) |
| `src/Registrar/FlowBuilder.php` | DSL builder | Builds workflow (no middleware method) |
| `src/Registrar/StepBuilder.php` | DSL builder | Builds step (no middleware method) |

## Configuration Flow

```
app.boot()
  ↓
ServiceProvider::register()
  ├─ mergeConfigFrom('config/livewire-workflows.php')
  └─ Register singletons
  ↓
ServiceProvider::boot()
  ├─ loadWorkflows()  ← Builds WorkflowDefinition objects
  └─ registerRoutes() ← RouteRegistrar applies middleware
```

## Current Middleware Application

```php
// config/livewire-workflows.php
'middleware' => ['web']  // Single global array

// Applied by RouteRegistrar
Route::middleware(['web'])  // Same for ALL routes
    ->get($path, $handler)
    ->name($routeName);
```

## What's Missing for Selective Auth Middleware

### Gap 1: Workflow-Level Middleware
Cannot set different middleware for different workflows:
```php
Workflow::flow('admin-workflow')
    ->middleware(['web', 'auth', 'admin'])  // NOT POSSIBLE
    ->entersAt(...)
```

### Gap 2: Step-Level Middleware  
Cannot set different middleware for different steps:
```php
->step('payment')
    ->middleware(['web', 'auth', 'verified'])  // NOT POSSIBLE
    ->goTo(PaymentStep::class)
```

### Gap 3: Mixed Access Levels
Cannot mix public and authenticated steps in same workflow:
```php
->step('view')          // Public
    ->goTo(PublicView::class)
    // middleware: ['web']
->step('edit')          // Authenticated
    ->goTo(EditView::class)
    // middleware: ['web', 'auth']  -- NOT POSSIBLE
```

## What Needs to Be Added

### 1. Update WorkflowDefinition DTO
```php
class WorkflowDefinition {
    public function __construct(
        // ... existing properties ...
        public readonly ?array $middleware = null,  // NEW
    ) {}
}
```

### 2. Update StepDefinition DTO
```php
class StepDefinition {
    public function __construct(
        // ... existing properties ...
        public readonly ?array $middleware = null,  // NEW
    ) {}
}
```

### 3. Add Methods to FlowBuilder
```php
class FlowBuilder {
    protected ?array $middleware = null;
    
    public function middleware(array $middleware): static {
        $this->middleware = $middleware;
        return $this;
    }
}
```

### 4. Add Methods to StepBuilder
```php
class StepBuilder {
    protected ?array $middleware = null;
    
    public function middleware(array $middleware): static {
        $this->middleware = $middleware;
        return $this;
    }
}
```

### 5. Update RouteRegistrar Logic
```php
public function register(?array $globalMiddleware = null): void {
    $globalMiddleware = $globalMiddleware ?? config('livewire-workflows.middleware', ['web']);

    foreach ($this->workflowRegistrar->all() as $workflow) {
        // Use workflow or global middleware
        $entryMiddleware = $workflow->middleware ?? $globalMiddleware;
        
        Route::middleware($entryMiddleware)
            ->get($workflow->entryPath, ...)
            ->name($workflow->entryRouteName);

        foreach ($workflow->steps as $step) {
            // Use step > workflow > global middleware (precedence)
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

## Usage Example (After Implementation)

```php
Workflow::flow('product-review')
    ->middleware(['web'])  // Workflow-level default
    ->entersAt(name: 'review.start', path: '/product/{product}/review')
    ->finishesAt('dashboard')
    
    ->step('view-details')
        ->goTo(ProductDetailsView::class)
        ->middleware(['web'])  // Public access
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

## Middleware Precedence

```
Step middleware > Workflow middleware > Global middleware
```

If not specified at a level, falls back to next level.

## Implementation Checklist

- [ ] Add `?array $middleware = null` to WorkflowDefinition constructor
- [ ] Add `?array $middleware = null` to StepDefinition constructor
- [ ] Add `middleware()` method to FlowBuilder
- [ ] Add `middleware()` method to StepBuilder
- [ ] Update RouteRegistrar to handle per-route middleware
- [ ] Update FlowBuilder::build() to pass middleware to WorkflowDefinition
- [ ] Update StepBuilder::build() to pass middleware to StepDefinition
- [ ] Add validation for middleware names (optional)
- [ ] Write tests for per-workflow middleware
- [ ] Write tests for per-step middleware
- [ ] Write tests for middleware precedence
- [ ] Update documentation

## Benefits

✓ Fully backward compatible (optional properties)
✓ No breaking changes to existing workflows
✓ Clear middleware precedence
✓ Follows Laravel conventions
✓ Type-safe (readonly properties)
✓ Simple and intuitive DSL

## Key Architectural Principles

1. **Immutability**: DTOs use readonly properties
2. **Fluent Builder**: DSL methods return `static` for chaining
3. **Lazy Finalization**: Builders auto-build in destructor
4. **Separation of Concerns**: Config → Builders → DTOs → Router → Routes
5. **Optional Extensions**: New properties are optional (backward compatible)
