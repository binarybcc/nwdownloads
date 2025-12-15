#!/bin/bash
# Test vacation API endpoint

API_URL="http://localhost:8081/api.php?action=get_longest_vacations&snapshot_date=2025-12-01"

echo "🧪 Testing Vacation API Endpoint"
echo "================================"
echo "URL: $API_URL"
echo ""

RESPONSE=$(curl -s "$API_URL")

echo "📊 Response:"
echo "$RESPONSE" | jq '.' 2>/dev/null || echo "$RESPONSE"
echo ""

# Check if successful
if echo "$RESPONSE" | grep -q '"success":true'; then
    echo "✅ API call successful!"

    # Show overall count
    OVERALL_COUNT=$(echo "$RESPONSE" | jq '.data.overall | length' 2>/dev/null)
    echo "Overall longest vacations: $OVERALL_COUNT"

    # Show by unit
    echo ""
    echo "By Business Unit:"
    echo "$RESPONSE" | jq '.data.by_unit | to_entries[] | "\(.key): \(.value | length) vacations"' -r 2>/dev/null
else
    echo "❌ API call failed!"
fi
