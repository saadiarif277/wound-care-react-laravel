# Service Cleanup Pre-Removal Checklist

## For Each Service Marked for Removal:

### 1. **Dependency Analysis**
- [ ] Search for direct imports: `use App\Services\{ServiceName}`
- [ ] Check service providers for registrations
- [ ] Look for dependency injection in constructors
- [ ] Search for facade usage if applicable
- [ ] Check config files for service references

### 2. **Database Impact**
- [ ] Check if service creates/modifies database records
- [ ] Identify any background jobs using the service
- [ ] Look for event listeners depending on the service
- [ ] Review scheduled commands using the service

### 3. **Testing Coverage**
- [ ] Run tests specific to the service
- [ ] Check integration tests that might use the service
- [ ] Identify mocked instances in tests
- [ ] Document any failing tests after removal

### 4. **API Endpoints**
- [ ] List all controllers using the service
- [ ] Document API endpoints that will be affected
- [ ] Check for API versioning implications
- [ ] Update API documentation

### 5. **Frontend Impact**
- [ ] Search TypeScript/JavaScript files for API calls
- [ ] Check for hardcoded service expectations
- [ ] Review error handling that expects specific responses

### 6. **Migration Strategy**
- [ ] Create mapping of old service methods to new ones
- [ ] Write migration scripts if needed
- [ ] Plan rollback strategy
- [ ] Document breaking changes

### 7. **Gradual Deprecation**
- [ ] Add @deprecated tags with migration notes
- [ ] Log usage with deprecation warnings
- [ ] Set removal timeline
- [ ] Notify team members

## Service-Specific Actions

### UnifiedEligibilityService (Duplicate)
```bash
# Check both versions
grep -r "UnifiedEligibilityService" app/
grep -r "unified-eligibility" config/
grep -r "UnifiedEligibilityService" tests/
```

### Supabase Services
```bash
# Find remaining references
grep -r "SupabaseService\|SupabaseRLSService" app/
grep -r "supabase" config/
grep -r "SUPABASE_" .env.example
```

### Eligibility Services
```bash
# Map provider usage
grep -r "EligibilityService\|OptumEligibilityService" app/
grep -r "eligibility\.providers" config/
```

## Post-Removal Verification

1. **Run Full Test Suite**
   ```bash
   php artisan test
   npm run test
   ```

2. **Check Application Health**
   ```bash
   php artisan health:check
   php artisan route:list
   php artisan queue:work --stop-when-empty
   ```

3. **Verify API Endpoints**
   ```bash
   # Test critical endpoints
   php artisan test --filter=Api
   ```

4. **Monitor Logs**
   - Check for new errors in storage/logs/
   - Monitor error tracking service
   - Review performance metrics

## Rollback Plan

1. Keep deleted services in a `deprecated/` folder initially
2. Maintain git history for easy restoration
3. Document all changes in CHANGELOG.md
4. Create feature flags for gradual rollout 
