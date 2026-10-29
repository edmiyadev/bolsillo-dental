<?php

use App\Enums\RoleEnum;
use App\Filament\Clusters\Settings\Pages\EditClinicProfile;
use App\Models\Clinic;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

it('renders and updates the clinic profile in the tenant panel', function (): void {
    seed([RoleSeeder::class, PermissionSeeder::class]);

    $clinic = Clinic::factory()->create([
        'name' => 'Clínica Dental Sonrisa',
        'email' => 'contacto@sonrisa.com',
        'phone' => '809-555-0100',
        'rnc' => '131000999',
        'address' => 'Av. 27 de Febrero #50, Santo Domingo',
    ]);

    $user = User::factory()->create();
    $user->assignRole(RoleEnum::SuperAdmin->value);

    $this->actingAs($user)
        ->get('/services')
        ->assertOk();

    $this->actingAs($user)
        ->get(EditClinicProfile::getUrl())
        ->assertOk();

    Livewire::test(EditClinicProfile::class)
        ->assertOk()
        ->assertFormSet([
            'name' => 'Clínica Dental Sonrisa',
            'email' => 'contacto@sonrisa.com',
            'phone' => '809-555-0100',
            'rnc' => '131000999',
            'address' => 'Av. 27 de Febrero #50, Santo Domingo',
        ])
        ->fillForm([
            'name' => 'Clínica Dental Sonrisa Plus',
            'email' => 'info@sonrisaplus.com',
            'phone' => '809-555-0200',
            'rnc' => '131000888',
            'address' => 'Calle Gustavo Mejía Ricart #12, Santo Domingo',
        ])
        ->call('save')
        ->assertNotified()
        ->assertHasNoFormErrors();

    assertDatabaseHas(Clinic::class, [
        'id' => $clinic->id,
        'name' => 'Clínica Dental Sonrisa Plus',
        'email' => 'info@sonrisaplus.com',
        'phone' => '809-555-0200',
        'rnc' => '131000888',
        'address' => 'Calle Gustavo Mejía Ricart #12, Santo Domingo',
    ]);
});
