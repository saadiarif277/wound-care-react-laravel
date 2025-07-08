# Agnostic Medical Distribution Platform Schema Transition

## Completed Tasks

### 1. ✅ Create backup directory and move existing migrations
- Created `/database/migrations/backup/` directory
- Moved all existing migration files to backup
- Preserved README files for reference

### 2. ✅ Create new consolidated migration file with all tables from new schema
- Created `2025_01_01_000000_create_agnostic_platform_schema.php`
- Implemented all tables from the new schema design
- Fixed JSON column defaults (made nullable to avoid MySQL errors)
- Added helpful views for role-based data access
- Includes all foreign key relationships

### 3. ✅ Update models to match new schema structure  
- Created new model files to avoid breaking existing functionality:
  - `Tenant.php` - Multi-tenant foundation model
  - `PatientReference.php` - PHI-free patient references
  - `NewUser.php` - Updated User model with UUID support
  - `UserRole.php` - Scoped RBAC implementation
  - `UserFacilityAssignment.php` - Fine-grained facility permissions
  - `NewEpisode.php` - Episode-based workflow model
  - `EpisodeCareTeam.php` - Episode care team management
  - `NewProductRequest.php` - Product request workflow

### 4. ✅ Test fresh migration and identify breaking changes
- Successfully ran `php artisan migrate:fresh`
- Fixed JSON column default value issues
- All tables created successfully

### 5. ✅ Update seeders to work with new schema
- Completely rewrote `DatabaseSeeder.php` for new schema
- Creates sample data with UUIDs
- Sets up proper scoped RBAC relationships
- Creates sample episodes, requests, and orders
- Successfully tested with `php artisan db:seed`

### 6. ✅ Document schema changes and update CLAUDE.md
- Created this comprehensive documentation

## Review

### Schema Changes Summary

1. **Multi-tenancy**: Added `tenants` table and tenant_id throughout
2. **UUID Primary Keys**: All tables now use CHAR(36) UUID primary keys
3. **Unified Organizations**: Combined facilities, manufacturers, payers into single `organizations` table
4. **Episode-Based Workflow**: New `episodes` table as central clinical context
5. **Scoped RBAC**: New permission system with scope_type and scope_id
6. **PHI Separation**: Added `patient_references` table for non-PHI patient identification
7. **Unified Verifications**: Single `verifications` table for all verification types
8. **Compliance Engine**: Added `compliance_rules` and `order_compliance_checks`
9. **Improved Audit**: Simplified `audit_logs` table structure

### Migration Approach

We took a "clean slate" approach:
- No data migration required (dev environment)
- Fresh migration creates all new tables
- Old migrations backed up for reference
- New models created alongside existing ones (prefixed with "New")

### Key Benefits

1. **Multi-Vertical Support**: Not limited to wound care - supports DME, surgical, pharma
2. **Better Scalability**: UUID keys and multi-tenancy ready
3. **Cleaner Architecture**: Episode → Request → Order → Verification flow
4. **Flexible Permissions**: Scoped RBAC allows fine-grained access control
5. **PHI Compliance**: Clear separation of business logic from PHI data

### Next Steps (Future Work)

1. **Replace Old Models**: Gradually replace old models with new ones
2. **Update Controllers**: Modify controllers to use new models
3. **Update API Endpoints**: Ensure APIs work with new schema
4. **Frontend Updates**: Update React components for new data structure
5. **Service Layer Updates**: Update services to work with new models
6. **Testing**: Create comprehensive tests for new models
7. **Documentation**: Update API documentation

### Breaking Changes to Address

1. **Authentication**: Need to update auth to use `password_hash` field
2. **Relationships**: Many relationships changed (e.g., facilities are now organizations)
3. **IDs**: All IDs are now UUIDs instead of integers
4. **Permissions**: New scoped permission system requires updates
5. **Episode Workflow**: Product requests now require episodes

### Development Notes

- Models created with "New" prefix to avoid conflicts during transition
- Can run old and new systems in parallel during migration
- Database supports both schemas temporarily
- Seeders create realistic test data for development