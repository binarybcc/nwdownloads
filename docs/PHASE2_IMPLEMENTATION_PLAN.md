# Phase 2 Implementation Plan

**Date:** 2025-12-01
**Status:** Planning

---

## 🎯 Phase 2 Priorities

Based on user requirements, Phase 2 will focus on:

1. **Advanced Comparison Options** (Business Unit & Overall Level)
2. **Export & Reporting** (PDF, CSV, Excel)
3. **Enhanced Analytics** (Trend indicators, forecasting, anomalies)
4. **Drill-Down Features** (Interactive exploration) - **HIGHEST PRIORITY**

---

## 📊 Feature 1: Advanced Comparison Options

### Business Unit Level Comparisons

**Problem:** Currently, comparisons only show at the overall level. Business units show raw data without comparison context.

**Solution:** Add YoY and Previous Week comparisons to each business unit card.

### Design Mockup (Text):

```
┌─────────────────────────────────────────────────────────────┐
│ 🏛️ South Carolina                              3,106 (40.7%) │
│ TJ                                                            │
├─────────────────────────────────────────────────────────────┤
│ Progress Bar: ████████████████░░░░░░░░░░░░ 40.7%            │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌─────────────────┐         Delivery Breakdown              │
│  │                 │         Mail: 2,425 (78.1%)             │
│  │    3,106        │         Digital: 304 (9.8%)             │
│  │  Deliverable    │         Carrier: 377 (12.1%)            │
│  │   of 3,106      │                                         │
│  │                 │         📊 Comparison:                   │
│  └─────────────────┘         vs Last Year:   -125 (-3.9%)   │
│  🏖️ On Vacation              vs Last Week:    +12 (+0.4%)   │
│  0 (0.00%)                                                    │
│                              Trend: ↘️ Declining              │
└─────────────────────────────────────────────────────────────┘
```

### Implementation Details:

#### 1. API Enhancement (`api.php`)

Add business unit comparison calculations:

```php
// In get_business_unit_data() function
function get_business_unit_comparison($unit_name, $current_date, $compare_type) {
    // Calculate comparison period (same logic as overall)
    // Return comparison data for this specific business unit
    return [
        'current' => [...],
        'comparison' => [...],
        'changes' => [
            'total' => -125,
            'total_percent' => -3.9,
            'deliverable' => -120,
            'mail' => -95,
            'digital' => +5,
            'carrier' => -30
        ],
        'trend_direction' => 'declining' // 'growing', 'stable', 'declining'
    ];
}
```

#### 2. Frontend Enhancement (`app.js`)

Add comparison data to business unit rendering:

```javascript
function renderBusinessUnits() {
    // ... existing code ...

    for (const [unitName, config] of Object.entries(BUSINESS_UNITS)) {
        const data = byUnit[unitName];
        const comparison = data.comparison; // NEW

        // Add comparison section to HTML
        html += `
            <div class="flex-1 ml-6 space-y-2">
                <!-- Existing delivery breakdown -->

                <!-- NEW: Comparison section -->
                <div class="mt-3 pt-3 border-t border-gray-200">
                    <div class="text-xs font-medium text-gray-500 mb-2">📊 Comparison</div>
                    ${renderBusinessUnitComparison(comparison)}
                </div>
            </div>
        `;
    }
}

function renderBusinessUnitComparison(comparison) {
    if (!comparison) return '<div class="text-xs text-gray-400">No comparison data</div>';

    const { changes, trend_direction } = comparison;
    const trendIcon = trend_direction === 'growing' ? '↗️' :
                      trend_direction === 'declining' ? '↘️' : '→';

    return `
        <div class="space-y-1 text-xs">
            <div class="flex justify-between">
                <span class="text-gray-600">vs Last Year:</span>
                <span class="${changes.total >= 0 ? 'text-green-600' : 'text-red-600'} font-medium">
                    ${formatChange(changes.total)} (${changes.total_percent > 0 ? '+' : ''}${changes.total_percent.toFixed(1)}%)
                </span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">vs Last Week:</span>
                <span class="${changes.week >= 0 ? 'text-green-600' : 'text-red-600'} font-medium">
                    ${formatChange(changes.week)} (${changes.week_percent > 0 ? '+' : ''}${changes.week_percent.toFixed(1)}%)
                </span>
            </div>
            <div class="flex justify-between items-center mt-2 pt-2 border-t border-gray-100">
                <span class="text-gray-600">Trend:</span>
                <span class="font-medium">${trendIcon} ${capitalize(trend_direction)}</span>
            </div>
        </div>
    `;
}
```

