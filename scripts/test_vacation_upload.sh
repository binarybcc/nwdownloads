#!/bin/bash
# Test vacation CSV upload to development environment

CSV_FILE="/Users/johncorbin/Desktop/projs/nwdownloads/queries/SubscribersOnVacation20251208174842.csv"
UPLOAD_URL="http://localhost:8081/upload_vacations.php"

echo "🧪 Testing Vacation Upload"
echo "=========================="
echo "CSV File: $CSV_FILE"
echo "Upload URL: $UPLOAD_URL"
echo ""

# Check if file exists
if [ ! -f "$CSV_FILE" ]; then
    echo "❌ Error: CSV file not found"
    exit 1
fi

# Upload the file
echo "📤 Uploading vacation data..."
RESPONSE=$(curl -s -X POST \
  -F "csv_file=@$CSV_FILE" \
  "$UPLOAD_URL")

echo ""
echo "📊 Response:"
echo "$RESPONSE" | jq '.' 2>/dev/null || echo "$RESPONSE"
echo ""

# Check if successful
if echo "$RESPONSE" | grep -q '"success":true'; then
    echo "✅ Upload successful!"
else
    echo "❌ Upload failed!"
fi
