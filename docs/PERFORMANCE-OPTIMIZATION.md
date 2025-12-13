# Performance Optimization Plan for NWDownloads Dashboard
**Date:** 2025-12-12
**Target:** Synology NAS deployment (limited resources)
**Current State:** 24MB database, ~16K subscriber records, weekly data updates

---

## 🔍 Performance Audit Findings

### Database Analysis

**Table Sizes:**
- `subscriber_snapshots`: 24MB (6.7MB data + 17.4MB indexes) - 16,254 rows
- `raw_uploads`: 2.6MB
- All other tables: <100KB each

**Index Status:**
- ✅ OPcache enabled (128MB memory, 2-second revalidation)
- ❌ APCu not installed (data caching unavailable)
- ⚠️  24 indexes on `subscriber_snapshots` (possibly excessive)
- ⚠️  Query execution doing full table scans despite available indexes

**Query Performance Issues:**
```
EXPLAIN shows:
- Table scan on subscriber_snapshots (8,107 rows)
- "Using temporary; Using filesort" (slow operations)
- Subquery prevents index usage on snapshot_date
```

---

## 🎯 Optimization Strategy (NAS-Friendly)

### Priority 1: Application-Level Caching (Highest Impact)

**Why This Matters:**
- Data only changes **once per week** (on CSV upload)
- Same complex queries run on every page load
- NAS has limited CPU for repeated aggregations

**Implementation: File-Based Cache**
```php
<?php
/**
 * Simple file-based cache for NAS deployment
 * No dependencies, no memory overhead, perfect for weekly data updates
 */
class SimpleCache {
    private $cacheDir = '/tmp/dashboard_cache/';
    private $ttl = 604800; // 7 days (until next upload)

    public function get($key) {
        $file = $this->cacheDir . md5($key) . '.cache';
        if (file_exists($file) && (time() - filemtime($file)) < $this->ttl) {
            return json_decode(file_get_contents($file), true);
        }
        return null;
    }

    public function set($key, $data) {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        $file = $this->cacheDir . md5($key) . '.cache';
        file_put_contents($file, json_encode($data));
    }

    public function clear() {
        array_map('unlink', glob($this->cacheDir . '*.cache'));
    }
}
```

**Usage in API endpoints:**
```php
// In revenue_intelligence.php
$cache = new SimpleCache();
$cacheKey = 'revenue_intelligence_' . $snapshot_date;

// Try cache first
$cachedData = $cache->get($cacheKey);
if ($cachedData !== null) {
    echo json_encode($cachedData);
    exit;
}

// Cache miss - run expensive queries
$expiration_risk = getExpirationRisk($pdo, $snapshot_date);
$legacy_rate_analysis = getLegacyRateAnalysis($pdo, $snapshot_date);
$revenue_metrics = getRevenueMetrics($pdo, $snapshot_date);

$response = [
    'success' => true,
    'snapshot_date' => $snapshot_date,
    'expiration_risk' => $expiration_risk,
    'legacy_rate_analysis' => $legacy_rate_analysis,
    'revenue_metrics' => $revenue_metrics,
    'generated_at' => date('Y-m-d H:i:s'),
    'cached' => false
];

// Cache for 7 days
$cache->set($cacheKey, $response);
echo json_encode($response);
```

**Cache Invalidation:**
```php
// In upload_subscribers.php (after successful import)
$cache = new SimpleCache();
$cache->clear(); // Invalidate all caches on new data upload
```

**Expected Impact:**
- ✅ 95%+ reduction in database load (queries only run on first page load after upload)
- ✅ Sub-50ms API responses (reading from disk instead of complex JOINs)
- ✅ Zero memory overhead (file-based, cleaned on upload)
- ✅ No code dependencies required

---

### Priority 2: Database Query Optimization

**Issue:** Subquery prevents index usage
```sql
WHERE s.snapshot_date = (SELECT MAX(snapshot_date) FROM subscriber_snapshots)
```

**Solution: Cache latest snapshot_date**
```php
// Store in session or simple cache file
$latest_date_cache = '/tmp/dashboard_cache/latest_snapshot.txt';
if (file_exists($latest_date_cache) && (time() - filemtime($latest_date_cache)) < 3600) {
    $snapshot_date = file_get_contents($latest_date_cache);
} else {
    $stmt = $pdo->query("SELECT MAX(snapshot_date) as latest_date FROM subscriber_snapshots");
    $latest = $stmt->fetch();
    $snapshot_date = $latest['latest_date'];
    file_put_contents($latest_date_cache, $snapshot_date);
}

// Now use direct comparison (allows index usage)
WHERE s.snapshot_date = :snapshot_date
```

**Expected Impact:**
- ✅ Index usage on `idx_snapshot_date` (currently unused)
- ✅ 50-80% faster query execution
- ✅ No more "Using temporary; Using filesort"

---

### Priority 3: Index Optimization (Cleanup + Add)

