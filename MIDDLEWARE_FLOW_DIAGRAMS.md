# Middleware Architecture - Visual Flow Diagrams

## 1. Current Middleware Application Flow

```
┌─────────────────────────────────────────────────────────────────┐
│ Application Bootstrap                                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ServiceProvider::register()                                    │
│  └─ mergeConfigFrom('config/livewire-workflows.php')            │
│     └─ 'middleware' => ['web']  (read into config)              │
│                                                                 │
│  ServiceProvider::boot()                                        │
│  ├─ loadWorkflows()                                             │
│  │  ├─ require routes/workflows.php                             │
│  │  ├─ Workflow::flow('checkout')...->build()                   │
│  │  └─ WorkflowDefinition objects created                       │
│  │                                                              │
│  └─ registerRoutes()                                            │
│     └─ RouteRegistrar->register()                               │
│        ├─ $middleware = config('livewire-workflows.middleware') │
│        ├─ For each WorkflowDefinition:                          │
│        │  ├─ Route::middleware(['web'])                         │
│        │  │  ->get(entryPath, WorkflowEntryController)          │
│        │  │  ->name(entryRouteName)                             │
│        │  │                                                     │
│        │  └─ For each Step:                                     │
│        │     └─ Route::middleware(['web'])                      │
│        │        ->get(stepPath, stepComponent)                  │
│        │        ->name(stepRouteName)                           │
│        │                                                        │
│        └─ All routes registered with identical middleware       │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

## 2. Request Handling Flow (Current)

```
HTTP Request: GET /checkout/shipping
        │
        ▼
┌───────────────────────────────┐
│ Route Matching                │
│ • Match route pattern         │
│ • Load middleware for route   │
│ • Find handler                │
└──────────────┬────────────────┘
               │
               ▼
┌───────────────────────────────────────────┐
│ Middleware Pipeline: ['web']              │
├───────────────────────────────────────────┤
│                                           │
│  StartSessionMiddleware                   │
│  ├─ Load session from cookie              │
│  └─ Make available via request->session() │
│                                           │
│  EncryptCookiesMiddleware                 │
│  ├─ Decrypt cookies from request          │
│  └─ Queue cookies for response            │
│                                           │
│  VerifyCsrfTokenMiddleware                │
│  └─ Validate CSRF token (if needed)       │
│                                           │
│  ... other middleware ...                 │
│                                           │
└──────────────┬───────────────────────────┘
               │
               ▼
┌───────────────────────────────┐
│ Handler Execution             │
│ • ShippingStep (Livewire)      │
│ • Component renders            │
│ • User interacts               │
│ • Calls $this->continue()      │
│   └─ Navigates to next step    │
└──────────────┬────────────────┘
               │
               ▼
┌───────────────────────────────┐
│ Response Generation           │
│ • Livewire response            │
│ • Or redirect response         │
└──────────────┬────────────────┘
               │
               ▼
┌───────────────────────────────┐
│ Middleware Response Phase     │
│ (Reverse order)               │
│                               │
│  EncryptCookies               │
│  ├─ Encrypt cookies           │
│  └─ Add to response           │
│                               │
│  ... other middleware ...     │
│                               │
└──────────────┬────────────────┘
               │
               ▼
   HTTP Response to Browser
```

## 3. DSL Builder Flow (Current)

```
Code in routes/workflows.php:

