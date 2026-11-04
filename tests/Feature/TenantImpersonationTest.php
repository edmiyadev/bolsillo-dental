<?php

use App\Actions\Tenants\CreateTenantAction;
use App\Actions\Tenants\EnsureTenantSupportUserAction;
use App\Data\Tenants\CreateTenantData;
use App\Enums\RoleEnum;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Stancl\Tenancy\Database\Models\ImpersonationToken;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    Tenant::query()->get()->each(function (Tenant $tenant): void {
        $dbPath = database_path($tenant->databaseName());
        if (file_exists($dbPath)) {
            @unlink($dbPath);
        }
    });
});

function createTestTenant(string $id = 'clinica-test', string $domain = 'clinica-test.localhost'): Tenant
{
    $client = Client::factory()->create();

    $data = CreateTenantData::fromArray([
        'id' => $id,
        'client_id' => $client->id,
        'name' => 'Clínica Test',
        'email' => "contacto@{$id}.com",
        'phone' => '809-555-0000',
        'rnc' => '101010101',
        'address' => 'Av. Principal #1',
        'is_active' => true,
        'tenancy_db_name' => "test_{$id}.sqlite",
        'domain' => $domain,
        'create_initial_user' => true,
        'admin_name' => 'Dr. Admin',
        'admin_email' => "admin@{$id}.com",
        'admin_password' => 'secret123',
    ]);

    return app(CreateTenantAction::class)->handle($data);
}

it('ensures support user is provisioned in the tenant database with admin role and verified email', function (): void {
    $tenant = createTestTenant();

    /** @var EnsureTenantSupportUserAction $action */
    $action = app(EnsureTenantSupportUserAction::class);

    $user = $action->handle($tenant);

    expect($user->email)->toBe('soporte@bolsillodental.com')
        ->and($user->hasVerifiedEmail())->toBeTrue();

    $tenant->run(function () use ($user): void {
        $tenantUser = User::query()->where('email', 'soporte@bolsillodental.com')->first();
        expect($tenantUser)->not->toBeNull()
            ->and($tenantUser->id)->toBe($user->id)
            ->and($tenantUser->hasRole(RoleEnum::Admin->value))->toBeTrue();
    });

    // Calling it again should be idempotent and return the same user
    $secondCallUser = $action->handle($tenant);
    expect($secondCallUser->id)->toBe($user->id);
});

it('generates an impersonation token on the central database', function (): void {
    $tenant = createTestTenant();
    $supportUser = app(EnsureTenantSupportUserAction::class)->handle($tenant);

    $token = tenancy()->impersonate($tenant, (string) $supportUser->id, 'http://clinica-test.localhost', 'web');

    expect($token)->toBeInstanceOf(ImpersonationToken::class)
        ->and($token->tenant_id)->toBe($tenant->id)
        ->and($token->user_id)->toBe((string) $supportUser->id)
        ->and($token->redirect_url)->toBe('http://clinica-test.localhost');

    $this->assertDatabaseHas('tenant_user_impersonation_tokens', [
        'token' => $token->token,
        'tenant_id' => $tenant->id,
        'user_id' => (string) $supportUser->id,
    ]);
});

it('logs in the support user and consumes the token when accessing the impersonate route on tenant domain', function (): void {
    $tenant = createTestTenant();
    $supportUser = app(EnsureTenantSupportUserAction::class)->handle($tenant);

    $token = tenancy()->impersonate($tenant, (string) $supportUser->id, 'http://clinica-test.localhost', 'web');

    $response = $this->get("http://clinica-test.localhost/impersonate/{$token->token}");

    $response->assertRedirect('http://clinica-test.localhost');

    $this->assertDatabaseMissing('tenant_user_impersonation_tokens', [
        'token' => $token->token,
    ]);

    $tenant->run(function () use ($supportUser): void {
        expect(Auth::guard('web')->id())->toBe($supportUser->id);
    });
});

it('rejects expired impersonation tokens with a 403 response', function (): void {
    $tenant = createTestTenant();
    $supportUser = app(EnsureTenantSupportUserAction::class)->handle($tenant);

    $token = tenancy()->impersonate($tenant, (string) $supportUser->id, '/', 'web');

    Carbon::setTestNow(now()->addMinutes(2));

    $response = $this->get("http://clinica-test.localhost/impersonate/{$token->token}");

    $response->assertStatus(403);

    Carbon::setTestNow();
});

it('rejects impersonation tokens used on a different tenant domain', function (): void {
    $tenantA = createTestTenant('clinica-a', 'clinica-a.localhost');
    $tenantB = createTestTenant('clinica-b', 'clinica-b.localhost');

    $supportUserA = app(EnsureTenantSupportUserAction::class)->handle($tenantA);

    $token = tenancy()->impersonate($tenantA, (string) $supportUserA->id, '/', 'web');

    // Attempting to consume Tenant A's token on Tenant B's domain
    $response = $this->get("http://clinica-b.localhost/impersonate/{$token->token}");

    $response->assertStatus(403);
});
