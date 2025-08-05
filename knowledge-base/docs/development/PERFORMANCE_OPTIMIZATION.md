# Performance Optimization Guide

**Version:** 1.0  
**Last Updated:** January 2025  
**Audience:** Developers, DevOps, System Administrators

---

## 📋 Overview

This guide provides comprehensive performance optimization strategies for the MSC Wound Care Portal, covering frontend optimization, backend services, database performance, and infrastructure scaling.

## 🎯 Performance Targets

### Response Time Goals
- **Dashboard Load**: < 200ms
- **Quick Request Workflow**: < 90 seconds end-to-end
- **API Responses**: < 100ms (95th percentile)
- **Insurance Verification**: < 3 seconds
- **Document Generation**: < 2 seconds
- **FHIR Operations**: < 500ms

### Throughput Targets
- **Concurrent Users**: 500+ simultaneous
- **Orders per Hour**: 1,000+
- **API Requests**: 10,000+ per minute
- **Document Processing**: 100+ per minute

## 🌐 Frontend Performance Optimization

### React/Inertia.js Optimization

#### Code Splitting & Lazy Loading
```typescript
// Dynamic imports for route-based code splitting
const QuickRequestCreate = lazy(() => 
    import('./Pages/QuickRequest/CreateNew')
);

const ProductRequestDashboard = lazy(() => 
    import('./Pages/ProductRequest/Dashboard')
);

// Component-level lazy loading
const HeavyComponent = lazy(() => 
    import('./Components/HeavyComponent')
);
```

#### Memoization Strategies
```typescript
// Expensive calculations
const memoizedCalculation = useMemo(() => {
    return expensiveOrderCalculation(orderData);
}, [orderData.products, orderData.quantities]);

// Component memoization
const MemoizedOrderItem = memo(({ item }) => {
    return <OrderItemDisplay item={item} />;
});

// Callback memoization
const handleOrderUpdate = useCallback((orderId, updates) => {
    updateOrder(orderId, updates);
}, [updateOrder]);
```

#### Virtual Scrolling for Large Lists
```typescript
// For provider lists, order history, etc.
import { FixedSizeList as List } from 'react-window';

const VirtualizedOrderList = ({ orders }) => (
    <List
        height={600}
        itemCount={orders.length}
        itemSize={80}
        itemData={orders}
    >
        {OrderRowRenderer}
    </List>
);
```

### Asset Optimization

#### Vite Configuration
```javascript
// vite.config.js
export default defineConfig({
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    vendor: ['react', 'react-dom'],
                    ui: ['@headlessui/react', '@heroicons/react'],
                    charts: ['recharts', 'chart.js'],
                    forms: ['react-hook-form', 'yup']
                }
            }
        },
        chunkSizeWarningLimit: 1000
    },
    optimizeDeps: {
        include: ['react', 'react-dom', '@inertiajs/react']
    }
});
```

#### Image Optimization
```typescript
// Responsive images with lazy loading
const OptimizedImage = ({ src, alt, className }) => (
    <img
        src={src}
        alt={alt}
        className={className}
        loading="lazy"
        decoding="async"
        sizes="(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 33vw"
    />
);
```

### State Management Optimization

#### Zustand State Optimization
```typescript
// Selective subscriptions to prevent unnecessary re-renders
const useOrderStore = create((set, get) => ({
    orders: [],
    selectedOrder: null,
    
    // Optimized selectors
    getOrderById: (id) => get().orders.find(order => order.id === id),
    
    // Batch updates
    updateMultipleOrders: (updates) => 
        set(state => ({
            orders: state.orders.map(order => 
                updates[order.id] ? { ...order, ...updates[order.id] } : order
            )
        }))
}));

// Selective subscription
const order = useOrderStore(state => state.getOrderById(orderId));
```

## ⚡ Backend Performance Optimization

### Laravel Optimization

#### Route Caching
```bash
# Production route caching
php artisan route:cache
php artisan config:cache
php artisan view:cache
```

