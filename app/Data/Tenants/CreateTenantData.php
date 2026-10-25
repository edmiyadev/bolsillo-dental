<?php

namespace App\Data\Tenants;

use Illuminate\Support\Str;

readonly class CreateTenantData
{
    /**
     * @param  array<int, string>  $domains
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email,
        public bool $isActive,
        public string $databaseName,
        public array $domains,
        public bool $createInitialUser = false,
        public ?string $adminName = null,
        public ?string $adminEmail = null,
        public ?string $adminPassword = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $id = Str::slug((string) ($data['id'] ?? $data['name'] ?? ''));
        $defaultDbName = (string) config('tenancy.database.prefix', 'bolsillo_tenant_').Str::replace('-', '_', $id);
        $databaseName = filled($data['tenancy_db_name'] ?? null)
            ? (string) $data['tenancy_db_name']
            : $defaultDbName;

        $rawDomains = $data['domains'] ?? [];
        if (is_string($rawDomains)) {
            $rawDomains = [$rawDomains];
        }

        $domains = [];
        foreach ((array) $rawDomains as $domainItem) {
            $domainValue = is_array($domainItem) ? ($domainItem['domain'] ?? '') : $domainItem;
            $normalized = Str::lower(trim((string) $domainValue));
            if ($normalized !== '') {
                $domains[] = $normalized;
            }
        }

        if ($domains === [] && filled($data['domain'] ?? null)) {
            $domains[] = Str::lower(trim((string) $data['domain']));
        }

        return new self(
            id: $id,
            name: trim((string) $data['name']),
            email: filled($data['email'] ?? null) ? trim((string) $data['email']) : null,
            isActive: (bool) ($data['is_active'] ?? true),
            databaseName: $databaseName,
            domains: array_values(array_unique($domains)),
            createInitialUser: (bool) ($data['create_initial_user'] ?? false),
            adminName: filled($data['admin_name'] ?? null) ? trim((string) $data['admin_name']) : null,
            adminEmail: filled($data['admin_email'] ?? null) ? trim((string) $data['admin_email']) : null,
            adminPassword: filled($data['admin_password'] ?? null) ? (string) $data['admin_password'] : null,
        );
    }
}
