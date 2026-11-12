<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Rename user_id to created_by on services and commission_records
        if (Schema::hasTable('services')) {
            Schema::table('services', function (Blueprint $table) {
                if (Schema::hasColumn('services', 'user_id') && ! Schema::hasColumn('services', 'created_by')) {
                    $table->renameColumn('user_id', 'created_by');
                } elseif (! Schema::hasColumn('services', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->index();
                }

                if (! Schema::hasColumn('services', 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('commission_records')) {
            Schema::table('commission_records', function (Blueprint $table) {
                if (Schema::hasColumn('commission_records', 'user_id') && ! Schema::hasColumn('commission_records', 'created_by')) {
                    $table->renameColumn('user_id', 'created_by');
                } elseif (! Schema::hasColumn('commission_records', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->index();
                }

                if (! Schema::hasColumn('commission_records', 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable()->index();
                }
            });
        }

        // 2. Add created_by and updated_by to remaining tenant tables
        $tables = [
            'users',
            'patients',
            'appointments',
            'branches',
            'cubicles',
            'clinics',
            'patient_medical_histories',
            'roles',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (! Schema::hasColumn($tableName, 'created_by')) {
                        $table->unsignedBigInteger('created_by')->nullable()->index();
                    }
                    if (! Schema::hasColumn($tableName, 'updated_by')) {
                        $table->unsignedBigInteger('updated_by')->nullable()->index();
                    }
                });
            }
        }

        // 3. Backfill author for existing historical records
        $firstUserId = DB::table('users')->orderBy('id')->value('id');

        if ($firstUserId) {
            $authorableTables = [
                'patients',
                'appointments',
                'branches',
                'cubicles',
                'clinics',
                'services',
                'commission_records',
            ];

            foreach ($authorableTables as $tableName) {
                if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'created_by')) {
                    DB::table($tableName)
                        ->whereNull('created_by')
                        ->update(['created_by' => $firstUserId]);
                }
            }
        }

        // 4. Ensure new granular permissions (view_all_*) exist and are assigned to existing roles
        if (class_exists(PermissionSeeder::class) && Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            (new PermissionSeeder)->run();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('services')) {
            Schema::table('services', function (Blueprint $table) {
                if (Schema::hasColumn('services', 'created_by') && ! Schema::hasColumn('services', 'user_id')) {
                    $table->renameColumn('created_by', 'user_id');
                }
                if (Schema::hasColumn('services', 'updated_by')) {
                    $table->dropColumn('updated_by');
                }
            });
        }

        if (Schema::hasTable('commission_records')) {
            Schema::table('commission_records', function (Blueprint $table) {
                if (Schema::hasColumn('commission_records', 'created_by') && ! Schema::hasColumn('commission_records', 'user_id')) {
                    $table->renameColumn('created_by', 'user_id');
                }
                if (Schema::hasColumn('commission_records', 'updated_by')) {
                    $table->dropColumn('updated_by');
                }
            });
        }

        $tables = [
            'users',
            'patients',
            'appointments',
            'branches',
            'cubicles',
            'clinics',
            'patient_medical_histories',
            'roles',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (Schema::hasColumn($tableName, 'updated_by')) {
                        $table->dropColumn('updated_by');
                    }
                    if (Schema::hasColumn($tableName, 'created_by')) {
                        $table->dropColumn('created_by');
                    }
                });
            }
        }
    }
};