#### Eager Loading Optimization
```php
// Prevent N+1 queries
$orders = Order::with([
    'items.product.manufacturer',
    'productRequest.patient',
    'productRequest.organization'
])->get();

// Selective loading based on need
$orders = Order::with([
    'items' => function ($query) {
        $query->select('id', 'order_id', 'product_id', 'quantity');
    },
    'items.product:id,name,sku,manufacturer_id'
])->get();
```

#### Query Optimization
```php
// Use database-level calculations
$stats = Order::selectRaw('
    COUNT(*) as total_orders,
    SUM(total_amount) as total_revenue,
    AVG(total_amount) as average_order
')->whereDate('created_at', today())->first();

// Chunked processing for large datasets
Order::where('status', 'pending')
    ->chunk(100, function ($orders) {
        foreach ($orders as $order) {
            ProcessOrderJob::dispatch($order);
        }
    });
```

### Service Layer Optimization

#### Caching Strategies
```php
// Service-level caching
class MacValidationService {
    public function validateZipCode(string $zipCode): array {
        return Cache::remember(
            "mac_validation_{$zipCode}",
            now()->addHours(24),
            fn() => $this->performMacValidation($zipCode)
        );
    }
    
    // Cache invalidation
    public function invalidateMacCache(string $zipCode): void {
        Cache::forget("mac_validation_{$zipCode}");
    }
}
```

#### Background Job Processing
```php
// Heavy operations in background
class QuickRequestOrchestrator {
    public function submitOrder(array $data): string {
        $orderId = $this->createOrder($data);
        
        // Process heavy operations asynchronously
        ProcessEligibilityJob::dispatch($orderId);
        GenerateDocumentsJob::dispatch($orderId);
        SendNotificationsJob::dispatch($orderId);
        
        return $orderId;
    }
}
```

#### Connection Pooling
```php
// config/database.php
'mysql' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'forge'),
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'prefix_indexes' => true,
    'strict' => true,
    'engine' => null,
    'options' => [
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'"
    ],
    'pool' => [
        'min' => env('DB_POOL_MIN', 1),
        'max' => env('DB_POOL_MAX', 10),
    ]
],
```

## 🗄️ Database Performance Optimization

### Query Optimization

#### Index Strategy
```sql
-- Composite indexes for common queries
CREATE INDEX idx_orders_status_created 
ON orders (status, created_at);

-- Covering indexes
CREATE INDEX idx_product_requests_complete 
ON product_requests (user_id, status, created_at) 
INCLUDE (id, request_number);

-- Partial indexes for filtered queries
CREATE INDEX idx_active_orders 
ON orders (created_at) 
WHERE status IN ('pending', 'processing');
```

#### Query Rewriting
```sql
-- Before: Inefficient subquery
SELECT * FROM orders 
WHERE id IN (
    SELECT order_id FROM order_items 
    WHERE product_id = 123
);

-- After: Efficient JOIN
SELECT DISTINCT o.* 
FROM orders o
INNER JOIN order_items oi ON o.id = oi.order_id
WHERE oi.product_id = 123;
```

### Database Configuration

#### MySQL Optimization
```ini
# my.cnf optimizations
[mysqld]
innodb_buffer_pool_size = 4G
innodb_buffer_pool_instances = 4
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# Query cache
query_cache_type = 1
query_cache_size = 256M
query_cache_limit = 2M

# Connection optimization
max_connections = 200
thread_cache_size = 50
table_open_cache = 4000
```

#### Read Replicas
```php
// config/database.php
'mysql' => [
    'read' => [
        'host' => [
            '192.168.1.1',
            '192.168.1.2',
        ],
    ],
    'write' => [
        'host' => ['192.168.1.3'],
    ],
    'sticky' => true,
    // ... other config
],
```

### Data Archiving Strategy

#### Automated Archiving
```php
// Archive old audit logs
class ArchiveAuditLogsJob implements ShouldQueue {
    public function handle(): void {
        $cutoffDate = now()->subMonths(6);
        
        // Move to archive table
        DB::statement("
            INSERT INTO audit_logs_archive 
            SELECT * FROM audit_logs 
            WHERE created_at < ?
        ", [$cutoffDate]);
        
        // Delete from main table
        DB::table('audit_logs')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
    }
}
```

