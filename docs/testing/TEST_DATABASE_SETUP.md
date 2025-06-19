# Test Database Setup Guide

## Why Tests Reset Your Database

Laravel tests use the `RefreshDatabase` trait which:

1. Runs all migrations before each test
2. Rolls back all migrations after each test
3. Ensures a clean slate for every test

**⚠️ WARNING**: If not configured properly, tests will reset your development database!

## Solution: Use a Separate Test Database

### Option 1: Create a Dedicated Test Database (Recommended)

1. **Create a test database in MySQL**:

```sql
CREATE DATABASE msc_test_db;
```

2. **Create `.env.testing` file** in your project root:

```env
APP_NAME="MSC Healthcare Test"
APP_ENV=testing
APP_KEY=base64:your-app-key-here
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=msc_test_db
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Copy other non-database settings from your .env file
```

3. **Update `phpunit.xml`** (already done):

```xml
<env name="DB_DATABASE" value="msc_test_db"/>
```

### Option 2: Use SQLite In-Memory Database (Fastest)

1. **Update `phpunit.xml`**:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

2. **Pros**:
   - Very fast (runs in memory)
   - No setup required
   - Automatically cleaned up

3. **Cons**:
   - Some MySQL-specific features may not work
   - Can't inspect data after tests

### Option 3: Use Database Transactions (No Reset)

If you need to test against real data without resetting:

1. **Replace `RefreshDatabase` with `DatabaseTransactions`**:

```php
use Illuminate\Foundation\Testing\DatabaseTransactions;

class YourTest extends TestCase
{
    use DatabaseTransactions; // Instead of RefreshDatabase
}
```

2. **How it works**:
   - Wraps each test in a database transaction
   - Rolls back the transaction after each test
   - Your data remains intact

## Running Tests Safely

### Before Running Tests

1. **Verify your database configuration**:

```bash
php artisan config:clear
php artisan config:cache --env=testing
```

2. **Check which database will be used**:

```bash
php artisan tinker --env=testing
>>> config('database.connections.mysql.database')
```

3. **Run migrations on test database**:

```bash
php artisan migrate --env=testing
```

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/ProductRequestPatientE2ETest.php

# Run with specific environment
php artisan test --env=testing
```

## Best Practices

### 1. **Always Use Separate Databases**

- Development: `msc_dev_db`
- Testing: `msc_test_db`
- Production: `msc_prod_db`

### 2. **Use Factories for Test Data**

Instead of relying on existing data:

```php
$user = User::factory()->create();
$productRequest = ProductRequest::factory()->create([
    'provider_id' => $user->id
]);
```

### 3. **Mock External Services**

Don't make real API calls in tests:

```php
Http::fake([
    'external-api.com/*' => Http::response(['data' => 'mocked'], 200)
]);
```

### 4. **Use Database Seeders for Consistent Test Data**

```bash
php artisan db:seed --class=TestDataSeeder --env=testing
```

## Troubleshooting

### "Table doesn't exist" errors

```bash
php artisan migrate:fresh --env=testing
```

### Tests using wrong database

1. Clear config cache: `php artisan config:clear`
2. Check `.env.testing` exists and is correct
3. Verify `APP_ENV=testing` in phpunit.xml

### Want to keep some data between tests

Use `DatabaseTransactions` instead of `RefreshDatabase`

## Emergency Recovery

If tests accidentally reset your development database:

1. **Check for backups**
2. **Use Laravel's migration rollback**:

   ```bash
   php artisan migrate:rollback --step=10
   ```

3. **Restore from database dumps** if available

## Recommended Setup for This Project

Given this is a healthcare application with sensitive data:

1. **Use Option 1** (Dedicated test database)
2. **Name it clearly**: `msc_test_db`
3. **Add to `.gitignore`**: `.env.testing`
4. **Document the setup** in your README
5. **Consider adding a pre-test check**:

```php
// In TestCase.php
protected function setUp(): void
{
    parent::setUp();
    
    if (config('database.connections.mysql.database') === 'msc-dev-rv') {
        $this->fail('Tests are configured to use production database! Check your .env.testing file.');
    }
}
```

This ensures tests never accidentally run against your real database.
