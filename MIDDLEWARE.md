# Middleware Guide for Livewire Workflows

This guide explains how to use middleware in Livewire Workflows to control access to your workflow routes at both the workflow and individual step levels.

## Table of Contents

1. [Introduction](#introduction)
2. [Quick Start](#quick-start)
3. [Middleware Declaration Methods](#middleware-declaration-methods)
4. [Middleware Precedence](#middleware-precedence)
5. [Common Use Cases](#common-use-cases)
6. [Configuration](#configuration)
7. [Advanced Usage](#advanced-usage)
8. [Best Practices](#best-practices)

---

## Introduction

By default, all workflow routes use the global middleware defined in `config/livewire-workflows.php` (typically `['web']`). However, you often need different authentication or authorization requirements for different workflows or steps.

Livewire Workflows provides three ways to customize middleware:

1. **Attribute-based** (Recommended) - Using `#[WorkflowStep]` attribute (all-in-one) or separate `#[StepMiddleware]` attributes
2. **DSL-based** - Using `->middleware()` methods in workflow definitions
3. **Callable** - Using closures for dynamic middleware resolution

---

## Quick Start

### Attribute-Based Middleware (Recommended)

The easiest and most declarative way to add middleware is using the `#[WorkflowStep]` attribute, which sets both the workflow name and middleware in one place:

```php
use Livewire\Component;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowStep;
use Pixelworxio\LivewireWorkflows\Livewire\Concerns\InteractsWithWorkflows;

#[WorkflowStep(name: 'checkout', middleware: ['web', 'auth', 'verified'])]
class PaymentStep extends Component
{
    use InteractsWithWorkflows;

    public function render()
    {
        return view('livewire.checkout.payment');
    }
}
```

Now the `/checkout/payment` route requires the user to be authenticated and email-verified.

**Alternative:** You can also use separate attributes if you prefer:

```php
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowName;
use Pixelworxio\LivewireWorkflows\Attributes\StepMiddleware;

#[WorkflowName('checkout')]
#[StepMiddleware(['web', 'auth', 'verified'])]
class PaymentStep extends Component
{
    use InteractsWithWorkflows;
    // ...
}
```

### DSL-Based Middleware

You can also define middleware in your workflow definition:

```php
// routes/workflows.php
use Pixelworxio\LivewireWorkflows\Facades\Workflow;

Workflow::flow('checkout')
    ->middleware(['web', 'auth']) // Workflow-level default
    ->entersAt(name: 'checkout.start', path: '/checkout')
    ->finishesAt('dashboard')
    ->step('payment')
        ->goTo(PaymentStep::class)
        ->middleware(['web', 'auth', 'verified']) // Step-level override
        ->order(10);
```

---

## Middleware Declaration Methods

### 1. Attribute-Based (Preferred)

**Best for:** Step-level middleware that's tightly coupled to the component

```php
#[StepMiddleware(['web', 'auth'])]
class ProfileSetupStep extends Component
{
    use InteractsWithWorkflows;
    // ...
}
```

**Advantages:**
- Middleware is declared close to the component
- Self-documenting
- Easier to refactor
- No need to update workflow definition file

### 2. DSL-Based

**Best for:** Workflow-level middleware or when you want centralized control

#### Workflow-Level Middleware

```php
Workflow::flow('admin-panel')
    ->middleware(['web', 'auth', 'admin']) // All steps inherit this
    ->entersAt(name: 'admin.start', path: '/admin')
    ->finishesAt('dashboard')
    ->step('settings')
        ->goTo(AdminSettings::class)
        ->order(10);
```

#### Step-Level Middleware

```php
Workflow::flow('product-review')
    ->entersAt(name: 'review.start', path: '/review')
    ->finishesAt('dashboard')
    ->step('view')
        ->goTo(ViewProduct::class)
        ->middleware(['web']) // Public step
        ->order(10)
    ->step('write-review')
        ->goTo(WriteReview::class)
        ->middleware(['web', 'auth']) // Requires auth
        ->order(20);
```

### 3. Callable Middleware (Dynamic)

**Best for:** Middleware that depends on runtime conditions

```php
Workflow::flow('order-management')
    ->middleware(function () {
        return auth()->user()?->isAdmin()
            ? ['web', 'auth', 'admin']
            : ['web', 'auth'];
    })
    ->entersAt(name: 'orders.start', path: '/orders')
    ->finishesAt('dashboard')
    ->step('edit')
        ->goTo(EditOrder::class)
        ->middleware(function () {
            // Dynamic middleware based on request
            return request()->route('order')?->status === 'draft'
                ? ['web', 'auth']
                : ['web', 'auth', 'verified'];
        })
        ->order(10);
```

---

## Middleware Precedence

Middleware can be defined at three levels:

1. **Global** - `config/livewire-workflows.php` → `'middleware' => ['web']`
2. **Workflow** - `->middleware(['web', 'auth'])` on FlowBuilder
3. **Step** - `->middleware(['web', 'auth', 'verified'])` on StepBuilder or `#[StepMiddleware]` attribute

### Override Mode (Default)

In override mode, more specific middleware **replaces** less specific middleware:

```
Step middleware > Workflow middleware > Global middleware
```

**Example:**

```php
// config/livewire-workflows.php
'middleware' => ['web'],
'middleware_precedence' => 'override',

// routes/workflows.php
Workflow::flow('checkout')
    ->middleware(['web', 'auth']) // Workflow default
    ->entersAt(name: 'checkout.start', path: '/checkout')
    ->finishesAt('dashboard')
    ->step('view-cart')
        ->goTo(ViewCart::class)
        // No step middleware → inherits ['web', 'auth']
        ->order(10)
    ->step('payment')
        ->goTo(PaymentStep::class)
        ->middleware(['web', 'auth', 'verified']) // Overrides workflow middleware
        ->order(20);
```

**Result:**
- `/checkout` entry route: `['web', 'auth']` (workflow middleware)
- `/checkout/view-cart`: `['web', 'auth']` (inherits workflow middleware)
- `/checkout/payment`: `['web', 'auth', 'verified']` (step middleware overrides)

### Merge Mode

In merge mode, middleware is **combined** from all levels:

```php
// config/livewire-workflows.php
'middleware_precedence' => 'merge',
```

**Example:**

```php
// Global: ['web']
// Workflow: ['auth']
// Step: ['verified']

// Result: ['web', 'auth', 'verified'] (all combined, deduplicated)
```

**Use merge mode when:**
- You want global middleware to always apply
- You want to build middleware layers additively
- You never want to remove middleware from parent levels

---

## Common Use Cases

### 1. Mixed Public and Authenticated Workflow

Allow guests to view products but require authentication for actions:

```php
Workflow::flow('product-catalog')
    ->entersAt(name: 'catalog.start', path: '/products')
    ->finishesAt('catalog.home')
    ->step('browse')
        ->goTo(BrowseProducts::class)
        ->middleware(['web']) // Public
        ->order(10)
    ->step('add-to-cart')
        ->goTo(AddToCart::class)
        ->middleware(['web', 'auth']) // Requires auth
        ->order(20)
    ->step('checkout')
        ->goTo(Checkout::class)
        ->middleware(['web', 'auth', 'verified']) // Requires verified email
        ->order(30);
```

### 2. Admin-Only Workflow

Protect an entire workflow with admin middleware:

```php
Workflow::flow('admin-settings')
    ->middleware(['web', 'auth', 'admin']) // All steps require admin
    ->entersAt(name: 'admin.settings', path: '/admin/settings')
    ->finishesAt('admin.dashboard')
    ->step('general')
        ->goTo(GeneralSettings::class)
        ->order(10)
    ->step('users')
        ->goTo(UserManagement::class)
        ->order(20);
```

### 3. Progressive Authorization

Require increasing levels of authorization as the user progresses:

```php
#[StepMiddleware(['web'])]
class OnboardingWelcome extends Component { /* ... */ }

#[StepMiddleware(['web', 'auth'])]
class OnboardingProfile extends Component { /* ... */ }

#[StepMiddleware(['web', 'auth', 'verified'])]
class OnboardingPreferences extends Component { /* ... */ }

Workflow::flow('onboarding')
    ->entersAt(name: 'onboarding.start', path: '/onboarding')
    ->finishesAt('dashboard')
    ->step('welcome')
        ->goTo(OnboardingWelcome::class) // Public
        ->order(10)
    ->step('profile')
        ->goTo(OnboardingProfile::class) // Requires auth
        ->order(20)
    ->step('preferences')
        ->goTo(OnboardingPreferences::class) // Requires verified email
        ->order(30);
```

### 4. Role-Based Access

Use custom middleware for role-based access:

```php
#[StepMiddleware(['web', 'auth', 'role:manager'])]
class ApproveTimesheets extends Component { /* ... */ }

#[StepMiddleware(['web', 'auth', 'role:admin'])]
class ManageUsers extends Component { /* ... */ }
```

### 5. Dynamic Middleware Based on Resource

Adjust middleware based on the resource being accessed:

```php
Workflow::flow('order-review')
    ->entersAt(name: 'order.review', path: '/order/{order}/review')
    ->finishesAt('orders.index')
    ->step('review')
        ->goTo(ReviewOrder::class)
        ->middleware(function () {
            $order = request()->route('order');

            // Allow viewing own orders, require admin for others
            return $order->user_id === auth()->id()
                ? ['web', 'auth']
                : ['web', 'auth', 'admin'];
        })
        ->order(10);
```

---

## Configuration

### Global Middleware

Set the default middleware for all workflows in `config/livewire-workflows.php`:

```php
return [
    'middleware' => ['web'], // Default for all workflows
    'middleware_precedence' => 'override', // or 'merge'
];
```

### Environment-Specific Configuration

Use environment variables for flexibility:

```php
// config/livewire-workflows.php
'middleware' => env('WORKFLOWS_MIDDLEWARE', 'web')
    ? explode(',', env('WORKFLOWS_MIDDLEWARE', 'web'))
    : ['web'],

'middleware_precedence' => env('WORKFLOWS_MIDDLEWARE_PRECEDENCE', 'override'),
```

```.env
WORKFLOWS_MIDDLEWARE=web,auth
WORKFLOWS_MIDDLEWARE_PRECEDENCE=override
```

---

## Advanced Usage

### Combining DSL and Attributes

DSL middleware **always overrides** attribute middleware:

```php
#[StepMiddleware(['web', 'auth'])] // This will be ignored
class PaymentStep extends Component { /* ... */ }

Workflow::flow('checkout')
    ->entersAt(name: 'checkout.start', path: '/checkout')
    ->finishesAt('dashboard')
    ->step('payment')
        ->goTo(PaymentStep::class)
        ->middleware(['web', 'auth', 'verified']) // This takes precedence
        ->order(10);
```

### Middleware with Route Model Binding

Middleware is evaluated at route registration time, but you can use callables for runtime checks:

```php
Workflow::flow('edit-post')
    ->entersAt(name: 'post.edit', path: '/post/{post:slug}/edit')
    ->finishesAt('posts.index')
    ->step('edit')
        ->goTo(EditPost::class)
        ->middleware(function () {
            // Can access route parameters
            $post = request()->route('post');

            return $post?->user_id === auth()->id()
                ? ['web', 'auth']
                : ['web', 'auth', 'admin'];
        })
        ->order(10);
```

### Custom Middleware

Create custom middleware for workflow-specific logic:

```php
// app/Http/Middleware/CheckWorkflowAccess.php
class CheckWorkflowAccess
{
    public function handle($request, Closure $next, $workflow)
    {
        if (! auth()->user()->hasAccessToWorkflow($workflow)) {
            abort(403);
        }

        return $next($request);
    }
}

// routes/workflows.php
Workflow::flow('sensitive-data')
    ->middleware(['web', 'auth', 'workflow:sensitive-data'])
    ->entersAt(name: 'sensitive.start', path: '/sensitive')
    ->finishesAt('dashboard')
    ->step('view')
        ->goTo(ViewSensitiveData::class)
        ->order(10);
```

---

## Best Practices

### 1. Prefer Attributes for Step-Level Middleware

**Good:**

```php
#[StepMiddleware(['web', 'auth', 'verified'])]
class PaymentStep extends Component { /* ... */ }
```

**Why:** Self-documenting, easy to refactor, middleware stays with the component.

### 2. Use DSL for Workflow-Level Middleware

**Good:**

```php
Workflow::flow('admin-panel')
    ->middleware(['web', 'auth', 'admin'])
    ->entersAt(name: 'admin.start', path: '/admin')
    ->finishesAt('dashboard');
```

**Why:** Centralized control, applies to all steps by default.

### 3. Keep Middleware Simple

**Good:**

```php
->middleware(['web', 'auth', 'verified'])
```

**Bad:**

```php
->middleware(function () {
    $user = auth()->user();
    $hasPermission = DB::table('permissions')->where('user_id', $user->id)->exists();
    $isVerified = $user->hasVerifiedEmail();
    // Complex logic...
    return $hasPermission && $isVerified ? ['web', 'auth'] : ['web'];
})
```

**Why:** Complex logic should be in custom middleware classes, not closures.

### 4. Document Middleware Requirements

Add comments to explain authorization requirements:

```php
// Only managers can approve timesheets
#[StepMiddleware(['web', 'auth', 'role:manager'])]
class ApproveTimesheets extends Component { /* ... */ }
```

### 5. Test Middleware Extensively

Test all middleware scenarios:

```php
test('payment step requires authentication', function () {
    $this->get('/checkout/payment')
        ->assertRedirect('/login');
});

test('payment step allows authenticated users', function () {
    $this->actingAs($user)
        ->get('/checkout/payment')
        ->assertOk();
});
```

### 6. Use Override Mode by Default

Override mode is simpler to reason about. Only use merge mode if you have a specific need for additive middleware.

### 7. Be Explicit

Don't rely on global middleware inheritance when it matters:

**Good:**

```php
#[StepMiddleware(['web', 'auth', 'verified'])] // Explicit
class PaymentStep extends Component { /* ... */ }
```

**Okay (if documented):**

```php
// Inherits ['web', 'auth'] from workflow
class ViewCart extends Component { /* ... */ }
```

---

## Troubleshooting

### Middleware Not Applied

1. Check that routes are registered: `php artisan route:list`
2. Verify workflow is defined in `routes/workflows.php`
3. Ensure `RouteServiceProvider` loads workflow routes
4. Clear route cache: `php artisan route:clear`

### Unexpected Middleware

1. Check precedence mode in config: `'middleware_precedence' => 'override'`
2. Verify DSL middleware isn't overriding attribute middleware
3. Check if workflow-level middleware is being inherited

### Callable Middleware Not Working

1. Ensure callable returns an array
2. Check that request context is available
3. Verify callable is executed at route registration time

---

## Summary

Livewire Workflows provides flexible middleware customization:

- **Attributes** (Preferred): `#[StepMiddleware(['web', 'auth'])]`
- **DSL Methods**: `->middleware(['web', 'auth'])`
- **Callable**: `->middleware(fn() => ['web', 'auth'])`
- **Precedence**: Step > Workflow > Global (in override mode)
- **Modes**: Override (default) or Merge

Use attributes for step-level middleware, DSL for workflow-level defaults, and callables for dynamic requirements.

For more information, see [README.md](README.md) and [CLAUDE.md](CLAUDE.md).