#### 3. Trend Direction Algorithm

```javascript
function calculateTrendDirection(last12Weeks) {
    // Calculate 4-week moving average
    const recentAvg = average(last12Weeks.slice(-4).map(w => w.total));
    const olderAvg = average(last12Weeks.slice(0, 8).map(w => w.total));

    const change = ((recentAvg - olderAvg) / olderAvg) * 100;

    if (change > 2) return 'growing';
    if (change < -2) return 'declining';
    return 'stable';
}
```

---

## 📥 Feature 2: Export & Reporting

### Export Options:

1. **Current View → CSV** - Export visible data table
2. **Current View → PDF** - Printable dashboard snapshot
3. **Historical Data → Excel** - Full data export with multiple sheets
4. **Scheduled Reports** - Email weekly summaries

### Implementation:

#### 1. CSV Export (Client-Side)

```javascript
function exportToCSV() {
    const data = dashboardData;

    // Build CSV rows
    let csv = 'Business Unit,Paper,Total Active,On Vacation,Deliverable,Mail,Digital,Carrier\n';

    for (const [unit, unitData] of Object.entries(data.by_business_unit)) {
        csv += `${unit},ALL,${unitData.total},${unitData.on_vacation},${unitData.deliverable},${unitData.mail},${unitData.digital},${unitData.carrier}\n`;
    }

    for (const [paper, paperData] of Object.entries(data.by_edition)) {
        csv += `${getPaperUnit(paper)},${paper},${paperData.total},${paperData.on_vacation},${paperData.deliverable},${paperData.mail},${paperData.digital},${paperData.carrier}\n`;
    }

    // Download
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `circulation-${dashboardData.period.label.replace(/\s/g, '-')}.csv`;
    a.click();
}
```

#### 2. PDF Export (Using jsPDF)

```html
<!-- Add to index.html -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
```

```javascript
async function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF('p', 'mm', 'a4');

    // Capture dashboard as image
    const dashboard = document.querySelector('main');
    const canvas = await html2canvas(dashboard, {
        scale: 2,
        backgroundColor: '#ffffff'
    });

    const imgData = canvas.toDataURL('image/png');
    const imgWidth = 210; // A4 width in mm
    const imgHeight = (canvas.height * imgWidth) / canvas.width;

    pdf.addImage(imgData, 'PNG', 0, 0, imgWidth, imgHeight);
    pdf.save(`circulation-dashboard-${dashboardData.period.label.replace(/\s/g, '-')}.pdf`);
}
```

#### 3. Excel Export (Using SheetJS)

```html
<!-- Add to index.html -->
<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
```

```javascript
function exportToExcel() {
    const wb = XLSX.utils.book_new();

    // Sheet 1: Summary
    const summaryData = [
        ['Period', dashboardData.period.label],
        ['Date Range', dashboardData.period.date_range],
        ['Total Active', dashboardData.current.total_active],
        ['On Vacation', dashboardData.current.on_vacation],
        ['Deliverable', dashboardData.current.deliverable],
        [''],
        ['Delivery Methods', ''],
        ['Mail', dashboardData.current.mail],
        ['Digital', dashboardData.current.digital],
        ['Carrier', dashboardData.current.carrier]
    ];
    const summarySheet = XLSX.utils.aoa_to_sheet(summaryData);
    XLSX.utils.book_append_sheet(wb, summarySheet, 'Summary');

    // Sheet 2: By Business Unit
    const unitData = [];
    unitData.push(['Business Unit', 'Papers', 'Total', 'Vacation', 'Deliverable', 'Mail', 'Digital', 'Carrier']);
    for (const [unit, data] of Object.entries(dashboardData.by_business_unit)) {
        unitData.push([
            unit,
            BUSINESS_UNITS[unit].papers.join(', '),
            data.total,
            data.on_vacation,
            data.deliverable,
            data.mail,
            data.digital,
            data.carrier
        ]);
    }
    const unitSheet = XLSX.utils.aoa_to_sheet(unitData);
    XLSX.utils.book_append_sheet(wb, unitSheet, 'By Business Unit');

    // Sheet 3: By Publication
    const paperData = [];
    paperData.push(['Paper', 'Name', 'Location', 'Total', 'Vacation', 'Deliverable', 'Mail', 'Digital', 'Carrier']);
    for (const [code, data] of Object.entries(dashboardData.by_edition)) {
        const info = PAPER_INFO[code];
        paperData.push([
            code,
            info.name,
            info.location,
            data.total,
            data.on_vacation,
            data.deliverable,
            data.mail,
            data.digital,
            data.carrier
        ]);
    }
    const paperSheet = XLSX.utils.aoa_to_sheet(paperData);
    XLSX.utils.book_append_sheet(wb, paperSheet, 'By Publication');

    // Sheet 4: 12-Week Trend
    const trendData = [['Date', 'Total Active', 'Deliverable', 'On Vacation']];
    for (const week of dashboardData.trend) {
        trendData.push([
            week.snapshot_date,
            week.total_active,
            week.deliverable,
            week.on_vacation
        ]);
    }
    const trendSheet = XLSX.utils.aoa_to_sheet(trendData);
    XLSX.utils.book_append_sheet(wb, trendSheet, '12-Week Trend');

    // Download
    XLSX.writeFile(wb, `circulation-full-export-${dashboardData.period.label.replace(/\s/g, '-')}.xlsx`);
}
```

