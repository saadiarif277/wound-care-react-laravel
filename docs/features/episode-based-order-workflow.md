# Episode-Based Order Workflow Documentation

## Overview

The Episode-Based Order Workflow represents a fundamental architectural shift from managing individual orders to managing **episodes** - grouped orders by provider and manufacturer combination. This approach aligns with clinical care patterns where providers often submit multiple orders for the same manufacturer over time, streamlining the administrative and approval process.

## 2025 Healthcare UX/UI Design Enhancements

### Overview of Design Improvements

Following 2025 healthcare UX/UI design trends and best practices, the episode-based order workflow has been enhanced with provider-centered design principles:

#### 1. Comprehensive Statistics Dashboard
- **Real-time metrics**: Total episodes, action required count, completion rate, today's episodes, expiring IVRs, total revenue, and average order value
- **Color-coded priority system**: Visual hierarchy using healthcare-appropriate color schemes
- **Responsive grid layout**: Adapts from 1-column on mobile to 7-column display on large screens
- **Interactive elements**: Hover states, refresh capabilities, and export functionality

#### 2. Enhanced Admin Order Center (Index.tsx)
- **Advanced filtering system**: Status, IVR status, manufacturer, and action-required filters
- **Dual view modes**: Cards and table views for different user preferences
- **Visual status indicators**: Priority-based color coding with clear iconography
- **Quick filter buttons**: One-click filtering for common scenarios
- **Real-time updates**: Last refresh timestamp and manual refresh capability
- **Accessibility features**: Screen reader compatible, proper color contrast, keyboard navigation

#### 3. Episode Detail Page (ShowEpisode.tsx)  
- **Three-column layout**: Order info | Episode info | Documents & actions
- **Enhanced information architecture**: Logical grouping of related information
- **Status visualization**: Clear episode and IVR status with descriptions
- **Collapsible sections**: User-controlled information density
- **Action-oriented design**: Context-sensitive action buttons based on permissions
- **Mobile-first responsive design**: Stacks appropriately on smaller screens

### Design Principles Applied

#### Provider-Centered User Experience
- **Clinical workflow alignment**: Matches real-world provider order patterns
- **Information hierarchy**: Most critical information prominently displayed
- **Contextual actions**: Actions appear based on episode status and user permissions
- **Provider efficiency**: Streamlined submission process with IVR generation built-in

#### 2025 Web Design Trends
- **Clean, uncluttered interfaces**: Emphasis on white space and clear typography
- **Micro-interactions**: Subtle hover states and transition effects
- **Consistent iconography**: Lucide React icons for healthcare-appropriate symbols
- **Gradient backgrounds**: Subtle gradients on statistic cards for visual appeal
- **Card-based layouts**: Information grouped in digestible chunks

#### Accessibility and Compliance
- **WCAG 2.1 AA standards**: Color contrast, keyboard navigation, screen reader support
- **HIPAA-compliant design**: Proper handling of PHI display and access controls
- **Cross-browser compatibility**: Consistent experience across modern browsers
- **Performance optimization**: Lazy loading and efficient re-rendering

### User Role-Specific Enhancements

#### For Administrators
- **Comprehensive oversight**: Full episode statistics and management capabilities
- **Bulk operations**: Multi-episode actions and export functionality
- **Advanced filtering**: Multiple filter combinations for complex queries
- **Permission-based UI**: Actions appear/disappear based on user capabilities

#### For Providers
- **Episode-aware interfaces**: Clear understanding of provider+manufacturer relationships
- **Simplified status display**: User-friendly status descriptions and next steps
- **Quick access to related orders**: Easy navigation between episode and individual orders
- **IVR generation built-in**: Providers generate IVRs during order submission
- **Communication tools**: Direct links to manufacturer contact information

#### For Office Managers
- **Operational efficiency**: Clear visibility into workload and priorities
- **Status tracking**: Visual indicators for items requiring attention
- **Workflow optimization**: Logical progression through episode lifecycle
- **Document management**: Centralized access to all episode-related documents

