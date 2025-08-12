<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EquipmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Equipment Categories
        $categories = [
            ['name' => 'Excavators', 'code' => 'EXC', 'description' => 'Hydraulic excavators and backhoes', 'icon' => 'excavator.svg'],
            ['name' => 'Bulldozers', 'code' => 'BUL', 'description' => 'Track and wheel bulldozers', 'icon' => 'bulldozer.svg'],
            ['name' => 'Dump Trucks', 'code' => 'DMP', 'description' => 'Mining dump trucks and haulers', 'icon' => 'dump-truck.svg'],
            ['name' => 'Loaders', 'code' => 'LOD', 'description' => 'Wheel and track loaders', 'icon' => 'loader.svg'],
            ['name' => 'Cranes', 'code' => 'CRN', 'description' => 'Mobile and crawler cranes', 'icon' => 'crane.svg'],
        ];

        foreach ($categories as $category) {
            DB::table('equipment_categories')->insert(array_merge($category, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }

        // Manufacturers
        $manufacturers = [
            ['name' => 'Caterpillar Inc.', 'code' => 'CAT', 'country' => 'United States', 'website' => 'https://www.caterpillar.com'],
            ['name' => 'Komatsu Ltd.', 'code' => 'KOM', 'country' => 'Japan', 'website' => 'https://www.komatsu.com'],
            ['name' => 'Liebherr Group', 'code' => 'LIE', 'country' => 'Germany', 'website' => 'https://www.liebherr.com'],
            ['name' => 'Volvo Construction Equipment', 'code' => 'VOL', 'country' => 'Sweden', 'website' => 'https://www.volvoce.com'],
            ['name' => 'Hitachi Construction Machinery', 'code' => 'HIT', 'country' => 'Japan', 'website' => 'https://www.hitachicm.com'],
            ['name' => 'John Deere', 'code' => 'JD', 'country' => 'United States', 'website' => 'https://www.deere.com'],
        ];

        foreach ($manufacturers as $manufacturer) {
            DB::table('manufacturers')->insert(array_merge($manufacturer, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }

        // Equipment Types
        $equipmentTypes = [
            // Excavators
            ['category_id' => 1, 'name' => 'Hydraulic Excavator 20-30 Tons', 'code' => 'EXC-20-30', 'operating_weight_min' => 20000, 'operating_weight_max' => 30000, 'engine_power_min' => 120, 'engine_power_max' => 200, 'bucket_capacity_min' => 1.0, 'bucket_capacity_max' => 1.8],
            ['category_id' => 1, 'name' => 'Hydraulic Excavator 30-50 Tons', 'code' => 'EXC-30-50', 'operating_weight_min' => 30000, 'operating_weight_max' => 50000, 'engine_power_min' => 200, 'engine_power_max' => 350, 'bucket_capacity_min' => 1.5, 'bucket_capacity_max' => 3.0],
            
            // Bulldozers
            ['category_id' => 2, 'name' => 'Track Bulldozer Medium', 'code' => 'BUL-MED', 'operating_weight_min' => 15000, 'operating_weight_max' => 25000, 'engine_power_min' => 150, 'engine_power_max' => 250],
            ['category_id' => 2, 'name' => 'Track Bulldozer Large', 'code' => 'BUL-LRG', 'operating_weight_min' => 25000, 'operating_weight_max' => 45000, 'engine_power_min' => 250, 'engine_power_max' => 450],
            
            // Dump Trucks
            ['category_id' => 3, 'name' => 'Articulated Dump Truck', 'code' => 'DMP-ART', 'operating_weight_min' => 35000, 'operating_weight_max' => 55000, 'engine_power_min' => 300, 'engine_power_max' => 500],
            ['category_id' => 3, 'name' => 'Rigid Dump Truck', 'code' => 'DMP-RIG', 'operating_weight_min' => 100000, 'operating_weight_max' => 400000, 'engine_power_min' => 1500, 'engine_power_max' => 4000],
            
            // Loaders
            ['category_id' => 4, 'name' => 'Wheel Loader Medium', 'code' => 'LOD-MED', 'operating_weight_min' => 15000, 'operating_weight_max' => 25000, 'engine_power_min' => 150, 'engine_power_max' => 300, 'bucket_capacity_min' => 2.5, 'bucket_capacity_max' => 4.5],
            ['category_id' => 4, 'name' => 'Wheel Loader Large', 'code' => 'LOD-LRG', 'operating_weight_min' => 25000, 'operating_weight_max' => 50000, 'engine_power_min' => 300, 'engine_power_max' => 600, 'bucket_capacity_min' => 4.0, 'bucket_capacity_max' => 8.0],
        ];

        foreach ($equipmentTypes as $type) {
            DB::table('equipment_types')->insert(array_merge($type, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }

        // Sample Equipment
        $equipment = [
            [
                'equipment_type_id' => 1,
                'manufacturer_id' => 1,
                'asset_number' => 'CAT-EXC-001',
                'serial_number' => 'CAT123456789',
                'model' => '320D2',
                'year_manufactured' => 2020,
                'purchase_date' => '2020-03-15',
                'operating_weight' => 22000,
                'engine_power' => 122,
                'bucket_capacity' => 1.2,
                'total_operating_hours' => 2450.5,
                'last_service_hours' => 2400.0,
                'next_service_hours' => 2500.0,
                'status' => 'active',
                'ownership_type' => 'owned',
                'purchase_price' => 285000.00,
                'current_book_value' => 220000.00,
            ],
            [
                'equipment_type_id' => 2,
                'manufacturer_id' => 2,
                'asset_number' => 'KOM-EXC-002',
                'serial_number' => 'KOM987654321',
                'model' => 'PC450-8',
                'year_manufactured' => 2019,
                'purchase_date' => '2019-08-20',
                'operating_weight' => 45000,
                'engine_power' => 272,
                'bucket_capacity' => 2.2,
                'total_operating_hours' => 3850.0,
                'last_service_hours' => 3800.0,
                'next_service_hours' => 4000.0,
                'status' => 'active',
                'ownership_type' => 'owned',
                'purchase_price' => 520000.00,
                'current_book_value' => 385000.00,
            ],
            [
                'equipment_type_id' => 3,
                'manufacturer_id' => 1,
                'asset_number' => 'CAT-BUL-003',
                'serial_number' => 'CATBUL456789',
                'model' => 'D6T',
                'year_manufactured' => 2021,
                'purchase_date' => '2021-01-10',
                'operating_weight' => 20500,
                'engine_power' => 185,
                'total_operating_hours' => 1250.0,
                'last_service_hours' => 1200.0,
                'next_service_hours' => 1500.0,
                'status' => 'maintenance',
                'ownership_type' => 'owned',
                'purchase_price' => 420000.00,
                'current_book_value' => 365000.00,
            ],
            [
                'equipment_type_id' => 5,
                'manufacturer_id' => 3,
                'asset_number' => 'LIE-DMP-004',
                'serial_number' => 'LIE789456123',
                'model' => 'T282C',
                'year_manufactured' => 2018,
                'purchase_date' => '2018-06-30',
                'operating_weight' => 365000,
                'engine_power' => 2700,
                'total_operating_hours' => 8500.5,
                'last_service_hours' => 8400.0,
                'next_service_hours' => 8750.0,
                'status' => 'active',
                'ownership_type' => 'owned',
                'purchase_price' => 4500000.00,
                'current_book_value' => 2800000.00,
            ],
            [
                'equipment_type_id' => 7,
                'manufacturer_id' => 4,
                'asset_number' => 'VOL-LOD-005',
                'serial_number' => 'VOL456123789',
                'model' => 'L150H',
                'year_manufactured' => 2022,
                'purchase_date' => '2022-04-12',
                'operating_weight' => 22800,
                'engine_power' => 268,
                'bucket_capacity' => 4.2,
                'total_operating_hours' => 650.0,
                'last_service_hours' => 500.0,
                'next_service_hours' => 750.0,
                'status' => 'active',
                'ownership_type' => 'leased',
                'lease_start_date' => '2022-04-15',
                'lease_end_date' => '2025-04-15',
                'lease_cost_monthly' => 18500.00,
            ],
        ];

        foreach ($equipment as $item) {
            DB::table('equipment')->insert(array_merge($item, [
                'created_at' => now(),
                'updated_at' => now(),
                'status_changed_at' => now(),
            ]));
        }

        // Inspection Templates
        $inspectionTemplates = [
            [
                'equipment_type_id' => 1,
                'name' => 'Daily Pre-Operation Check - Excavator',
                'description' => 'Standard daily inspection before operating excavator',
                'inspection_type' => 'pre_operation',
                'estimated_duration_minutes' => 15,
                'is_mandatory' => true,
            ],
            [
                'equipment_type_id' => 1,
                'name' => 'Weekly Safety Inspection - Excavator',
                'description' => 'Comprehensive weekly safety inspection for excavators',
                'inspection_type' => 'weekly',
                'estimated_duration_minutes' => 45,
                'is_mandatory' => true,
            ],
            [
                'equipment_type_id' => 3,
                'name' => 'Daily Pre-Operation Check - Bulldozer',
                'description' => 'Standard daily inspection before operating bulldozer',
                'inspection_type' => 'pre_operation',
                'estimated_duration_minutes' => 20,
                'is_mandatory' => true,
            ],
            [
                'equipment_type_id' => 5,
                'name' => 'Daily Pre-Operation Check - Dump Truck',
                'description' => 'Standard daily inspection before operating dump truck',
                'inspection_type' => 'pre_operation',
                'estimated_duration_minutes' => 25,
                'is_mandatory' => true,
            ],
        ];

        foreach ($inspectionTemplates as $template) {
            DB::table('inspection_templates')->insert(array_merge($template, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }

        // System Settings
        $systemSettings = [
            ['key_name' => 'maintenance_reminder_days', 'value' => '7', 'data_type' => 'integer', 'category' => 'maintenance', 'description' => 'Days before maintenance due to send reminder'],
            ['key_name' => 'inspection_overdue_hours', 'value' => '24', 'data_type' => 'integer', 'category' => 'inspection', 'description' => 'Hours after which inspection is considered overdue'],
            ['key_name' => 'fuel_efficiency_threshold', 'value' => '15.5', 'data_type' => 'decimal', 'category' => 'operations', 'description' => 'Fuel efficiency threshold (L/hour)'],
            ['key_name' => 'max_operating_hours_per_day', 'value' => '12', 'data_type' => 'integer', 'category' => 'operations', 'description' => 'Maximum operating hours per day'],
            ['key_name' => 'system_backup_enabled', 'value' => 'true', 'data_type' => 'boolean', 'category' => 'system', 'description' => 'Enable automatic system backup'],
            ['key_name' => 'notification_email_enabled', 'value' => 'true', 'data_type' => 'boolean', 'category' => 'notifications', 'description' => 'Enable email notifications'],
        ];

        foreach ($systemSettings as $setting) {
            DB::table('system_settings')->insert(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }
    }
}