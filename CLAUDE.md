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