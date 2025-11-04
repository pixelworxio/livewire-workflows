<?php

use Pixelworxio\LivewireWorkflows\Facades\Workflow;

/*
|--------------------------------------------------------------------------
| Workflow Routes
|--------------------------------------------------------------------------
|
| Define your multi-step workflows here using the readable DSL.
| Each workflow automatically registers its entry and step routes.
|
| Example:
|
| Workflow::flow('onboarding')
|     ->entersAt(name: 'onboarding.start', path: '/onboarding')
|     ->finishesAt('dashboard')
|     ->historyMode('stack')
|     ->step('verify-email')
|         ->goTo(\App\Livewire\Onboarding\VerifyEmail::class)
|         ->unlessPasses(\App\Guards\EmailVerifiedGuard::class)
|         ->order(10)
|     ->step('profile')
|         ->goTo(\App\Livewire\Onboarding\EditProfile::class)
|         ->unlessPasses(\App\Guards\ProfileCompletedGuard::class)
|         ->order(20);
|
*/
