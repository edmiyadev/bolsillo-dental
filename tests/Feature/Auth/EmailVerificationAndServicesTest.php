<?php

use App\Models\User;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Auth\Pages\Register;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('sends verification email notification upon registration and waits for verification before creating services', function () {
    Notification::fake();

    Livewire::test(Register::class)
        ->fillForm([
            'name' => 'Dra. Asistente Test',
            'email' => 'asistente@example.com',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'asistente@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo($user, VerifyEmail::class);

    // Services should not be created while email is unverified
    expect($user->services()->count())->toBe(0);
});

it('blocks unverified users from accessing the dashboard and redirects to verification prompt', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(Filament::getUrl())
        ->assertRedirect(route('filament.admin.auth.email-verification.prompt'));
});

it('creates default services when the user verifies their email via the signed verification URL', function () {
    $user = User::factory()->unverified()->create();

    expect($user->services()->count())->toBe(0);

    $verificationUrl = URL::temporarySignedRoute(
        'filament.admin.auth.email-verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
    );

    $this->actingAs($user)
        ->get($verificationUrl)
        ->assertRedirect(Filament::getUrl());

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
    expect($user->services()->count())->toBe(4);
});

it('creates default services when Verified event is dispatched', function () {
    $user = User::factory()->unverified()->create();

    expect($user->services()->count())->toBe(0);

    event(new Verified($user));

    expect($user->services()->count())->toBe(4);
});
