# Workflow State Management

The package provides powerful state management capabilities for persisting data throughout multi-step workflows. State is automatically scoped to the workflow and user, making it perfect for storing form data, user preferences, or temporary workflow context.

## Quick Start

### Automatic State Persistence with Attributes

Mark component properties with `#[WorkflowState]` to automatically persist them:

```php
use Pixelworxio\LivewireWorkflows\Attributes\WorkflowState;
use Pixelworxio\LivewireWorkflows\Livewire\Concerns\InteractsWithWorkflows;

class ProfileStep extends Component
{
    use InteractsWithWorkflows;
    
    protected ?string $workflowName = 'onboarding';
    
    #[WorkflowState]
    public ?string $fullName = null;
    
    #[WorkflowState]
    public ?string $email = null;
    
    #[WorkflowState(encrypt: true)]
    public ?string $ssn = null;
    
    #[WorkflowState(namespace: 'preferences')]
    public ?string $theme = null;
    
    public function save()
    {
        // State is automatically persisted
        $this->continue('onboarding');
    }
}
```

Properties are:
- **Hydrated** when the component mounts
- **Synced** automatically on every update
- **Scoped** to the workflow and current user/session

### Manual State Management

Use helper methods for programmatic state control:

```php
class PaymentStep extends Component
{
    use InteractsWithWorkflows;
    
    protected ?string $workflowName = 'checkout';
    
    public function savePaymentMethod($method)
    {
        $this->putWorkflowState('payment_method', $method);
        $this->putWorkflowState('payment_date', now());
    }
    
    public function loadSavedMethod()
    {
        return $this->getWorkflowState('payment_method', 'card');
    }
    
    public function hasPaymentInfo()
    {
        return $this->hasWorkflowState('payment_method');
    }
    
    public function clearPaymentData()
    {
        $this->clearWorkflowState('payment');
    }
}
```

## Attribute Options

### Encryption

Encrypt sensitive data in storage:

```php
#[WorkflowState(encrypt: true)]
public ?string $creditCard = null;

#[WorkflowState(encrypt: true)]
public ?array $sensitiveData = null;
```

Encrypted values are automatically decrypted on hydration.

### Namespacing

Group related state keys:

```php
#[WorkflowState(namespace: 'billing')]
public ?string $address = null;

#[WorkflowState(namespace: 'billing')]
public ?string $zipCode = null;

#[WorkflowState(namespace: 'shipping')]
public ?string $address = null;
```

This stores as:
- `billing.address`
- `billing.zipCode`
- `shipping.address`

Clear an entire namespace:
```php
$this->clearWorkflowState('billing'); // Clears only billing.*
```

## Helper Methods

All methods are available in components using `InteractsWithWorkflows`:

### `putWorkflowState(string $key, mixed $value): void`
Store a value in workflow state:
```php
$this->putWorkflowState('user_preferences', ['theme' => 'dark']);
```

### `getWorkflowState(string $key, mixed $default = null): mixed`
Retrieve a value from workflow state:
```php
$theme = $this->getWorkflowState('theme', 'light');
```

### `hasWorkflowState(string $key): bool`
Check if a key exists:
```php
if ($this->hasWorkflowState('payment_method')) {
    // Process payment
}
```

### `forgetWorkflowState(string $key): void`
Remove a specific key:
```php
$this->forgetWorkflowState('temporary_token');
```

### `clearWorkflowState(?string $namespace = null): void`
Clear all state or a specific namespace:
```php
$this->clearWorkflowState();           // Clear all
$this->clearWorkflowState('shipping'); // Clear shipping.* only
```

### `allWorkflowState(): array`
Get all workflow state:
```php
$allData = $this->allWorkflowState();
```

## State Lifecycle

### Automatic Clearing

State is automatically cleared when:
1. A workflow completes (all guards pass)
2. The repository's `clear()` method is called

Before clearing, the `WorkflowStateClearing` event is fired with all state data, allowing you to archive or process it:

```php
Event::listen(WorkflowStateClearing::class, function ($event) {
    // Archive state data before it's deleted
    Archive::create([
        'workflow' => $event->flow,
        'user_id' => $event->userKey,
        'data' => $event->stateData,
    ]);
});
```

### Manual Clearing

Clear state programmatically:

