<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Super Admin
        $superAdmin = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@heavyequipment.com',
            'phone' => '+1234567890',
            'employee_id' => 'SA001',
            'department' => 'IT',
            'position' => 'System Administrator',
            'certification_level' => 'expert',
            'is_active' => true,
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $superAdmin->assignRole('super_admin');

        // Create Admin
        $admin = User::create([
            'first_name' => 'John',
            'last_name' => 'Manager',
            'email' => 'admin@heavyequipment.com',
            'phone' => '+1234567891',
            'employee_id' => 'ADM001',
            'department' => 'Operations',
            'position' => 'Operations Manager',
            'certification_level' => 'advanced',
            'is_active' => true,
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        // Create Supervisor
        $supervisor = User::create([
            'first_name' => 'Sarah',
            'last_name' => 'Wilson',
            'email' => 'supervisor@heavyequipment.com',
            'phone' => '+1234567892',
            'employee_id' => 'SUP001',
            'department' => 'Operations',
            'position' => 'Site Supervisor',
            'certification_level' => 'advanced',
            'is_active' => true,
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $supervisor->assignRole('supervisor');

        // Create Inspector
        $inspector = User::create([
            'first_name' => 'Mike',
            'last_name' => 'Rodriguez',
            'email' => 'inspector@heavyequipment.com',
            'phone' => '+1234567893',
            'employee_id' => 'INS001',
            'department' => 'Quality Control',
            'position' => 'Equipment Inspector',
            'certification_level' => 'intermediate',
            'certification_expiry' => now()->addYear(),
            'is_active' => true,
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $inspector->assignRole('inspector');

        // Create Operators
        $operators = [
            [
                'first_name' => 'David',
                'last_name' => 'Thompson',
                'email' => 'operator1@heavyequipment.com',
                'phone' => '+1234567894',
                'employee_id' => 'OP001',
                'department' => 'Operations',
                'position' => 'Heavy Equipment Operator',
                'certification_level' => 'intermediate',
                'certification_expiry' => now()->addMonths(6),
            ],
            [
                'first_name' => 'Robert',
                'last_name' => 'Brown',
                'email' => 'operator2@heavyequipment.com',
                'phone' => '+1234567895',
                'employee_id' => 'OP002',
                'department' => 'Operations',
                'position' => 'Excavator Operator',
                'certification_level' => 'advanced',
                'certification_expiry' => now()->addYear(),
            ],
            [
                'first_name' => 'Lisa',
                'last_name' => 'Garcia',
                'email' => 'operator3@heavyequipment.com',
                'phone' => '+1234567896',
                'employee_id' => 'OP003',
                'department' => 'Operations',
                'position' => 'Bulldozer Operator',
                'certification_level' => 'intermediate',
                'certification_expiry' => now()->addMonths(8),
            ],
        ];

        foreach ($operators as $operatorData) {
            $operator = User::create(array_merge($operatorData, [
                'is_active' => true,
                'password' => 'password',
                'email_verified_at' => now(),
            ]));
            $operator->assignRole('operator');
        }

        // Create Maintenance Technicians
        $maintenanceTechs = [
            [
                'first_name' => 'James',
                'last_name' => 'Miller',
                'email' => 'maintenance1@heavyequipment.com',
                'phone' => '+1234567897',
                'employee_id' => 'MT001',
                'department' => 'Maintenance',
                'position' => 'Senior Maintenance Technician',
                'certification_level' => 'advanced',
                'certification_expiry' => now()->addYear(),
            ],
            [
                'first_name' => 'Carlos',
                'last_name' => 'Martinez',
                'email' => 'maintenance2@heavyequipment.com',
                'phone' => '+1234567898',
                'employee_id' => 'MT002',
                'department' => 'Maintenance',
                'position' => 'Hydraulic Specialist',
                'certification_level' => 'expert',
                'certification_expiry' => now()->addMonths(18),
            ],
        ];

        foreach ($maintenanceTechs as $techData) {
            $tech = User::create(array_merge($techData, [
                'is_active' => true,
                'password' => 'password',
                'email_verified_at' => now(),
            ]));
            $tech->assignRole('maintenance_tech');
        }

        // Create Viewer
        $viewer = User::create([
            'first_name' => 'Emily',
            'last_name' => 'Davis',
            'email' => 'viewer@heavyequipment.com',
            'phone' => '+1234567899',
            'employee_id' => 'VW001',
            'department' => 'Accounting',
            'position' => 'Cost Analyst',
            'certification_level' => 'basic',
            'is_active' => true,
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $viewer->assignRole('viewer');

        // Assign equipment to some operators
        $this->assignEquipmentToOperators();
    }

    private function assignEquipmentToOperators(): void
    {
        // Assign equipment to operators
        $equipmentAssignments = [
            1 => 4, // CAT-EXC-001 to David Thompson (OP001)
            2 => 5, // KOM-EXC-002 to Robert Brown (OP002)
            4 => 6, // LIE-DMP-004 to Lisa Garcia (OP003)
            5 => 4, // VOL-LOD-005 to David Thompson (OP001)
        ];

        foreach ($equipmentAssignments as $equipmentId => $userId) {
            \DB::table('equipment')
                ->where('id', $equipmentId)
                ->update(['assigned_to_user' => $userId]);
        }
    }
}