### Technical Implementation Details

#### Component Architecture
```typescript
// Enhanced status configuration with 2025 design principles
const episodeStatusConfig = {
  ready_for_review: {
    icon: Clock,
    label: 'Ready for Review',
    color: 'blue',
    bgColor: 'bg-blue-50',
    textColor: 'text-blue-700',
    borderColor: 'border-blue-200',
    priority: 'high',
    description: 'Episode with IVR ready awaiting admin review',
    providerMessage: 'Your order has been submitted with IVR and is under review'
  },
  // ... additional statuses
};
```

#### Responsive Design Implementation
- **Mobile-first approach**: Base styles for mobile, enhanced for larger screens
- **Flexible grid system**: CSS Grid and Flexbox for complex layouts
- **Breakpoint strategy**: sm (640px), md (768px), lg (1024px), xl (1280px)

#### Performance Optimizations
- **Memoized calculations**: Using useMemo for expensive statistics computations
- **Conditional rendering**: Sections only render when expanded
- **Optimized re-renders**: Strategic use of React.memo and useCallback

## Architecture

### Core Concept

```
Traditional:  Order 1 → Order 2 → Order 3 (Individual Management)
Episode-Based: Provider + Manufacturer = Episode → [Order 1, Order 2, Order 3] (Group Management)
```

### Database Schema

#### Primary Model: `PatientIVRStatus`

- **Table**: `patient_manufacturer_ivr_episodes`
- **Purpose**: Represents a provider+manufacturer episode (note: "patient" in table name is legacy)
- **Key Fields**:
  - `id` (UUID): Primary key
  - `patient_id` (UUID): Provider identifier (legacy field name)
  - `manufacturer_id` (UUID): Manufacturer reference
  - `status` (enum): Episode status
  - `ivr_status` (enum): IVR verification status
  - `verification_date`: When IVR was verified
  - `expiration_date`: When IVR expires
  - `docuseal_*`: DocuSeal integration fields

#### Order Relationship

- **Field**: `ivr_episode_id` in `orders` table
- **Relationship**: Orders belong to episodes
- **Migration**: Added via `2024_07_01_000001_add_ivr_episode_id_to_orders_table.php`

## Status Management

### Episode Status Flow

```
ready_for_review → ivr_verified → sent_to_manufacturer → tracking_added → completed
```

### IVR Status Flow

```
verified → expired (time-based)
```

### Status Definitions

| Episode Status | Description | Admin Actions Available |
|---|---|---|
| `ready_for_review` | Provider submitted order with IVR, awaiting admin review | Review and approve |
| `ivr_verified` | Admin verified IVR and approved episode | Send to manufacturer |
| `sent_to_manufacturer` | Episode submitted to manufacturer | Add tracking |
| `tracking_added` | Tracking information added | Mark completed |
| `completed` | Episode fully processed | None |

| IVR Status | Description | Expiration Logic |
|---|---|---|
| `verified` | IVR verified and active | Based on frequency |
| `expired` | IVR past expiration date | Requires regeneration |

## Frontend Implementation

### Index Page (`Index.tsx`)

**Key Features:**

- Episode-based data display instead of individual orders
- Dual status filtering (episode status + IVR status)
- Episode metrics and summary cards
- Provider+manufacturer grouping visualization

**Data Structure:**

```typescript
interface Episode {
  id: string;
  patient_id: string; // Actually provider_id (legacy field name)
  provider_name?: string;
  provider_display_id: string;
  manufacturer: {
    id: number;
    name: string;
    contact_email?: string;
  };
  status: keyof typeof episodeStatusConfig;
  ivr_status: keyof typeof ivrStatusConfig;
  orders_count: number;
  total_order_value: number;
  action_required: boolean;
  orders: Order[];
}
```

### Episode Detail Page (`ShowEpisode.tsx`)

**Layout:** Three-column design

