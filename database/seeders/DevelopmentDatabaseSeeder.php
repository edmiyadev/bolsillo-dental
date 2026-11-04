<?php

namespace Database\Seeders;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Actions\Appointments\UpdateAppointmentAction;
use App\Actions\Services\CreateDefaultServicesForUserAction;
use App\Actions\Tenants\CreateTenantAction;
use App\Data\Appointments\CreateAppointmentData;
use App\Data\Tenants\CreateTenantData;
use App\Enums\AppointmentStatus;
use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use App\Enums\CubicleStatus;
use App\Enums\DayOfWeek;
use App\Enums\DentalSpecialty;
use App\Enums\IdentificationType;
use App\Enums\RoleEnum;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\CommissionRecord;
use App\Models\Cubicle;
use App\Models\Patient;
use App\Models\Role;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DevelopmentDatabaseSeeder extends Seeder
{
    /**
     * Run the development database seeders.
     * This seeder is intended for local/testing development workflows
     * and is NOT executed automatically by migrate:fresh --seed.
     */
    public function run(): void
    {
        $this->command?->info('Iniciando carga de datos de desarrollo...');

        // 1. Contexto Central: Asegurar superadmin central
        $centralSuperAdmin = User::query()->firstOrCreate(
            ['email' => 'superadmin@email.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
        Role::findOrCreate(RoleEnum::SuperAdmin->value, 'web');
        $centralSuperAdmin->syncRoles([RoleEnum::SuperAdmin->value]);

        // 2. Contexto Central: Cliente corporativo
        $client = Client::query()->firstOrCreate(
            ['email' => 'corporativo@sonrisa.com'],
            [
                'name' => 'Inversiones Dentales Sonrisa SRL',
                'phone' => '809-555-0100',
                'document_number' => '131234567',
                'address' => 'Av. 27 de Febrero #100, Ensanche Naco, Santo Domingo',
            ],
        );

        // 3. Contexto Central: Crear o recuperar el Tenant
        $tenantId = 'sonrisa';
        $dbName = config('database.default') === 'sqlite'
            ? 'bolsillo_tenant_sonrisa.sqlite'
            : 'bolsillo_tenant_sonrisa';

        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant) {
            $this->command?->info('Limpiando base de datos huérfana previa y aprovisionando tenant...');

            // Si la base de datos física del tenant ya existía de un reset anterior, la limpiamos
            if (config('database.default') === 'sqlite') {
                $sqlitePath = database_path($dbName);
                if (file_exists($sqlitePath)) {
                    @unlink($sqlitePath);
                }
            } else {
                try {
                    DB::statement("DROP DATABASE IF EXISTS \"{$dbName}\" WITH (FORCE)");
                } catch (\Throwable) {
                    try {
                        DB::statement("DROP DATABASE IF EXISTS \"{$dbName}\"");
                    } catch (\Throwable) {
                    }
                }
            }

            $tenant = app(CreateTenantAction::class)->handle(CreateTenantData::fromArray([
                'id' => $tenantId,
                'client_id' => $client->id,
                'name' => 'Clínica Dental Sonrisa',
                'email' => 'contacto@clinicasonrisa.com',
                'phone' => '809-555-0100',
                'rnc' => '131234567',
                'address' => 'Av. 27 de Febrero #100, Ensanche Naco, Santo Domingo',
                'is_active' => true,
                'tenancy_db_name' => $dbName,
                'domains' => ['sonrisa.localhost', 'sonrisa'],
                'create_initial_user' => true,
                'admin_name' => 'Dra. María Gómez',
                'admin_email' => 'admin@sonrisa.com',
                'admin_password' => 'password',
            ]));
        } else {
            // Asegurar que los dominios existan y que el tenant tenga las últimas migraciones
            foreach (['sonrisa.localhost', 'sonrisa'] as $domain) {
                $tenant->domains()->firstOrCreate(['domain' => $domain]);
            }

            Artisan::call('tenants:migrate', [
                '--tenants' => [$tenant->id],
                '--force' => true,
            ]);
        }

        // 4. Contexto del Tenant: Poblar la clínica dental
        $this->command?->info('Poblando datos dentro del tenant (clínica, colaboradores, pacientes, comisiones)...');
        $tenant->run(function (): void {
            $this->seedTenantData();
        });

        $this->command?->info('¡Datos de desarrollo cargados exitosamente!');
        $this->command?->table(
            ['Contexto', 'Acceso / URL', 'Credenciales'],
            [
                ['Panel Central', 'http://localhost:8080/central', 'superadmin@email.com / password'],
                ['Panel Tenant (Admin)', 'http://sonrisa.localhost:8080', 'admin@sonrisa.com / password'],
                ['Panel Tenant (Odontólogo)', 'http://sonrisa.localhost:8080', 'dr.ramirez@sonrisa.com / password'],
                ['Panel Tenant (Recepcionista)', 'http://sonrisa.localhost:8080', 'recepcion@sonrisa.com / password'],
                ['Panel Tenant (Asistente)', 'http://sonrisa.localhost:8080', 'asistente@sonrisa.com / password'],
            ]
        );
    }

    /**
     * Poblar entidades dentro de la base de datos de la clínica dental.
     */
    protected function seedTenantData(): void
    {
        // Actualizar datos del perfil de la clínica
        $clinic = Clinic::query()->first() ?? Clinic::query()->create([
            'name' => 'Clínica Dental Sonrisa',
            'phone' => '809-555-0100',
            'email' => 'contacto@clinicasonrisa.com',
            'rnc' => '131234567',
            'address' => 'Av. 27 de Febrero #100, Ensanche Naco, Santo Domingo',
        ]);

        $clinic->update([
            'name' => 'Clínica Dental Sonrisa',
            'phone' => '809-555-0100',
            'email' => 'contacto@clinicasonrisa.com',
            'rnc' => '131234567',
            'address' => 'Av. 27 de Febrero #100, Ensanche Naco, Santo Domingo',
        ]);

        // 1. Sucursales
        $mainBranch = $clinic->mainBranch ?? $clinic->branches()->where('is_main', true)->first();
        if (! $mainBranch) {
            $mainBranch = Branch::query()->create([
                'clinic_id' => $clinic->id,
                'name' => 'Sede Principal Naco',
                'code' => 'MAIN',
                'phone' => '809-555-0100',
                'address' => 'Av. 27 de Febrero #100, Naco',
                'opening_hours' => DayOfWeek::defaultSchedule(),
                'is_main' => true,
                'is_active' => true,
            ]);
        } else {
            $mainBranch->update([
                'name' => 'Sede Principal Naco',
                'code' => 'MAIN',
                'phone' => '809-555-0100',
                'address' => 'Av. 27 de Febrero #100, Naco',
            ]);
        }

        $piantiniBranch = Branch::query()->firstOrCreate(
            ['clinic_id' => $clinic->id, 'code' => 'PIAN-01'],
            [
                'name' => 'Sucursal Piantini',
                'phone' => '809-555-0199',
                'address' => 'Calle Federico Geraldino #45, Piantini',
                'opening_hours' => DayOfWeek::defaultSchedule(),
                'is_main' => false,
                'is_active' => true,
            ],
        );

        // 2. Cubículos en Sede Principal
        $cubiclesMainData = [
            [
                'name' => 'Cubículo 1 - Odontología General',
                'specialty_types' => [DentalSpecialty::General->value, DentalSpecialty::PediatricDentistry->value],
                'status' => CubicleStatus::Active,
            ],
            [
                'name' => 'Cubículo 2 - Ortodoncia y Estética',
                'specialty_types' => [DentalSpecialty::Orthodontics->value, DentalSpecialty::Aesthetics->value],
                'specialty_schedules' => [
                    [
                        'day' => DayOfWeek::Tuesday->value,
                        'specialty' => DentalSpecialty::Orthodontics->value,
                        'start_time' => '09:00',
                        'end_time' => '17:00',
                    ],
                    [
                        'day' => DayOfWeek::Thursday->value,
                        'specialty' => DentalSpecialty::Orthodontics->value,
                        'start_time' => '09:00',
                        'end_time' => '17:00',
                    ],
                ],
                'status' => CubicleStatus::Active,
            ],
            [
                'name' => 'Cubículo 3 - Quirófano e Implantes',
                'specialty_types' => [DentalSpecialty::OralSurgery->value, DentalSpecialty::Implantology->value],
                'status' => CubicleStatus::Active,
            ],
            [
                'name' => 'Cubículo 4 - Endodoncia',
                'specialty_types' => [DentalSpecialty::Endodontics->value, DentalSpecialty::Periodontics->value],
                'status' => CubicleStatus::Maintenance,
            ],
        ];

        foreach ($cubiclesMainData as $index => $cubicleData) {
            Cubicle::query()->firstOrCreate(
                ['branch_id' => $mainBranch->id, 'name' => $cubicleData['name']],
                array_merge($cubicleData, [
                    'branch_id' => $mainBranch->id,
                    'code' => sprintf('CUB-%02d', $index + 1),
                ]),
            );
        }

        // Cubículos en Sucursal Piantini
        $cubiclesPiantiniData = [
            [
                'name' => 'Cubículo 1 - Atención Integral',
                'specialty_types' => [DentalSpecialty::General->value, DentalSpecialty::Aesthetics->value],
                'status' => CubicleStatus::Active,
            ],
            [
                'name' => 'Cubículo 2 - Rehabilitación y Prótesis',
                'specialty_types' => [DentalSpecialty::Prosthodontics->value, DentalSpecialty::General->value],
                'status' => CubicleStatus::Active,
            ],
        ];

        foreach ($cubiclesPiantiniData as $index => $cubicleData) {
            Cubicle::query()->firstOrCreate(
                ['branch_id' => $piantiniBranch->id, 'name' => $cubicleData['name']],
                array_merge($cubicleData, [
                    'branch_id' => $piantiniBranch->id,
                    'code' => sprintf('CUB-%02d', $index + 1),
                ]),
            );
        }

        // 3. Colaboradores del Tenant con sus respectivos Roles
        $staffMembers = [
            [
                'name' => 'Dra. María Gómez',
                'email' => 'admin@sonrisa.com',
                'role' => RoleEnum::Admin->value,
                'specialties' => [DentalSpecialty::General->value],
            ],
            [
                'name' => 'Dr. Carlos Ramírez',
                'email' => 'dr.ramirez@sonrisa.com',
                'role' => RoleEnum::Dentist->value,
                'specialties' => [
                    DentalSpecialty::General->value,
                    DentalSpecialty::Aesthetics->value,
                    DentalSpecialty::OralSurgery->value,
                ],
            ],
            [
                'name' => 'Dra. Laura Morales',
                'email' => 'dra.morales@sonrisa.com',
                'role' => RoleEnum::Dentist->value,
                'specialties' => [
                    DentalSpecialty::Orthodontics->value,
                    DentalSpecialty::Implantology->value,
                    DentalSpecialty::Endodontics->value,
                    DentalSpecialty::General->value,
                ],
            ],
            [
                'name' => 'Carmen Santana',
                'email' => 'recepcion@sonrisa.com',
                'role' => RoleEnum::Receptionist->value,
                'specialties' => null,
            ],
            [
                'name' => 'Ana Martínez',
                'email' => 'asistente@sonrisa.com',
                'role' => RoleEnum::DentalAssistant->value,
                'specialties' => null,
            ],
        ];

        $usersByEmail = [];
        foreach ($staffMembers as $staff) {
            $user = User::query()->firstOrCreate(
                ['email' => $staff['email']],
                [
                    'name' => $staff['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'specialties' => $staff['specialties'],
                ],
            );
            $user->update(['specialties' => $staff['specialties']]);
            $user->syncRoles([$staff['role']]);
            $usersByEmail[$staff['email']] = $user;
        }

        // 3.1 Asignar Sucursales y Horarios a los Colaboradores
        $morningMainSchedule = array_map(
            fn (DayOfWeek $day): array => [
                'day' => $day->value,
                'is_open' => $day !== DayOfWeek::Sunday,
                'open_time' => $day !== DayOfWeek::Sunday ? '08:00' : null,
                'close_time' => match ($day) {
                    DayOfWeek::Sunday => null,
                    DayOfWeek::Saturday => '13:00',
                    default => '12:00',
                },
            ],
            DayOfWeek::cases(),
        );

        $afternoonPiantiniSchedule = array_map(
            fn (DayOfWeek $day): array => [
                'day' => $day->value,
                'is_open' => ! in_array($day, [DayOfWeek::Saturday, DayOfWeek::Sunday], true),
                'open_time' => ! in_array($day, [DayOfWeek::Saturday, DayOfWeek::Sunday], true) ? '13:00' : null,
                'close_time' => ! in_array($day, [DayOfWeek::Saturday, DayOfWeek::Sunday], true) ? '18:00' : null,
            ],
            DayOfWeek::cases(),
        );

        $usersByEmail['admin@sonrisa.com']->branchSchedules()->updateOrCreate(
            ['branch_id' => $mainBranch->id],
            ['schedule' => DayOfWeek::defaultSchedule(), 'is_active' => true],
        );

        $usersByEmail['dr.ramirez@sonrisa.com']->branchSchedules()->updateOrCreate(
            ['branch_id' => $mainBranch->id],
            ['schedule' => $morningMainSchedule, 'is_active' => true],
        );
        $usersByEmail['dr.ramirez@sonrisa.com']->branchSchedules()->updateOrCreate(
            ['branch_id' => $piantiniBranch->id],
            ['schedule' => $afternoonPiantiniSchedule, 'is_active' => true],
        );

        $usersByEmail['dra.morales@sonrisa.com']->branchSchedules()->updateOrCreate(
            ['branch_id' => $piantiniBranch->id],
            ['schedule' => DayOfWeek::defaultSchedule(), 'is_active' => true],
        );

        $usersByEmail['recepcion@sonrisa.com']->branchSchedules()->updateOrCreate(
            ['branch_id' => $mainBranch->id],
            ['schedule' => DayOfWeek::defaultSchedule(), 'is_active' => true],
        );

        $usersByEmail['asistente@sonrisa.com']->branchSchedules()->updateOrCreate(
            ['branch_id' => $mainBranch->id],
            ['schedule' => DayOfWeek::defaultSchedule(), 'is_active' => true],
        );

        // 4. Servicios para cada Doctor
        $drRamirez = $usersByEmail['dr.ramirez@sonrisa.com'];
        $servicesRamirez = [
            ['name' => 'Limpieza Dental Profunda (Profilaxis)', 'default_price' => 2500.00, 'default_commission' => 30.00, 'commission_type' => CommissionType::Percentage, 'specialty' => DentalSpecialty::General, 'duration_minutes' => 30],
            ['name' => 'Restauración Resina Simple', 'default_price' => 1800.00, 'default_commission' => 25.00, 'commission_type' => CommissionType::Percentage, 'specialty' => DentalSpecialty::General, 'duration_minutes' => 45],
            ['name' => 'Blanqueamiento Dental LED', 'default_price' => 8500.00, 'default_commission' => 35.00, 'commission_type' => CommissionType::Percentage, 'specialty' => DentalSpecialty::Aesthetics, 'duration_minutes' => 60],
            ['name' => 'Extracción Dental Simple', 'default_price' => 2000.00, 'default_commission' => 600.00, 'commission_type' => CommissionType::Fixed, 'specialty' => DentalSpecialty::OralSurgery, 'duration_minutes' => 45],
        ];

        $ramirezCreatedServices = [];
        foreach ($servicesRamirez as $serv) {
            $ramirezCreatedServices[] = Service::withoutGlobalScopes()->updateOrCreate(
                ['user_id' => $drRamirez->id, 'name' => $serv['name']],
                $serv,
            );
        }

        $draMorales = $usersByEmail['dra.morales@sonrisa.com'];
        $servicesMorales = [
            ['name' => 'Instalación Brackets Autoligados', 'default_price' => 28000.00, 'default_commission' => 40.00, 'commission_type' => CommissionType::Percentage, 'specialty' => DentalSpecialty::Orthodontics, 'duration_minutes' => 60],
            ['name' => 'Control Mensual de Ortodoncia', 'default_price' => 2200.00, 'default_commission' => 50.00, 'commission_type' => CommissionType::Percentage, 'specialty' => DentalSpecialty::Orthodontics, 'duration_minutes' => 30],
            ['name' => 'Implante Dental Titanio (Fase 1)', 'default_price' => 45000.00, 'default_commission' => 30.00, 'commission_type' => CommissionType::Percentage, 'specialty' => DentalSpecialty::Implantology, 'duration_minutes' => 90],
            ['name' => 'Endodoncia Molar Unirradicular', 'default_price' => 12000.00, 'default_commission' => 4000.00, 'commission_type' => CommissionType::Fixed, 'specialty' => DentalSpecialty::Endodontics, 'duration_minutes' => 60],
        ];

        $moralesCreatedServices = [];
        foreach ($servicesMorales as $serv) {
            $moralesCreatedServices[] = Service::withoutGlobalScopes()->updateOrCreate(
                ['user_id' => $draMorales->id, 'name' => $serv['name']],
                $serv,
            );
        }

        // Dra. María Gómez (Admin) recibe servicios por defecto
        app(CreateDefaultServicesForUserAction::class)->handle($usersByEmail['admin@sonrisa.com']);

        // 5. Pacientes Realistas
        $patientsData = [
            ['first_name' => 'Alejandro', 'last_name' => 'Valdez', 'document_type' => IdentificationType::IdentityCard, 'identification_number' => '00118273645', 'email' => 'alejandro.valdez@email.com', 'phone' => '809-555-2011', 'address' => 'Bella Vista, Santo Domingo'],
            ['first_name' => 'Patricia', 'last_name' => 'Rosario', 'document_type' => IdentificationType::IdentityCard, 'identification_number' => '40283726154', 'email' => 'patricia.rosario@email.com', 'phone' => '809-555-2022', 'address' => 'Piantini, Santo Domingo'],
            ['first_name' => 'Marcos', 'last_name' => 'Báez', 'document_type' => IdentificationType::IdentityCard, 'identification_number' => '00192837465', 'email' => 'marcos.baez@email.com', 'phone' => '809-555-2033', 'address' => 'Naco, Santo Domingo'],
            ['first_name' => 'Carla', 'last_name' => 'Jiménez', 'document_type' => IdentificationType::IdentityCard, 'identification_number' => '40271625344', 'email' => 'carla.jimenez@email.com', 'phone' => '809-555-2044', 'address' => 'Evaristo Morales, Santo Domingo'],
            ['first_name' => 'Luis', 'last_name' => 'Mendoza', 'document_type' => IdentificationType::Passport, 'identification_number' => 'RD987654321', 'email' => 'luis.mendoza@email.com', 'phone' => '829-555-3011', 'address' => 'Gazcue, Santo Domingo'],
            ['first_name' => 'Sofía', 'last_name' => 'Peralta', 'document_type' => IdentificationType::IdentityCard, 'identification_number' => '00183746251', 'email' => 'sofia.peralta@email.com', 'phone' => '809-555-2055', 'address' => 'Mirador Sur, Santo Domingo'],
            ['first_name' => 'Gabriel', 'last_name' => 'Cruz', 'document_type' => IdentificationType::IdentityCard, 'identification_number' => '40294837261', 'email' => 'gabriel.cruz@email.com', 'phone' => '809-555-2066', 'address' => 'Arroyo Hondo, Santo Domingo'],
            ['first_name' => 'Valeria', 'last_name' => 'Castillo', 'document_type' => IdentificationType::IdentityCard, 'identification_number' => '00174625341', 'email' => 'valeria.castillo@email.com', 'phone' => '809-555-2077', 'address' => 'Paraíso, Santo Domingo'],
        ];

        $createdPatients = [];
        foreach ($patientsData as $patient) {
            $createdPatients[] = Patient::query()->firstOrCreate(
                ['identification_number' => $patient['identification_number']],
                $patient,
            );
        }

        // 5.1 Citas de Ejemplo (Agenda Clínica)
        if (Appointment::query()->count() === 0 && count($createdPatients) >= 4) {
            $mainCubicle1 = Cubicle::query()->where('branch_id', $mainBranch->id)->where('code', 'CUB-01')->first();
            $mainCubicle2 = Cubicle::query()->where('branch_id', $mainBranch->id)->where('code', 'CUB-02')->first();
            $piantiniCubicle1 = Cubicle::query()->where('branch_id', $piantiniBranch->id)->where('code', 'CUB-01')->first();

            $nextMonday = Carbon::now()->next(Carbon::MONDAY);

            if ($mainCubicle1) {
                $apt1 = app(CreateAppointmentAction::class)->handle(
                    CreateAppointmentData::fromArray([
                        'branch_id' => $mainBranch->id,
                        'patient_id' => $createdPatients[0]->id,
                        'dentist_id' => $drRamirez->id,
                        'cubicle_id' => $mainCubicle1->id,
                        'service_id' => $ramirezCreatedServices[0]->id,
                        'starts_at' => $nextMonday->copy()->setTime(9, 0),
                        'duration_minutes' => 30,
                        'observations' => 'Paciente solicita revisión periódica junto con profilaxis.',
                    ])
                );
                app(UpdateAppointmentAction::class)->handle($apt1, ['status' => AppointmentStatus::Confirmed]);

                app(CreateAppointmentAction::class)->handle(
                    CreateAppointmentData::fromArray([
                        'branch_id' => $mainBranch->id,
                        'patient_id' => $createdPatients[1]->id,
                        'dentist_id' => $drRamirez->id,
                        'cubicle_id' => $mainCubicle1->id,
                        'service_id' => null,
                        'custom_reason' => 'Evaluación por sensibilidad dental aguda',
                        'starts_at' => $nextMonday->copy()->setTime(10, 0),
                        'duration_minutes' => 30,
                        'observations' => 'Refiere molestia al frío en cuadrante inferior izquierdo.',
                    ])
                );
            }

            if ($mainCubicle2) {
                app(CreateAppointmentAction::class)->handle(
                    CreateAppointmentData::fromArray([
                        'branch_id' => $mainBranch->id,
                        'patient_id' => $createdPatients[2]->id,
                        'dentist_id' => $drRamirez->id,
                        'cubicle_id' => $mainCubicle2->id,
                        'service_id' => $ramirezCreatedServices[2]->id,
                        'starts_at' => $nextMonday->copy()->setTime(10, 30),
                        'duration_minutes' => 60,
                    ])
                );
            }

            if ($piantiniCubicle1) {
                $apt4 = app(CreateAppointmentAction::class)->handle(
                    CreateAppointmentData::fromArray([
                        'branch_id' => $piantiniBranch->id,
                        'patient_id' => $createdPatients[3]->id,
                        'dentist_id' => $draMorales->id,
                        'cubicle_id' => $piantiniCubicle1->id,
                        'service_id' => null,
                        'custom_reason' => 'Consulta inicial de diagnóstico integral',
                        'starts_at' => $nextMonday->copy()->setTime(14, 0),
                        'duration_minutes' => 45,
                    ])
                );
                app(UpdateAppointmentAction::class)->handle($apt4, ['status' => AppointmentStatus::Confirmed]);
            }
        }

        // 6. Registros de Comisiones Realistas (pendientes y pagadas en los últimos 30 días)
        if (CommissionRecord::withoutGlobalScopes()->count() === 0) {
            $now = Carbon::now();

            $commissionSamples = [
                // Doctor Ramírez
                ['user' => $drRamirez, 'service' => $ramirezCreatedServices[0], 'status' => CommissionStatus::Paid, 'days_ago' => 20, 'notes' => 'Profilaxis completa paciente Alejandro Valdez'],
                ['user' => $drRamirez, 'service' => $ramirezCreatedServices[1], 'status' => CommissionStatus::Paid, 'days_ago' => 15, 'notes' => 'Resina oclusal pieza 36'],
                ['user' => $drRamirez, 'service' => $ramirezCreatedServices[2], 'status' => CommissionStatus::Pending, 'days_ago' => 3, 'notes' => 'Blanqueamiento estético superior e inferior'],
                ['user' => $drRamirez, 'service' => $ramirezCreatedServices[3], 'status' => CommissionStatus::Pending, 'days_ago' => 1, 'notes' => 'Extracción tercer molar superior derecho'],

                // Doctora Morales
                ['user' => $draMorales, 'service' => $moralesCreatedServices[0], 'status' => CommissionStatus::Paid, 'days_ago' => 25, 'notes' => 'Caso ortodoncia Clase II - Inicial'],
                ['user' => $draMorales, 'service' => $moralesCreatedServices[1], 'status' => CommissionStatus::Paid, 'days_ago' => 10, 'notes' => 'Ajuste mensual y cambio de arcos'],
                ['user' => $draMorales, 'service' => $moralesCreatedServices[2], 'status' => CommissionStatus::Pending, 'days_ago' => 5, 'notes' => 'Colocación implante zona 46'],
                ['user' => $draMorales, 'service' => $moralesCreatedServices[3], 'status' => CommissionStatus::Pending, 'days_ago' => 2, 'notes' => 'Tratamiento conducto molar inferior'],
            ];

            foreach ($commissionSamples as $sample) {
                CommissionRecord::query()->create([
                    'user_id' => $sample['user']->id,
                    'service_id' => $sample['service']->id,
                    'price' => $sample['service']->default_price,
                    'commission' => $sample['service']->default_commission,
                    'commission_type' => $sample['service']->commission_type,
                    'status' => $sample['status'],
                    'notes' => $sample['notes'],
                    'worked_at' => $now->copy()->subDays($sample['days_ago'])->format('Y-m-d'),
                ]);
            }
        }
    }
}