## 🚀 Infrastructure Performance

### Redis Optimization

#### Cache Configuration
```bash
# redis.conf optimizations
maxmemory 2gb
maxmemory-policy allkeys-lru
tcp-keepalive 300
timeout 0

# Persistence for cache
save 900 1
save 300 10
save 60 10000
```

#### Session Management
```php
// config/session.php
'driver' => 'redis',
'connection' => 'session',
'expire_on_close' => false,
'encrypt' => true,
'files' => storage_path('framework/sessions'),
'store' => null,
'lottery' => [2, 100],
'cookie' => env('SESSION_COOKIE', 'laravel_session'),
'path' => '/',
'domain' => env('SESSION_DOMAIN', null),
'secure' => env('SESSION_SECURE_COOKIE'),
'http_only' => true,
'same_site' => 'lax',
```

### CDN & Asset Delivery

#### CloudFlare Configuration
```javascript
// Cloudflare Workers for dynamic caching
addEventListener('fetch', event => {
    event.respondWith(handleRequest(event.request));
});

async function handleRequest(request) {
    const url = new URL(request.url);
    
    // Cache API responses for 5 minutes
    if (url.pathname.startsWith('/api/')) {
        const cache = caches.default;
        const cacheKey = new Request(url.toString(), request);
        
        let response = await cache.match(cacheKey);
        if (!response) {
            response = await fetch(request);
            if (response.ok) {
                response = new Response(response.body, {
                    status: response.status,
                    statusText: response.statusText,
                    headers: {
                        ...response.headers,
                        'Cache-Control': 'max-age=300'
                    }
                });
                await cache.put(cacheKey, response.clone());
            }
        }
        return response;
    }
    
    return fetch(request);
}
```

### Azure Optimization

#### App Service Configuration
```json
{
    "webApp": {
        "sku": "P2V2",
        "numberOfWorkers": 3,
        "alwaysOn": true,
        "http20Enabled": true,
        "minTlsVersion": "1.2",
        "ftpsState": "Disabled",
        "appSettings": [
            {
                "name": "WEBSITE_NODE_DEFAULT_VERSION",
                "value": "18.17.1"
            },
            {
                "name": "WEBSITE_ENABLE_SYNC_UPDATE_SITE",
                "value": "true"
            }
        ]
    }
}
```

#### Auto-scaling Rules
```json
{
    "profiles": [
        {
            "name": "Default",
            "capacity": {
                "minimum": "2",
                "maximum": "10",
                "default": "3"
            },
            "rules": [
                {
                    "metricTrigger": {
                        "metricName": "CpuPercentage",
                        "threshold": 70,
                        "operator": "GreaterThan",
                        "timeAggregation": "Average",
                        "timeWindow": "PT5M"
                    },
                    "scaleAction": {
                        "direction": "Increase",
                        "type": "ChangeCount",
                        "value": "1",
                        "cooldown": "PT10M"
                    }
                }
            ]
        }
    ]
}
```

## 📊 Monitoring & Metrics

### Application Performance Monitoring

#### Laravel Telescope
```php
// config/telescope.php
'watchers' => [
    Watchers\CacheWatcher::class => [
        'enabled' => env('TELESCOPE_CACHE_WATCHER', true),
    ],
    Watchers\QueryWatcher::class => [
        'enabled' => env('TELESCOPE_QUERY_WATCHER', true),
        'slow' => 100, // milliseconds
    ],
    Watchers\RequestWatcher::class => [
        'enabled' => env('TELESCOPE_REQUEST_WATCHER', true),
        'size_limit' => 64,
    ],
],
```

#### Custom Metrics
```php
// Performance tracking middleware
class PerformanceTrackingMiddleware {
    public function handle($request, Closure $next) {
        $start = microtime(true);
        
        $response = $next($request);
        
        $duration = (microtime(true) - $start) * 1000;
        
        // Log slow requests
        if ($duration > 1000) {
            Log::warning('Slow request detected', [
                'url' => $request->url(),
                'method' => $request->method(),
                'duration' => $duration,
                'memory' => memory_get_peak_usage(true),
            ]);
        }
        
        return $response;
    }
}
```

