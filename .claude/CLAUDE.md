# Heavy Equipment Management System - Claude Context

## Project Overview
Sistem manajemen dan inspeksi alat berat pertambangan dengan Laravel Vue starterkit, MySQL, dan RBAC API untuk akses mobile.

## System Architecture
- **Backend**: Laravel 12 dengan API-first approach
- **Frontend**: Vue.js 3 dengan Inertia.js dan SSR
- **Database**: MySQL dengan migrasi terstruktur
- **Authentication**: Laravel Sanctum untuk API tokens
- **Authorization**: Role-Based Access Control (RBAC)

## Core Modules

### 1. RBAC System
- **Users**: email, password, profile data
- **Roles**: admin, supervisor, operator, inspector
- **Permissions**: create, read, update, delete, inspect, report
- **Relationships**: Many-to-Many dengan pivot tables

### 2. Equipment Management
- **Equipment Types**: excavator, bulldozer, dump_truck, crane, loader
- **Equipment**: brand, model, serial_number, year, status, location
- **Status Types**: active, maintenance, repair, retired
- **Ownership**: company-owned, rented, leased

### 3. Inspection System
- **Inspections**: scheduled, unscheduled, pre-operation, post-operation
- **Inspection Items**: checklist items per equipment type
- **Results**: pass, fail, warning, notes, photos
- **Scheduling**: daily, weekly, monthly inspections

### 4. Maintenance System
- **Maintenance Records**: preventive, corrective, emergency
- **Parts & Inventory**: spare parts tracking
- **Costs**: labor, parts, external services
- **Scheduling**: based on hours, kilometers, calendar

## Database Design Rules

### Naming Conventions
- Tables: plural snake_case (users, heavy_equipment)
- Columns: snake_case (created_at, equipment_type_id)
- Foreign Keys: {table_singular}_id (user_id, equipment_id)
- Pivot Tables: alphabetical order (equipment_user, role_permission)

### Required Columns
- All tables: id, created_at, updated_at
- Soft deletes where needed: deleted_at
- User tracking: created_by, updated_by (nullable)
- Status tracking: status, status_changed_at

### Data Integrity
- Foreign key constraints with CASCADE/RESTRICT
- Unique constraints where appropriate
- NOT NULL for required fields
- Default values for status fields

## API Design Principles

### REST Endpoints
```
GET    /api/equipment           - List equipment
POST   /api/equipment           - Create equipment
GET    /api/equipment/{id}      - Show equipment
PUT    /api/equipment/{id}      - Update equipment
DELETE /api/equipment/{id}      - Delete equipment
```

### Response Format
```json
{
  "success": true,
  "data": {...},
  "message": "Operation successful",
  "pagination": {...}
}
```

### Error Handling
- HTTP status codes (200, 201, 400, 401, 403, 404, 422, 500)
- Validation errors dengan field-specific messages
- Consistent error response format

## Security Requirements

### Authentication
- Laravel Sanctum untuk API authentication
- Token-based authentication untuk mobile
- Session-based untuk web interface
- Password reset dengan email verification

### Authorization
- Middleware untuk route protection
- Role-based permissions checking
- Resource-based authorization (policies)
- API rate limiting

### Data Protection
- Input validation dan sanitization
- SQL injection prevention (Eloquent)
- XSS protection
- CSRF protection untuk web forms

## Business Logic Rules

### Equipment Status
- Active equipment dapat di-inspect dan di-maintain
- Equipment dalam maintenance tidak dapat dioperasikan
- Retired equipment hanya read-only
- Status changes harus di-log dengan timestamp

### Inspection Rules
- Pre-operation inspection wajib sebelum equipment digunakan
- Failed inspection membutuhkan immediate action
- Inspection results mempengaruhi equipment status
- Inspector harus memiliki certification untuk equipment type

### Maintenance Scheduling
- Preventive maintenance berdasarkan operating hours atau calendar
- Emergency maintenance menghentikan semua operations
- Parts availability check sebelum schedule maintenance
- Cost approval untuk maintenance diatas threshold

## Testing Strategy
- Unit tests untuk models dan business logic
- Feature tests untuk API endpoints
- Browser tests untuk critical user flows
- Database tests dengan transactions dan rollbacks

## Development Workflow
1. Design database schema dengan migrations
2. Create models dengan relationships
3. Build API controllers dengan validation
4. Implement authorization policies
5. Create seeders untuk test data
6. Test endpoints dengan Postman/Insomnia
7. Build Vue components untuk admin interface
8. Mobile API documentation

## Performance Considerations
- Database indexes untuk frequently queried columns
- Eager loading untuk related data
- API pagination untuk large datasets
- Caching untuk static data (equipment types, roles)
- Queue jobs untuk heavy operations (reports, exports)

## Commands to Remember
- `php artisan migrate` - Run migrations
- `php artisan db:seed` - Run seeders
- `php artisan tinker` - Laravel REPL
- `php artisan test` - Run tests
- `npm run dev` - Development build
- `npm run build` - Production build

## Key Packages
- laravel/sanctum - API authentication
- spatie/laravel-permission - RBAC (if using external package)
- intervention/image - Image processing for inspection photos
- maatwebsite/excel - Excel exports for reports
- laravel/horizon - Queue monitoring (if using Redis)

## Comprehensive Database Schema Design

### Core Tables Structure

#### 1. User Management & RBAC

```sql
-- Users table
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NULL,
    employee_id VARCHAR(50) UNIQUE NULL,
    department VARCHAR(100) NULL,
    position VARCHAR(100) NULL,
    certification_level ENUM('basic', 'intermediate', 'advanced', 'expert') DEFAULT 'basic',
    certification_expiry DATE NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_employee_id (employee_id),
    INDEX idx_certification_level (certification_level),
    INDEX idx_is_active (is_active)
);

-- Roles table
CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    guard_name VARCHAR(50) DEFAULT 'web',
    description TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_guard_name (guard_name)
);

-- Permissions table
CREATE TABLE permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    guard_name VARCHAR(50) DEFAULT 'web',
    module VARCHAR(50) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_module (module)
);

-- User roles pivot
CREATE TABLE user_roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    assigned_by BIGINT UNSIGNED NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_role (user_id, role_id),
    INDEX idx_user_id (user_id),
    INDEX idx_role_id (role_id)
);

-- Role permissions pivot
CREATE TABLE role_permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role_id, permission_id)
);
```

#### 2. Equipment Management

