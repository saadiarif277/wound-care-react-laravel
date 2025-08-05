# Testing Guide

## 🧪 Testing Strategy Overview

The MSC Wound Care Portal implements a comprehensive testing strategy to ensure code quality, functionality, and reliability across all system components.

## 🎯 Testing Pyramid

### Unit Tests (Foundation)
- **Purpose**: Test individual methods and classes in isolation
- **Coverage Target**: 80%+ code coverage
- **Tools**: PHPUnit, Jest
- **Scope**: Models, services, utilities, API endpoints

### Integration Tests (Middle Layer)
- **Purpose**: Test component interactions and data flow
- **Coverage**: Database operations, API integrations, service interactions
- **Tools**: PHPUnit with database transactions
- **Scope**: Service layer integration, external API mocking

### End-to-End Tests (Top Layer)
- **Purpose**: Test complete user workflows
- **Coverage**: Critical user journeys and business processes
- **Tools**: Laravel Dusk, Cypress
- **Scope**: Full application workflows from UI to database

## 🔧 Testing Setup

### Local Testing Environment
```bash
# Install testing dependencies
composer install --dev
npm install --dev

# Copy test environment configuration
cp .env.testing.example .env.testing

# Configure test database
php artisan config:cache --env=testing
php artisan migrate --env=testing

# Run all tests
php artisan test
npm run test
```

### Test Database Configuration
```php
// .env.testing
APP_ENV=testing
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
QUEUE_CONNECTION=sync
CACHE_DRIVER=array
SESSION_DRIVER=array
MAIL_MAILER=array
```

### CI/CD Pipeline Testing
```yaml
# .github/workflows/tests.yml
name: Tests
on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ALLOW_EMPTY_PASSWORD: yes
          MYSQL_DATABASE: testing
        ports:
          - 3306:3306
          
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
          extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite, bcmath, soap, intl, gd, exif, iconv
          
      - name: Install Dependencies
        run: |
          composer install --no-progress --prefer-dist --optimize-autoloader
          npm ci
          
      - name: Run Tests
        run: |
          php artisan test --coverage
          npm run test
```

## 🧪 Unit Testing

### Model Testing
```php
// tests/Unit/Models/PatientTest.php
<?php

namespace Tests\Unit\Models;

use App\Models\Patient;
use App\Models\Episode;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PatientTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_has_full_name_attribute()
    {
        $patient = Patient::factory()->make([
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);

        $this->assertEquals('John Doe', $patient->full_name);
    }

    public function test_patient_can_have_multiple_episodes()
    {
        $patient = Patient::factory()->create();
        $episodes = Episode::factory()->count(3)->create([
            'patient_id' => $patient->id
        ]);

        $this->assertCount(3, $patient->episodes);
        $this->assertInstanceOf(Episode::class, $patient->episodes->first());
    }

    public function test_patient_age_calculation()
    {
        $patient = Patient::factory()->make([
            'date_of_birth' => now()->subYears(45)->format('Y-m-d')
        ]);

        $this->assertEquals(45, $patient->age);
    }
}
```

### Service Testing
```php
// tests/Unit/Services/OrderServiceTest.php
<?php

namespace Tests\Unit\Services;

use App\Services\OrderService;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Order;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderService = app(OrderService::class);
    }

    public function test_can_create_order_with_valid_data()
    {
        $patient = Patient::factory()->create();
        $product = Product::factory()->create(['unit_price' => 100.00]);
        
        $orderData = [
            'patient_id' => $patient->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 100.00
                ]
            ]
        ];

        $order = $this->orderService->createOrder($orderData);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals(200.00, $order->total_amount);
        $this->assertEquals('pending', $order->status);
    }

    public function test_throws_exception_for_invalid_product()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid product ID');

        $patient = Patient::factory()->create();
        
        $orderData = [
            'patient_id' => $patient->id,
            'items' => [
                [
                    'product_id' => 99999, // Non-existent product
                    'quantity' => 1,
                    'unit_price' => 100.00
                ]
            ]
        ];

        $this->orderService->createOrder($orderData);
    }
}
```

