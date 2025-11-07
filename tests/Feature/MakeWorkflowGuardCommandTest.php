<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Pixelworxio\LivewireWorkflows\Commands\MakeWorkflowGuardCommand;

beforeEach(function () {
    $this->guardsPath = app_path('Guards');

    if (File::isDirectory($this->guardsPath)) {
        File::deleteDirectory($this->guardsPath);
    }
});

afterEach(function () {
    if (File::isDirectory($this->guardsPath)) {
        File::deleteDirectory($this->guardsPath);
    }
});

test('creates guard class with correct structure', function () {
    $this->artisan(MakeWorkflowGuardCommand::class, ['name' => 'EmailVerified'])
        ->assertSuccessful();

    $guardPath = app_path('Guards/EmailVerifiedGuard.php');

    expect(File::exists($guardPath))->toBeTrue();

    $contents = File::get($guardPath);

    expect($contents)
        ->toContain('namespace App\Guards;')
        ->toContain('use Illuminate\Http\Request;')
        ->toContain('use Pixelworxio\LivewireWorkflows\Contracts\GuardContract;')
        ->toContain('class EmailVerifiedGuard implements GuardContract')
        ->toContain('public function passes(Request $request): bool')
        ->toContain('return true;')
        ->toContain('public function onEnter(Request $request): void')
        ->toContain('public function onExit(Request $request): void');
});

test('automatically appends Guard suffix if missing', function () {
    $this->artisan(MakeWorkflowGuardCommand::class, ['name' => 'EmailVerified'])
        ->assertSuccessful();

    $guardPath = app_path('Guards/EmailVerifiedGuard.php');

    expect(File::exists($guardPath))->toBeTrue();
});

test('does not duplicate Guard suffix', function () {
    $this->artisan(MakeWorkflowGuardCommand::class, ['name' => 'EmailVerifiedGuard'])
        ->assertSuccessful();

    $guardPath = app_path('Guards/EmailVerifiedGuard.php');
    $contents = File::get($guardPath);

    expect($contents)->toContain('class EmailVerifiedGuard implements GuardContract');
    expect($contents)->not->toContain('EmailVerifiedGuardGuard');
});

test('creates Guards directory if it does not exist', function () {
    expect(File::isDirectory(app_path('Guards')))->toBeFalse();

    $this->artisan(MakeWorkflowGuardCommand::class, ['name' => 'TestGuard'])
        ->assertSuccessful();

    expect(File::isDirectory(app_path('Guards')))->toBeTrue();
});

test('fails if guard already exists', function () {
    File::ensureDirectoryExists(app_path('Guards'));
    File::put(app_path('Guards/ExistingGuard.php'), '<?php');

    $this->artisan(MakeWorkflowGuardCommand::class, ['name' => 'Existing'])
        ->assertFailed();
});

test('creates guards with various naming conventions', function () {
    $names = [
        'EmailVerified' => 'EmailVerifiedGuard',
        'email-verified' => 'EmailVerifiedGuard',
        'email_verified' => 'EmailVerifiedGuard',
        'EMAILVERIFIED' => 'EMAILVERIFIEDGuard',
    ];

    foreach ($names as $input => $expected) {
        if (File::isDirectory(app_path('Guards'))) {
            File::deleteDirectory(app_path('Guards'));
        }

        $this->artisan(MakeWorkflowGuardCommand::class, ['name' => $input])
            ->assertSuccessful();

        $guardPath = app_path("Guards/{$expected}.php");
        expect(File::exists($guardPath))->toBeTrue("Failed for input: {$input}");
    }
});

test('guard class has proper method signatures', function () {
    $this->artisan(MakeWorkflowGuardCommand::class, ['name' => 'ProfileComplete'])
        ->assertSuccessful();

    $guardPath = app_path('Guards/ProfileCompleteGuard.php');
    $contents = File::get($guardPath);

    expect($contents)
        ->toMatch('/public function passes\(Request \$request\): bool/')
        ->toMatch('/public function onEnter\(Request \$request\): void/')
        ->toMatch('/public function onExit\(Request \$request\): void/');
});

test('guard passes method returns true by default', function () {
    $this->artisan(MakeWorkflowGuardCommand::class, ['name' => 'DefaultBehavior'])
        ->assertSuccessful();

    $guardPath = app_path('Guards/DefaultBehaviorGuard.php');
    $contents = File::get($guardPath);

    expect($contents)->toContain('return true;');
});