```sql
-- Equipment categories
CREATE TABLE equipment_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT NULL,
    icon VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_name (name)
);

-- Equipment types
CREATE TABLE equipment_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT NULL,
    specifications JSON NULL,
    operating_weight_min DECIMAL(10,2) NULL,
    operating_weight_max DECIMAL(10,2) NULL,
    engine_power_min DECIMAL(8,2) NULL,
    engine_power_max DECIMAL(8,2) NULL,
    bucket_capacity_min DECIMAL(8,2) NULL,
    bucket_capacity_max DECIMAL(8,2) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES equipment_categories(id) ON DELETE RESTRICT,
    INDEX idx_category_id (category_id),
    INDEX idx_code (code),
    INDEX idx_name (name)
);

-- Equipment manufacturers
CREATE TABLE manufacturers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    country VARCHAR(100) NULL,
    website VARCHAR(255) NULL,
    contact_email VARCHAR(255) NULL,
    contact_phone VARCHAR(50) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_code (code)
);

-- Main equipment table
CREATE TABLE equipment (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipment_type_id BIGINT UNSIGNED NOT NULL,
    manufacturer_id BIGINT UNSIGNED NOT NULL,
    asset_number VARCHAR(50) UNIQUE NOT NULL,
    serial_number VARCHAR(100) UNIQUE NOT NULL,
    model VARCHAR(100) NOT NULL,
    year_manufactured YEAR NOT NULL,
    purchase_date DATE NULL,
    warranty_expiry DATE NULL,
    
    -- Technical specifications
    engine_model VARCHAR(100) NULL,
    engine_serial VARCHAR(100) NULL,
    operating_weight DECIMAL(10,2) NULL,
    engine_power DECIMAL(8,2) NULL,
    bucket_capacity DECIMAL(8,2) NULL,
    max_digging_depth DECIMAL(8,2) NULL,
    max_reach DECIMAL(8,2) NULL,
    travel_speed DECIMAL(6,2) NULL,
    fuel_capacity DECIMAL(8,2) NULL,
    
    -- Operational data
    total_operating_hours DECIMAL(10,1) DEFAULT 0,
    total_distance_km DECIMAL(12,2) DEFAULT 0,
    last_service_hours DECIMAL(10,1) DEFAULT 0,
    next_service_hours DECIMAL(10,1) NULL,
    
    -- Status and location
    status ENUM('active', 'maintenance', 'repair', 'standby', 'retired', 'disposal') DEFAULT 'active',
    status_changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status_changed_by BIGINT UNSIGNED NULL,
    status_notes TEXT NULL,
    
    -- Ownership and assignment
    ownership_type ENUM('owned', 'leased', 'rented') DEFAULT 'owned',
    lease_start_date DATE NULL,
    lease_end_date DATE NULL,
    lease_cost_monthly DECIMAL(12,2) NULL,
    assigned_to_user BIGINT UNSIGNED NULL,
    assigned_to_site VARCHAR(100) NULL,
    current_location_lat DECIMAL(10, 8) NULL,
    current_location_lng DECIMAL(11, 8) NULL,
    current_location_address TEXT NULL,
    
    -- Financial data
    purchase_price DECIMAL(15,2) NULL,
    current_book_value DECIMAL(15,2) NULL,
    depreciation_rate DECIMAL(5,2) NULL,
    insurance_policy VARCHAR(100) NULL,
    insurance_expiry DATE NULL,
    
    -- Tracking
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (equipment_type_id) REFERENCES equipment_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (manufacturer_id) REFERENCES manufacturers(id) ON DELETE RESTRICT,
    FOREIGN KEY (assigned_to_user) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (status_changed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_asset_number (asset_number),
    INDEX idx_serial_number (serial_number),
    INDEX idx_equipment_type (equipment_type_id),
    INDEX idx_manufacturer (manufacturer_id),
    INDEX idx_status (status),
    INDEX idx_assigned_user (assigned_to_user),
    INDEX idx_location (current_location_lat, current_location_lng),
    INDEX idx_operating_hours (total_operating_hours),
    INDEX idx_next_service (next_service_hours)
);

-- Equipment documents
CREATE TABLE equipment_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipment_id BIGINT UNSIGNED NOT NULL,
    document_type ENUM('manual', 'certificate', 'invoice', 'insurance', 'lease', 'photo', 'other') NOT NULL,
    title VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT UNSIGNED NULL,
    mime_type VARCHAR(100) NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    is_public BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_equipment_id (equipment_id),
    INDEX idx_document_type (document_type),
    INDEX idx_expires_at (expires_at)
);
```

#### 3. Inspection System

```sql
-- Inspection templates
CREATE TABLE inspection_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipment_type_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    inspection_type ENUM('pre_operation', 'post_operation', 'daily', 'weekly', 'monthly', 'quarterly', 'annual', 'custom') NOT NULL,
    estimated_duration_minutes INT DEFAULT 30,
    is_mandatory BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    version VARCHAR(10) DEFAULT '1.0',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (equipment_type_id) REFERENCES equipment_types(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_equipment_type (equipment_type_id),
    INDEX idx_inspection_type (inspection_type),
    INDEX idx_is_active (is_active)
);

-- Inspection template items (checklist items)
CREATE TABLE inspection_template_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id BIGINT UNSIGNED NOT NULL,
    category VARCHAR(100) NOT NULL,
    item_code VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    instruction TEXT NULL,
    is_critical BOOLEAN DEFAULT FALSE,
    requires_photo BOOLEAN DEFAULT FALSE,
    requires_measurement BOOLEAN DEFAULT FALSE,
    measurement_unit VARCHAR(20) NULL,
    measurement_min_value DECIMAL(10,3) NULL,
    measurement_max_value DECIMAL(10,3) NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    
    FOREIGN KEY (template_id) REFERENCES inspection_templates(id) ON DELETE CASCADE,
    INDEX idx_template_id (template_id),
    INDEX idx_category (category),
    INDEX idx_item_code (item_code),
    INDEX idx_is_critical (is_critical),
    INDEX idx_sort_order (sort_order)
);

-- Inspections
CREATE TABLE inspections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipment_id BIGINT UNSIGNED NOT NULL,
    template_id BIGINT UNSIGNED NOT NULL,
    inspector_id BIGINT UNSIGNED NOT NULL,
    
    -- Scheduling and timing
    scheduled_date DATE NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    duration_minutes INT NULL,
    
    -- Results
    overall_status ENUM('pending', 'in_progress', 'passed', 'failed', 'cancelled') DEFAULT 'pending',
    overall_score DECIMAL(5,2) NULL,
    critical_issues_count INT DEFAULT 0,
    minor_issues_count INT DEFAULT 0,
    
    -- Operational context
    equipment_hours_at_inspection DECIMAL(10,1) NULL,
    weather_conditions VARCHAR(100) NULL,
    work_site_location VARCHAR(255) NULL,
    operator_present BIGINT UNSIGNED NULL,
    
    -- Notes and actions
    inspector_notes TEXT NULL,
    supervisor_notes TEXT NULL,
    recommended_actions TEXT NULL,
    follow_up_required BOOLEAN DEFAULT FALSE,
    follow_up_due_date DATE NULL,
    
    -- Approval workflow
    supervisor_approval_required BOOLEAN DEFAULT FALSE,
    approved_by BIGINT UNSIGNED NULL,
    approved_at TIMESTAMP NULL,
    approval_notes TEXT NULL,
    
    -- Tracking
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES inspection_templates(id) ON DELETE RESTRICT,
    FOREIGN KEY (inspector_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (operator_present) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_equipment_id (equipment_id),
    INDEX idx_template_id (template_id),
    INDEX idx_inspector_id (inspector_id),
    INDEX idx_scheduled_date (scheduled_date),
    INDEX idx_overall_status (overall_status),
    INDEX idx_completed_at (completed_at),
    INDEX idx_follow_up_due (follow_up_due_date)
);

-- Inspection results
CREATE TABLE inspection_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inspection_id BIGINT UNSIGNED NOT NULL,
    template_item_id BIGINT UNSIGNED NOT NULL,
    
    -- Result data
    status ENUM('pass', 'fail', 'warning', 'not_applicable', 'pending') DEFAULT 'pending',
    measurement_value DECIMAL(10,3) NULL,
    notes TEXT NULL,
    
    -- Photos and evidence
    photo_before VARCHAR(500) NULL,
    photo_after VARCHAR(500) NULL,
    additional_photos JSON NULL,
    
    -- Corrective actions
    corrective_action_required BOOLEAN DEFAULT FALSE,
    corrective_action_description TEXT NULL,
    corrective_action_priority ENUM('low', 'medium', 'high', 'critical') NULL,
    corrective_action_due_date DATE NULL,
    corrective_action_completed_at TIMESTAMP NULL,
    corrective_action_completed_by BIGINT UNSIGNED NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (inspection_id) REFERENCES inspections(id) ON DELETE CASCADE,
    FOREIGN KEY (template_item_id) REFERENCES inspection_template_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (corrective_action_completed_by) REFERENCES users(id) ON DELETE SET NULL,
    
    UNIQUE KEY unique_inspection_item (inspection_id, template_item_id),
    INDEX idx_inspection_id (inspection_id),
    INDEX idx_template_item_id (template_item_id),
    INDEX idx_status (status),
    INDEX idx_corrective_action_required (corrective_action_required),
    INDEX idx_corrective_action_due (corrective_action_due_date)
);
```

#### 4. Maintenance System