```php
use Pixelworxio\LivewireWorkflows\Contracts\WorkflowStateRepository;

$repository = app(WorkflowStateRepository::class);
$repository->clearState('onboarding', $userKey);
```

## State Repositories

State persistence depends on your configured repository:

### Eloquent (Default)
```php
'repository' => 'eloquent',
```
- Stored in `workflow_states` table
- Survives sessions and deploys
- Required for resume links
- Supports both guest keys and user IDs

### Null
```php
'repository' => 'null',
```
- No persistence
- Useful for stateless workflows

## Migration

The state management feature requires the `data` column. If you're upgrading:

```bash
php artisan make:migration add_data_column_to_workflow_states

# In migration:
public function up()
{
    Schema::table('workflow_states', function (Blueprint $table) {
        $table->json('data')->nullable()->after('metadata');
    });
}
```

## Examples

### Multi-Step Form with State

```php
// Step 1: Personal Info
class PersonalInfoStep extends Component
{
    use InteractsWithWorkflows;
    
    protected ?string $workflowName = 'registration';
    
    #[WorkflowState]
    public ?string $firstName = null;
    
    #[WorkflowState]
    public ?string $lastName = null;
    
    #[WorkflowState]
    public ?string $email = null;
    
    public function submit()
    {
        $this->validate([
            'firstName' => 'required',
            'lastName' => 'required',
            'email' => 'required|email',
        ]);
        
        // State auto-saved, move to next step
        $this->continue('registration');
    }
}

// Step 2: Account Security
class SecurityStep extends Component
{
    use InteractsWithWorkflows;
    
    protected ?string $workflowName = 'registration';
    
    #[WorkflowState(encrypt: true)]
    public ?string $password = null;
    
    public function submit()
    {
        $this->validate(['password' => 'required|min:8']);
        
        // Access previous step's data
        $email = $this->getWorkflowState('email');
        
        // Create user with all collected data
        User::create([
            'first_name' => $this->getWorkflowState('firstName'),
            'last_name' => $this->getWorkflowState('lastName'),
            'email' => $email,
            'password' => Hash::make($this->password),
        ]);
        
        $this->continue('registration');
    }
}
```

### Preserving Cart Data

```php
class CheckoutFlow extends Component
{
    use InteractsWithWorkflows;
    
    protected ?string $workflowName = 'checkout';
    
    #[WorkflowState(namespace: 'cart')]
    public array $items = [];
    
    #[WorkflowState(namespace: 'shipping')]
    public ?string $address = null;
    
    #[WorkflowState(namespace: 'payment', encrypt: true)]
    public ?array $cardDetails = null;
    
    public function mounted()
    {
        // Load existing cart
        if (empty($this->items)) {
            $this->items = session('cart', []);
        }
    }
    
    public function clearCart()
    {
        $this->clearWorkflowState('cart');
        session()->forget('cart');
    }
}
```

### Capturing State on Completion

```php
use Pixelworxio\LivewireWorkflows\Events\WorkflowStateClearing;

// In a service provider or listener
Event::listen(WorkflowStateClearing::class, function ($event) {
    if ($event->flow === 'onboarding') {
        // Save onboarding analytics
        OnboardingAnalytics::create([
            'user_id' => $event->userKey,
            'completed_at' => now(),
            'form_data' => $event->stateData,
        ]);
    }
});
```

## Best Practices

1. **Set `workflowName`**: Always define `protected ?string $workflowName` in components using state
2. **Use Encryption**: Encrypt sensitive data with `encrypt: true`
3. **Namespace Related Data**: Group related keys with `namespace`
4. **Validate Before Storage**: Validate data before persisting it
5. **Clear When Done**: State auto-clears on completion, but clear manually if workflow is abandoned
6. **Listen to Events**: Use `WorkflowStateClearing` to archive important data

## Troubleshooting

**State not persisting?**
- Ensure `workflowName` is set in your component
- Verify `WORKFLOWS_REPOSITORY=eloquent` is set in your `.env`
- Confirm the `workflow_states` table exists: run `php artisan migrate`

**State not hydrating?**
- Confirm properties have the `#[WorkflowState]` attribute
- Make sure `bootInteractsWithWorkflows()` is being called
- Check property visibility (public properties work best)

**Encryption errors?**
- Ensure `APP_KEY` is set in your `.env`
- Verify Laravel's encryption is working: `Crypt::encrypt('test')`
