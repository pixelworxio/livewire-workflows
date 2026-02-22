# Upgrade Guide

## Upgrading to v1.0.0

### Overview

v1.0.0 is the first stable release. It introduces **workflow resume links** and makes the **Eloquent state repository the default**. The session repository is deprecated and no longer recommended.

---

### Breaking Change: Eloquent is Now the Default Repository

The default `WORKFLOWS_REPOSITORY` has changed from `session` to `eloquent`.

**Who is affected:** Anyone using session-based state management (the previous default).

**Why:** Session state is tied to a browser session and cannot survive across devices or after cookies are cleared. This makes it incompatible with resume links and unreliable for production workflows. Eloquent state persists in the database and is the correct backend for production use.

---

### Upgrading an Existing Installation

Run the built-in upgrade command:

```bash
php artisan workflows:upgrade
```

The command will:

1. Warn you that in-progress session-based workflow state **cannot be migrated** to the database
2. Ask for your confirmation before making any changes
3. Publish the `workflow_states` migration (if not already published)
4. Run `php artisan migrate` automatically
5. Update your published `config/livewire-workflows.php` to default to `eloquent`

> **Important:** After running the upgrade command, set `WORKFLOWS_REPOSITORY=eloquent` in your `.env` file.

**Note on existing in-progress workflows:** Session state is keyed by the Laravel session ID, which has no association with a real user record. It cannot be automatically migrated. Any users who were mid-way through a workflow will restart from the beginning after this change.

---

### New Installation

`workflows:install` now always sets up the database. The `--with-db` flag has been removed.

```bash
php artisan workflows:install
php artisan migrate
```

---

### New Feature: Resume Links

Generate a signed URL that drops a user directly into their current incomplete workflow step — ideal for abandoned onboarding or checkout emails.

```php
// In a notification, mailable, or controller:
$url = workflow('onboarding')->resumeUrlFor(user: $user);
$url = workflow('onboarding')->resumeUrlFor(user: $user, expiresInMinutes: 2880); // 48h

// Guest / explicit key (Eloquent-persisted guest flows):
$url = workflow('checkout')->resumeUrlFor(userKey: 'guest-abc123', expiresInMinutes: 60);
```

**Requirements:**
- Must be using the Eloquent state repository (`WORKFLOWS_REPOSITORY=eloquent`)
- Requires `APP_KEY` to be set (used for HMAC signature)

The signed URL expires after 24 hours by default. Expired or tampered URLs return a 403.

**Configure the default expiry:**
```env
WORKFLOWS_RESUME_EXPIRES=2880  # minutes (default: 1440 = 24h)
```

---

### Deprecated: Session Repository

The `SessionWorkflowStateRepository` class is still present in the codebase but is no longer documented or recommended. It will be removed in a future version.

If your use case genuinely requires session-only state (e.g., fully anonymous flows with no database), the `null` repository remains supported.

---

### Summary of Changes

| What | Before (0.x) | After (v1.0.0) |
|------|--------------|-----------------|
| Default repository | `session` | `eloquent` |
| `workflows:install` | Had `--with-db` flag | Always sets up DB |
| Resume links | Not available | `resumeUrlFor()` |
| `workflows:upgrade` | Not available | New command |
| Session repository | Documented, default | Deprecated |
