<?php

use App\Filament\Central\Resources\Clients\Pages\CreateClient;
use App\Filament\Central\Resources\Clients\Pages\EditClient;
use App\Filament\Central\Resources\Clients\Pages\ListClients;
use App\Models\Client;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertModelMissing;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('central'));
    $this->actingAs(User::factory()->create());
});

it('lists and searches clients in the central panel table', function (): void {
    $clientA = Client::factory()->create(['name' => 'Grupo Odontológico Dominicano']);
    $clientB = Client::factory()->create(['name' => 'Inversiones Médicas del Sur']);

    Livewire::test(ListClients::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$clientA, $clientB])
        ->searchTable('Dominicano')
        ->assertCanSeeTableRecords([$clientA])
        ->assertCanNotSeeTableRecords([$clientB]);
});

it('creates a new client from the central panel', function (): void {
    Livewire::test(CreateClient::class)
        ->fillForm([
            'name' => 'Dr. Roberto Méndez',
            'email' => 'roberto@mendez.do',
            'phone' => '809-555-0199',
            'document_number' => '001-1234567-8',
            'address' => 'Av. Abraham Lincoln #45, Santo Domingo',
            'is_active' => true,
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertDatabaseHas(Client::class, [
        'name' => 'Dr. Roberto Méndez',
        'email' => 'roberto@mendez.do',
        'phone' => '809-555-0199',
        'document_number' => '001-1234567-8',
        'address' => 'Av. Abraham Lincoln #45, Santo Domingo',
        'is_active' => true,
    ]);
});

it('validates required fields and unique email when creating a client', function (): void {
    Client::factory()->create(['email' => 'duplicado@cliente.do']);

    Livewire::test(CreateClient::class)
        ->fillForm([
            'name' => null,
            'email' => 'duplicado@cliente.do',
        ])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'email' => 'unique',
        ])
        ->assertNotNotified();
});

it('updates and deletes an existing client', function (): void {
    $client = Client::factory()->create([
        'name' => 'Cliente Original',
        'email' => 'original@cliente.do',
    ]);

    Livewire::test(EditClient::class, ['record' => $client->id])
        ->fillForm([
            'name' => 'Cliente Actualizado',
            'email' => 'actualizado@cliente.do',
            'is_active' => false,
        ])
        ->call('save')
        ->assertNotified()
        ->assertHasNoFormErrors();

    assertDatabaseHas(Client::class, [
        'id' => $client->id,
        'name' => 'Cliente Actualizado',
        'email' => 'actualizado@cliente.do',
        'is_active' => false,
    ]);

    Livewire::test(EditClient::class, ['record' => $client->id])
        ->callAction(DeleteAction::class)
        ->assertNotified();

    assertModelMissing($client);
});