```sql
-- Maintenance types
CREATE TABLE maintenance_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    category ENUM('preventive', 'corrective', 'emergency', 'overhaul') NOT NULL,
    description TEXT NULL,
    estimated_duration_hours DECIMAL(6,2) NULL,
    requires_shutdown BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_code (code),
    INDEX idx_category (category)
);

-- Maintenance schedules
CREATE TABLE maintenance_schedules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipment_id BIGINT UNSIGNED NOT NULL,
    maintenance_type_id BIGINT UNSIGNED NOT NULL,
    
    -- Scheduling rules
    schedule_type ENUM('hours', 'kilometers', 'calendar', 'condition') NOT NULL,
    interval_hours DECIMAL(10,1) NULL,
    interval_kilometers DECIMAL(10,2) NULL,
    interval_days INT NULL,
    
    -- Triggers
    trigger_hours DECIMAL(10,1) NULL,
    trigger_kilometers DECIMAL(10,2) NULL,
    trigger_date DATE NULL,
    
    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    last_maintenance_date DATE NULL,
    next_due_date DATE NULL,
    next_due_hours DECIMAL(10,1) NULL,
    overdue_tolerance_days INT DEFAULT 7,
    
    -- Notification settings
    advance_notification_days INT DEFAULT 7,
    notification_sent_at TIMESTAMP NULL,
    
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE,
    FOREIGN KEY (maintenance_type_id) REFERENCES maintenance_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_equipment_id (equipment_id),
    INDEX idx_maintenance_type (maintenance_type_id),
    INDEX idx_next_due_date (next_due_date),
    INDEX idx_is_active (is_active)
);

-- Maintenance work orders
CREATE TABLE maintenance_work_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipment_id BIGINT UNSIGNED NOT NULL,
    maintenance_type_id BIGINT UNSIGNED NOT NULL,
    schedule_id BIGINT UNSIGNED NULL,
    
    -- Work order details
    work_order_number VARCHAR(50) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    priority ENUM('low', 'medium', 'high', 'emergency') DEFAULT 'medium',
    
    -- Scheduling
    requested_date DATE NULL,
    scheduled_start_date DATE NULL,
    scheduled_end_date DATE NULL,
    actual_start_date DATE NULL,
    actual_end_date DATE NULL,
    estimated_hours DECIMAL(8,2) NULL,
    actual_hours DECIMAL(8,2) NULL,
    
    -- Status tracking
    status ENUM('draft', 'approved', 'scheduled', 'in_progress', 'completed', 'cancelled', 'on_hold') DEFAULT 'draft',
    status_changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status_changed_by BIGINT UNSIGNED NULL,
    completion_percentage DECIMAL(5,2) DEFAULT 0,
    
    -- Assignment
    assigned_to_team VARCHAR(100) NULL,
    primary_technician BIGINT UNSIGNED NULL,
    supervisor_id BIGINT UNSIGNED NULL,
    
    -- Equipment state
    equipment_hours_at_start DECIMAL(10,1) NULL,
    equipment_hours_at_completion DECIMAL(10,1) NULL,
    
    -- Approval workflow
    requires_approval BOOLEAN DEFAULT FALSE,
    approved_by BIGINT UNSIGNED NULL,
    approved_at TIMESTAMP NULL,
    approval_notes TEXT NULL,
    
    -- Financial
    estimated_cost DECIMAL(12,2) NULL,
    actual_cost DECIMAL(12,2) NULL,
    cost_approved BOOLEAN DEFAULT FALSE,
    cost_approved_by BIGINT UNSIGNED NULL,
    
    -- Documentation
    work_performed TEXT NULL,
    parts_used TEXT NULL,
    issues_found TEXT NULL,
    recommendations TEXT NULL,
    
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE,
    FOREIGN KEY (maintenance_type_id) REFERENCES maintenance_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (schedule_id) REFERENCES maintenance_schedules(id) ON DELETE SET NULL,
    FOREIGN KEY (primary_technician) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (cost_approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (status_changed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_work_order_number (work_order_number),
    INDEX idx_equipment_id (equipment_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_scheduled_start (scheduled_start_date),
    INDEX idx_assigned_technician (primary_technician),
    INDEX idx_supervisor (supervisor_id)
);

-- Parts and inventory management
CREATE TABLE parts_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT NULL,
    parent_category_id BIGINT UNSIGNED NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (parent_category_id) REFERENCES parts_categories(id) ON DELETE SET NULL,
    INDEX idx_code (code),
    INDEX idx_parent_category (parent_category_id)
);

CREATE TABLE parts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    part_number VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    manufacturer_part_number VARCHAR(100) NULL,
    manufacturer_id BIGINT UNSIGNED NULL,
    
    -- Inventory details
    unit_of_measure VARCHAR(20) DEFAULT 'piece',
    unit_cost DECIMAL(10,2) NULL,
    reorder_level INT DEFAULT 0,
    reorder_quantity INT DEFAULT 0,
    current_stock INT DEFAULT 0,
    reserved_stock INT DEFAULT 0,
    
    -- Physical properties
    weight_kg DECIMAL(8,3) NULL,
    dimensions VARCHAR(100) NULL,
    storage_location VARCHAR(100) NULL,
    storage_requirements TEXT NULL,
    
    -- Lifecycle
    is_consumable BOOLEAN DEFAULT FALSE,
    shelf_life_months INT NULL,
    warranty_period_months INT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (category_id) REFERENCES parts_categories(id) ON DELETE RESTRICT,
    FOREIGN KEY (manufacturer_id) REFERENCES manufacturers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_part_number (part_number),
    INDEX idx_category_id (category_id),
    INDEX idx_manufacturer_id (manufacturer_id),
    INDEX idx_current_stock (current_stock),
    INDEX idx_reorder_level (reorder_level)
);

-- Equipment-parts compatibility
CREATE TABLE equipment_parts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipment_type_id BIGINT UNSIGNED NOT NULL,
    part_id BIGINT UNSIGNED NOT NULL,
    is_required BOOLEAN DEFAULT FALSE,
    quantity_required INT DEFAULT 1,
    replacement_interval_hours DECIMAL(10,1) NULL,
    notes TEXT NULL,
    
    FOREIGN KEY (equipment_type_id) REFERENCES equipment_types(id) ON DELETE CASCADE,
    FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE CASCADE,
    UNIQUE KEY unique_equipment_part (equipment_type_id, part_id),
    INDEX idx_equipment_type (equipment_type_id),
    INDEX idx_part_id (part_id)
);

-- Work order parts usage
CREATE TABLE work_order_parts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    work_order_id BIGINT UNSIGNED NOT NULL,
    part_id BIGINT UNSIGNED NOT NULL,
    quantity_planned INT NOT NULL,
    quantity_used INT DEFAULT 0,
    unit_cost DECIMAL(10,2) NULL,
    total_cost DECIMAL(12,2) NULL,
    notes TEXT NULL,
    
    FOREIGN KEY (work_order_id) REFERENCES maintenance_work_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE RESTRICT,
    INDEX idx_work_order_id (work_order_id),
    INDEX idx_part_id (part_id)
);
```

#### 5. Operational Tracking