**Remove Redundant Indexes:**
```sql
-- These are redundant (covered by composite indexes)
ALTER TABLE subscriber_snapshots DROP INDEX idx_email;
ALTER TABLE subscriber_snapshots DROP INDEX idx_phone;
```

**Add Composite Index for Rate Flags JOIN:**
```sql
-- Optimize the new paper_code + rate_name JOIN we added
CREATE INDEX idx_rate_paper_lookup
ON subscriber_snapshots(snapshot_date, paper_code, rate_name, last_payment_amount);
```

**Expected Impact:**
- ✅ Faster JOIN operations with rate_flags
- ✅ Reduced index size (17MB → ~14MB)
- ✅ Less disk I/O on NAS

---

### Priority 4: HTTP/Browser Caching

**Add Cache Headers to API Responses:**
```php
// For data that changes weekly
header('Cache-Control: public, max-age=86400'); // 24 hours
header('ETag: ' . md5($snapshot_date)); // Invalidates on new data

// Check If-None-Match for 304 Not Modified
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) &&
    $_SERVER['HTTP_IF_NONE_MATCH'] === md5($snapshot_date)) {
    http_response_code(304);
    exit;
}
```

**Expected Impact:**
- ✅ Browser caches API responses for 24 hours
- ✅ Reduced network traffic
- ✅ Instant page loads on repeat visits

---

### Priority 5: PHP OPcache Tuning

**Current Settings:**
- `opcache.memory_consumption = 128MB` ✅ Good
- `opcache.revalidate_freq = 2` ⚠️ Too aggressive for production

**Recommended Production Settings:**
```ini
; /usr/local/etc/php/conf.d/opcache-production.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60        ; Check every 60 seconds (was 2)
opcache.validate_timestamps=1
opcache.fast_shutdown=1
```

**Expected Impact:**
- ✅ Less filesystem checks on NAS
- ✅ 5-10% faster PHP execution

---

## 📊 Expected Performance Improvements

| Metric | Before | After | Improvement |
|--------|--------|-------|-------------|
| **API Response Time** | 200-500ms | 20-50ms | **90% faster** |
| **Database Load** | Every page load | Weekly | **95% reduction** |
| **NAS CPU Usage** | 20-40% spikes | <5% steady | **75% reduction** |
| **Browser Experience** | 1-2 second loads | <200ms | **80% faster** |
| **Cache Hit Ratio** | 0% | 95%+ | **New capability** |

---

## 🚀 Implementation Priority

**Phase 1: Quick Wins (30 minutes)**
1. ✅ Implement file-based cache in `revenue_intelligence.php`
2. ✅ Add cache invalidation to `upload_subscribers.php`
3. ✅ Cache latest snapshot_date lookup

**Phase 2: Database Optimization (15 minutes)**
4. ✅ Add composite index for rate_flags JOIN
5. ✅ Remove redundant email/phone indexes

**Phase 3: HTTP Optimization (10 minutes)**
6. ✅ Add Cache-Control headers to API endpoints
7. ✅ Implement ETag support

**Phase 4: Production Tuning (5 minutes)**
8. ✅ Update OPcache revalidate_freq to 60 seconds

**Total Time: ~60 minutes**
**Expected ROI: 90% faster, 95% less load on NAS**

---

## 🔧 Maintenance & Monitoring

**Weekly (Automated):**
- Cache auto-clears on CSV upload ✅
- Latest snapshot date refreshes ✅

**Monthly:**
- Check `/tmp/dashboard_cache/` disk usage (should be <10MB)
- Review slow query log if enabled

**Quarterly:**
- Analyze index usage: `SELECT * FROM sys.schema_unused_indexes;`
- Consider archiving old snapshot data (>1 year)

---

## ⚠️ What NOT to Do

**Don't:**
- ❌ Install Redis/Memcached on NAS (overkill, memory overhead)
- ❌ Use session-based caching (lost on logout)
- ❌ Cache in database (defeats purpose)
- ❌ Over-index (current 24 indexes is excessive)
- ❌ Use CDN for API responses (data changes weekly, not worth complexity)

**Do:**
- ✅ Keep it simple (file-based cache perfect for NAS)
- ✅ Invalidate on upload (weekly cycle)
- ✅ Trust OPcache for PHP code
- ✅ Let browser cache aggressively

---

## 📝 Testing Checklist

Before deploying to production:
- [ ] Test cache hit/miss with Chrome DevTools Network tab
- [ ] Verify cache clears after CSV upload
- [ ] Check `/tmp/dashboard_cache/` permissions (755)
- [ ] Confirm API response times <50ms on cache hit
- [ ] Test with browser cache disabled (first load performance)
- [ ] Verify 304 Not Modified responses work correctly

---

**Bottom Line:** With simple file-based caching and minor database tweaks, we can achieve **90% faster performance** with **95% less load** on the NAS - perfect for a "lil ol' NAS" that punches above its weight class! 🥊
