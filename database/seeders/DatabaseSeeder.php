<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::factory()->create([
            'name' => 'superadmin',
            'email' => 'superadmin@email.com',
            'email_verified_at' => now(),
        ]);

        Role::findOrCreate(RoleEnum::SuperAdmin->value, 'web');
        $user->assignRole(RoleEnum::SuperAdmin->value);
    }
}