### API Testing
```php
// tests/Unit/Http/Controllers/PatientControllerTest.php
<?php

namespace Tests\Unit\Http\Controllers;

use App\Models\User;
use App\Models\Patient;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PatientControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_patients_with_proper_permissions()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view-patients');
        
        Patient::factory()->count(5)->create();

        $response = $this->actingAs($user)
                        ->getJson('/api/v1/patients');

        $response->assertStatus(200)
                ->assertJsonCount(5, 'data')
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'first_name',
                            'last_name',
                            'date_of_birth'
                        ]
                    ]
                ]);
    }

    public function test_cannot_list_patients_without_permission()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
                        ->getJson('/api/v1/patients');

        $response->assertStatus(403);
    }

    public function test_can_create_patient_with_valid_data()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('create-patients');

        $patientData = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'date_of_birth' => '1980-05-15',
            'email' => 'jane.smith@example.com'
        ];

        $response = $this->actingAs($user)
                        ->postJson('/api/v1/patients', $patientData);

        $response->assertStatus(201)
                ->assertJsonFragment(['first_name' => 'Jane']);

        $this->assertDatabaseHas('patients', [
            'first_name' => 'Jane',
            'last_name' => 'Smith'
        ]);
    }
}
```

## 🔗 Integration Testing

### Database Integration
```php
// tests/Integration/Database/PatientOrderIntegrationTest.php
<?php

namespace Tests\Integration\Database;

use App\Models\Patient;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PatientOrderIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_order_workflow()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $product = Product::factory()->create(['unit_price' => 50.00]);
        $orderService = app(OrderService::class);

        // Act - Create order
        $order = $orderService->createOrder([
            'patient_id' => $patient->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3,
                    'unit_price' => 50.00
                ]
            ]
        ]);

        // Process order
        $orderService->processOrder($order->id);

        // Assert
        $order->refresh();
        $this->assertEquals('processing', $order->status);
        $this->assertEquals(150.00, $order->total_amount);
        $this->assertCount(1, $order->items);
        
        // Verify patient relationship
        $this->assertTrue($patient->orders->contains($order));
    }
}
```

### External API Integration
```php
// tests/Integration/ExternalAPIs/AvailityIntegrationTest.php
<?php

namespace Tests\Integration\ExternalAPIs;

use App\Services\AvailityService;
use Tests\TestCase;
use Illuminate\Support\Facades\Http;

class AvailityIntegrationTest extends TestCase
{
    public function test_eligibility_check_integration()
    {
        // Mock external API response
        Http::fake([
            'api.availity.com/*' => Http::response([
                'eligible' => true,
                'coverage' => [
                    'active' => true,
                    'deductible' => 1000.00,
                    'coinsurance' => 20
                ]
            ], 200)
        ]);

        $availityService = app(AvailityService::class);
        
        $result = $availityService->checkEligibility([
            'member_id' => 'TEST123',
            'payer_id' => 'BCBS',
            'service_type' => 'durable_medical_equipment'
        ]);

        $this->assertTrue($result['eligible']);
        $this->assertEquals(1000.00, $result['coverage']['deductible']);
        
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.availity.com/eligibility' &&
                   $request['member_id'] === 'TEST123';
        });
    }
}
```

## 🌐 End-to-End Testing

### Browser Testing with Laravel Dusk
```php
// tests/Browser/OrderWorkflowTest.php
<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Patient;
use App\Models\Product;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderWorkflowTest extends DuskTestCase
{
    use RefreshDatabase;

    public function test_provider_can_create_order_for_patient()
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['view-patients', 'create-orders']);
        
        $patient = Patient::factory()->create();
        $product = Product::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $patient, $product) {
            $browser->loginAs($user)
                   ->visit('/orders/create')
                   ->select('patient_id', $patient->id)
                   ->waitFor('#product-selector')
                   ->click('#add-product-' . $product->id)
                   ->type('quantity', '2')
                   ->press('Submit Order')
                   ->waitForText('Order created successfully')
                   ->assertSee('Order #');
        });

        $this->assertDatabaseHas('orders', [
            'patient_id' => $patient->id,
            'status' => 'pending'
        ]);
    }

    public function test_complete_patient_onboarding_workflow()
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(['manage-patients', 'manage-organizations']);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                   ->visit('/admin/patients/create')
                   ->type('first_name', 'Test')
                   ->type('last_name', 'Patient')
                   ->type('date_of_birth', '1980-01-15')
                   ->type('email', 'test.patient@example.com')
                   ->select('insurance_primary_company', 'Blue Cross')
                   ->type('insurance_primary_policy', 'BC123456')
                   ->press('Create Patient')
                   ->waitForText('Patient created successfully')
                   ->assertPathIs('/admin/patients/*')
                   ->assertSee('Test Patient');
        });
    }
}
```

