![Livewire Workflows Banner](https://raw.githubusercontent.com/pixelworxio/livewire-workflows/main/resources/livewire-workflows-banner.png)

<p align="center">
  <a href="https://github.com/pixelworxio/livewire-workflows/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/pixelworxio/livewire-workflows/run-tests.yml?branch=main&label=tests&style=flat-square" alt="GitHub Tests Action Status"></a>
  <a href="https://github.com/pixelworxio/livewire-workflows"><img src="https://img.shields.io/github/stars/pixelworxio/livewire-workflows?style=flat-square" alt="GitHub Stars"></a>
</p>

---
# Livewire Workflows (BETA)
**Build powerful multi-step workflows in Laravel with zero boilerplate.** Define complex user journeys—onboarding, checkouts, surveys—using an expressive, route-like DSL. Get automatic route registration, guard-based navigation, state persistence, and full Livewire 4.x integration out of the box. Supports Laravel 11, 12, and 13. Livewire 3.x is also supported.

```php
// Define a complete workflow in seconds
Workflow::flow('onboarding')
    ->entersAt(name: 'onboarding.start', path: '/onboarding')
    ->finishesAt('dashboard')
    ->step('verify-email')
        ->goTo(VerifyEmail::class)
        ->unlessPasses(EmailVerifiedGuard::class)
        ->order(10)
    ->step('profile')
        ->goTo(EditProfile::class)
        ->unlessPasses(ProfileCompletedGuard::class)
        ->order(20);
```

That's it. No manual routes. No state management headaches.

View the testbench repo, https://github.com/pixelworxio/livewire-workflows-testbench, for examples of how this package can help you with your project.

---

## ✨ Why Livewire Workflows?

| Feature | Livewire Workflows | Manual Implementation |
|---------|-------------------|----------------------|
| **Route Management** | ✅ Auto-generated | ❌ Define every route manually |
| **State Persistence** | ✅ Built-in (Session/DB) | ❌ Roll your own |
| **Navigation Logic** | ✅ Guard-based pipeline | ❌ Complex conditionals everywhere |
| **Back Button** | ✅ History tracking | ❌ Custom session juggling |
| **Progress Tracking** | ✅ One method call | ❌ Calculate yourself |
| **Events & Analytics** | ✅ Fire automatically | ❌ Remember to dispatch |

**Result:** Spend time building features, not workflow infrastructure.

---

## 📦 Installation

```bash
composer require pixelworxio/livewire-workflows
```

### Quick Setup (Session-Based)
```bash
php artisan workflows:install
```

### Production Setup (Database-Backed)
```bash
php artisan workflows:install --with-db
php artisan migrate
```

---

## 🚀 Quick Start (3 Minutes)

### 1. Define Your Workflow

Open `routes/workflows.php`:

```php
use Pixelworxio\LivewireWorkflows\Facades\Workflow;

Workflow::flow('onboarding')
    ->entersAt(name: 'onboarding.start', path: '/onboarding')
    ->finishesAt('dashboard')
    ->step('verify-email')
        ->goTo(\App\Livewire\Onboarding\VerifyEmail::class)
        ->unlessPasses(\App\Guards\EmailVerifiedGuard::class)
        ->order(10)
    ->step('profile')
        ->goTo(\App\Livewire\Onboarding\EditProfile::class)
        ->unlessPasses(\App\Guards\ProfileCompletedGuard::class)
        ->order(20);
```

**Routes auto-registered:**
- Entry: `GET /onboarding` → `onboarding.start`
- Step 1: `GET /onboarding/verify-email` → `onboarding.verify-email`
- Step 2: `GET /onboarding/profile` → `onboarding.profile`

### 2. Create a Guard

**Quick Generation:**
```bash
php artisan make:workflow-guard EmailVerified
```

This generates a guard at `App\Guards\EmailVerifiedGuard.php` with the proper structure.

```php
namespace App\Guards;

use Illuminate\Http\Request;
use Pixelworxio\LivewireWorkflows\Contracts\GuardContract;

class EmailVerifiedGuard implements GuardContract
{
    public function passes(Request $request): bool
    {
        return true;
    }

    public function onEnter(Request $request): void {}
    public function onExit(Request $request): void {}
    public function onPass(Request $request): void {}
    public function onFail(Request $request): void {}
}
```

**Guard Logic:** `passes() = true` → skip step. `passes() = false` → show step.

### 3. Build Your Livewire Component

```php
namespace App\Livewire\Onboarding;

use Livewire\Component;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowStep;
use Pixelworxio\LivewireWorkflows\Livewire\Concerns\InteractsWithWorkflows;

#[WorkflowStep(flow:'onboarding', key:'verify-email')]
class VerifyEmail extends Component
{
    use InteractsWithWorkflows;

    public function resend()
    {
        auth()->user()->sendEmailVerificationNotification();
        session()->flash('message', 'Verification email sent!');
    }
    
    public function goToNextStep(): void
    {
        // ... handle your logic
        
        $this->continue('onboarding'); // workflow name is optional when using WorkflowStep attribute
    }

    public function render()
    {
        return view('livewire.onboarding.verify-email');
    }
}
```

**And in your Blade view:**
```blade
<div>
    <h1>Verify Your Email</h1>
    <p>Check your inbox for the verification link.</p>
    
    <button wire:click="resend">Resend Email</button>
    
    <button wire:click="goToNextStep">
        I've Verified — Continue
    </button>
</div>
```

### 4. Done!

Visit `/onboarding`. The package:
1. Evaluates guards in order
2. Redirects to first incomplete step
3. Tracks progress automatically
4. Completes when all steps pass

---

## 📖 Core Concepts

### Auto-Generated Routes

Routes are **automatically registered** from your DSL:

```php
->entersAt(name: 'checkout.start', path: '/checkout')  // Entry route
->step('shipping')                                       // Auto: /checkout/shipping
->step('payment')                                        // Auto: /checkout/payment
```

**Generated:**
- `checkout.start` → `/checkout`
- `checkout.shipping` → `/checkout/shipping`
- `checkout.payment` → `/checkout/payment`

### Dynamic Routes with Parameters

Workflows support **dynamic route parameters** and **route model binding**:

```php
Workflow::flow('user-checkout')
    ->entersAt(name: 'user-checkout.start', path: '/user/{user}/checkout/{product}')
    ->finishesAt('dashboard')
    ->step('shipping')
        ->goTo(\App\Livewire\Checkout\Shipping::class)
        ->unlessPasses(\App\Guards\HasShippingAddress::class)
        ->order(10)
    ->step('payment')
        ->goTo(\App\Livewire\Checkout\Payment::class)
        ->unlessPasses(\App\Guards\HasPaymentMethod::class)
        ->order(20);
```

**Generated Routes:**
- `user-checkout.start` → `/user/{user}/checkout/{product}`
- `user-checkout.shipping` → `/user/{user}/checkout/{product}/shipping`
- `user-checkout.payment` → `/user/{user}/checkout/{product}/payment`

**Route Model Binding:**

```php
// Supports Laravel's route model binding syntax
->entersAt(name: 'checkout.start', path: '/user/{user:id}/product/{product:slug}')
```

**Navigation Preserves Parameters:**

Route parameters are automatically passed through all workflow navigation:

```php
class Shipping extends Component
{
    use InteractsWithWorkflows;
    
    protected ?string $workflowName = 'user-checkout';

    // Livewire receives route parameters
    public function mount($user, $product)
    {
        // $user and $product are automatically injected
    }

    public function submit()
    {
        // Parameters are automatically preserved when continuing
        $this->continue($this->workflowName); // Still navigates with {user} and {product}
    }
}
```

The `continue()` and `back()` methods automatically extract and pass route parameters from the current request, ensuring seamless navigation throughout the workflow.

### Guard Behavior

Guards use **positive semantics**:

```php
class ProfileCompleteGuard implements GuardContract
{
    public function passes(Request $request): bool
    {
        return $request->user()->profile_completed;
    }
}
```

| Guard Result | Step Behavior |
|--------------|---------------|
| `passes() = true` | **Skip** this step |
| `passes() = false` | **Show** this step |

Use `unlessPasses(Guard::class)`: "Show step UNLESS guard passes."

### Navigation Flow

1. User visits entry route (`/onboarding`)
2. Package evaluates guards in `order`
3. Redirects to first failing guard's step
4. User completes step, calls `$this->continue('onboarding')`
5. Re-evaluates from entry → next unmet step or finish

---

## 🧩 Advanced Features

### State Management

#### Setting Workflow Name

Use the `#[WorkflowName]` attribute to explicitly declare your component's workflow, when not using the WorkflowStep attribute:

```php
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowName;

#[WorkflowName('checkout')]
class CheckoutShipping extends Component
{
    use InteractsWithWorkflows;

    // protected ?string $workflowName = 'checkout'; // No need to set 
}
```

**Benefits:**
- ✅ Eliminates boilerplate
- ✅ Makes workflow association explicit
- ✅ Auto-detected during component boot
- ✅ Falls back to route-based detection if not present

#### Persisting State

Persist data across workflow steps with the `#[WorkflowState]` attribute:

```php
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowName;
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowState;

#[WorkflowName('checkout')]
class CheckoutShipping extends Component
{
    use InteractsWithWorkflows;

    #[WorkflowState]
    public ?string $address = null;

    #[WorkflowState(encrypt: true)]
    public ?string $creditCard = null;

    #[WorkflowState(namespace: 'shipping')]
    public ?string $method = null;
}
```

**Features:**
- Auto-hydration on mount
- Auto-persistence on dehydrate
- Encryption support
- Namespace grouping
- Session or database storage

[Full state management guide →](STATE_MANAGEMENT.md)

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

### Events

Listen to workflow lifecycle:

```php
use Pixelworxio\LivewireWorkflows\Events\{WorkflowAdvanced, WorkflowCompleted};

Event::listen(WorkflowAdvanced::class, function ($event) {
    Log::info("User {$event->userKey} moved from {$event->fromKey} to {$event->toKey}");
});

Event::listen(WorkflowCompleted::class, function ($event) {
    Mail::to($user)->send(new OnboardingComplete());
});
```

### CLI Tools

```bash
# Generate a new workflow
php artisan make:workflow checkout

# Generate a guard class
php artisan make:workflow-guard EmailVerified

# Add a step to existing workflow
php artisan make:workflow-step checkout payment \
    --component=App\\Livewire\\Checkout\\Payment \
    --guard=App\\Guards\\CartNotEmptyGuard \
    --order=20

# Validate and document all workflows
php artisan workflows:scan
```

---

## 🎯 Real-World Examples

### Multi-Page Checkout

```php
Workflow::flow('checkout')
    ->entersAt(name: 'checkout.start', path: '/checkout')
    ->finishesAt('orders.confirmation')
    ->step('cart')
        ->goTo(ReviewCart::class)
        ->unlessPasses(CartNotEmptyGuard::class)
        ->order(10)
    ->step('shipping')
        ->goTo(ShippingAddress::class)
        ->unlessPasses(ShippingAddressGuard::class)
        ->order(20)
    ->step('payment')
        ->goTo(PaymentMethod::class)
        ->unlessPasses(PaymentMethodGuard::class)
        ->order(30);
```

### Employee Onboarding

```php
Workflow::flow('employee-onboarding')
    ->entersAt(name: 'onboard.start', path: '/onboard')
    ->finishesAt('employee.dashboard')
    ->step('paperwork')
        ->goTo(Paperwork::class)
        ->unlessPasses(PaperworkCompleteGuard::class)
        ->order(10)
    ->step('it-setup')
        ->goTo(ITSetup::class)
        ->unlessPasses(ITAccountGuard::class)
        ->order(20)
    ->step('training')
        ->goTo(TrainingModules::class)
        ->unlessPasses(TrainingCompleteGuard::class)
        ->order(30);
```

### User Registration Flow

```php
Workflow::flow('signup')
    ->entersAt(name: 'signup.start', path: '/signup')
    ->finishesAt('welcome')
    ->step('account')
        ->goTo(CreateAccount::class)
        ->unlessPasses(AccountCreatedGuard::class)
        ->order(10)
    ->step('verify-email')
        ->goTo(VerifyEmail::class)
        ->unlessPasses(EmailVerifiedGuard::class)
        ->order(20)
    ->step('preferences')
        ->goTo(SetPreferences::class)
        ->unlessPasses(PreferencesSetGuard::class)
        ->order(30);
```

---

## 🔧 Configuration

Publish and customize the config:

```bash
php artisan vendor:publish --tag=livewire-workflows-config
```

**`config/livewire-workflows.php`:**

```php
return [
    // State persistence: 'null', 'session', or 'eloquent'
    'repository' => env('WORKFLOWS_REPOSITORY', 'session'),
    
    // Middleware applied to all workflow routes
    'middleware' => ['web', 'auth'],
];
```

### State Repository Options

| Repository | Use Case | Persistence |
|------------|----------|-------------|
| `null` | Stateless workflows | None |
| `session` | Guest users, simple flows | Session lifetime |
| `eloquent` | Authenticated users, production | Database |

---

## 📊 API Reference

### Workflow DSL

```php
Workflow::flow(string $name)
    ->entersAt(name: string, path: string)
    ->finishesAt(string $routeName)
    ->step(string $key)
        ->goTo(string $componentClass)
        ->unlessPasses(string $guardClass)
        ->order(int $order);
```

### Livewire Trait Methods

```php
use InteractsWithWorkflows;

$this->continue(string $flow): RedirectResponse
$this->back(string $flow, string $currentKey): ?RedirectResponse
$this->syncState(): void  // Manually persist state
```

Note: When using the `#[WorkflowStep]` attribute, the `$flow` and `$currentKey` properties are optional

```
#[WorkflowStep(flow: 'my-flow', key: 'current-step', middleware: ['auth'])]

use InteractsWithWorkflows;

$this->continue(): RedirectResponse
$this->back(): ?RedirectResponse
```

### Helper Functions

```php
workflow(string $flow)->redirect(Request $request, ?string $doneRoute = null)
workflow(string $flow)->nextRouteNameFor(Request $request, ?string $doneRoute = null)
workflow(string $flow)->previousRouteNameFor(string $currentKey, Request $request)
workflow(string $flow)->progressFor(Request $request)
```

### State Management Helpers

```php
// Automatic with attributes
#[WorkflowState] public ?string $email = null;
#[WorkflowState(encrypt: true)] public ?string $password = null;
#[WorkflowState(namespace: 'profile')] public ?string $name = null;

// Manual helpers
$this->putWorkflowState(string $key, mixed $value)
$this->getWorkflowState(string $key, mixed $default = null)
$this->hasWorkflowState(string $key)
$this->forgetWorkflowState(string $key)
$this->clearWorkflowState(?string $namespace = null)
$this->allWorkflowState()
```

---

## 🆚 Comparison with Alternatives
**Or so Claude.ai says...

| Feature | Livewire Workflows | Spatie Laravel Wizard | Custom Solution |
|---------|-------------------|----------------------|-----------------|
| Route Auto-Registration | ✅ | ❌ | ❌ |
| Guard-Based Navigation | ✅ | ❌ | Custom |
| State Persistence | ✅ Built-in | ❌ | Custom |
| Livewire 4 & 3 Native | ✅ | ⚠️ Limited | N/A |
| History/Back Support | ✅ | ⚠️ Basic | Custom |
| Progress Tracking | ✅ | ❌ | Custom |
| Events | ✅ | ❌ | Custom |
| Learning Curve | Low | Medium | High |

---

## 💬 FAQ

<details>
<summary><strong>Does this support Livewire v4?</strong></summary>

Yes. The package is built and certified for Livewire v4, with full backward compatibility for Livewire v3. All lifecycle hooks and the redirect/navigation API are identical across both versions.
</details>

<details>
<summary><strong>Do I need to define routes manually?</strong></summary>

No. Step routes are auto-registered from your DSL. You only need to define the finish route in your regular `routes/web.php`, if not already present.
</details>

<details>
<summary><strong>Can I protect workflows with authentication?</strong></summary>

Yes. Add `'auth'` to the `middleware` array in `config/livewire-workflows.php`.
</details>

<details>
<summary><strong>How do I skip steps dynamically?</strong></summary>

Make the guard's `passes()` method return `true` when the step should be skipped.
</details>

<details>
<summary><strong>Can workflows share state?</strong></summary>

No. State is scoped per workflow and per user. This is by design for data isolation.
</details>

<details>
<summary><strong>What happens if a user jumps to a step URL directly?</strong></summary>

The step page loads, but calling `continue()` will re-evaluate guards and redirect appropriately.
</details>

---

## 🛠️ Requirements

- PHP 8.3+
- Laravel 11.x, 12.x, or 13.x
- Livewire 4.x (3.x also supported)

---

## 🤝 Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

### Development Setup

```bash
git clone https://github.com/pixelworxio/livewire-workflows.git
cd livewire-workflows
composer install
composer test
```

---

## 📜 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

---

## 🙏 Credits

- [Whoisthisstud](https://github.com/whoisthisstud)

[//]: # (- [All Contributors]&#40;../../contributors&#41;)

Built with ❤️ using [Laravel](https://laravel.com), [Livewire](https://livewire.laravel.com), and [Spatie's Package Tools](https://github.com/spatie/laravel-package-tools).

---

## 🌟 Show Your Support

Give a ⭐️ if this project helped you!

<p align="center">
  <a href="https://github.com/pixelworxio/livewire-workflows">
    <img src="https://img.shields.io/github/stars/pixelworxio/livewire-workflows?style=social" alt="GitHub Stars">
  </a>
  <a href="https://twitter.com/intent/tweet?text=Check%20out%20Livewire%20Workflows%20for%20Laravel!&url=https://github.com/pixelworxio/livewire-workflows">
    <img src="https://img.shields.io/twitter/url?style=social&url=https%3A%2F%2Fgithub.com%2Fpixelworxio%2Flivewire-workflows" alt="Tweet">
  </a>
</p>