test('guard has proper PHP declarations', function () {
    $this->artisan(MakeWorkflowGuardCommand::class, ['name' => 'StrictTypes'])
        ->assertSuccessful();

    $guardPath = app_path('Guards/StrictTypesGuard.php');
    $contents = File::get($guardPath);

    expect($contents)
        ->toStartWith("<?php\n\ndeclare(strict_types=1);");
});

test('displays helpful messages after creation', function () {
    $this->artisan(MakeWorkflowGuardCommand::class, ['name' => 'HelpfulMessage'])
        ->expectsOutput('Implement the passes() method to define when the step should be skipped.')
        ->expectsOutput('Remember: passes() returns true = skip step, false = show step.')
        ->assertSuccessful();
});

test('generated guard can be instantiated and implements contract', function () {
    $this->artisan(MakeWorkflowGuardCommand::class, ['name' => 'Instantiable'])
        ->assertSuccessful();

    require_once app_path('Guards/InstantiableGuard.php');

    $guard = new \App\Guards\InstantiableGuard;

    expect($guard)->toBeInstanceOf(\Pixelworxio\LivewireWorkflows\Contracts\GuardContract::class);
    expect($guard->passes(request()))->toBe(true);
});

test('creates guard in subdirectory with forward slash', function () {
    $this->artisan(MakeWorkflowGuardCommand::class, ['name' => 'Onboarding/EmailVerified'])
        ->assertSuccessful();

    $guardPath = app_path('Guards/Onboarding/EmailVerifiedGuard.php');

    expect(File::exists($guardPath))->toBeTrue();

    $contents = File::get($guardPath);

    expect($contents)
        ->toContain('namespace App\Guards\Onboarding;')
        ->toContain('class EmailVerifiedGuard implements GuardContract');
});

test('creates guard in subdirectory with backslash', function () {
    $this->artisan(MakeWorkflowGuardCommand::class, ['name' => 'Checkout\PaymentMethod'])
        ->assertSuccessful();

    $guardPath = app_path('Guards/Checkout/PaymentMethodGuard.php');

    expect(File::exists($guardPath))->toBeTrue();

    $contents = File::get($guardPath);

    expect($contents)
        ->toContain('namespace App\Guards\Checkout;')
        ->toContain('class PaymentMethodGuard implements GuardContract');
});

test('creates guard in nested subdirectories', function () {
    $this->artisan(MakeWorkflowGuardCommand::class, ['name' => 'Onboarding/Steps/ProfileComplete'])
        ->assertSuccessful();

    $guardPath = app_path('Guards/Onboarding/Steps/ProfileCompleteGuard.php');

    expect(File::exists($guardPath))->toBeTrue();

    $contents = File::get($guardPath);

    expect($contents)
        ->toContain('namespace App\Guards\Onboarding\Steps;')
        ->toContain('class ProfileCompleteGuard implements GuardContract');
});

test('creates subdirectories if they do not exist', function () {
    expect(File::isDirectory(app_path('Guards/NewFolder')))->toBeFalse();

    $this->artisan(MakeWorkflowGuardCommand::class, ['name' => 'NewFolder/TestGuard'])
        ->assertSuccessful();

    expect(File::isDirectory(app_path('Guards/NewFolder')))->toBeTrue();
    expect(File::exists(app_path('Guards/NewFolder/TestGuard.php')))->toBeTrue();
});

test('handles kebab-case subdirectories', function () {
    $this->artisan(MakeWorkflowGuardCommand::class, ['name' => 'email-verification/email-verified'])
        ->assertSuccessful();

    $guardPath = app_path('Guards/EmailVerification/EmailVerifiedGuard.php');

    expect(File::exists($guardPath))->toBeTrue();

    $contents = File::get($guardPath);

    expect($contents)->toContain('namespace App\Guards\EmailVerification;');
});

test('handles snake_case subdirectories', function () {
    $this->artisan(MakeWorkflowGuardCommand::class, ['name' => 'user_onboarding/profile_complete'])
        ->assertSuccessful();

    $guardPath = app_path('Guards/UserOnboarding/ProfileCompleteGuard.php');

    expect(File::exists($guardPath))->toBeTrue();

    $contents = File::get($guardPath);

    expect($contents)->toContain('namespace App\Guards\UserOnboarding;');
});

test('fails if guard exists in subdirectory', function () {
    File::ensureDirectoryExists(app_path('Guards/Onboarding'));
    File::put(app_path('Guards/Onboarding/ExistingGuard.php'), '<?php');

    $this->artisan(MakeWorkflowGuardCommand::class, ['name' => 'Onboarding/Existing'])
        ->assertFailed();
});