```sql
-- Operating sessions
CREATE TABLE operating_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipment_id BIGINT UNSIGNED NOT NULL,
    operator_id BIGINT UNSIGNED NOT NULL,
    shift_supervisor_id BIGINT UNSIGNED NULL,
    
    -- Session timing
    started_at TIMESTAMP NOT NULL,
    ended_at TIMESTAMP NULL,
    planned_duration_hours DECIMAL(6,2) NULL,
    actual_duration_hours DECIMAL(6,2) NULL,
    
    -- Location and project
    work_site VARCHAR(255) NULL,
    project_code VARCHAR(100) NULL,
    work_area VARCHAR(255) NULL,
    start_location_lat DECIMAL(10, 8) NULL,
    start_location_lng DECIMAL(11, 8) NULL,
    end_location_lat DECIMAL(10, 8) NULL,
    end_location_lng DECIMAL(11, 8) NULL,
    
    -- Equipment readings
    start_hours DECIMAL(10,1) NOT NULL,
    end_hours DECIMAL(10,1) NULL,
    start_odometer DECIMAL(12,2) NULL,
    end_odometer DECIMAL(12,2) NULL,
    fuel_level_start DECIMAL(5,2) NULL,
    fuel_level_end DECIMAL(5,2) NULL,
    
    -- Performance metrics
    material_moved_cubic_meters DECIMAL(12,2) NULL,
    loads_completed INT NULL,
    distance_traveled_km DECIMAL(8,2) NULL,
    fuel_consumed_liters DECIMAL(8,2) NULL,
    
    -- Conditions
    weather_conditions VARCHAR(100) NULL,
    terrain_type VARCHAR(100) NULL,
    work_type VARCHAR(100) NULL,
    
    -- Issues and notes
    issues_reported TEXT NULL,
    operator_notes TEXT NULL,
    supervisor_notes TEXT NULL,
    
    -- Status
    status ENUM('active', 'completed', 'interrupted', 'cancelled') DEFAULT 'active',
    interruption_reason VARCHAR(255) NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE,
    FOREIGN KEY (operator_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (shift_supervisor_id) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_equipment_id (equipment_id),
    INDEX idx_operator_id (operator_id),
    INDEX idx_started_at (started_at),
    INDEX idx_work_site (work_site),
    INDEX idx_status (status)
);

-- Equipment status log (for tracking all status changes)
CREATE TABLE equipment_status_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipment_id BIGINT UNSIGNED NOT NULL,
    previous_status ENUM('active', 'maintenance', 'repair', 'standby', 'retired', 'disposal') NULL,
    new_status ENUM('active', 'maintenance', 'repair', 'standby', 'retired', 'disposal') NOT NULL,
    reason VARCHAR(255) NULL,
    notes TEXT NULL,
    changed_by BIGINT UNSIGNED NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_equipment_id (equipment_id),
    INDEX idx_changed_at (changed_at),
    INDEX idx_new_status (new_status)
);
```

#### 6. Reporting and Analytics

```sql
-- System settings and configurations
CREATE TABLE system_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(100) UNIQUE NOT NULL,
    value TEXT NULL,
    data_type ENUM('string', 'integer', 'decimal', 'boolean', 'json') DEFAULT 'string',
    category VARCHAR(50) DEFAULT 'general',
    description TEXT NULL,
    is_encrypted BOOLEAN DEFAULT FALSE,
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_key_name (key_name),
    INDEX idx_category (category)
);

-- Activity logs for audit trail
CREATE TABLE activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    model_type VARCHAR(100) NULL,
    model_id BIGINT UNSIGNED NULL,
    description TEXT NULL,
    changes JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_model (model_type, model_id),
    INDEX idx_created_at (created_at)
);

-- Notifications
CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSON NULL,
    read_at TIMESTAMP NULL,
    action_url VARCHAR(500) NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_read_at (read_at),
    INDEX idx_created_at (created_at)
);
```

### Key Relationships and Constraints

1. **Equipment Hierarchy**: Categories → Types → Equipment
2. **RBAC Chain**: Users → Roles → Permissions
3. **Inspection Flow**: Templates → Template Items → Inspections → Results
4. **Maintenance Chain**: Schedules → Work Orders → Parts Usage
5. **Operational Tracking**: Equipment → Operating Sessions → Status Logs

### Performance Optimization Indexes

```sql
-- Composite indexes for common queries
CREATE INDEX idx_equipment_status_type ON equipment(status, equipment_type_id);
CREATE INDEX idx_inspection_equipment_date ON inspections(equipment_id, scheduled_date);
CREATE INDEX idx_workorder_status_priority ON maintenance_work_orders(status, priority);
CREATE INDEX idx_operating_session_equipment_date ON operating_sessions(equipment_id, started_at);
CREATE INDEX idx_parts_stock_reorder ON parts(current_stock, reorder_level);

-- Full-text search indexes
ALTER TABLE equipment ADD FULLTEXT(model, asset_number, serial_number);
ALTER TABLE parts ADD FULLTEXT(name, description, part_number);
```

### Sample Data Seeding Structure

```sql
-- Default roles
INSERT INTO roles (name, description) VALUES
('super_admin', 'Full system access'),
('admin', 'Administrative access'),
('supervisor', 'Supervisory access'),
('inspector', 'Inspection and reporting access'),
('operator', 'Equipment operation access'),
('maintenance_tech', 'Maintenance and repair access'),
('viewer', 'Read-only access');

-- Default permissions by module
INSERT INTO permissions (name, module, description) VALUES
-- Equipment module
('equipment.view', 'equipment', 'View equipment'),
('equipment.create', 'equipment', 'Create equipment'),
('equipment.edit', 'equipment', 'Edit equipment'),
('equipment.delete', 'equipment', 'Delete equipment'),
('equipment.operate', 'equipment', 'Operate equipment'),

-- Inspection module
('inspection.view', 'inspection', 'View inspections'),
('inspection.create', 'inspection', 'Create inspections'),
('inspection.edit', 'inspection', 'Edit inspections'),
('inspection.approve', 'inspection', 'Approve inspections'),

-- Maintenance module
('maintenance.view', 'maintenance', 'View maintenance'),
('maintenance.create', 'maintenance', 'Create maintenance'),
('maintenance.edit', 'maintenance', 'Edit maintenance'),
('maintenance.approve', 'maintenance', 'Approve maintenance'),

-- Reporting module
('reports.view', 'reports', 'View reports'),
('reports.export', 'reports', 'Export reports'),
('reports.advanced', 'reports', 'Advanced reporting');

-- Equipment categories
INSERT INTO equipment_categories (name, code, description) VALUES
('Excavators', 'EXC', 'Hydraulic excavators and backhoes'),
('Bulldozers', 'BUL', 'Track and wheel bulldozers'),
('Dump Trucks', 'DMP', 'Mining dump trucks and haulers'),
('Loaders', 'LOD', 'Wheel and track loaders'),
('Cranes', 'CRN', 'Mobile and crawler cranes');
```

### Mobile API Considerations

#### Authentication Endpoints
```
POST /api/auth/login
POST /api/auth/logout
POST /api/auth/refresh
GET  /api/auth/user
```

#### Core Mobile Endpoints
```
GET  /api/mobile/equipment/assigned        # Equipment assigned to user
GET  /api/mobile/inspections/pending       # Pending inspections
POST /api/mobile/inspections/{id}/start    # Start inspection
POST /api/mobile/inspections/{id}/submit   # Submit inspection
POST /api/mobile/operating-sessions/start  # Start operating session
PUT  /api/mobile/operating-sessions/{id}   # Update session
POST /api/mobile/issues/report             # Report issues
```

This comprehensive database schema provides the foundation for a robust heavy equipment management system with full traceability, RBAC, and mobile-first API design for field operations.

## Scalable & Maintainable API Architecture Design

### API Design Principles

#### 1. Architectural Patterns
- **Service Layer Pattern**: Business logic separated from controllers
- **Repository Pattern**: Data access abstraction layer  
- **Resource Pattern**: Consistent API response transformation
- **Policy Pattern**: Authorization logic encapsulation
- **Observer Pattern**: Event-driven side effects
- **Command/Query Separation**: Clear separation of read/write operations

#### 2. API Structure Philosophy
```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/           # API versioning
│   │   │   ├── Auth/         # Authentication endpoints
│   │   │   ├── Equipment/    # Equipment management
│   │   │   ├── Inspection/   # Inspection system
│   │   │   ├── Maintenance/  # Maintenance operations
│   │   │   ├── Operations/   # Operational tracking
│   │   │   └── System/       # System management
│   ├── Requests/             # Form request validation
│   ├── Resources/            # API response transformers
│   ├── Middleware/           # Custom middleware
│   └── Policies/             # Authorization policies
├── Services/                 # Business logic layer
├── Repositories/             # Data access layer  
├── Events/                   # Domain events
├── Listeners/                # Event handlers
├── Jobs/                     # Background tasks
└── Exceptions/               # Custom exceptions
```

#### 3. Modular API Design

### Module 1: Authentication & RBAC API

