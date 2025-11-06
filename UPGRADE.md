# Upgrade Guide

This guide covers upgrading between major versions of Livewire Workflows.

## General Upgrade Instructions

1. **Review the changelog** for breaking changes
2. **Update your composer.json** dependency version
3. **Run composer update**:
   ```bash
   composer update pixelworxio/livewire-workflows
   ```
4. **Clear caches**:
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```
5. **Run tests** to ensure compatibility
6. **Review deprecated features** and update your code

---

## Upgrading to 0.2a from 0.1a

### High Impact Changes

#### State Management Migration

If you were using the package before state management was added, you'll need to:

**1. Add the `data` column to your `workflow_states` table:**

```bash
php artisan make:migration add_data_column_to_workflow_states
```

```php
public function up()
{
    Schema::table('workflow_states', function (Blueprint $table) {
        $table->json('data')->nullable()->after('metadata');
    });
}
```

**2. Run the migration:**
```bash
php artisan migrate
```

#### Method Signature Changes

None in 1.0, but see Medium Impact Changes below.

### Medium Impact Changes

#### WorkflowStateRepository Interface

If you implemented a custom state repository, you should now implement these new methods:

```php
public function getState(string $flow, string|int|null $userKey, string $key): mixed;
public function setState(string $flow, string|int|null $userKey, string $key, mixed $value): void;
public function hasState(string $flow, string|int|null $userKey, string $key): bool;
public function forgetState(string $flow, string|int|null $userKey, string $key): void;
public function clearState(string $flow, string|int|null $userKey, ?string $namespace = null): void;
public function getAllState(string $flow, string|int|null $userKey): array;
```

Reference the built-in implementations for guidance:
- `SessionWorkflowStateRepository`
- `EloquentWorkflowStateRepository`

#### New Events

A new event `WorkflowStateClearing` is now fired before state is cleared:

```php
use Pixelworxio\LivewireWorkflows\Events\WorkflowStateClearing;

Event::listen(WorkflowStateClearing::class, function ($event) {
    // Archive state before it's deleted
});
```

If you have global event listeners, be aware of this new event.

### Low Impact Changes

#### New Attributes Available

You can now use `#[WorkflowState]` attributes on Livewire component properties:

```php
#[WorkflowState]
public ?string $email = null;

#[WorkflowState(encrypt: true)]
public ?string $password = null;
```

This is **opt-in** and doesn't affect existing code.

#### New Trait Methods

The `InteractsWithWorkflows` trait now includes state helper methods:

- `putWorkflowState()`
- `getWorkflowState()`
- `hasWorkflowState()`
- `forgetWorkflowState()`
- `clearWorkflowState()`
- `allWorkflowState()`

These are **new additions** and don't break existing usage.

---

## Upgrading to 1.0 beta (Future)

**Note:** This section will be updated when version 1.0b is released.

### Planned Changes

1. Potential DSL syntax improvements
2. Alerts/notifications for Guard failures
3. Stubbed Guard classes

Stay tuned for updates.

---

## Version Compatibility Matrix

| Package Version | PHP  | Laravel | Livewire      | Status     |
|-----------------|------|---------|---------------|------------|
| 1.x             | 8.3+ | 12.x | 3.x, 4.x beta | Pending    |
| 0.2a            | 8.3+ | 11.x, 12.x | 3.x     | Active     |
| 0.1a            | 8.2+ | 11.x | 3.x           | Deprecated |

---

## Deprecation Policy

Features marked as deprecated will:
1. **Continue working** for at least one major version
2. **Trigger warnings** in development
3. **Include migration instructions** in documentation
4. **Be removed** in the next major version

When a feature is deprecated, we'll provide:
- Clear migration path
- Examples of the new approach
- Automated upgrade scripts when possible

---

## Getting Help

If you encounter issues during an upgrade:

1. Check the [Changelog](CHANGELOG.md)
2. Review [GitHub Issues](https://github.com/pixelworxio/livewire-workflows/issues)
3. Ask in [Discussions](https://github.com/pixelworxio/livewire-workflows/discussions)
4. Open a new issue if you find a bug

---

## Rollback Instructions

If you need to rollback to a previous version:

```bash
# Rollback to specific version
composer require pixelworxio/livewire-workflows:^0.1

# Clear caches
php artisan config:clear
php artisan route:clear

# If you migrated the database, rollback migrations
php artisan migrate:rollback
```

Always test rollbacks in a staging environment first!