### Database Monitoring

#### Slow Query Detection
```sql
-- Enable slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL slow_query_log_file = '/var/log/mysql/slow.log';
SET GLOBAL long_query_time = 1;
SET GLOBAL log_queries_not_using_indexes = 'ON';
```

#### Performance Schema
```sql
-- Monitor top queries by execution time
SELECT 
    DIGEST_TEXT,
    COUNT_STAR,
    AVG_TIMER_WAIT/1000000000 AS avg_time_ms,
    SUM_TIMER_WAIT/1000000000 AS total_time_ms
FROM performance_schema.events_statements_summary_by_digest
ORDER BY total_time_ms DESC
LIMIT 10;
```

## 🔧 Performance Testing

### Load Testing with Artillery

#### Basic Load Test
```yaml
# artillery-config.yml
config:
  target: 'https://api.mscwoundcare.com'
  phases:
    - duration: 60
      arrivalRate: 5
    - duration: 120
      arrivalRate: 10
    - duration: 60
      arrivalRate: 20
  defaults:
    headers:
      Authorization: 'Bearer {{ $randomString() }}'

scenarios:
  - name: 'Quick Request Flow'
    weight: 70
    flow:
      - get:
          url: '/api/v1/quick-request/form-data'
      - post:
          url: '/api/v1/quick-request/submit'
          json:
            patient_data: '{{ patient_data }}'
            products: '{{ products }}'
  
  - name: 'Eligibility Check'
    weight: 30
    flow:
      - post:
          url: '/api/v1/eligibility/check'
          json:
            patient_id: '{{ $randomString() }}'
            insurance_id: '{{ $randomString() }}'
```

#### Stress Testing
```javascript
// k6 stress test
import http from 'k6/http';
import { check, sleep } from 'k6';

export let options = {
    stages: [
        { duration: '2m', target: 100 },
        { duration: '5m', target: 100 },
        { duration: '2m', target: 200 },
        { duration: '5m', target: 200 },
        { duration: '10m', target: 0 },
    ],
    thresholds: {
        http_req_duration: ['p(95)<500'],
        http_req_failed: ['rate<0.02'],
    },
};

export default function() {
    let response = http.get('https://api.mscwoundcare.com/health');
    check(response, {
        'status is 200': (r) => r.status === 200,
        'response time < 200ms': (r) => r.timings.duration < 200,
    });
    sleep(1);
}
```

## 📈 Performance Best Practices

### Development Practices

#### Code Review Checklist
- [ ] Database queries are optimized and indexed
- [ ] N+1 query problems are avoided
- [ ] Heavy operations are moved to background jobs
- [ ] Appropriate caching is implemented
- [ ] Frontend components are optimized for re-renders
- [ ] Large datasets use pagination or virtual scrolling
- [ ] Error handling doesn't impact performance

#### Performance Testing in CI/CD
```yaml
# .github/workflows/performance.yml
name: Performance Tests
on:
  pull_request:
    branches: [main]

jobs:
  performance:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
      - name: Run Performance Tests
        run: |
          php artisan test --testsuite=Performance
          npm run test:performance
```

### Production Optimization

#### Environment Configuration
```bash
# .env production settings
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=warning

# Database optimization
DB_CONNECTION=mysql
DB_POOL_MIN=5
DB_POOL_MAX=20

# Cache optimization
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# File optimization
FILESYSTEM_DISK=s3
```

#### Deployment Optimization
```bash
#!/bin/bash
# optimize-deployment.sh

echo "Optimizing Laravel application..."

# Clear all caches
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimize Composer autoloader
composer install --optimize-autoloader --no-dev

# Build optimized frontend assets
npm run build

echo "Optimization complete!"
```

---

**Related Documentation:**
- [System Architecture](./SYSTEM_ARCHITECTURE.md)
- [Monitoring Guide](../deployment/MONITORING.md)
- [Development Setup](../development/DEVELOPMENT_SETUP.md)