#### Endpoints Structure
```php
// Authentication
POST   /api/v1/auth/login
POST   /api/v1/auth/logout  
POST   /api/v1/auth/refresh
GET    /api/v1/auth/me
POST   /api/v1/auth/forgot-password
POST   /api/v1/auth/reset-password

// User Management
GET    /api/v1/users                    # List users with filters
POST   /api/v1/users                    # Create user
GET    /api/v1/users/{id}               # Get user details
PUT    /api/v1/users/{id}               # Update user
DELETE /api/v1/users/{id}               # Soft delete user
POST   /api/v1/users/{id}/restore       # Restore deleted user
PUT    /api/v1/users/{id}/activate      # Activate/deactivate user
PUT    /api/v1/users/{id}/roles         # Assign roles to user

// Role Management  
GET    /api/v1/roles                    # List roles
POST   /api/v1/roles                    # Create role
GET    /api/v1/roles/{id}               # Get role details
PUT    /api/v1/roles/{id}               # Update role
DELETE /api/v1/roles/{id}               # Delete role
PUT    /api/v1/roles/{id}/permissions   # Assign permissions to role

// Permission Management
GET    /api/v1/permissions              # List permissions by module
GET    /api/v1/permissions/modules      # Get available modules
```

#### Request/Response Patterns
```php
// Standard Response Format
{
    "success": true,
    "data": {...},
    "message": "Operation completed successfully",
    "meta": {
        "pagination": {...},
        "filters": {...},
        "performance": {
            "query_time": "0.045s",
            "memory_usage": "2.1MB"
        }
    }
}

// Error Response Format
{
    "success": false,
    "error": {
        "code": "VALIDATION_FAILED",
        "message": "The given data was invalid",
        "details": {
            "email": ["The email field is required"],
            "password": ["The password must be at least 8 characters"]
        }
    },
    "meta": {
        "request_id": "req_1234567890",
        "timestamp": "2025-01-15T10:30:00Z"
    }
}
```

### Module 2: Equipment Management API

#### Endpoints Structure
```php
// Equipment Categories
GET    /api/v1/equipment-categories           # List categories
POST   /api/v1/equipment-categories           # Create category
GET    /api/v1/equipment-categories/{id}      # Get category
PUT    /api/v1/equipment-categories/{id}      # Update category
DELETE /api/v1/equipment-categories/{id}      # Delete category

// Equipment Types
GET    /api/v1/equipment-types                # List types with category filter
POST   /api/v1/equipment-types                # Create type
GET    /api/v1/equipment-types/{id}           # Get type with specifications
PUT    /api/v1/equipment-types/{id}           # Update type
DELETE /api/v1/equipment-types/{id}           # Delete type

// Manufacturers
GET    /api/v1/manufacturers                  # List manufacturers
POST   /api/v1/manufacturers                  # Create manufacturer
GET    /api/v1/manufacturers/{id}             # Get manufacturer
PUT    /api/v1/manufacturers/{id}             # Update manufacturer
DELETE /api/v1/manufacturers/{id}             # Delete manufacturer

// Equipment Management
GET    /api/v1/equipment                      # List equipment with advanced filters
POST   /api/v1/equipment                      # Create equipment
GET    /api/v1/equipment/{id}                 # Get equipment details with relationships
PUT    /api/v1/equipment/{id}                 # Update equipment
DELETE /api/v1/equipment/{id}                 # Soft delete equipment
POST   /api/v1/equipment/{id}/restore         # Restore equipment

// Equipment Status Management
PUT    /api/v1/equipment/{id}/status          # Change equipment status
GET    /api/v1/equipment/{id}/status-history  # Get status change history
PUT    /api/v1/equipment/{id}/location        # Update location
GET    /api/v1/equipment/{id}/location-history # Get location history

// Equipment Documents
GET    /api/v1/equipment/{id}/documents       # List equipment documents
POST   /api/v1/equipment/{id}/documents       # Upload document
GET    /api/v1/equipment/{id}/documents/{doc_id} # Download document
PUT    /api/v1/equipment/{id}/documents/{doc_id} # Update document metadata
DELETE /api/v1/equipment/{id}/documents/{doc_id} # Delete document

// Equipment Assignment
PUT    /api/v1/equipment/{id}/assign          # Assign equipment to user/site
PUT    /api/v1/equipment/{id}/unassign        # Unassign equipment
GET    /api/v1/equipment/assigned             # List equipment assigned to current user
```

#### Advanced Query Features
```php
// Equipment List with Filters
GET /api/v1/equipment?filters[status]=active,maintenance
                     &filters[type_id]=1,2,3
                     &filters[manufacturer_id]=5
                     &filters[assigned_to_user]=10
                     &filters[location_radius]=10km:lat,lng
                     &filters[operating_hours][min]=1000
                     &filters[operating_hours][max]=5000
                     &filters[next_service_due]=7_days
                     &sort=-created_at,operating_hours
                     &include=type,manufacturer,assignedUser
                     &page=1&per_page=25

// Response with Meta Information
{
    "success": true,
    "data": [...],
    "meta": {
        "pagination": {
            "current_page": 1,
            "per_page": 25,
            "total": 150,
            "last_page": 6
        },
        "filters_applied": {
            "status": ["active", "maintenance"],
            "type_id": [1, 2, 3]
        },
        "aggregations": {
            "total_operating_hours": 45000,
            "avg_operating_hours": 3000,
            "status_counts": {
                "active": 120,
                "maintenance": 20,
                "repair": 10
            }
        }
    }
}
```

### Module 3: Inspection System API

#### Endpoints Structure  
```php
// Inspection Templates
GET    /api/v1/inspection-templates           # List templates by equipment type
POST   /api/v1/inspection-templates           # Create template
GET    /api/v1/inspection-templates/{id}      # Get template with items
PUT    /api/v1/inspection-templates/{id}      # Update template
DELETE /api/v1/inspection-templates/{id}      # Delete template
POST   /api/v1/inspection-templates/{id}/clone # Clone template

// Template Items Management
GET    /api/v1/inspection-templates/{id}/items # List template items
POST   /api/v1/inspection-templates/{id}/items # Add item to template
PUT    /api/v1/inspection-template-items/{id}  # Update template item
DELETE /api/v1/inspection-template-items/{id}  # Delete template item
PUT    /api/v1/inspection-templates/{id}/items/reorder # Reorder items

// Inspections Management
GET    /api/v1/inspections                    # List inspections with filters
POST   /api/v1/inspections                    # Create/schedule inspection
GET    /api/v1/inspections/{id}               # Get inspection details
PUT    /api/v1/inspections/{id}               # Update inspection
DELETE /api/v1/inspections/{id}               # Cancel inspection

// Inspection Process
POST   /api/v1/inspections/{id}/start         # Start inspection
PUT    /api/v1/inspections/{id}/items/{item_id} # Update inspection item result
POST   /api/v1/inspections/{id}/items/{item_id}/photos # Upload photos
PUT    /api/v1/inspections/{id}/complete      # Complete inspection
POST   /api/v1/inspections/{id}/approve       # Approve inspection (supervisor)

// Inspection Results & Actions
GET    /api/v1/inspections/{id}/results       # Get detailed results
GET    /api/v1/inspections/{id}/actions       # Get corrective actions
PUT    /api/v1/inspection-results/{id}/action-complete # Mark action as completed
GET    /api/v1/inspections/pending-actions    # List pending corrective actions
```

### Module 4: Maintenance System API

#### Endpoints Structure
```php
// Maintenance Types
GET    /api/v1/maintenance-types              # List maintenance types
POST   /api/v1/maintenance-types              # Create maintenance type
GET    /api/v1/maintenance-types/{id}         # Get maintenance type
PUT    /api/v1/maintenance-types/{id}         # Update maintenance type
DELETE /api/v1/maintenance-types/{id}         # Delete maintenance type

// Maintenance Schedules
GET    /api/v1/maintenance-schedules          # List schedules with filters
POST   /api/v1/maintenance-schedules          # Create schedule
GET    /api/v1/maintenance-schedules/{id}     # Get schedule details
PUT    /api/v1/maintenance-schedules/{id}     # Update schedule
DELETE /api/v1/maintenance-schedules/{id}     # Delete schedule
GET    /api/v1/maintenance-schedules/due      # Get overdue/upcoming schedules

// Work Orders
GET    /api/v1/work-orders                    # List work orders with filters
POST   /api/v1/work-orders                    # Create work order
GET    /api/v1/work-orders/{id}               # Get work order details
PUT    /api/v1/work-orders/{id}               # Update work order
DELETE /api/v1/work-orders/{id}               # Cancel work order

// Work Order Lifecycle
PUT    /api/v1/work-orders/{id}/approve       # Approve work order
PUT    /api/v1/work-orders/{id}/schedule      # Schedule work order
PUT    /api/v1/work-orders/{id}/start         # Start work order
PUT    /api/v1/work-orders/{id}/complete      # Complete work order
PUT    /api/v1/work-orders/{id}/assign        # Assign technician

// Parts Management
GET    /api/v1/parts                          # List parts with inventory info
POST   /api/v1/parts                          # Create part
GET    /api/v1/parts/{id}                     # Get part details
PUT    /api/v1/parts/{id}                     # Update part
DELETE /api/v1/parts/{id}                     # Delete part
GET    /api/v1/parts/low-stock               # List parts with low stock
PUT    /api/v1/parts/{id}/stock              # Update stock levels

// Work Order Parts
GET    /api/v1/work-orders/{id}/parts         # List required parts
POST   /api/v1/work-orders/{id}/parts         # Add part to work order
PUT    /api/v1/work-order-parts/{id}          # Update part usage
DELETE /api/v1/work-order-parts/{id}          # Remove part from work order
```

