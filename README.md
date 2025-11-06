# Livewire Workflows (ALPHA)

A Laravel 11+ package for building pipeline-driven, multi-step workflows with Livewire v3/v4. Define complex onboarding flows, checkout processes, or surveys using a readable DSL with automatic route registration.

## Features

- **Readable DSL** - Define workflows in `routes/workflows.php` with an English-like syntax
- **Auto-routed** - Step routes generated automatically, no manual routing needed
- **Guard-based** - Control step visibility with reusable guard classes
- **History modes** - Track user navigation with `none` or `stack` history
- **State repositories** - Choose from Null, Session, or Eloquent persistence
- **Events** - Track workflow progress with `WorkflowAdvanced` and `WorkflowCompleted`
- **Livewire integration** - Trait methods for `continue()` and `back()` navigation
- **CLI tools** - Commands for installation, generation, and validation

## Installation
```bash
composer require Pixelworxio/livewire-workflows
```

### Basic Setup
```bash
php artisan workflows:install
```

This creates `routes/workflows.php` and publishes the config file.

### Database Setup (Optional)

For persistent state tracking with Eloquent:
```bash
php artisan workflows:install --with-db
php artisan migrate
```

## Quick Start

### 1. Define a Workflow

In `routes/workflows.php`:
```php
use Pixelworxio\LivewireWorkflows\Facades\Workflow;

Workflow::flow('onboarding')
    ->entersAt(name: 'onboarding.start', path: '/onboarding')
    ->finishesAt('dashboard')
    ->historyMode('stack')
    ->step('verify-email')
        ->goTo(\App\Livewire\Onboarding\VerifyEmail::class)
        ->unlessPasses(\App\Guards\EmailVerifiedGuard::class)
        ->order(10)
    ->step('profile')
        ->goTo(\App\Livewire\Onboarding\EditProfile::class)
        ->unlessPasses(\App\Guards\ProfileCompletedGuard::class)
        ->order(20);
```

### 2. Create a Guard
```php
namespace App\Guards;

use Illuminate\Http\Request;
use Pixelworxio\LivewireWorkflows\Contracts\GuardContract;

class EmailVerifiedGuard implements GuardContract
{
    public function passes(Request $request): bool
    {
        return $request->user()?->hasVerifiedEmail() ?? false;
    }

    public function onEnter(Request $request): void
    {
        // Optional: Run logic when entering this step
    }

    public function onExit(Request $request): void
    {
        // Optional: Run logic when leaving this step
    }
}
```

### 3. Create a Livewire Component
```php
namespace App\Livewire\Onboarding;

use Livewire\Component;
use Pixelworxio\LivewireWorkflows\Livewire\Concerns\InteractsWithWorkflows;

class VerifyEmail extends Component
{
    use InteractsWithWorkflows;

    public function resendVerification()
    {
        $this->user->sendEmailVerificationNotification();
    }

    public function render()
    {
        return view('livewire.onboarding.verify-email');
    }
}
```

In your view, use the trait methods to navigate:
```blade
<div>
    <h1>Verify Your Email</h1>
    <p>Check your inbox for the verification link.</p>
    
    <button wire:click="resendVerification">Resend</button>
    
    <button wire:click="$parent.continue('onboarding')">
        I've verified, continue
    </button>
</div>
```

## How It Works

### Auto-Generated Routes

The package automatically registers routes based on your DSL:

- **Entry route**: `onboarding.start` → `/onboarding`
- **Step routes**: `onboarding.verify-email` → `/onboarding/verify-email`
- **Step routes**: `onboarding.profile` → `/onboarding/profile`

Route naming: `{flow}.{stepKey}`  
Route paths: `{basePath}/{stepKey}`

### Guard Behavior

Guards use **positive semantics**:

- `passes()` returns `true` → step is **skipped**
- `passes()` returns `false` → step is **shown**

Use `unlessPasses(Guard::class)` in the DSL: "show this step UNLESS the guard passes."

### Navigation

The entry route (`/onboarding`) evaluates all guards in order and redirects to:

1. The **first step** where the guard fails, or
2. The **finish route** if all guards pass

When a user completes a step, call `$this->continue('flow-name')` to re-evaluate and advance.

## DSL Reference
```php
Workflow::flow('flow-name')
    ->entersAt(name: 'route.name', path: '/base-path')  // Entry route
    ->finishesAt('dashboard')                            // Redirect on completion
    ->historyMode('stack')                               // 'none' or 'stack'
    ->step('step-key')                                   // Step identifier
        ->goTo(ComponentClass::class)                    // Livewire component
        ->unlessPasses(GuardClass::class)                // Guard (optional)
        ->order(10);                                     // Sort order
```

## Livewire Trait
```php
use InteractsWithWorkflows;

$this->continue('flow-name');           // Advance to next step
$this->back('flow-name', 'current-key'); // Go to previous step
```

## Helper Functions
```php
// Redirect to next step or finish
workflow('onboarding')->redirect($request);

// Get next route name
workflow('onboarding')->nextRouteNameFor($request);

// Get previous route name
workflow('onboarding')->previousRouteNameFor('current-step', $request);

// Get progress info
workflow('onboarding')->progressFor($request);
// Returns: ['total', 'completed', 'remaining', 'percentage', 'next_step', 'is_complete']
```

## State Repositories

Configure in `config/livewire-workflows.php`:
```php
'repository' => env('WORKFLOWS_REPOSITORY', 'session'),
```

- **`null`** - Stateless (no persistence)
- **`session`** - Session storage (guests/simple use)
- **`eloquent`** - Database storage (requires migration)

## Events

### WorkflowAdvanced

Fired when advancing between steps:
```php
Event::listen(WorkflowAdvanced::class, function ($event) {
    // $event->flow, $event->userKey, $event->fromKey, $event->toKey
});
```

### WorkflowCompleted

Fired when all steps pass:
```php
Event::listen(WorkflowCompleted::class, function ($event) {
    // $event->flow, $event->userKey
});
```

## CLI Commands

### Create a Workflow
```bash
php artisan make:workflow onboarding
```

### Create a Step
```bash
php artisan make:workflow-step onboarding verify-email \
    --component=App\\Livewire\\Onboarding\\VerifyEmail \
    --guard=App\\Guards\\EmailVerifiedGuard \
    --order=10
```

### Validate & Document
```bash
php artisan workflows:scan
```

Outputs:
- Validation of `#[WorkflowStep]` attributes vs DSL
- Markdown documentation of all workflows
- Suggested DSL snippets

## Testing
```php
use Pixelworxio\LivewireWorkflows\Facades\Workflow;

beforeEach(function () {
    Workflow::flow('test-flow')
        ->entersAt(name: 'test.start', path: '/test')
        ->finishesAt('dashboard')
        ->step('step-one')
            ->goTo(StepOneComponent::class)
            ->unlessPasses(StepOneGuard::class)
            ->order(10);
});

test('redirects to first unmet step', function () {
    $this->get('/test')->assertRedirect(route('test.step-one'));
});
```

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x
- Livewire 3.x (currently untested with 4.x beta)

## License

MIT

## Credits

Built with Laravel, Livewire, and ❤
