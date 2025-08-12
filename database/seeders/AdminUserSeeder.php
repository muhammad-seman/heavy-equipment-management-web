<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Super Administrator',
                'email' => 'admin@heavy-equipment.com',
                'password' => Hash::make('admin123'),
                'employee_id' => 'ADM001',
                'phone' => '+62812345678',
                'status' => 'active',
                'email_verified_at' => now(),
                'role' => 'super-admin'
            ],
            [
                'name' => 'System Administrator',
                'email' => 'sysadmin@heavy-equipment.com',
                'password' => Hash::make('sysadmin123'),
                'employee_id' => 'ADM002',
                'phone' => '+62812345679',
                'status' => 'active',
                'email_verified_at' => now(),
                'role' => 'admin'
            ],
            [
                'name' => 'Mine Supervisor',
                'email' => 'supervisor@heavy-equipment.com',
                'password' => Hash::make('supervisor123'),
                'employee_id' => 'SUP001',
                'phone' => '+62812345680',
                'status' => 'active',
                'email_verified_at' => now(),
                'role' => 'supervisor'
            ]
        ];

        foreach ($users as $userData) {
            $role = $userData['role'];
            unset($userData['role']);

            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            // Assign role to user
            $roleModel = Role::where('slug', $role)->first();
            if ($roleModel) {
                $user->roles()->syncWithoutDetaching([$roleModel->id => [
                    'assigned_at' => now(),
                    'assigned_by' => null
                ]]);
            }
        }
    }
}
