<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\CommissionRecords\CommissionRecordResource;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders dashboard with action to create commission record', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Dashboard::class)
        ->assertOk()
        ->assertActionExists('createCommissionRecord')
        ->assertActionHasLabel('createCommissionRecord', __('New commission record'))
        ->assertActionHasIcon('createCommissionRecord', Heroicon::OutlinedPlus)
        ->assertActionHasUrl('createCommissionRecord', CommissionRecordResource::getUrl('create'));
});