- **Left Column**: Episode info, provider details, metrics
- **Middle Column**: List of all orders in the episode
- **Right Column**: IVR status, admin actions, audit log

**Key Components:**

- Episode status badges with icons
- Order list with individual statuses
- Permission-based action buttons
- DocuSeal integration status
- Audit trail display

## Backend Implementation

### Controller: `OrderCenterController`

**Key Methods:**

#### `index()` - Episode List

- Queries `PatientIVRStatus` instead of `Order`
- Includes episode and IVR status counts
- Transforms episodes for frontend consumption
- Fetches provider names from user records

#### `showEpisode()` - Episode Detail

- Loads episode with related orders
- Fetches DocuSeal status
- Determines permission-based actions
- Generates audit history

#### Episode-Level Actions

- `reviewEpisode()`: Admin reviews provider-submitted episode with IVR
- `sendEpisodeToManufacturer()`: Submit episode to manufacturer
- `updateEpisodeTracking()`: Add tracking for episode
- `markEpisodeCompleted()`: Mark episode as completed

### Model Relationships

```php
// PatientIVRStatus (Episode) - Note: "patient" is legacy naming
public function orders() {
    return $this->hasMany(Order::class, 'ivr_episode_id');
}

public function manufacturer() {
    return $this->belongsTo(Manufacturer::class);
}

public function provider() {
    return $this->belongsTo(User::class, 'patient_id'); // Legacy field name
}

// Order
public function ivrEpisode() {
    return $this->belongsTo(PatientIVRStatus::class, 'ivr_episode_id');
}
```

## Routing Structure

### Episode Routes

```php
// Main episode routes
Route::get('/admin/orders', 'OrderCenterController@index') // Episode list
Route::get('/admin/episodes/{episode}', 'OrderCenterController@showEpisode')

// Episode-level actions
Route::post('/admin/episodes/{episode}/review', 'OrderCenterController@reviewEpisode')
Route::post('/admin/episodes/{episode}/send-to-manufacturer', 'OrderCenterController@sendEpisodeToManufacturer')
Route::post('/admin/episodes/{episode}/update-tracking', 'OrderCenterController@updateEpisodeTracking')
Route::post('/admin/episodes/{episode}/mark-completed', 'OrderCenterController@markEpisodeCompleted')
```

### Legacy Compatibility

- Individual order routes maintained for backwards compatibility
- Orders with `ivr_episode_id` redirect to episode view
- Legacy actions still functional for non-episode orders

## Episode Creation Logic

### Automatic Episode Creation

Episodes are automatically created during provider order submission in `QuickRequestController`:

```php
// Find or create episode for provider+manufacturer
$episode = PatientIVRStatus::where('patient_id', $providerId) // Legacy field name
    ->where('manufacturer_id', $manufacturerId)
    ->where(function($q) {
        $q->whereNull('expiration_date')->orWhere('expiration_date', '>', now());
    })
    ->first();

if (!$episode) {
    $episode = PatientIVRStatus::create([
        'id' => Str::uuid(),
        'patient_id' => $providerId, // Legacy field name for provider
        'manufacturer_id' => $manufacturerId,
        'status' => 'ready_for_review', // Provider already generated IVR
        'ivr_status' => 'verified',
    ]);
}

// Link order to episode
$order->ivr_episode_id = $episode->id;
```

## Provider IVR Generation Workflow

### New Provider-Centered Flow

1. **Provider Submits Order**: Provider uses QuickRequest/CreateNew interface
2. **IVR Generation**: Provider generates IVR during order submission using DocuSeal
3. **Episode Creation**: System automatically creates or finds existing episode for provider+manufacturer
4. **Admin Review**: Admin reviews completed order with IVR already generated
5. **Approval**: Admin approves and sends to manufacturer

### Key Changes from Legacy Workflow

- **Providers generate IVRs**: No longer admin responsibility
- **Episode status starts at ready_for_review**: IVR already completed
- **Admin role is review/approval**: Not document generation
- **Streamlined process**: Fewer steps, faster turnaround

