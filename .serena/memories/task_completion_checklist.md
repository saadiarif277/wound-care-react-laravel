# Task Completion Checklist

When completing any development task, follow these steps:

## 1. Code Quality Checks
- [ ] Run TypeScript type checking: `npm run type-check`
- [ ] Run ESLint: `npm run lint`
- [ ] Fix any linting issues: `npm run lint:fix`
- [ ] Run PHP linting: `./vendor/bin/pint`

## 2. Testing
- [ ] Write/update tests for new functionality
- [ ] Run PHP tests: `php artisan test`
- [ ] Run JavaScript tests: `npm test`
- [ ] Ensure all tests pass
- [ ] Check test coverage if applicable

## 3. Database Changes
- [ ] If database changes were made, ensure migrations are created
- [ ] Test migrations with rollback: `php artisan migrate:rollback`
- [ ] Re-run migrations: `php artisan migrate`
- [ ] Update seeders if necessary

## 4. Security Considerations
- [ ] Verify no PHI data is stored outside FHIR
- [ ] Check that all PHI access is logged
- [ ] Ensure proper authorization checks are in place
- [ ] Validate all user inputs
- [ ] Check for SQL injection vulnerabilities

## 5. Documentation
- [ ] Update inline documentation/comments
- [ ] Update type definitions if interfaces changed
- [ ] Document any new API endpoints
- [ ] Update CLAUDE.md if adding new patterns/conventions

## 6. Performance
- [ ] Check for N+1 queries (use Laravel Debugbar in dev)
- [ ] Optimize database queries with eager loading
- [ ] Ensure proper indexing for new database columns
- [ ] Check frontend bundle size impact

## 7. Final Checks
- [ ] Clear all caches: `php artisan cache:clear`
- [ ] Test in both development and production modes
- [ ] Verify functionality works with both light/dark themes
- [ ] Check responsive design on mobile devices
- [ ] Review git diff for unintended changes

## 8. Git Workflow
- [ ] Stage changes: `git add .`
- [ ] Commit with descriptive message
- [ ] Push to feature branch
- [ ] Create PR targeting master branch
- [ ] Ensure CI/CD checks pass

## Special Considerations
- For Docuseal integrations: Test webhook handling
- For FHIR operations: Verify audit logging
- For commission calculations: Test edge cases
- For UI changes: Screenshot for PR description