### Module 5: Operational Tracking API

#### Endpoints Structure
```php
// Operating Sessions
GET    /api/v1/operating-sessions             # List sessions with filters
POST   /api/v1/operating-sessions             # Start operating session
GET    /api/v1/operating-sessions/{id}        # Get session details
PUT    /api/v1/operating-sessions/{id}        # Update session
PUT    /api/v1/operating-sessions/{id}/end    # End session

// Current User Sessions
GET    /api/v1/my/operating-sessions/active   # Get active sessions for current user
POST   /api/v1/my/operating-sessions/start    # Start session for current user
PUT    /api/v1/my/operating-sessions/{id}/update # Update current session

// Performance Analytics
GET    /api/v1/equipment/{id}/performance     # Get equipment performance metrics
GET    /api/v1/operators/{id}/performance     # Get operator performance metrics
GET    /api/v1/performance/summary            # Get overall performance summary
```

### Module 6: System & Analytics API

#### Endpoints Structure
```php
// System Settings
GET    /api/v1/settings                       # List settings by category
PUT    /api/v1/settings                       # Update multiple settings
GET    /api/v1/settings/{key}                 # Get specific setting
PUT    /api/v1/settings/{key}                 # Update specific setting

// Activity Logs & Audit Trail
GET    /api/v1/activity-logs                  # List activity logs with filters
GET    /api/v1/activity-logs/user/{id}        # User-specific activity logs
GET    /api/v1/activity-logs/equipment/{id}   # Equipment-specific activity logs

// Notifications
GET    /api/v1/notifications                  # List user notifications
PUT    /api/v1/notifications/{id}/read        # Mark notification as read
PUT    /api/v1/notifications/mark-all-read    # Mark all as read
DELETE /api/v1/notifications/{id}             # Delete notification

// Reporting & Analytics
GET    /api/v1/reports/equipment-utilization  # Equipment utilization report
GET    /api/v1/reports/maintenance-costs      # Maintenance cost analysis
GET    /api/v1/reports/inspection-compliance  # Inspection compliance report
GET    /api/v1/reports/downtime-analysis      # Equipment downtime analysis
POST   /api/v1/reports/export                 # Export reports to Excel/PDF
```

### API Implementation Standards

#### 1. Controller Layer Pattern
```php
// Base API Controller
abstract class BaseApiController extends Controller
{
    protected function successResponse($data, $message = null, $meta = [])
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'meta' => array_merge([
                'timestamp' => now()->toISOString(),
                'request_id' => request()->header('X-Request-ID') ?? Str::uuid()
            ], $meta)
        ]);
    }

    protected function errorResponse($message, $code = 400, $details = [])
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $this->getErrorCode($code),
                'message' => $message,
                'details' => $details
            ],
            'meta' => [
                'timestamp' => now()->toISOString(),
                'request_id' => request()->header('X-Request-ID') ?? Str::uuid()
            ]
        ], $code);
    }
}

// Equipment Controller Example
class EquipmentController extends BaseApiController
{
    public function __construct(
        protected EquipmentService $equipmentService,
        protected EquipmentRepository $equipmentRepository
    ) {}

    public function index(EquipmentIndexRequest $request)
    {
        $filters = $request->getFilters();
        $equipment = $this->equipmentService->getEquipmentList($filters);
        
        return $this->successResponse(
            EquipmentResource::collection($equipment),
            'Equipment retrieved successfully',
            [
                'pagination' => $equipment->toArray(),
                'filters_applied' => $filters,
                'aggregations' => $this->equipmentService->getAggregations($filters)
            ]
        );
    }

    public function store(EquipmentStoreRequest $request)
    {
        $equipment = $this->equipmentService->createEquipment($request->validated());
        
        return $this->successResponse(
            new EquipmentResource($equipment),
            'Equipment created successfully'
        );
    }
}
```

#### 2. Service Layer Pattern
```php
class EquipmentService
{
    public function __construct(
        protected EquipmentRepository $repository,
        protected ActivityLogService $activityLog,
        protected NotificationService $notifications
    ) {}

    public function createEquipment(array $data): Equipment
    {
        DB::beginTransaction();
        
        try {
            $equipment = $this->repository->create($data);
            
            // Log activity
            $this->activityLog->log('equipment.created', $equipment);
            
            // Send notifications
            $this->notifications->notifyEquipmentCreated($equipment);
            
            DB::commit();
            return $equipment;
            
        } catch (Exception $e) {
            DB::rollback();
            throw new EquipmentCreationException('Failed to create equipment: ' . $e->getMessage());
        }
    }

    public function updateEquipmentStatus(Equipment $equipment, string $status, ?string $reason = null): Equipment
    {
        $previousStatus = $equipment->status;
        
        $equipment = $this->repository->updateStatus($equipment, $status, $reason);
        
        // Log status change
        $this->activityLog->log('equipment.status_changed', $equipment, [
            'previous_status' => $previousStatus,
            'new_status' => $status,
            'reason' => $reason
        ]);
        
        // Trigger status-based notifications
        $this->notifications->notifyStatusChange($equipment, $previousStatus);
        
        return $equipment;
    }
}
```

#### 3. Repository Pattern
```php
interface EquipmentRepositoryInterface
{
    public function findWithFilters(array $filters): LengthAwarePaginator;
    public function findByIdWithRelations(int $id, array $relations = []): ?Equipment;
    public function create(array $data): Equipment;
    public function update(Equipment $equipment, array $data): Equipment;
    public function updateStatus(Equipment $equipment, string $status, ?string $reason): Equipment;
}

class EquipmentRepository implements EquipmentRepositoryInterface
{
    public function findWithFilters(array $filters): LengthAwarePaginator
    {
        $query = Equipment::query()
            ->with(['type.category', 'manufacturer', 'assignedUser'])
            ->when($filters['status'] ?? null, fn($q, $status) => 
                $q->whereIn('status', is_array($status) ? $status : [$status])
            )
            ->when($filters['type_id'] ?? null, fn($q, $typeIds) => 
                $q->whereIn('equipment_type_id', is_array($typeIds) ? $typeIds : [$typeIds])
            )
            ->when($filters['operating_hours'] ?? null, function($q, $hours) {
                if (isset($hours['min'])) $q->where('total_operating_hours', '>=', $hours['min']);
                if (isset($hours['max'])) $q->where('total_operating_hours', '<=', $hours['max']);
            })
            ->when($filters['location_radius'] ?? null, function($q, $location) {
                // Implement geospatial query for location radius
                [$radius, $coordinates] = explode(':', $location);
                [$lat, $lng] = explode(',', $coordinates);
                $radiusKm = (float) str_replace('km', '', $radius);
                
                $q->whereRaw("
                    ST_Distance_Sphere(
                        POINT(current_location_lng, current_location_lat),
                        POINT(?, ?)
                    ) / 1000 <= ?
                ", [$lng, $lat, $radiusKm]);
            });

        return $query->paginate($filters['per_page'] ?? 25);
    }
}
```

