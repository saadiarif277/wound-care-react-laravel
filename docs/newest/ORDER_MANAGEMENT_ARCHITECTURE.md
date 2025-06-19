# Order Management Architecture - 2025 Healthcare UI

## Overview
The order management system uses three key components for different workflows:

## 1. Show.tsx - Individual Order Management
**Purpose**: Display and manage single orders
**Use Case**: When viewing a specific order's details, regardless of IVR episode

### Key Features:
- Individual order status tracking
- Direct order actions (approve, deny, send back)
- Product visualization with images
- AI-powered predictions for approval probability
- Real-time supply chain insights
- QR code integration for mobile scanning
- Wound measurement tools with auto-calculation
- Visual timeline for service and delivery dates

### When to Use:
- Viewing a single order from any context
- Managing orders that aren't part of an episode
- Quick order actions without episode context
- Individual order tracking and updates

## 2. ShowEpisode.tsx - Episode-Based Order Management
**Purpose**: Manage multiple orders grouped under a single IVR episode (Ashley's Workflow)
**Use Case**: When providers have completed IVR forms that cover multiple orders for the same patient/manufacturer combination

### Key Features:
- Episode-level management (multiple orders)
- Provider-generated IVR review workflow
- Three-column layout for comprehensive view
- Episode timeline and status tracking
- Manufacturer-specific IVR frequency requirements
- Bulk actions across all orders in episode
- Document management at episode level
- Expiration tracking and alerts

### When to Use:
- Managing provider-generated IVR episodes
- Reviewing multiple orders under one IVR
- Bulk submission to manufacturers
- Episode-level tracking and documentation

## 3. Episodes/ Folder - Episode Listings and Management
**Purpose**: List and filter episodes across different states
**Location**: `/resources/js/Pages/Admin/Episodes/`

### Components:
- `PendingReview.tsx` - Episodes awaiting admin review
- `Index.tsx` - All episodes listing (if exists)
- `Active.tsx` - Currently active episodes (if exists)

### When to Use:
- Viewing all episodes in a specific state
- Filtering and searching across episodes
- Dashboard views of episode statuses
- Bulk episode management

## Key Differences Summary

| Feature | Show.tsx | ShowEpisode.tsx | Episodes/ |
|---------|----------|-----------------|-----------|
| Scope | Single order | Multiple orders in episode | Episode listings |
| Use Case | Individual order management | Episode-based workflow | Browse/filter episodes |
| IVR Handling | Generate per order | Review provider-generated | List by IVR status |
| Actions | Order-specific | Episode-wide | Bulk operations |
| Layout | 2-column details | 3-column comprehensive | Grid/list view |
| Primary Users | All admin users | Admin reviewing provider IVRs | Admin dashboard |

## 2025 Healthcare Design Principles Applied

### Visual Elements:
- Product images and visual catalogs
- QR code integration for mobile workflows
- Real-time status indicators
- AI-powered predictions and insights

### Automation Features:
- Auto-calculation for wound measurements
- Predictive delivery timelines
- Smart inventory level monitoring
- Automated supply chain alerts

### Mobile Integration:
- QR code scanning capabilities
- Touch-friendly interfaces
- Responsive layouts
- Mobile-optimized actions

### Data Visualization:
- Visual timelines
- Progress indicators
- Status badges with icons
- Color-coded alerts and warnings

## Workflow Integration

### Provider Workflow (Ashley's Requirements):
1. Provider submits order with completed IVR
2. Creates episode in `ready_for_review` status
3. Admin uses ShowEpisode.tsx to review
4. Bulk approval and submission to manufacturer

### Individual Order Workflow:
1. Order created without IVR
2. Admin uses Show.tsx to manage
3. Generate IVR if needed
4. Individual approval process

This architecture provides flexibility for both individual order management and efficient episode-based workflows while incorporating 2025's best practices for healthcare software design.