## Integration Points

### FHIR Integration

- Provider data fetched from user records
- Clinical data stored in Azure Health Data Services
- PHI remains in FHIR, only references stored locally

### DocuSeal Integration

- Episode-level DocuSeal submissions
- Provider-generated IVRs during order submission
- Status synchronization via webhooks
- Document management at episode level

### Manufacturer Integration

- Episode-level submission to manufacturers
- Tracking updates for entire episodes
- Batch processing capabilities

## Performance Considerations

### Database Optimization

- Eager loading of relationships: `with(['manufacturer', 'orders', 'provider'])`
- Indexed queries on patient_id + manufacturer_id (provider_id + manufacturer_id)
- Pagination for large episode lists

### Caching Strategy

- Provider name caching (user table lookups)
- Episode status counts caching
- DocuSeal status caching

### Query Optimization

```php
// Efficient episode loading
$episodes = PatientIVRStatus::with([
    'manufacturer',
    'provider', // User relationship via patient_id field
    'orders' => function($q) {
        $q->orderBy('created_at', 'desc');
    }
])->paginate(20);
```

## Security & Permissions

### Access Control

- Episode access based on `manage-orders` permission
- Provider data access logged via audit service
- Role-based action availability

### Data Protection

- Provider names from user records
- No PHI stored in local episode records
- Audit trail for all episode actions

## Migration Strategy

### Phase 1: Backwards Compatibility ✅

- Legacy order routes maintained
- Existing orders continue to function
- Gradual migration to episode-based workflow

### Phase 2: Full Episode Adoption

- All new orders create/join episodes
- Legacy orders migrated to episodes
- Full episode-based workflow

### Phase 3: Legacy Cleanup

- Remove individual order management
- Consolidate to episode-only workflow
- Archive legacy routes

## Benefits

### Clinical Alignment

- Matches real-world provider order patterns
- Reduces administrative overhead
- Improves care coordination

### Operational Efficiency

- Batch processing of related orders
- Consolidated IVR management (provider-generated)
- Streamlined manufacturer communication

### User Experience

- Logical grouping of related orders
- Reduced context switching
- Comprehensive episode view
- Provider empowerment through IVR generation

## Future Enhancements

### Planned Features

1. **Episode Analytics**: Metrics on episode completion rates
2. **Bulk Actions**: Process multiple episodes simultaneously
3. **Episode Templates**: Predefined episode workflows
4. **Advanced Filtering**: Complex episode queries
5. **Episode Notifications**: Automated alerts for episode events

### Integration Opportunities

1. **Clinical Decision Support**: Episode-based recommendations
2. **Inventory Management**: Episode-level product tracking
3. **Quality Metrics**: Episode outcome tracking
4. **Billing Integration**: Episode-based billing workflows

## Testing Strategy

### Unit Tests

- Episode model methods
- Status transition logic
- Relationship integrity

### Feature Tests

- Episode creation workflow
- Status updates
- Permission enforcement

### Integration Tests

- Provider IVR generation
- DocuSeal workflow
- Manufacturer submission

## Troubleshooting

### Common Issues

1. **Missing Patient Names**
   - Check FHIR service connectivity
   - Verify patient_id format
   - Review caching configuration

2. **Episode Not Created**
   - Verify manufacturer_id exists
   - Check patient_id format
   - Review episode creation logic

3. **Status Not Updating**
   - Check permission requirements
   - Verify status transition logic
   - Review audit logs

### Debug Tools

```php
// Check episode status
PatientIVRStatus::where('patient_id', $patientId)->get();

// Verify order-episode relationship
Order::where('ivr_episode_id', $episodeId)->get();

// Check FHIR patient name resolution
$this->getPatientName($patientId);
```

## Conclusion

The Episode-Based Order Workflow represents a significant architectural improvement that aligns the system with clinical care patterns while improving operational efficiency. The implementation maintains backwards compatibility while providing a foundation for future enhancements in patient care coordination and administrative workflow optimization.