#### 4. Request Validation Pattern
```php
class EquipmentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('equipment.create');
    }

    public function rules(): array
    {
        return [
            'equipment_type_id' => 'required|exists:equipment_types,id',
            'manufacturer_id' => 'required|exists:manufacturers,id',
            'asset_number' => 'required|string|max:50|unique:equipment,asset_number',
            'serial_number' => 'required|string|max:100|unique:equipment,serial_number',
            'model' => 'required|string|max:100',
            'year_manufactured' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            
            // Technical specifications
            'operating_weight' => 'nullable|numeric|min:0',
            'engine_power' => 'nullable|numeric|min:0',
            'bucket_capacity' => 'nullable|numeric|min:0',
            
            // Financial data
            'purchase_price' => 'nullable|numeric|min:0',
            'ownership_type' => 'required|in:owned,leased,rented',
            
            // Location
            'current_location_lat' => 'nullable|numeric|between:-90,90',
            'current_location_lng' => 'nullable|numeric|between:-180,180',
            
            // Assignment
            'assigned_to_user' => 'nullable|exists:users,id',
            'assigned_to_site' => 'nullable|string|max:100'
        ];
    }

    public function messages(): array
    {
        return [
            'equipment_type_id.required' => 'Equipment type is required',
            'asset_number.unique' => 'Asset number already exists in the system',
            'serial_number.unique' => 'Serial number already exists in the system'
        ];
    }

    protected function prepareForValidation(): void
    {
        // Auto-generate asset number if not provided
        if (!$this->has('asset_number')) {
            $this->merge([
                'asset_number' => $this->generateAssetNumber()
            ]);
        }
    }

    private function generateAssetNumber(): string
    {
        $type = EquipmentType::find($this->equipment_type_id);
        $prefix = $type?->code ?? 'EQ';
        $year = now()->format('y');
        $sequence = Equipment::whereYear('created_at', now()->year)->count() + 1;
        
        return sprintf('%s%s%04d', $prefix, $year, $sequence);
    }
}
```

#### 5. Resource Transformation Pattern
```php
class EquipmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'asset_number' => $this->asset_number,
            'serial_number' => $this->serial_number,
            'model' => $this->model,
            'year_manufactured' => $this->year_manufactured,
            
            // Status information
            'status' => $this->status,
            'status_changed_at' => $this->status_changed_at,
            'status_notes' => $this->when($this->status_notes, $this->status_notes),
            
            // Operational data
            'total_operating_hours' => $this->total_operating_hours,
            'last_service_hours' => $this->last_service_hours,
            'next_service_hours' => $this->next_service_hours,
            'next_service_due' => $this->when(
                $this->next_service_hours && $this->total_operating_hours,
                $this->next_service_hours - $this->total_operating_hours
            ),
            
            // Location
            'current_location' => $this->when(
                $this->current_location_lat && $this->current_location_lng,
                [
                    'latitude' => $this->current_location_lat,
                    'longitude' => $this->current_location_lng,
                    'address' => $this->current_location_address
                ]
            ),
            
            // Relationships
            'type' => new EquipmentTypeResource($this->whenLoaded('type')),
            'manufacturer' => new ManufacturerResource($this->whenLoaded('manufacturer')),
            'assigned_user' => new UserResource($this->whenLoaded('assignedUser')),
            
            // Computed fields
            'utilization_rate' => $this->getUtilizationRate(),
            'maintenance_due' => $this->isMaintenanceDue(),
            'inspection_due' => $this->isInspectionDue(),
            
            // Metadata
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
```

#### 6. Performance & Caching Strategy
```php
// Cache configuration for different data types
class CacheStrategy
{
    const CACHE_DURATIONS = [
        'equipment_types' => 3600,        // 1 hour (rarely changes)
        'manufacturers' => 3600,          // 1 hour (rarely changes) 
        'equipment_list' => 300,          // 5 minutes (changes frequently)
        'user_permissions' => 1800,       // 30 minutes (moderate changes)
        'system_settings' => 7200,        // 2 hours (rarely changes)
        'inspection_templates' => 1800,   // 30 minutes (moderate changes)
    ];

    // Cache tags for efficient invalidation
    const CACHE_TAGS = [
        'equipment' => ['equipment', 'equipment_list'],
        'users' => ['users', 'permissions'],
        'inspections' => ['inspections', 'templates'],
        'maintenance' => ['maintenance', 'work_orders']
    ];
}

// Service layer with caching
class EquipmentService
{
    public function getEquipmentList(array $filters): LengthAwarePaginator
    {
        $cacheKey = 'equipment_list_' . md5(serialize($filters));
        
        return Cache::tags(['equipment'])->remember($cacheKey, 300, function() use ($filters) {
            return $this->repository->findWithFilters($filters);
        });
    }

    public function updateEquipment(Equipment $equipment, array $data): Equipment
    {
        $updated = $this->repository->update($equipment, $data);
        
        // Invalidate related caches
        Cache::tags(['equipment'])->flush();
        
        return $updated;
    }
}
```

### API Security Implementation

#### 1. Authentication & Authorization Middleware
```php
// API Authentication Middleware
class ApiAuthMiddleware
{
    public function handle($request, Closure $next, ...$guards)
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTHENTICATION_REQUIRED',
                    'message' => 'Authentication token required'
                ]
            ], 401);
        }

        $user = PersonalAccessToken::findToken($token)?->tokenable;
        
        if (!$user || !$user->is_active) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_TOKEN',
                    'message' => 'Invalid or expired token'
                ]
            ], 401);
        }

        Auth::setUser($user);
        return $next($request);
    }
}

// Permission Middleware
class CheckPermissionMiddleware
{
    public function handle($request, Closure $next, string $permission)
    {
        if (!$request->user()->can($permission)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INSUFFICIENT_PERMISSIONS',
                    'message' => 'You do not have permission to perform this action'
                ]
            ], 403);
        }

        return $next($request);
    }
}
```

#### 2. Rate Limiting Strategy
```php
// API Rate Limiting Configuration
class ApiRateLimiter
{
    public function boot()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-heavy', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-upload', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });
    }
}

// Route configuration with rate limiting
Route::middleware(['api', 'auth:sanctum', 'throttle:api'])
    ->prefix('api/v1')
    ->group(function () {
        
        // Standard API endpoints
        Route::apiResource('equipment', EquipmentController::class);
        
        // Heavy operations (reports, exports)
        Route::middleware('throttle:api-heavy')->group(function () {
            Route::post('reports/export', [ReportController::class, 'export']);
            Route::get('analytics/dashboard', [AnalyticsController::class, 'dashboard']);
        });
        
        // File upload endpoints
        Route::middleware('throttle:api-upload')->group(function () {
            Route::post('equipment/{equipment}/documents', [EquipmentDocumentController::class, 'store']);
            Route::post('inspections/{inspection}/photos', [InspectionPhotoController::class, 'store']);
        });
    });
```

### Structured Implementation Roadmap

#### Phase 1: Foundation Setup (Week 1-2)

##### 1.1 Project Setup & Base Structure
```bash
# Project initialization
composer create-project laravel/laravel heavy-equipment-management
cd heavy-equipment-management

# Core packages installation
composer require laravel/sanctum
composer require spatie/laravel-permission
composer require intervention/image
composer require maatwebsite/excel

# Development packages
composer require --dev laravel/telescope
composer require --dev barryvdh/laravel-debugbar
composer require --dev pestphp/pest
```

##### 1.2 Database Schema Implementation
- Create all migration files based on designed schema
- Implement foreign key constraints and indexes
- Create database seeders for initial data
- Setup development database with sample data

##### 1.3 Base API Infrastructure
- Setup API versioning structure
- Create base controller, service, repository classes
- Implement standardized response format
- Setup error handling and logging
- Configure CORS for frontend integration

#### Phase 2: Authentication & RBAC (Week 2-3)

##### 2.1 Authentication System
```
Priority Order:
1. User model with Sanctum integration
2. Authentication controllers (login, logout, refresh)
3. Password reset functionality
4. API token management
5. User profile management
```

##### 2.2 RBAC Implementation
```
Priority Order:
1. Role and Permission models
2. User-Role relationships
3. Role-Permission relationships  
4. Authorization policies
5. Permission middleware
6. RBAC API endpoints
```

