#!/bin/bash
# Automated Dashboard Screenshot Tool
# Captures full-page screenshot after data loads

set -e

# Configuration
DASHBOARD_URL="${1:-http://localhost:8081}"
OUTPUT_DIR="${2:-$HOME/Desktop}"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
OUTPUT_FILE="${OUTPUT_DIR}/dashboard-${TIMESTAMP}.png"
WINDOW_WIDTH=1920
WINDOW_HEIGHT=4000

echo "📸 Dashboard Screenshot Tool"
echo "================================"
echo "URL: $DASHBOARD_URL"
echo "Output: $OUTPUT_FILE"
echo ""

# Check if Chrome is available
if [ ! -f "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" ]; then
    echo "❌ Google Chrome not found. Please install Chrome."
    exit 1
fi

# Create temp HTML file to inject wait logic
TEMP_HTML=$(mktemp /tmp/screenshot-wrapper.XXXXXX.html)

cat > "$TEMP_HTML" << 'EOF'
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Loading Dashboard...</title>
    <style>
        body { margin: 0; padding: 0; }
        iframe { border: 0; width: 100vw; height: 100vh; }
    </style>
</head>
<body>
    <iframe id="dashboard" src="DASHBOARD_URL_PLACEHOLDER"></iframe>
    <script>
        // Wait for iframe to load, then wait for dashboard data
        const iframe = document.getElementById('dashboard');
        iframe.onload = function() {
            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;

            // Function to check if data is loaded
            function isDataLoaded() {
                // Check if metric cards have actual data (not "--")
                const totalActive = iframeDoc.getElementById('totalActive');
                if (!totalActive || totalActive.textContent === '--') {
                    return false;
                }

                // Check if business units are loaded
                const businessUnits = iframeDoc.getElementById('businessUnits');
                if (!businessUnits) return false;

                const loadingText = businessUnits.textContent.includes('Loading');
                return !loadingText;
            }

            // Poll until data is loaded
            let checkCount = 0;
            const maxChecks = 20; // 10 seconds max

            const checkInterval = setInterval(() => {
                checkCount++;

                if (isDataLoaded()) {
                    console.log('✓ Dashboard data loaded');
                    clearInterval(checkInterval);

                    // Add a marker for screenshot tool
                    iframeDoc.body.setAttribute('data-ready', 'true');
                } else if (checkCount >= maxChecks) {
                    console.log('⚠ Timeout waiting for data, capturing anyway');
                    clearInterval(checkInterval);
                    iframeDoc.body.setAttribute('data-ready', 'timeout');
                }
            }, 500);
        };
    </script>
</body>
</html>
EOF

# Replace placeholder with actual URL
sed -i '' "s|DASHBOARD_URL_PLACEHOLDER|$DASHBOARD_URL|g" "$TEMP_HTML"

echo "⏳ Loading dashboard and waiting for data..."

# Use Chrome to take screenshot with longer delay
/Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome \
  --headless \
  --disable-gpu \
  --screenshot="$OUTPUT_FILE" \
  --window-size=$WINDOW_WIDTH,$WINDOW_HEIGHT \
  --hide-scrollbars \
  --virtual-time-budget=15000 \
  --run-all-compositor-stages-before-draw \
  "$DASHBOARD_URL" \
  2>&1 | grep -v "DevTools\|SharedImageManager" | grep -v "^$" || true

# Wait a moment for file to be written
sleep 2

# Clean up
rm -f "$TEMP_HTML"

# Verify screenshot was created
if [ -f "$OUTPUT_FILE" ]; then
    FILE_SIZE=$(ls -lh "$OUTPUT_FILE" | awk '{print $5}')
    echo ""
    echo "✅ Screenshot captured successfully!"
    echo "📁 File: $OUTPUT_FILE"
    echo "📊 Size: $FILE_SIZE"
    echo ""

    # Optionally open the screenshot
    if command -v open &> /dev/null; then
        echo "Opening screenshot..."
        open "$OUTPUT_FILE"
    fi
else
    echo "❌ Failed to capture screenshot"
    exit 1
fi