### JavaScript Testing with Jest
```javascript
// resources/js/tests/components/PatientSearch.test.js
import { mount } from '@vue/test-utils'
import PatientSearch from '@/Components/PatientSearch.vue'
import { createPinia, setActivePinia } from 'pinia'

describe('PatientSearch Component', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders search input correctly', () => {
    const wrapper = mount(PatientSearch)
    
    expect(wrapper.find('input[type="text"]').exists()).toBe(true)
    expect(wrapper.find('button[type="submit"]').exists()).toBe(true)
  })

  it('emits search event when form is submitted', async () => {
    const wrapper = mount(PatientSearch)
    
    await wrapper.find('input').setValue('John Doe')
    await wrapper.find('form').trigger('submit')
    
    expect(wrapper.emitted('search')).toBeTruthy()
    expect(wrapper.emitted('search')[0]).toEqual(['John Doe'])
  })

  it('displays search results when provided', async () => {
    const patients = [
      { id: 1, first_name: 'John', last_name: 'Doe' },
      { id: 2, first_name: 'Jane', last_name: 'Smith' }
    ]
    
    const wrapper = mount(PatientSearch, {
      props: { results: patients }
    })
    
    expect(wrapper.findAll('.patient-result')).toHaveLength(2)
    expect(wrapper.text()).toContain('John Doe')
    expect(wrapper.text()).toContain('Jane Smith')
  })
})
```

## 🔒 Security Testing

### Authentication Testing
```php
// tests/Feature/Security/AuthenticationTest.php
<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword'
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_user_is_locked_after_multiple_failed_attempts()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        // Attempt login 5 times with wrong password
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword'
            ]);
        }

        // Sixth attempt should be blocked
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123' // Even with correct password
        ]);

        $response->assertStatus(429); // Too Many Requests
    }
}
```

### Authorization Testing
```php
// tests/Feature/Security/AuthorizationTest.php
<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\Organization;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_cannot_access_admin_routes()
    {
        $provider = User::factory()->create();
        $provider->assignRole('provider');

        $response = $this->actingAs($provider)
                        ->get('/admin/users');

        $response->assertStatus(403);
    }

    public function test_user_can_only_access_own_organization_data()
    {
        $organization1 = Organization::factory()->create();
        $organization2 = Organization::factory()->create();
        
        $user = User::factory()->create(['organization_id' => $organization1->id]);
        $user->givePermissionTo('view-orders');

        // Should be able to access own organization's data
        $response = $this->actingAs($user)
                        ->getJson("/api/v1/organizations/{$organization1->id}/orders");
        $response->assertStatus(200);

        // Should not be able to access other organization's data
        $response = $this->actingAs($user)
                        ->getJson("/api/v1/organizations/{$organization2->id}/orders");
        $response->assertStatus(403);
    }
}
```

## 📊 Performance Testing

### Load Testing with Pest
```php
// tests/Performance/OrderProcessingPerformanceTest.php
<?php

namespace Tests\Performance;

use App\Models\User;
use App\Models\Patient;
use App\Models\Product;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderProcessingPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_creation_performance()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('create-orders');
        
        $patient = Patient::factory()->create();
        $product = Product::factory()->create();

        $orderData = [
            'patient_id' => $patient->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 100.00
                ]
            ]
        ];

        $startTime = microtime(true);
        
        $response = $this->actingAs($user)
                        ->postJson('/api/v1/orders', $orderData);
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $response->assertStatus(201);
        $this->assertLessThan(500, $executionTime, 'Order creation took too long');
    }

    public function test_bulk_order_processing_performance()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('create-orders');
        
        $patients = Patient::factory()->count(10)->create();
        $product = Product::factory()->create();

        $startTime = microtime(true);

        foreach ($patients as $patient) {
            $this->actingAs($user)->postJson('/api/v1/orders', [
                'patient_id' => $patient->id,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 1,
                        'unit_price' => 100.00
                    ]
                ]
            ]);
        }

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $this->assertLessThan(5000, $executionTime, 'Bulk order processing took too long');
    }
}
```

## 🔧 Testing Utilities

### Custom Test Helpers
```php
// tests/TestCase.php
<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Create a user with specific permissions
     */
    protected function createUserWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        return $user;
    }

    /**
     * Create an admin user
     */
    protected function createAdminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('msc-admin');
        return $user;
    }

    /**
     * Create a provider user
     */
    protected function createProviderUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('provider');
        return $user;
    }

    /**
     * Assert JSON response has required structure
     */
    protected function assertJsonApiStructure($response, array $structure)
    {
        $response->assertJsonStructure([
            'data' => $structure,
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'per_page', 'total']
        ]);
    }
}
```