Workflow::flow('checkout')
  │
  ├─ WorkflowRegistrar->flow('checkout')
  │  └─ Returns FlowBuilder
  │
  ├─ ->entersAt(name: '...', path: '...')
  │  ├─ Sets entryName
  │  ├─ Sets entryPath
  │  └─ Returns this (FlowBuilder)
  │
  ├─ ->finishesAt('dashboard')
  │  ├─ Sets finishRoute
  │  ├─ Validates entryName exists
  │  └─ Returns this (FlowBuilder)
  │
  ├─ ->historyMode('stack')
  │  ├─ Sets historyMode
  │  └─ Returns this (FlowBuilder)
  │
  ├─ ->step('shipping')
  │  ├─ Creates StepBuilder
  │  ├─ Stores previous step in FlowBuilder->steps[]
  │  └─ Returns StepBuilder
  │
  │   ├─ ->goTo(ShippingStep::class)
  │   │  ├─ Sets component
  │   │  └─ Returns this (StepBuilder)
  │   │
  │   ├─ ->unlessPasses(ShippingGuard::class)
  │   │  ├─ Sets guardClass
  │   │  └─ Returns this (StepBuilder)
  │   │
  │   └─ ->order(10)
  │      ├─ Sets order
  │      └─ Returns this (StepBuilder)
  │
  └─ ~FlowBuilder() [Destructor]
     └─ if (finishRoute !== null && !isBuilt)
        ├─ Calls build()
        ├─ Creates WorkflowDefinition
        ├─ Registers with WorkflowRegistrar
        └─ Sets isBuilt = true
