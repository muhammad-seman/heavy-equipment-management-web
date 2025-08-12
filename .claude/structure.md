File Tree: heavy-equipment-management
Generated on: 8/12/2025, 2:55:36 PM
Root path: /Users/macbook/Documents/PROJECT/FULLSTACK/heavy-equipment-management

────────────────────────────────────────────────────────────────────────────────
├── 📁 .claude/
│   └── 📄 settings.local.json
├── 📁 .git/ 🚫 (auto-hidden)
├── 📁 app/
│   ├── 📁 Exceptions/
│   │   ├── 📁 Equipment/
│   │   │   ├── 🐘 EquipmentNotFoundException.php
│   │   │   └── 🐘 EquipmentValidationException.php
│   │   ├── 📁 Inspection/
│   │   ├── 📁 Maintenance/
│   │   ├── 📁 Notification/
│   │   └── 🐘 HeavyEquipmentException.php
│   ├── 📁 Http/
│   │   ├── 📁 Controllers/
│   │   │   ├── 📁 Api/
│   │   │   │   ├── 📁 V1/
│   │   │   │   │   ├── 📁 Auth/
│   │   │   │   │   │   └── 🐘 AuthController.php
│   │   │   │   │   ├── 📁 Equipment/
│   │   │   │   │   │   ├── 🐘 EquipmentCategoryController.php
│   │   │   │   │   │   ├── 🐘 EquipmentController.php
│   │   │   │   │   │   ├── 🐘 EquipmentTypeController.php
│   │   │   │   │   │   └── 🐘 ManufacturerController.php
│   │   │   │   │   ├── 📁 Inspection/
│   │   │   │   │   ├── 📁 Maintenance/
│   │   │   │   │   ├── 📁 Operations/
│   │   │   │   │   ├── 📁 System/
│   │   │   │   │   └── 📁 Users/
│   │   │   │   │       └── 🐘 UserController.php
│   │   │   │   ├── 🐘 AuthController.php
│   │   │   │   └── 🐘 BaseApiController.php
│   │   │   ├── 📁 Auth/
│   │   │   │   ├── 🐘 AuthenticatedSessionController.php
│   │   │   │   ├── 🐘 ConfirmablePasswordController.php
│   │   │   │   ├── 🐘 EmailVerificationNotificationController.php
│   │   │   │   ├── 🐘 EmailVerificationPromptController.php
│   │   │   │   ├── 🐘 NewPasswordController.php
│   │   │   │   ├── 🐘 PasswordController.php
│   │   │   │   ├── 🐘 PasswordResetLinkController.php
│   │   │   │   ├── 🐘 RegisteredUserController.php
│   │   │   │   └── 🐘 VerifyEmailController.php
│   │   │   ├── 🐘 Controller.php
│   │   │   └── 🐘 ProfileController.php
│   │   ├── 📁 Middleware/
│   │   │   ├── 🐘 ApiAuthenticationMiddleware.php
│   │   │   ├── 🐘 ApiRateLimitingMiddleware.php
│   │   │   ├── 🐘 CheckPermissionMiddleware.php
│   │   │   └── 🐘 HandleInertiaRequests.php
│   │   ├── 📁 Requests/
│   │   │   ├── 📁 Api/
│   │   │   │   └── 📁 V1/
│   │   │   │       ├── 📁 Auth/
│   │   │   │       ├── 📁 Equipment/
│   │   │   │       ├── 📁 Inspection/
│   │   │   │       ├── 📁 Maintenance/
│   │   │   │       ├── 📁 Operations/
│   │   │   │       └── 📁 System/
│   │   │   ├── 📁 Auth/
│   │   │   │   └── 🐘 LoginRequest.php
│   │   │   └── 🐘 ProfileUpdateRequest.php
│   │   └── 📁 Resources/
│   │       └── 📁 Api/
│   │           └── 📁 V1/
│   │               ├── 📁 Auth/
│   │               ├── 📁 Equipment/
│   │               ├── 📁 Inspection/
│   │               ├── 📁 Maintenance/
│   │               ├── 📁 Operations/
│   │               └── 📁 System/
│   ├── 📁 Models/
│   │   ├── 🐘 ActivityLog.php
│   │   ├── 🐘 Equipment.php
│   │   ├── 🐘 EquipmentCategory.php
│   │   ├── 🐘 EquipmentDocument.php
│   │   ├── 🐘 EquipmentStatusLog.php
│   │   ├── 🐘 EquipmentType.php
│   │   ├── 🐘 InspectionTemplate.php
│   │   ├── 🐘 Manufacturer.php
│   │   ├── 🐘 Notification.php
│   │   ├── 🐘 OperatingSession.php
│   │   ├── 🐘 Permission.php
│   │   ├── 🐘 Role.php
│   │   ├── 🐘 SystemSetting.php
│   │   └── 🐘 User.php
│   ├── 📁 Providers/
│   │   ├── 🐘 AppServiceProvider.php
│   │   └── 🐘 TelescopeServiceProvider.php
│   ├── 📁 Repositories/
│   │   ├── 📁 Equipment/
│   │   ├── 📁 Inspection/
│   │   ├── 📁 Maintenance/
│   │   └── 📁 Notification/
│   └── 📁 Services/
│       ├── 📁 Equipment/
│       ├── 📁 Inspection/
│       ├── 📁 Maintenance/
│       ├── 📁 Notification/
│       └── 🐘 BaseService.php
├── 📁 bootstrap/
│   ├── 📁 cache/ 🚫 (auto-hidden)
│   ├── 📁 ssr/ 🚫 (auto-hidden)
│   ├── 🐘 app.php
│   └── 🐘 providers.php
├── 📁 config/
│   ├── 🐘 app.php
│   ├── 🐘 auth.php
│   ├── 🐘 cache.php
│   ├── 🐘 database.php
│   ├── 🐘 filesystems.php
│   ├── 🐘 logging.php
│   ├── 🐘 mail.php
│   ├── 🐘 permission.php
│   ├── 🐘 queue.php
│   ├── 🐘 sanctum.php
│   ├── 🐘 services.php
│   ├── 🐘 session.php
│   └── 🐘 telescope.php
├── 📁 database/
│   ├── 📁 factories/
│   │   └── 🐘 UserFactory.php
│   ├── 📁 migrations/
│   │   ├── 🐘 0001_01_01_000000_create_users_table.php
│   │   ├── 🐘 0001_01_01_000001_create_cache_table.php
│   │   ├── 🐘 0001_01_01_000002_create_jobs_table.php
│   │   ├── 🐘 2025_08_12_060306_create_telescope_entries_table.php
│   │   ├── 🐘 2025_08_12_060600_create_personal_access_tokens_table.php
│   │   ├── 🐘 2025_08_12_060918_create_equipment_categories_table.php
│   │   ├── 🐘 2025_08_12_060928_create_manufacturers_table.php
│   │   ├── 🐘 2025_08_12_060938_create_equipment_types_table.php
│   │   ├── 🐘 2025_08_12_060950_create_equipment_table.php
│   │   ├── 🐘 2025_08_12_061012_create_equipment_documents_table.php
│   │   ├── 🐘 2025_08_12_061027_create_equipment_status_log_table.php
│   │   ├── 🐘 2025_08_12_061038_create_system_settings_table.php
│   │   ├── 🐘 2025_08_12_061049_create_activity_logs_table.php
│   │   ├── 🐘 2025_08_12_061100_create_notifications_table.php
│   │   ├── 🐘 2025_08_12_061112_add_fields_to_users_table.php
│   │   ├── 🐘 2025_08_12_061856_create_inspection_templates_table.php
│   │   ├── 🐘 2025_08_12_061911_create_operating_sessions_table.php
│   │   └── 🐘 2025_08_12_062212_create_permission_tables.php
│   ├── 📁 seeders/
│   │   ├── 🐘 AdminUserSeeder.php
│   │   ├── 🐘 DatabaseSeeder.php
│   │   ├── 🐘 EquipmentSeeder.php
│   │   ├── 🐘 PermissionSeeder.php
│   │   ├── 🐘 RolePermissionSeeder.php
│   │   ├── 🐘 RoleSeeder.php
│   │   └── 🐘 UserSeeder.php
│   ├── 🚫 .gitignore
│   └── 🗄️ database.sqlite
├── 📁 node_modules/ 🚫 (auto-hidden)
├── 📁 public/
│   ├── 📁 build/ 🚫 (auto-hidden)
│   ├── 📄 .htaccess
│   ├── 🖼️ favicon.ico
│   ├── 🐘 index.php
│   └── 📄 robots.txt
├── 📁 resources/
│   ├── 📁 css/
│   │   └── 🎨 app.css
│   ├── 📁 js/
│   │   ├── 📁 Components/
│   │   │   ├── 🟢 ApplicationLogo.vue
│   │   │   ├── 🟢 Checkbox.vue
│   │   │   ├── 🟢 DangerButton.vue
│   │   │   ├── 🟢 Dropdown.vue
│   │   │   ├── 🟢 DropdownLink.vue
│   │   │   ├── 🟢 InputError.vue
│   │   │   ├── 🟢 InputLabel.vue
│   │   │   ├── 🟢 Modal.vue
│   │   │   ├── 🟢 NavLink.vue
│   │   │   ├── 🟢 PrimaryButton.vue
│   │   │   ├── 🟢 ResponsiveNavLink.vue
│   │   │   ├── 🟢 SecondaryButton.vue
│   │   │   └── 🟢 TextInput.vue
│   │   ├── 📁 Layouts/
│   │   │   ├── 🟢 AuthenticatedLayout.vue
│   │   │   └── 🟢 GuestLayout.vue
│   │   ├── 📁 Pages/
│   │   │   ├── 📁 Auth/
│   │   │   │   ├── 🟢 ConfirmPassword.vue
│   │   │   │   ├── 🟢 ForgotPassword.vue
│   │   │   │   ├── 🟢 Login.vue
│   │   │   │   ├── 🟢 Register.vue
│   │   │   │   ├── 🟢 ResetPassword.vue
│   │   │   │   └── 🟢 VerifyEmail.vue
│   │   │   ├── 📁 Profile/
│   │   │   │   ├── 📁 Partials/
│   │   │   │   │   ├── 🟢 DeleteUserForm.vue
│   │   │   │   │   ├── 🟢 UpdatePasswordForm.vue
│   │   │   │   │   └── 🟢 UpdateProfileInformationForm.vue
│   │   │   │   └── 🟢 Edit.vue
│   │   │   ├── 🟢 Dashboard.vue
│   │   │   └── 🟢 Welcome.vue
│   │   ├── 📄 app.js
│   │   ├── 📄 bootstrap.js
│   │   └── 📄 ssr.js
│   └── 📁 views/
│       └── 🐘 app.blade.php
├── 📁 routes/
│   ├── 🐘 api.php
│   ├── 🐘 auth.php
│   ├── 🐘 console.php
│   └── 🐘 web.php
├── 📁 storage/
│   ├── 📁 app/
│   │   ├── 📁 private/
│   │   │   └── 🚫 .gitignore
│   │   ├── 📁 public/
│   │   │   └── 🚫 .gitignore
│   │   └── 🚫 .gitignore
│   ├── 📁 framework/
│   │   ├── 📁 cache/ 🚫 (auto-hidden)
│   │   ├── 📁 sessions/
│   │   │   └── 🚫 .gitignore
│   │   ├── 📁 testing/
│   │   │   └── 🚫 .gitignore
│   │   ├── 📁 views/
│   │   │   ├── 🚫 .gitignore
│   │   │   └── 🐘 3c6fb5db3bbc7ecf1cb12dbfb7ed6221.php
│   │   └── 🚫 .gitignore
│   ├── 📁 logs/
│   │   ├── 🚫 .gitignore
│   │   └── 📋 laravel.log 🚫 (auto-hidden)
│   └── 📁 pail/ 🚫 (auto-hidden)
├── 📁 tests/
│   ├── 📁 Feature/
│   │   ├── 📁 Auth/
│   │   │   ├── 🐘 AuthenticationTest.php
│   │   │   ├── 🐘 EmailVerificationTest.php
│   │   │   ├── 🐘 PasswordConfirmationTest.php
│   │   │   ├── 🐘 PasswordResetTest.php
│   │   │   ├── 🐘 PasswordUpdateTest.php
│   │   │   └── 🐘 RegistrationTest.php
│   │   ├── 🐘 ExampleTest.php
│   │   └── 🐘 ProfileTest.php
│   ├── 📁 Unit/
│   │   └── 🐘 ExampleTest.php
│   └── 🐘 TestCase.php
├── 📁 vendor/ 🚫 (auto-hidden)
├── 📄 .DS_Store 🚫 (auto-hidden)
├── 📄 .editorconfig
├── 🔒 .env 🚫 (auto-hidden)
├── 📄 .env.example
├── 📄 .gitattributes
├── 🚫 .gitignore
├── 📝 CLAUDE.md
├── 📄 Heavy_Equipment_API.postman_collection.json
├── 📄 Heavy_Equipment_API.postman_environment.json
├── 📖 README.md
├── 📝 SETUP_SUMMARY.md
├── 📄 artisan
├── 📄 composer.json
├── 🔒 composer.lock 🚫 (auto-hidden)
├── 📄 jsconfig.json
├── 📄 package-lock.json
├── 📄 package.json
├── 📄 phpunit.xml
├── 📄 postcss.config.js
├── 📄 tailwind.config.js
└── 📄 vite.config.js

────────────────────────────────────────────────────────────────────────────────
Generated by FileTree Pro Extension