#### 4. Export Menu UI

Add to header:

```html
<div class="relative">
    <button id="exportBtn" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition flex items-center space-x-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <span>Export</span>
    </button>

    <div id="exportMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
        <button onclick="exportToCSV()" class="w-full px-4 py-2 text-left hover:bg-gray-50 flex items-center space-x-2">
            <span>📊</span><span>Export to CSV</span>
        </button>
        <button onclick="exportToPDF()" class="w-full px-4 py-2 text-left hover:bg-gray-50 flex items-center space-x-2">
            <span>📄</span><span>Export to PDF</span>
        </button>
        <button onclick="exportToExcel()" class="w-full px-4 py-2 text-left hover:bg-gray-50 flex items-center space-x-2">
            <span>📗</span><span>Export to Excel</span>
        </button>
    </div>
</div>
```

---

## 📈 Feature 3: Enhanced Analytics

### 1. Trend Indicators

Add visual trend indicators throughout the dashboard:

```javascript
function renderTrendIndicator(trendDirection, changePercent) {
    const indicators = {
        growing: { icon: '↗️', color: 'text-green-600', label: 'Growing' },
        declining: { icon: '↘️', color: 'text-red-600', label: 'Declining' },
        stable: { icon: '→', color: 'text-gray-600', label: 'Stable' }
    };

    const indicator = indicators[trendDirection];

    return `
        <span class="${indicator.color} flex items-center space-x-1">
            <span class="text-lg">${indicator.icon}</span>
            <span class="text-sm font-medium">${indicator.label}</span>
            <span class="text-xs">(${changePercent > 0 ? '+' : ''}${changePercent.toFixed(1)}%)</span>
        </span>
    `;
}
```

### 2. Forecasting (Simple Linear Regression)

```javascript
function forecastNextWeek(last12Weeks) {
    // Simple linear regression
    const n = last12Weeks.length;
    let sumX = 0, sumY = 0, sumXY = 0, sumX2 = 0;

    last12Weeks.forEach((week, i) => {
        const x = i + 1;
        const y = week.total_active;
        sumX += x;
        sumY += y;
        sumXY += x * y;
        sumX2 += x * x;
    });

    const slope = (n * sumXY - sumX * sumY) / (n * sumX2 - sumX * sumX);
    const intercept = (sumY - slope * sumX) / n;

    // Forecast next week (x = n + 1)
    const forecast = Math.round(slope * (n + 1) + intercept);
    const confidence = calculateConfidence(last12Weeks, slope);

    return {
        value: forecast,
        confidence: confidence, // 'high', 'medium', 'low'
        change: forecast - last12Weeks[last12Weeks.length - 1].total_active
    };
}
```

### 3. Anomaly Detection

```javascript
function detectAnomalies(trend) {
    const values = trend.map(w => w.total_active);
    const mean = average(values);
    const stdDev = standardDeviation(values);

    return trend.map((week, i) => {
        const zScore = (week.total_active - mean) / stdDev;
        return {
            ...week,
            isAnomaly: Math.abs(zScore) > 2, // 2 standard deviations
            severity: Math.abs(zScore) > 3 ? 'high' : Math.abs(zScore) > 2 ? 'medium' : 'low'
        };
    });
}
```

