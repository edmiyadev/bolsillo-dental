<?php

declare(strict_types=1);

namespace App\Tenancy\DatabaseManagers;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager as BaseSQLiteDatabaseManager;

class SQLiteDatabaseManager extends BaseSQLiteDatabaseManager
{
    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        try {
            return file_put_contents(database_path($tenant->database()->getName()), '') !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        $path = database_path($tenant->database()->getName());

        if (! file_exists($path)) {
            return true;
        }

        try {
            return unlink($path);
        } catch (\Throwable) {
            return false;
        }
    }
}