### Database Factories
```php
// database/factories/PatientFactory.php
<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'date_of_birth' => $this->faker->dateTimeBetween('-80 years', '-18 years')->format('Y-m-d'),
            'email' => $this->faker->unique()->safeEmail,
            'phone' => $this->faker->phoneNumber,
            'address' => $this->faker->streetAddress,
            'city' => $this->faker->city,
            'state' => $this->faker->stateAbbr,
            'zip_code' => $this->faker->postcode,
        ];
    }

    public function withInsurance(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'insurance_primary_company' => $this->faker->company,
                'insurance_primary_policy' => $this->faker->bothify('??###????'),
                'insurance_primary_group' => $this->faker->bothify('GRP###'),
            ];
        });
    }

    public function elderly(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'date_of_birth' => $this->faker->dateTimeBetween('-90 years', '-65 years')->format('Y-m-d'),
            ];
        });
    }
}
```

## 📈 Test Coverage & Reporting

### Coverage Configuration
```xml
<!-- phpunit.xml -->
<phpunit>
    <coverage>
        <include>
            <directory suffix=".php">./app</directory>
        </include>
        <exclude>
            <directory>./app/Console</directory>
            <file>./app/Http/Kernel.php</file>
        </exclude>
        <report>
            <html outputDirectory="coverage-report" lowUpperBound="35" highLowerBound="70"/>
            <text outputFile="php://stdout" showUncoveredFiles="true"/>
        </report>
    </coverage>
</phpunit>
```

### Running Coverage Reports
```bash
# Generate HTML coverage report
php artisan test --coverage-html coverage-report

# Generate text coverage report
php artisan test --coverage-text

# Generate coverage with minimum threshold
php artisan test --coverage --min=80

# Run specific test suites
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
```

## 🚀 Continuous Integration

### GitHub Actions Workflow
```yaml
# .github/workflows/ci.yml
name: CI

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main, develop]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ALLOW_EMPTY_PASSWORD: yes
          MYSQL_DATABASE: testing
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

      redis:
        image: redis:6
        ports:
          - 6379:6379
        options: --health-cmd="redis-cli ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
      - name: Checkout code
        uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
          extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite, bcmath, soap, intl, gd, exif, iconv
          coverage: xdebug

      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: 18
          cache: 'npm'

      - name: Install PHP dependencies
        run: composer install --no-progress --prefer-dist --optimize-autoloader --no-dev

      - name: Install JavaScript dependencies
        run: npm ci

      - name: Build assets
        run: npm run build

      - name: Prepare Laravel Application
        run: |
          cp .env.ci .env
          php artisan key:generate
          php artisan migrate --force

      - name: Run PHP tests
        run: php artisan test --coverage-clover coverage.xml

      - name: Run JavaScript tests
        run: npm run test:coverage

      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v3
        with:
          file: ./coverage.xml
          flags: backend

      - name: Upload JS coverage to Codecov
        uses: codecov/codecov-action@v3
        with:
          file: ./coverage/lcov.info
          flags: frontend
```

## 📚 Best Practices

### Test Organization
- **Descriptive Names**: Use clear, descriptive test method names
- **Single Responsibility**: Each test should test one specific behavior
- **Arrange-Act-Assert**: Structure tests with clear setup, execution, and verification
- **Data Providers**: Use data providers for testing multiple scenarios

### Test Data Management
- **Factories**: Use factories for creating test data
- **Traits**: Create reusable test traits for common functionality
- **Seeders**: Use seeders for consistent test data setup
- **Cleanup**: Ensure proper test data cleanup between tests

### Mocking & Stubbing
- **External APIs**: Always mock external API calls
- **Time-Dependent Code**: Mock time for consistent results
- **File Operations**: Mock file system operations
- **Email/SMS**: Mock notification services

### Performance Considerations
- **Database Transactions**: Use database transactions for faster tests
- **In-Memory Database**: Use SQLite in-memory for unit tests
- **Selective Testing**: Run only relevant tests during development
- **Parallel Testing**: Use parallel test execution when possible

## 📞 Support & Resources

### Internal Testing Support
- **QA Team**: qa@mscwoundcare.com
- **Development Team**: dev-team@mscwoundcare.com
- **Test Automation**: automation@mscwoundcare.com

### External Resources
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Laravel Testing Guide](https://laravel.com/docs/testing)
- [Vue Test Utils](https://vue-test-utils.vuejs.org/)
- [Jest Documentation](https://jestjs.io/docs/getting-started)