### 4. Analytics Dashboard Section

Add new section:

```html
<section class="mb-8">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">🔍 Analytics Insights</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <!-- Next Week Forecast -->
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl shadow p-6">
            <div class="flex items-center justify-between mb-2">
                <div class="text-sm font-medium text-blue-900">Next Week Forecast</div>
                <div class="text-2xl">🔮</div>
            </div>
            <div class="text-3xl font-bold text-blue-900" id="forecastValue">--</div>
            <div class="text-sm text-blue-700 mt-2">
                <span id="forecastChange">--</span>
                <span class="text-xs ml-1" id="forecastConfidence">(--)</span>
            </div>
        </div>

        <!-- Strongest Growth -->
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl shadow p-6">
            <div class="flex items-center justify-between mb-2">
                <div class="text-sm font-medium text-green-900">Strongest Growth</div>
                <div class="text-2xl">🚀</div>
            </div>
            <div class="text-2xl font-bold text-green-900" id="strongestGrowth">--</div>
            <div class="text-sm text-green-700 mt-2" id="strongestGrowthChange">--</div>
        </div>

        <!-- Anomaly Alert -->
        <div class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl shadow p-6">
            <div class="flex items-center justify-between mb-2">
                <div class="text-sm font-medium text-amber-900">Unusual Activity</div>
                <div class="text-2xl">⚠️</div>
            </div>
            <div class="text-2xl font-bold text-amber-900" id="anomalyCount">--</div>
            <div class="text-sm text-amber-700 mt-2" id="anomalyDetail">--</div>
        </div>

    </div>
</section>
```

---

## 🔍 Feature 4: Drill-Down Features (HIGHEST PRIORITY)

### User Flow:

1. **Click Business Unit Card** → Expand to show detailed breakdown
2. **Click Publication Card** → Show subscriber-level details
3. **Interactive Filtering** → Filter by delivery type, vacation status
4. **Search** → Find specific subscribers

### Implementation:

#### 1. Expandable Business Unit Cards

```javascript
function renderBusinessUnits() {
    // Add click handler
    html += `
        <div class="paper-card bg-white rounded-xl shadow-sm p-6 border-l-4 cursor-pointer transition-all"
             style="border-left-color: ${config.color}"
             onclick="toggleBusinessUnitDetails('${unitName}')">

            <!-- Existing card content -->

            <!-- Expand/Collapse indicator -->
            <div class="flex items-center justify-center mt-4 text-sm text-gray-500">
                <span id="expand-${unitName}">▼ Click for detailed breakdown</span>
            </div>
        </div>

        <!-- Expandable detail section (hidden by default) -->
        <div id="details-${unitName}" class="hidden bg-gray-50 rounded-xl shadow-inner p-6 mb-4 transition-all">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Left: Publication breakdown -->
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">📰 Publications</h4>
                    <div id="papers-${unitName}" class="space-y-2">
                        <!-- Paper details will be inserted here -->
                    </div>
                </div>

                <!-- Right: Trend chart for this unit -->
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">📊 12-Week Trend</h4>
                    <canvas id="trend-${unitName}" height="200"></canvas>
                </div>
            </div>

            <!-- Delivery method breakdown chart -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">📦 Delivery Method Trends</h4>
                <canvas id="delivery-trend-${unitName}" height="150"></canvas>
            </div>
        </div>
    `;
}

function toggleBusinessUnitDetails(unitName) {
    const details = document.getElementById(`details-${unitName}`);
    const expandBtn = document.getElementById(`expand-${unitName}`);

    if (details.classList.contains('hidden')) {
        // Expand
        details.classList.remove('hidden');
        expandBtn.textContent = '▲ Click to collapse';

        // Load detailed data
        loadBusinessUnitDetails(unitName);
    } else {
        // Collapse
        details.classList.add('hidden');
        expandBtn.textContent = '▼ Click for detailed breakdown';
    }
}
```

#### 2. API Enhancement for Drill-Down Data

