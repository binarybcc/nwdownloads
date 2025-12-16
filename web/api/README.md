# Circulation Dashboard API

**Version:** 2.0 (Modular Structure)
**Status:** Migration in progress
**Created:** December 16, 2025

---

## 📁 Directory Structure

```
web/api/
├── legacy.php          # Original monolithic API (2,573 lines)
├── shared/             # Shared functionality (reusable across endpoints)
│   ├── database.php    # Database connection (connectDB, getDBConfig)
│   ├── response.php    # JSON response helpers (sendResponse, sendError)
│   └── utils.php       # Utility functions (date helpers, param validation)
├── endpoints/          # Individual endpoint modules (future migration)
│   ├── dashboard.php   # Dashboard endpoints (planned)
│   ├── subscribers.php # Subscriber endpoints (planned)
│   ├── trends.php      # Trend endpoints (planned)
│   ├── vacations.php   # Vacation endpoints (planned)
│   └── churn.php       # Churn tracking endpoints (planned)
└── functions/          # Data-fetching functions (future migration)
    └── (organized by feature)
```

---

## 🔄 Current State

**Phase:** Structural foundation established
**Router:** `web/api.php` → delegates to `legacy.php`
**Shared modules:** ✅ Extracted and ready for use

All API requests currently handled by `legacy.php` (maintains 100% backward compatibility).

---

## 📊 API Endpoints (14 total)

### Dashboard Endpoints (5)
- `?action=overview` - Get dashboard overview with comparisons
- `?action=business_unit_detail` - Get business unit details
- `?action=paper` - Get individual publication details
- `?action=data_range` - Get available data date range
- `?action=detail_panel` - Get detail panel data for business unit

### Subscriber Endpoints (1)
- `?action=get_subscribers` - Get subscriber list for specific metric

### Trend Endpoints (1)
- `?action=get_trend` - Get historical trend data

### Vacation Endpoints (2)
- `?action=get_longest_vacations` - Get longest vacation subscribers
- `?action=vacation_subscribers` - Get vacation subscriber list

### Churn Endpoints (5)
- `?action=get_churn_overview` - Get churn overview metrics
- `?action=get_churn_by_subscription_type` - Get churn by subscription type
- `?action=get_churn_by_publication` - Get churn by publication
- `?action=get_churn_trend` - Get churn trend over time
- `?action=get_renewal_events` - Get renewal event details

---

## 🛠️ Shared Modules

### database.php
```php
getDBConfig()    // Get database configuration
connectDB($cfg)  // Connect to database with config
```

### response.php
```php
sendResponse($data)           // Send successful JSON response
sendError($msg, $code=400)    // Send error response
sendNotFound($resource)       // Send 404 error
sendBadRequest($msg)          // Send 400 error
sendServerError($msg)         // Send 500 error
```

### utils.php
```php
getWeekBoundaries($date)        // Get week start/end dates
getSaturdayForWeek($date)       // Get Saturday for given date
isValidDate($date)              // Validate Y-m-d format
requireParam($arr, $key, $err)  // Validate required parameter
getParam($arr, $key, $default)  // Get optional parameter
```

---

## 🚀 Future Migration Plan

### Phase 1: Foundation (✅ COMPLETE)
- [x] Create `api/` directory structure
- [x] Extract shared modules (database, response, utils)
- [x] Move monolithic API to `legacy.php`
- [x] Create backward-compatible router

### Phase 2: Endpoint Migration (Planned)
1. **Dashboard Module** (First priority)
   - Extract 5 dashboard endpoints
   - Create `endpoints/dashboard.php`
   - Move supporting functions to `functions/dashboard/`
   - Update router to support new endpoint

2. **Churn Module** (Second priority)
   - Extract 5 churn endpoints
   - Create `endpoints/churn.php`
   - High complexity, good refactoring candidate

3. **Remaining Modules**
   - Subscribers, Trends, Vacations
   - Lower complexity, migrate last

### Phase 3: Deprecation
- Once all endpoints migrated, remove `legacy.php`
- Clean up router
- Update documentation

---

## 📝 Migration Template

**Example: Migrating an endpoint to modular structure**

```php
// endpoints/dashboard.php
<?php
require_once __DIR__ . '/../shared/database.php';
require_once __DIR__ . '/../shared/response.php';
require_once __DIR__ . '/../shared/utils.php';
require_once __DIR__ . '/../functions/dashboard/overview.php';

/**
 * Handle dashboard-related API requests
 */
function handleDashboardEndpoint(PDO $pdo, string $action): void
{
    switch ($action) {
        case 'overview':
            $params = [
                'date' => getParam($_GET, 'date'),
                'compare' => getParam($_GET, 'compare', 'yoy'),
            ];
            $data = getOverviewEnhanced($pdo, $params);
            sendResponse($data);
            break;

        case 'business_unit_detail':
            $unit = requireParam($_GET, 'unit', 'Business unit name required');
            $date = getParam($_GET, 'date');
            $data = getBusinessUnitDetail($pdo, $unit, $date);
            sendResponse($data);
            break;

        default:
            sendBadRequest("Unknown dashboard action: $action");
    }
}
```

---

## 🧪 Testing

**Current Tests:**
- All endpoints still work via `legacy.php`
- Backward compatibility maintained

**Future Testing Strategy:**
1. Test original endpoint: `api.php?action=overview`
2. Migrate endpoint to new module
3. Test new endpoint still works
4. Verify same data returned
5. Update frontend if endpoint URL changes

---

## 📋 Benefits of Modular Structure

### Current (Monolithic)
- ❌ 2,573 lines in one file
- ❌ Hard to find specific endpoints
- ❌ Difficult to test individual features
- ❌ 49 PHPCS warnings (line length)
- ❌ All-or-nothing deployments

### Future (Modular)
- ✅ Files ~200-400 lines each
- ✅ Clear separation of concerns
- ✅ Easy to test individual endpoints
- ✅ Better code quality (smaller files)
- ✅ Independent endpoint deployments

---

## 🔐 Authentication

All API endpoints require authentication via `auth_check.php` (checked at router level).

---

## 🐛 Error Handling

**Standard error response:**
```json
{
  "error": "Error message here"
}
```

**HTTP Status Codes:**
- `200` - Success
- `400` - Bad Request (missing/invalid parameters)
- `404` - Not Found
- `500` - Server Error (database issues, etc.)

---

## 📖 Usage Examples

**Get Dashboard Overview:**
```javascript
fetch('api.php?action=overview&date=2025-12-16&compare=yoy')
  .then(res => res.json())
  .then(data => console.log(data));
```

**Get Churn Overview:**
```javascript
fetch('api.php?action=get_churn_overview&time_range=4weeks')
  .then(res => res.json())
  .then(data => console.log(data));
```

---

## 📚 Additional Documentation

- Main project docs: `/docs/KNOWLEDGE-BASE.md`
- Database schema: `/docs/KNOWLEDGE-BASE.md` (Database Schema section)
- Troubleshooting: `/docs/TROUBLESHOOTING.md`

---

*Last updated: December 16, 2025*
*Maintained by: Development Team*
