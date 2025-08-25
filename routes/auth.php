<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use App\Livewire\Auth\AuthForm;
use App\Livewire\Auth\ConfirmPassword;
use App\Livewire\Auth\VerifyEmail;
use Illuminate\Support\Facades\Route;
use Maize\MagicLogin\Facades\MagicLink;

Route::middleware('guest')->group(function () {
    // 🔐 MAGIC LOGIN AUTHENTICATION
    Route::get('login', AuthForm::class)->name('login');
    Route::get('auth', AuthForm::class)->name('auth');

    // 📧 MAGIC LINK HANDLER - Auto-login from email links
    MagicLink::route();

    // Optional: Traditional auth routes (not needed for magic login)
    // Route::get('forgot-password', ForgotPassword::class)->name('password.request');
    // Route::get('reset-password/{token}', ResetPassword::class)->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', VerifyEmail::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::get('confirm-password', ConfirmPassword::class)
        ->name('password.confirm');
});

Route::post('logout', App\Livewire\Actions\Logout::class)
    ->name('logout');
