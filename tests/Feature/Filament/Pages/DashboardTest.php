<?php

use App\Enums\RoleEnum;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\CommissionRecords\CommissionRecordResource;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Support\Icons\Heroicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

it('renders dashboard with action to create commission record', function () {
    seed([RoleSeeder::class, PermissionSeeder::class]);

    $user = User::factory()->create();
    $user->assignRole(RoleEnum::SuperAdmin->value);
    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->assertOk()
        ->assertActionExists('createCommissionRecord')
        ->assertActionHasLabel('createCommissionRecord', __('New commission record'))
        ->assertActionHasIcon('createCommissionRecord', Heroicon::OutlinedPlus)
        ->assertActionHasUrl('createCommissionRecord', CommissionRecordResource::getUrl('create'));
});