##### 2.3 Testing & Validation
- Unit tests for authentication logic
- API tests for all auth endpoints
- Permission-based access testing
- Security vulnerability testing

#### Phase 3: Equipment Management (Week 3-5)

##### 3.1 Master Data (Week 3)
```
Implementation Order:
1. Equipment Categories (CRUD + API)
2. Equipment Types (CRUD + API)
3. Manufacturers (CRUD + API)
4. Parts Categories (CRUD + API)
5. Parts Management (CRUD + API)
```

##### 3.2 Core Equipment Features (Week 4)
```
Implementation Order:
1. Equipment CRUD operations
2. Equipment status management
3. Equipment assignment system
4. Equipment location tracking
5. Operating hours tracking
6. Equipment document management
```

##### 3.3 Advanced Equipment Features (Week 5)
```
Implementation Order:
1. Equipment search and filtering
2. Geospatial location queries
3. Equipment utilization analytics
4. Maintenance scheduling integration
5. Performance metrics calculation
```

#### Phase 4: Inspection System (Week 5-7)

##### 4.1 Template Management (Week 5-6)
```
Implementation Order:
1. Inspection Template CRUD
2. Template Item management
3. Template cloning functionality
4. Template versioning system
5. Equipment-Template associations
```

##### 4.2 Inspection Process (Week 6-7)
```
Implementation Order:
1. Inspection scheduling system
2. Inspection execution workflow
3. Photo upload and management
4. Measurement recording
5. Corrective action tracking
6. Approval workflow
```

##### 4.3 Inspection Analytics
```
Implementation Order:
1. Compliance reporting
2. Issue trend analysis
3. Inspector performance metrics
4. Equipment reliability scoring
```

#### Phase 5: Maintenance System (Week 7-9)

##### 5.1 Maintenance Planning (Week 7-8)
```
Implementation Order:
1. Maintenance Types and Categories
2. Maintenance Scheduling engine
3. Preventive maintenance automation
4. Work Order generation
5. Parts requirement calculation
```

##### 5.2 Work Order Management (Week 8-9)
```
Implementation Order:
1. Work Order lifecycle management
2. Technician assignment system
3. Parts consumption tracking
4. Cost calculation and approval
5. Completion verification
6. Documentation and reporting
```

##### 5.3 Inventory Integration
```
Implementation Order:
1. Parts stock management
2. Low stock notifications
3. Procurement workflow
4. Vendor management integration
5. Cost analysis and reporting
```

#### Phase 6: Operational Tracking (Week 9-10)

##### 6.1 Session Management
```
Implementation Order:
1. Operating Session CRUD
2. Real-time session tracking
3. Performance metrics collection
4. Fuel consumption monitoring
5. Location tracking integration
```

##### 6.2 Analytics & Reporting
```
Implementation Order:
1. Equipment utilization reports
2. Operator performance analytics
3. Cost center analysis
4. Downtime tracking
5. Efficiency measurements
```

#### Phase 7: System Features (Week 10-11)

##### 7.1 Notification System
```
Implementation Order:
1. Real-time notification engine
2. Email notification templates
3. SMS integration (optional)
4. Push notification setup
5. Notification preferences
```

##### 7.2 System Administration
```
Implementation Order:
1. System settings management
2. Activity logging and audit trail
3. Data export functionality
4. System health monitoring
5. Performance optimization
```

#### Phase 8: Testing & Quality Assurance (Week 11-12)

##### 8.1 Comprehensive Testing
```
Testing Priority:
1. API endpoint testing (all modules)
2. Permission and security testing
3. Performance and load testing
4. Data integrity testing
5. Error handling validation
```

##### 8.2 Documentation & Deployment
```
Final Tasks:
1. API documentation completion
2. Deployment configuration
3. Environment setup guides
4. Database migration scripts
5. Production security hardening
```

### Implementation Guidelines

#### 1. Development Standards
```php
// Code Organization Rules
- Follow PSR-12 coding standards
- Use strict typing declarations
- Implement comprehensive DocBlocks
- Follow SOLID principles
- Use dependency injection consistently

// File Structure Convention
app/
├── Http/Controllers/Api/V1/
│   ├── Auth/
│   ├── Equipment/
│   ├── Inspection/
│   ├── Maintenance/
│   └── System/
├── Services/
│   ├── Equipment/
│   ├── Inspection/
│   ├── Maintenance/
│   └── Notification/
├── Repositories/
│   ├── Equipment/
│   ├── Inspection/
│   └── Maintenance/
└── Policies/
    ├── EquipmentPolicy.php
    ├── InspectionPolicy.php
    └── MaintenancePolicy.php
```

#### 2. Testing Strategy per Phase
```php
// Testing Requirements per Module
Equipment Module:
- 25+ API endpoint tests
- 15+ unit tests for business logic
- 10+ integration tests
- 5+ performance tests

Inspection Module:
- 30+ API endpoint tests
- 20+ unit tests for workflow logic
- 15+ integration tests with file uploads
- 8+ permission tests

Maintenance Module:
- 35+ API endpoint tests
- 25+ unit tests for scheduling logic
- 20+ integration tests
- 10+ data consistency tests
```

#### 3. Performance Benchmarks
```php
// Performance Targets
API Response Times:
- Simple GET requests: < 100ms
- Complex filtered queries: < 300ms
- File uploads: < 2s for 10MB
- Report generation: < 5s

Database Performance:
- Equipment list query: < 50ms for 10k records
- Inspection data: < 100ms for complex joins
- Maintenance scheduling: < 200ms
- Full-text search: < 150ms

Caching Efficiency:
- Cache hit ratio: > 80%
- Cache invalidation: < 10ms
- Memory usage: < 512MB for 1k concurrent users
```

#### 4. Security Checklist per Phase
```php
// Security Validation Points
Authentication:
✓ Token expiration handling
✓ Brute force protection
✓ Password strength requirements
✓ Account lockout policies
✓ Session management

Authorization:
✓ Permission boundary testing
✓ Resource ownership validation
✓ Role escalation prevention
✓ API endpoint protection
✓ Data access restrictions

Data Protection:
✓ Input validation and sanitization
✓ SQL injection prevention
✓ XSS protection
✓ File upload security
✓ Sensitive data encryption
```

#### 5. Database Optimization Strategy
```sql
-- Index Creation Schedule
Phase 2: User and RBAC indexes
CREATE INDEX idx_users_active ON users(is_active, deleted_at);
CREATE INDEX idx_user_roles_active ON user_roles(user_id, expires_at);

Phase 3: Equipment indexes
CREATE INDEX idx_equipment_composite ON equipment(status, equipment_type_id, assigned_to_user);
CREATE INDEX idx_equipment_location ON equipment(current_location_lat, current_location_lng);
CREATE INDEX idx_equipment_service ON equipment(next_service_hours, total_operating_hours);

Phase 4: Inspection indexes
CREATE INDEX idx_inspections_status_date ON inspections(overall_status, scheduled_date);
CREATE INDEX idx_inspection_results_status ON inspection_results(status, corrective_action_required);

Phase 5: Maintenance indexes  
CREATE INDEX idx_work_orders_composite ON maintenance_work_orders(status, priority, scheduled_start_date);
CREATE INDEX idx_parts_stock ON parts(current_stock, reorder_level);
```

#### 6. Deployment Checklist
```bash
# Production Deployment Steps
1. Environment Configuration
   - Database connection strings
   - Redis/cache configuration  
   - File storage settings
   - Email service setup
   - Queue worker configuration

2. Security Hardening
   - SSL certificate installation
   - Firewall configuration
   - API rate limiting
   - Environment variable security
   - Database user permissions

3. Performance Optimization
   - PHP OPcache configuration
   - Database query optimization
   - CDN setup for file storage
   - Nginx/Apache optimization
   - Redis memory allocation

4. Monitoring Setup
   - Application performance monitoring
   - Database performance tracking
   - Error logging and alerting
   - Security monitoring
   - Backup automation
```

This roadmap provides a structured, modular approach to building the heavy equipment management API with clear priorities, testing requirements, and quality gates at each phase.