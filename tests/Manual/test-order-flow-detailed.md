# MSC Wound Portal - Order Flow Testing Guide

## Prerequisites
- PHP 8.2+ installed
- Composer dependencies installed
- npm packages installed
- Database configured and migrated
- DocuSeal API credentials in .env
- Azure FHIR credentials in .env (for PHI data)

## Step 1: Environment Setup

```bash
# 1. Install dependencies if not already done
composer install
npm install

# 2. Copy .env.example if needed
cp .env.example .env

# 3. Generate application key
php artisan key:generate

# 4. Run migrations
php artisan migrate

# 5. Seed basic data
php artisan db:seed
```

## Step 2: Create Test Data

```bash
# Create a test order in 'pending_ivr' status
php artisan db:seed --class=TestOrderSeeder

# Create test users if needed
php artisan tinker
>>> $admin = User::create([
...     'first_name' => 'Test',
...     'last_name' => 'Admin',
...     'email' => 'admin@test.com',
...     'password' => bcrypt('password123'),
...     'account_id' => 1
... ]);
>>> $admin->assignRole('msc-admin');
```

## Step 3: Start Development Servers

```bash
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Vite dev server
npm run dev
```

## Step 4: Test Product Request Creation

### Option A: Quick Request Flow (Recommended for Testing)
1. Login as a provider user
2. Navigate to `/quick-requests/create`
3. Fill in the form:
   - **Patient Info**: John Doe, DOB: 01/01/1970
   - **Product**: Select any available product
   - **Facility**: Select test facility
   - **Insurance**: Medicare
   - **Service Date**: Tomorrow's date
4. Submit the request
5. Verify order created with status `pending_ivr`

### Option B: Standard Request Flow
1. Navigate to `/product-requests/create`
2. Complete all 6 steps:
   - Patient Information
   - Clinical Assessment
   - Validation & Eligibility
   - Clinical Opportunities
   - Product Selection
   - Review & Submit

## Step 5: Test IVR Generation (Admin)

1. Login as admin user (admin@test.com)
2. Navigate to `/admin/orders`
3. Find order with status "Pending IVR"
4. Click on the order to view details
5. In the order detail page, you should see:
   - Patient info (de-identified)
   - Provider details
   - Product information
   - "Generate IVR" button

### Test IVR Generation:
```bash
# Get order ID from the UI or database
php artisan tinker
>>> $order = ProductRequest::where('order_status', 'pending_ivr')->latest()->first();
>>> echo $order->id;

# Test via API (replace {order_id} with actual ID)
curl -X POST http://localhost:8000/admin/orders/{order_id}/generate-ivr \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"ivr_required": true}'
```

## Step 6: Test IVR Auto-Population

The IVR should auto-populate with:
- Patient demographics (from FHIR)
- Provider information
- Facility details
- Product specifications
- Insurance information
- Clinical data

Check populated fields in:
- `/app/Services/IvrFieldMappingService.php`
- Manufacturer-specific mappings

## Step 7: Test Send to Manufacturer

1. After IVR generation, click "Send to Manufacturer"
2. Verify:
   - Status changes to `ivr_sent`
   - Timestamp recorded
   - Email would be sent (check logs)

## Step 8: Complete the Flow

### Simulate Manufacturer Approval:
```bash
php artisan tinker
>>> $order = ProductRequest::find('{order_id}');
>>> $order->update([
...     'order_status' => 'ivr_confirmed',
...     'manufacturer_approved' => true,
...     'manufacturer_approved_at' => now()
... ]);
```

### Admin Final Approval:
1. Navigate back to order details
2. Click "Approve Order"
3. Status changes to `approved`

### Submit to Manufacturer:
1. Click "Submit to Manufacturer"
2. Status changes to `submitted_to_manufacturer`
3. Order complete!

## Verification Checklist

- [ ] Product request creates successfully
- [ ] Order appears in admin center with correct status
- [ ] IVR generation works without errors
- [ ] DocuSeal document is created
- [ ] Fields are auto-populated correctly
- [ ] Send to manufacturer updates status
- [ ] Manufacturer approval can be recorded
- [ ] Final approval workflow completes
- [ ] Order history/audit trail is recorded

## Common Issues & Solutions

### 1. DocuSeal API Error
```bash
# Check config
php artisan tinker
>>> config('services.docuseal.api_key')
>>> config('services.docuseal.api_url')
```

### 2. FHIR Connection Error
```bash
# Test FHIR connection
php artisan tinker
>>> app(App\Services\FhirService::class)->testConnection()
```

### 3. No Products Available
```bash
# Seed products
php artisan db:seed --class=ProductSeeder
```

### 4. Permission Errors
```bash
# Check user permissions
php artisan tinker
>>> $user = User::find(1);
>>> $user->hasPermission('manage-orders')
>>> $user->getAllPermissions()->pluck('name')
```

## API Testing with Postman

### 1. Get CSRF Token
```
GET http://localhost:8000/csrf-token
```

### 2. Login
```
POST http://localhost:8000/login
Body: {
    "email": "admin@test.com",
    "password": "password123"
}
```

### 3. Generate IVR
```
POST http://localhost:8000/admin/orders/{order_id}/generate-ivr
Headers: 
    X-CSRF-TOKEN: {token}
    Accept: application/json
Body: {
    "ivr_required": true
}
```

### 4. Send to Manufacturer
```
POST http://localhost:8000/admin/orders/{order_id}/send-ivr-to-manufacturer
Headers:
    X-CSRF-TOKEN: {token}
    Accept: application/json
```

## Demo Mode Testing

For a visual demonstration without real data:
1. Navigate to `/demo/complete-order-flow`
2. Click through each step
3. Uses mock data (order ID starts with 'demo-')
4. No real API calls, just UI demonstration

## Success Criteria

The order flow is working correctly when:
1. Orders progress through all statuses smoothly
2. IVR documents generate without errors
3. Auto-population fills >90% of fields
4. Status transitions are logged in history
5. No PHP errors in logs
6. UI updates reflect backend changes
7. Email notifications would be sent (check logs)

## Next Steps

After successful testing:
1. Configure production DocuSeal templates
2. Set up manufacturer email addresses
3. Configure FHIR production endpoints
4. Set up monitoring for failed IVRs
5. Train users on the workflow