```

## 4. Route Registration Process

```
Before registerRoutes():
  WorkflowRegistrar contains:
  ├─ $workflows[] = [
  │  └─ WorkflowDefinition {
  │     • flow: 'checkout'
  │     • entryRouteName: 'checkout.start'
  │     • entryPath: '/checkout'
  │     • steps[]: [
  │        ├─ StepDefinition (shipping)
  │        ├─ StepDefinition (payment)
  │        └─ ...
  │     ]
  │  }
  │
  └─ $pendingBuilders[] = []

After registerRoutes():
  Laravel Route Registry contains:
  ├─ GET /checkout
  │  ├─ Name: checkout.start
  │  ├─ Handler: WorkflowEntryController
  │  └─ Middleware: ['web']
  │
  ├─ GET /checkout/shipping
  │  ├─ Name: checkout.shipping
  │  ├─ Handler: ShippingStep component
  │  └─ Middleware: ['web']  ◄── SAME
  │
  ├─ GET /checkout/payment
  │  ├─ Name: checkout.payment
  │  ├─ Handler: PaymentStep component
  │  └─ Middleware: ['web']  ◄── SAME
  │
  └─ ... more routes ...
```

## 5. Middleware Application Points

```
                        ╔═══════════════════════════════════════╗
                        ║   THREE PLACES MIDDLEWARE MATTERS      ║
                        ╚═══════════════════════════════════════╝
                                      │
                ┌─────────────────────┼─────────────────────┐
                │                     │                     │
                ▼                     ▼                     ▼
         ┌────────────────┐   ┌────────────────┐  ┌────────────────┐
         │ CONFIGURATION  │   │  REGISTRATION  │  │    REQUEST     │
         │     TIME       │   │      TIME      │  │      TIME      │
         └────────────────┘   └────────────────┘  └────────────────┘
         • Read from config  • Call Route::      • Middleware
         • Single array        middleware()       pipeline runs
         • No validation     • Applied to all    • Executes handlers
                              routes uniformly   • Not differentiated

         WHERE?                WHERE?               WHERE?
         config/              src/Support/        Laravel HTTP
         livewire-            RouteRegistrar      Pipeline
         workflows.php        .php
```

## 6. Current Middleware Hierarchy (Flat)

```
                    ┌──────────────────────────┐
                    │  Global Middleware Set   │
                    │   from config file       │
                    │                          │
                    │  ['web']                 │
                    └──────────────┬───────────┘
                                   │
                    ┌──────────────┴────────────────┐
                    │                               │
                    ▼                               ▼
            ┌──────────────┐              ┌──────────────┐
            │ All Entries  │              │  All Steps   │
            │              │              │              │
            │ Middleware:  │              │ Middleware:  │
            │ ['web']      │              │ ['web']      │
            │              │              │              │
            │ GET /        │              │ GET /shipping│
            │ GET /payment │              │ GET /confirm │
            │ GET /review  │              │ ...          │
            └──────────────┘              └──────────────┘

              ◄── NO DIFFERENTIATION ──►

            ALL ROUTES HAVE SAME MIDDLEWARE
```

## 7. Proposed Middleware Hierarchy (Multi-Level)

```
                    ┌──────────────────────────┐
                    │  Global Middleware Set   │
                    │   from config file       │
                    │                          │
                    │  ['web']  (fallback)     │
                    └──────────────┬───────────┘
                                   │
                    ┌──────────────┴────────────────┐
                    │                               │
                    ▼                               ▼
        ┌──────────────────────┐      ┌──────────────────────┐
        │ Workflow Middleware  │      │ Workflow Middleware  │
        │                      │      │                      │
        │ ['web', 'auth']      │      │ (not specified)      │
        │ (overrides global)   │      │ (uses global)        │
        └──────────────┬───────┘      └──────────┬───────────┘
                       │                         │
        ┌──────────────┴──────────┐   ┌──────────┴──────────┐
        │                         │   │                     │
        ▼                         ▼   ▼                     ▼
  ┌───────────────┐       ┌───────────────┐       ┌──────────────┐
  │ Step 1        │       │ Step 2        │       │ Step 3       │
  │ Middleware:   │       │ Middleware:   │       │ Middleware:  │
  │ (not spec.)   │       │ ['web', 'auth'│       │ (not spec.)  │
  │ Uses: ['web'] │       │ 'verified']   │       │ Uses:        │
  │               │       │ (overrides)   │       │ ['web', 'auth']
  │ GET /view     │       │               │       │               │
  │ Public!       │       │ GET /payment  │       │ GET /confirm │
  │               │       │ Auth+verified │       │ Auth+verified │
  └───────────────┘       └───────────────┘       └──────────────┘

            FALLBACK → OVERRIDE → FALLBACK → OVERRIDE
            
            Step > Workflow > Global
```

## 8. Proposed Code Structure Changes

```
BEFORE (Current):
┌────────────────────────────┐
│ WorkflowDefinition         │
├────────────────────────────┤
│ public readonly flow       │
│ public readonly entryPath  │
│ public readonly steps[]    │
│ ... other properties ...   │
│                            │
│ (NO MIDDLEWARE)            │
└────────────────────────────┘

AFTER (Proposed):
┌────────────────────────────┐
│ WorkflowDefinition         │
├────────────────────────────┤
│ public readonly flow       │
│ public readonly entryPath  │
│ public readonly steps[]    │
│ public readonly middleware │  ◄── NEW
│ ... other properties ...   │
└────────────────────────────┘

BEFORE (Current):
┌────────────────────────────┐
│ StepDefinition             │
├────────────────────────────┤
│ public readonly key        │
│ public readonly component  │
│ public readonly guardClass │
│ public readonly order      │
│                            │
│ (NO MIDDLEWARE)            │
└────────────────────────────┘

AFTER (Proposed):
┌────────────────────────────┐
│ StepDefinition             │
├────────────────────────────┤
│ public readonly key        │
│ public readonly component  │
│ public readonly guardClass │
│ public readonly order      │
│ public readonly middleware │  ◄── NEW
└────────────────────────────┘
```

## 9. Builder Method Additions

```
CURRENT FlowBuilder:

FlowBuilder
├─ entersAt(name, path)
├─ finishesAt(route)
├─ historyMode(mode)
├─ step(key)
└─ build()

PROPOSED FlowBuilder:

FlowBuilder
├─ entersAt(name, path)
├─ finishesAt(route)
├─ historyMode(mode)
├─ middleware(array)      ◄── NEW
├─ step(key)
└─ build()

CURRENT StepBuilder:

StepBuilder
├─ goTo(component)
├─ unlessPasses(guard)
├─ order(order)
├─ step(key)
└─ build()

PROPOSED StepBuilder:

StepBuilder
├─ goTo(component)
├─ unlessPasses(guard)
├─ middleware(array)      ◄── NEW
├─ order(order)
├─ step(key)
└─ build()
```

## 10. RouteRegistrar Logic Update

```
CURRENT:
┌────────────────────────────────────────┐
│ register(?$middleware)                 │
├────────────────────────────────────────┤
│                                        │
│ $middleware = $middleware ??           │
│   config('livewire-workflows.middleware')
│ // $middleware = ['web']               │
│                                        │
│ foreach workflows:                     │
│   Route::middleware($middleware)       │
│     ->get(entryPath, ...)              │
│   Route::middleware($middleware)       │
│     ->get(stepPath, ...)               │
│     (SAME for all steps)               │
│                                        │
└────────────────────────────────────────┘

PROPOSED:
┌────────────────────────────────────────┐
│ register(?$middleware)                 │
├────────────────────────────────────────┤
│                                        │
│ $global = $middleware ??               │
│   config('livewire-workflows.middleware')
│ // $global = ['web']                   │
│                                        │
│ foreach workflows:                     │
│   // Entry: workflow > global          │
│   $entryMW = $workflow->middleware     │
│     ?? $global                         │
│   Route::middleware($entryMW)          │
│     ->get(entryPath, ...)              │
│                                        │
│   foreach steps:                       │
│     // Step: step > workflow > global  │
│     $stepMW = $step->middleware        │
│       ?? $workflow->middleware         │
│       ?? $global                       │
│     Route::middleware($stepMW)         │
│       ->get(stepPath, ...)             │
│                                        │
└────────────────────────────────────────┘
```

## 11. Complete Request Flow with Proposed Changes

```
Request: GET /checkout/payment
         │
         ├─ Route: checkout.payment
         │  ├─ Middleware (Step-level):
         │  │  $step->middleware = ['web', 'auth', 'verified']
         │  ├─ Handler: PaymentStep component
         │  │
         │  └─ Middleware Pipeline:
         │     ├─ StartSession
         │     ├─ Verify Auth
         │     └─ Verify Verified
         │
         ├─ If auth/verified fails:
         │  └─ Redirect to login/verify
         │
         └─ If passes:
            └─ Execute PaymentStep handler
               └─ User completes payment
```

## 12. Backward Compatibility Guarantee

```
EXISTING CODE (continues to work):

Workflow::flow('checkout')
    ->entersAt(name: 'checkout.start', path: '/checkout')
    ->finishesAt('dashboard')
    ->step('shipping')
        ->goTo(ShippingStep::class)
        ->order(10)
    ->step('payment')
        ->goTo(PaymentStep::class)
        ->order(20);

Result:
├─ Workflow middleware = null
│  → Uses global: ['web']
│
├─ Step 1 middleware = null
│  → Uses workflow: null → Uses global: ['web']
│
└─ Step 2 middleware = null
   → Uses workflow: null → Uses global: ['web']

All routes still get ['web'] (same as before!)


NEW CODE (uses new features):

Workflow::flow('checkout')
    ->middleware(['web', 'auth'])  ◄── NEW
    ->entersAt(name: 'checkout.start', path: '/checkout')
    ->finishesAt('dashboard')
    ->step('shipping')
        ->goTo(ShippingStep::class)
        ->middleware(['web'])      ◄── NEW (overrides)
        ->order(10)
    ->step('payment')
        ->goTo(PaymentStep::class)
        ->middleware(['web', 'auth', 'verified'])  ◄── NEW
        ->order(20);

Result:
├─ Workflow middleware = ['web', 'auth']
│  → Used as default for steps
│
├─ Step 1 middleware = ['web']
│  → Overrides workflow (public!)
│
└─ Step 2 middleware = ['web', 'auth', 'verified']
   → Overrides workflow (strict auth)
```

