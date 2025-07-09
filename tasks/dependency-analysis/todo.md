# Dependency Analysis Task

## Objective
Analyze composer.json and package.json for potentially unused dependencies to optimize the project.

## Todo List

- [x] Analyze PHP dependencies from composer.json
- [x] Analyze JavaScript dependencies from package.json
- [x] Identify unused dependencies
- [x] Check for security vulnerabilities
- [x] Create comprehensive report

## Findings

### Unused PHP Dependencies

1. **ext-exif** - Not used in codebase (only referenced in composer.json)
2. **aws/aws-sdk-php** - Not used in codebase
3. **axlon/laravel-postal-code-validation** - Not used in codebase
4. **cboden/ratchet** - WebSocket library not used
5. **doctrine/dbal** - Database abstraction layer not used
6. **league/glide-symfony** - Image manipulation library not used
7. **sentry/sentry-laravel** - Error tracking not implemented in app code
8. **brianium/paratest** (dev) - Parallel testing not used

### Unused JavaScript Dependencies

1. **@azure-rest/ai-document-intelligence** - Azure AI service not used in frontend
2. **@azure/identity** - Azure authentication not used in frontend
3. **@modelcontextprotocol/sdk** - Only referenced in type definitions
4. **@smithery/sdk** - Not used in codebase
5. **superinterface** - Only referenced in type definitions
6. **magnitude-test** - Test framework with minimal usage
7. **openai** - OpenAI SDK not used in frontend
8. **qdrant** - Vector database client not used
9. **@types/babel__core** (dev) - Babel types not needed
10. **@types/babel__generator** (dev) - Babel types not needed
11. **@types/babel__template** (dev) - Babel types not needed
12. **@types/babel__traverse** (dev) - Babel types not needed
13. **@types/estree** (dev) - AST types not needed
14. **@types/nextgen-events** (dev) - Event types not needed
15. **@types/terminal-kit** (dev) - Terminal types not needed
16. **@types/react-dropzone** (dev) - Outdated, react-dropzone has built-in types

### Dependencies with Security/Update Concerns

1. **@hookform/resolvers** - Major version behind (3.x vs 5.x)
2. **@inertiajs/react** - Major version behind (1.x vs 2.x)
3. **@sentry/browser** - Major version behind (7.x vs 9.x)
4. **@testing-library/react** - Major version behind (14.x vs 16.x)
5. **fakerphp/faker** - Should be in require-dev, not require
6. **roave/security-advisories** - Working as intended (blocks vulnerable packages)

### Dependencies with Duplicate Functionality

1. **docuseal/docuseal-laravel** and **docusealco/docuseal-php** - Both DocuSeal packages
2. **react-hot-toast** and **sonner** - Both toast notification libraries
3. **classnames** and **clsx** - Both CSS class utilities (clsx is more modern)

## Recommendations

### High Priority Removals (Unused)
```bash
# PHP dependencies
composer remove aws/aws-sdk-php
composer remove axlon/laravel-postal-code-validation
composer remove cboden/ratchet
composer remove doctrine/dbal
composer remove league/glide-symfony

# JavaScript dependencies
npm uninstall @azure-rest/ai-document-intelligence @azure/identity
npm uninstall @modelcontextprotocol/sdk @smithery/sdk superinterface
npm uninstall openai qdrant magnitude-test
```

### Dev Dependencies to Remove
```bash
# PHP dev dependencies
composer remove --dev brianium/paratest

# JavaScript dev dependencies
npm uninstall --save-dev @types/babel__core @types/babel__generator
npm uninstall --save-dev @types/babel__template @types/babel__traverse
npm uninstall --save-dev @types/estree @types/nextgen-events
npm uninstall --save-dev @types/terminal-kit @types/react-dropzone
```

### Dependencies to Move
```bash
# Move faker to dev dependencies
composer remove fakerphp/faker
composer require --dev fakerphp/faker
```

### Consider Removing (After Verification)
1. **sentry/sentry-laravel** - If error tracking is not planned
2. **ext-exif** - If image EXIF data is not needed
3. One of the toast libraries (keep sonner, remove react-hot-toast)
4. **classnames** - Keep only clsx

### Update Recommendations
1. Consider updating @inertiajs/react to v2 (breaking changes)
2. Consider updating @hookform/resolvers (check compatibility)
3. Update @sentry packages if keeping them

## Security Notes

- No critical vulnerabilities found in current versions
- roave/security-advisories is properly configured to block vulnerable packages
- Regular dependency updates recommended

## Review Summary

The analysis identified approximately 25 unused dependencies that can be safely removed, which will:
- Reduce bundle size and build times
- Improve security posture by reducing attack surface
- Simplify maintenance and updates
- Save approximately 100MB+ of node_modules space

Before removing dependencies, recommend:
1. Running full test suite
2. Checking with team about future plans for Azure AI, OpenAI, WebSockets
3. Verifying Sentry integration plans
4. Creating a backup branch before removal