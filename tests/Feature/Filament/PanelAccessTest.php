<?php

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows authenticated users to access the admin panel in production environment', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $user = User::factory()->create();
    $panel = Filament::getPanel('admin');

    expect($user->canAccessPanel($panel))->toBeTrue();

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk();
});