```php
// Add new API endpoint
case 'business_unit_detail':
    $unit_name = $_GET['unit'] ?? '';
    $date = $_GET['date'] ?? null;

    $result = get_business_unit_detail($pdo, $unit_name, $date);
    echo json_encode(['success' => true, 'data' => $result]);
    break;

function get_business_unit_detail($pdo, $unit_name, $date = null) {
    // Get papers for this unit
    $papers = get_papers_for_unit($unit_name);

    // Get 12-week trend for each paper in unit
    $trend = get_unit_trend($pdo, $papers, $date);

    // Get delivery method trends
    $delivery_trends = get_delivery_method_trends($pdo, $papers, $date);

    return [
        'unit_name' => $unit_name,
        'papers' => $papers,
        'trend' => $trend,
        'delivery_trends' => $delivery_trends,
        'details_by_paper' => get_paper_details($pdo, $papers, $date)
    ];
}
```

#### 3. Modal for Subscriber Details

```javascript
function showSubscriberDetails(paperCode) {
    // Create modal overlay
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white border-b border-gray-200 p-6 flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-900">${PAPER_INFO[paperCode].name} - Subscriber Details</h3>
                <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="p-6">
                <!-- Filters -->
                <div class="mb-4 flex gap-4">
                    <input type="text" placeholder="Search subscribers..." class="flex-1 px-4 py-2 border border-gray-300 rounded-lg" id="subscriberSearch">
                    <select class="px-4 py-2 border border-gray-300 rounded-lg" id="deliveryFilter">
                        <option value="">All Delivery Types</option>
                        <option value="mail">Mail Only</option>
                        <option value="digital">Digital Only</option>
                        <option value="carrier">Carrier Only</option>
                    </select>
                    <select class="px-4 py-2 border border-gray-300 rounded-lg" id="vacationFilter">
                        <option value="">All Subscribers</option>
                        <option value="active">Active Only</option>
                        <option value="vacation">On Vacation</option>
                    </select>
                </div>

                <!-- Subscriber table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subscriber ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Delivery Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Start Date</th>
                            </tr>
                        </thead>
                        <tbody id="subscriberTableBody" class="bg-white divide-y divide-gray-200">
                            <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-4 flex justify-between items-center">
                    <div class="text-sm text-gray-500">
                        Showing <span id="subscriberStart">1</span> to <span id="subscriberEnd">50</span> of <span id="subscriberTotal">--</span>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="previousPage()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Previous</button>
                        <button onclick="nextPage()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Next</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    // Load subscriber data
    loadSubscriberData(paperCode);
}
```

---

## 🗂️ Database Schema Updates

To support drill-down, we may need to ensure we can query individual subscribers:

```sql
-- Check if we need indexes for performance
CREATE INDEX IF NOT EXISTS idx_subscribers_paper ON subscribers(edition_code);
CREATE INDEX IF NOT EXISTS idx_subscribers_delivery ON subscribers(delivery_type);
CREATE INDEX IF NOT EXISTS idx_subscribers_status ON subscribers(status);
```

---

## 📅 Implementation Timeline

**Week 1: Advanced Comparisons**
- Day 1-2: API enhancement for business unit comparisons
- Day 3-4: Frontend rendering of comparisons
- Day 5: Testing and refinement

**Week 2: Export Functionality**
- Day 1-2: CSV and Excel export
- Day 3-4: PDF export and formatting
- Day 5: Testing and UI polish

**Week 3: Enhanced Analytics**
- Day 1-2: Trend indicators and forecasting
- Day 3-4: Anomaly detection
- Day 5: Analytics dashboard section

**Week 4: Drill-Down Features** (Priority)
- Day 1-2: Expandable business unit cards
- Day 3-4: Subscriber detail modal
- Day 5: Filtering and search functionality

**Week 5: Testing & Deployment**
- Day 1-3: Integration testing
- Day 4: Performance optimization
- Day 5: Production deployment

---

## 📊 Success Metrics

**How we'll know Phase 2 is successful:**

1. **Usage Metrics:**
   - Users drilling down into business units within first week
   - Export function used at least weekly
   - Analytics insights reviewed regularly

2. **Performance:**
   - Drill-down loads in < 2 seconds
   - Export completes in < 5 seconds
   - No performance degradation on main dashboard

3. **User Feedback:**
   - Easier to spot trends and anomalies
   - Comparison data at business unit level is valuable
   - Drill-down provides needed detail without overwhelming

---

## 🚀 Ready to Start?

**Recommended Start:** Feature 4 (Drill-Down) first since it's highest priority, then work backwards.

**Next Steps:**
1. Review this plan
2. Confirm priorities
3. Start implementation

Would you like me to start with the drill-down feature implementation?
