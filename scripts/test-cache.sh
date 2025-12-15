#!/bin/bash

# Test Cache Implementation
# Tests file-based caching for revenue intelligence API

echo "==> Testing Dashboard Cache Implementation"
echo ""

echo "1. First request (should be cache MISS):"
curl -s http://localhost:8081/api/revenue_intelligence.php | jq '.cached, .generated_at, .served_at' 2>/dev/null || echo "Could not parse JSON"
echo ""

sleep 1

echo "2. Second request (should be cache HIT):"
curl -s http://localhost:8081/api/revenue_intelligence.php | jq '.cached, .cache_generated_at, .served_at' 2>/dev/null || echo "Could not parse JSON"
echo ""

echo "3. Check cache directory:"
docker exec circulation_web ls -lh /tmp/dashboard_cache/ 2>/dev/null | head -10
echo ""

echo "4. Check cache stats:"
docker exec circulation_web php -r "
require_once '/var/www/html/SimpleCache.php';
\$cache = new SimpleCache();
\$stats = \$cache->getStats();
echo 'Cache Files: ' . \$stats['file_count'] . PHP_EOL;
echo 'Total Size: ' . \$stats['total_size_mb'] . ' MB' . PHP_EOL;
echo 'Oldest File: ' . \$stats['oldest_file_age_hours'] . ' hours' . PHP_EOL;
"
echo ""

echo "==> Test Complete!"
echo ""
echo "Expected Results:"
echo "  - First request: cached = false"
echo "  - Second request: cached = true (served from cache)"
echo "  - Cache directory contains .cache files"
echo "  - Cache stats show non-zero file count"
