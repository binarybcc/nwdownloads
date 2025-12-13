# Revenue Opportunity Visualization - Design Plan
**Date:** 2025-12-12
**Status:** Design Phase
**Goal:** Transform single aggregate card into per-publication breakdown with historical trends

---

## 🎯 Problem Statement

**Current Implementation:**
- Single "Legacy Rate Opportunity" card showing aggregate totals across all publications
- Displays: total legacy subscribers, average rate, monthly gap, annual opportunity
- **Problem**: Cannot identify which publications have the largest revenue opportunities
- **Problem**: No way to track if opportunities are growing or shrinking over time

**User Requirements:**
1. **Main Dashboard**: Per-publication breakdown showing revenue at market rates vs legacy rates with gain calculations
2. **Detail Views**: Historical trends showing evolution over time
3. **Sorting**: Largest opportunities displayed first
4. **Totals**: Aggregate row showing overall metrics

---

## 📊 Proposed Solution - Overview

### Main Dashboard: Revenue Opportunity Table Card

**Visual Design:** Hybrid table with embedded horizontal bar charts

**Data Structure:**
```
Publication | Revenue @ Market | Revenue @ Legacy | Opportunity Gain | Visual Bar
---------------------------------------------------------------------------
TR (Wyoming)     | $45,234/mo    | $12,678/mo      | +$32,556/mo     | [===|=====]
TJ (SC)          | $38,120/mo    | $15,890/mo      | +$22,230/mo     | [==|====]
TA (Michigan)    | $28,450/mo    | $18,230/mo      | +$10,220/mo     | [=|==]
---------------------------------------------------------------------------
TOTAL            | $111,804/mo   | $46,798/mo      | +$65,006/mo     | [===|====]
```

**Features:**
- ✅ Sortable columns (default: largest opportunity first)
- ✅ Inline stacked bar chart showing market vs legacy split
- ✅ Color coding: Green (market rate revenue), Amber (legacy rate revenue)
- ✅ Click row to open publication detail slider
- ✅ Total row with bold styling
- ✅ Responsive: stacks on mobile

### Publication Detail Slider: Deep Dive Analysis

**Section 1: Current State (Top Half)**
- **Left**: Donut chart showing market vs legacy revenue proportion
- **Right**: Key metrics card
  - Total subscribers on legacy rates
  - Average legacy rate vs market rate
  - Monthly opportunity
  - Annual opportunity

**Section 2: Historical Trends (Bottom Half)**
- **Stacked Area Chart** showing evolution over time
  - X-axis: Weekly snapshots (last 12 weeks)
  - Y-axis: Monthly recurring revenue
  - Bottom area (green): Revenue @ market rates
  - Top area (amber): Revenue @ legacy rates
  - Gap between = opportunity
  - Trend indicators: "Opportunity growing ↑" or "Opportunity shrinking ↓"

**Section 3: Action Panel (Bottom)**
- Export subscriber list (legacy rate holders)
- Download revenue opportunity report
- View rate management page

---

## 🎨 Visual Design Specifications

### Color Palette

**Market Rate Revenue** (Good - Already paying market rates):
- Primary: `#10B981` (Green-500)
- Light: `#D1FAE5` (Green-100)
- Dark: `#047857` (Green-700)

**Legacy Rate Revenue** (Opportunity - Could pay more):
- Primary: `#F59E0B` (Amber-500)
- Light: `#FEF3C7` (Amber-100)
- Dark: `#D97706` (Amber-700)

**Opportunity Gain** (Revenue potential):
- Primary: `#3B82F6` (Blue-500)
- Light: `#DBEAFE` (Blue-100)
- Dark: `#1D4ED8` (Blue-700)

### Typography

**Table Headers:**
- Font: Inter 600 (Semi-bold)
- Size: 14px
- Color: `#374151` (Gray-700)

**Table Values:**
- Font: Inter 500 (Medium)
- Size: 16px
- Color: `#111827` (Gray-900)

**Total Row:**
- Font: Inter 700 (Bold)
- Size: 18px
- Color: `#0F172A` (Slate-900)

### Spacing & Layout

**Table Card:**
- Padding: 24px
- Border radius: 12px
- Shadow: `0 4px 6px rgba(0,0,0,0.1)`

**Table Rows:**
- Height: 56px
- Padding: 12px 16px
- Border bottom: 1px solid `#E5E7EB` (Gray-200)

**Total Row:**
- Border top: 2px solid `#D1D5DB` (Gray-300)
- Background: `#F9FAFB` (Gray-50)

---

## 📐 Component Breakdown

### 1. Revenue Opportunity Table Component

**File:** `web/assets/revenue-opportunity-table.js`

**Class:** `RevenueOpportunityTable`

**Methods:**
```javascript
class RevenueOpportunityTable {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.data = null;
    }

    /**
     * Render table with per-publication breakdown
     * @param {Object} data - Revenue opportunity data
     */
    render(data) {
        this.data = data;
        const html = this.buildTableHTML();
        this.container.innerHTML = html;
        this.attachEventListeners();
    }

    /**
     * Build table HTML
     * @returns {string} HTML string
     */
    buildTableHTML() {
        // Sort by opportunity size (largest first)
        const sorted = this.sortByOpportunity(this.data.by_publication);

        // Build rows
        const rows = sorted.map(pub => this.buildRowHTML(pub)).join('');

        // Build total row
        const totalRow = this.buildTotalRowHTML(this.data.totals);

        return `
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    💎 Revenue Opportunity by Publication
                </h3>
                <table class="w-full">
                    <thead>
                        <tr class="border-b-2 border-gray-300">
                            <th class="text-left py-3 px-4">Publication</th>
                            <th class="text-right py-3 px-4">Market Rate MRR</th>
                            <th class="text-right py-3 px-4">Legacy Rate MRR</th>
                            <th class="text-right py-3 px-4">Opportunity</th>
                            <th class="w-1/4 py-3 px-4">Distribution</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows}
                    </tbody>
                    <tfoot>
                        ${totalRow}
                    </tfoot>
                </table>
            </div>
        `;
    }

    /**
     * Build individual row HTML
     * @param {Object} pub - Publication data
     * @returns {string} Row HTML
     */
    buildRowHTML(pub) {
        const marketRevenue = pub.market_rate_revenue;
        const legacyRevenue = pub.legacy_rate_revenue;
        const opportunity = marketRevenue - legacyRevenue;
        const total = marketRevenue;

        const marketPercent = (marketRevenue / total * 100).toFixed(1);
        const legacyPercent = (legacyRevenue / total * 100).toFixed(1);

        return `
            <tr class="border-b border-gray-200 hover:bg-gray-50 cursor-pointer transition"
                data-paper="${pub.paper_code}"
                onclick="openPublicationDetail('${pub.paper_code}')">
                <td class="py-4 px-4">
                    <div class="font-semibold text-gray-900">${pub.paper_name}</div>
                    <div class="text-xs text-gray-500">${pub.business_unit}</div>
                </td>
                <td class="py-4 px-4 text-right font-medium text-green-700">
                    ${this.formatCurrency(marketRevenue)}
                </td>
                <td class="py-4 px-4 text-right font-medium text-amber-700">
                    ${this.formatCurrency(legacyRevenue)}
                </td>
                <td class="py-4 px-4 text-right font-bold text-blue-700">
                    +${this.formatCurrency(opportunity)}
                </td>
                <td class="py-4 px-4">
                    ${this.buildStackedBar(marketPercent, legacyPercent)}
                </td>
            </tr>
        `;
    }

    /**
     * Build stacked horizontal bar chart
     * @param {number} marketPercent - Market rate percentage
     * @param {number} legacyPercent - Legacy rate percentage
     * @returns {string} Bar HTML
     */
    buildStackedBar(marketPercent, legacyPercent) {
        return `
            <div class="flex items-center gap-2">
                <div class="flex-1 h-6 bg-gray-100 rounded-full overflow-hidden flex">
                    <div class="bg-green-500 h-full"
                         style="width: ${marketPercent}%"
                         title="Market Rate: ${marketPercent}%"></div>
                    <div class="bg-amber-500 h-full"
                         style="width: ${legacyPercent}%"
                         title="Legacy Rate: ${legacyPercent}%"></div>
                </div>
                <div class="text-xs text-gray-500 w-12 text-right">
                    ${legacyPercent}%
                </div>
            </div>
        `;
    }

    /**
     * Build total row HTML
     * @param {Object} totals - Aggregate totals
     * @returns {string} Total row HTML
     */
    buildTotalRowHTML(totals) {
        const marketRevenue = totals.total_market_revenue;
        const legacyRevenue = totals.total_legacy_revenue;
        const opportunity = marketRevenue - legacyRevenue;

        const marketPercent = (marketRevenue / marketRevenue * 100).toFixed(1);
        const legacyPercent = (legacyRevenue / marketRevenue * 100).toFixed(1);

        return `
            <tr class="border-t-2 border-gray-300 bg-gray-50">
                <td class="py-4 px-4 font-bold text-gray-900">TOTAL</td>
                <td class="py-4 px-4 text-right font-bold text-green-700">
                    ${this.formatCurrency(marketRevenue)}
                </td>
                <td class="py-4 px-4 text-right font-bold text-amber-700">
                    ${this.formatCurrency(legacyRevenue)}
                </td>
                <td class="py-4 px-4 text-right font-bold text-blue-700">
                    +${this.formatCurrency(opportunity)}
                </td>
                <td class="py-4 px-4">
                    ${this.buildStackedBar(marketPercent, legacyPercent)}
                </td>
            </tr>
        `;
    }

    /**
     * Sort publications by opportunity size (descending)
     * @param {Array} publications - Array of publication objects
     * @returns {Array} Sorted array
     */
    sortByOpportunity(publications) {
        return publications.sort((a, b) => {
            const oppA = a.market_rate_revenue - a.legacy_rate_revenue;
            const oppB = b.market_rate_revenue - b.legacy_rate_revenue;
            return oppB - oppA; // Descending
        });
    }

    /**
     * Format currency
     * @param {number} amount - Dollar amount
     * @returns {string} Formatted string
     */
    formatCurrency(amount) {
        return '$' + amount.toLocaleString('en-US', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }

    /**
     * Attach click event listeners
     */
    attachEventListeners() {
        // Rows already have onclick handler in HTML
    }
}
```

---

### 2. Publication Detail Slider Component

**File:** `web/assets/publication-revenue-detail.js`

**Class:** `PublicationRevenueDetail`

**Methods:**
```javascript
class PublicationRevenueDetail {
    constructor() {
        this.panel = null;
        this.charts = {};
    }

    /**
     * Show detail panel for a publication
     * @param {string} paperCode - Publication code (TJ, TA, etc.)
     */
    async show(paperCode) {
        // Fetch detailed data
        const response = await fetch(`api/revenue_intelligence.php?action=publication_detail&paper=${paperCode}`);
        const data = await response.json();

        // Create panel
        this.createPanel(data);

        // Render charts
        this.renderCurrentStateDonut(data.current);
        this.renderHistoricalTrend(data.historical);
    }

    /**
     * Create slide-out panel
     * @param {Object} data - Publication revenue data
     */
    createPanel(data) {
        const panelHTML = `
            <div id="revenueDetailPanel" class="fixed inset-y-0 right-0 w-3/4 bg-white shadow-2xl z-50 transform transition-transform duration-300 overflow-y-auto">
                <!-- Header -->
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-2xl font-bold">${data.paper_name} Revenue Opportunity</h2>
                            <p class="text-blue-100 mt-1">${data.business_unit}</p>
                        </div>
                        <button onclick="closeRevenueDetail()" class="text-white hover:text-blue-100">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Content -->
                <div class="p-6 space-y-6">
                    <!-- Current State -->
                    <section>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Current State</h3>
                        <div class="grid grid-cols-2 gap-6">
                            <!-- Donut Chart -->
                            <div class="bg-gray-50 rounded-lg p-4">
                                <canvas id="revenueDonutChart" width="300" height="300"></canvas>
                            </div>

                            <!-- Metrics -->
                            <div class="space-y-4">
                                <div class="bg-white rounded-lg p-4 shadow">
                                    <div class="text-sm text-gray-600">Legacy Rate Subscribers</div>
                                    <div class="text-3xl font-bold text-gray-900">${data.current.legacy_subscribers}</div>
                                </div>
                                <div class="bg-white rounded-lg p-4 shadow">
                                    <div class="text-sm text-gray-600">Average Legacy Rate</div>
                                    <div class="text-3xl font-bold text-amber-700">${this.formatCurrency(data.current.avg_legacy_rate)}/mo</div>
                                </div>
                                <div class="bg-white rounded-lg p-4 shadow">
                                    <div class="text-sm text-gray-600">Monthly Opportunity</div>
                                    <div class="text-3xl font-bold text-blue-700">+${this.formatCurrency(data.current.monthly_opportunity)}</div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Historical Trend -->
                    <section>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Trend Over Time (Last 12 Weeks)</h3>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <canvas id="revenueTrendChart" width="800" height="400"></canvas>
                        </div>
                        <div class="mt-4 text-center">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${data.trend_direction === 'growing' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'}">
                                ${data.trend_direction === 'growing' ? '⚠️ Opportunity Growing' : '✓ Opportunity Shrinking'}
                                ${data.trend_direction === 'growing' ? '↑' : '↓'} ${Math.abs(data.trend_percent)}%
                            </span>
                        </div>
                    </section>

                    <!-- Actions -->
                    <section>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Actions</h3>
                        <div class="flex gap-4">
                            <button onclick="exportLegacySubscribers('${data.paper_code}')" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                📊 Export Legacy Rate Subscribers
                            </button>
                            <button onclick="openRateManagement()" class="flex-1 bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                                ⚙️ Manage Rates
                            </button>
                        </div>
                    </section>
                </div>
            </div>

            <!-- Backdrop -->
            <div id="revenueDetailBackdrop" class="fixed inset-0 bg-black bg-opacity-50 z-40" onclick="closeRevenueDetail()"></div>
        `;

        document.body.insertAdjacentHTML('beforeend', panelHTML);
    }

    /**
     * Render donut chart showing market vs legacy split
     * @param {Object} data - Current state data
     */
    renderCurrentStateDonut(data) {
        const ctx = document.getElementById('revenueDonutChart').getContext('2d');

        this.charts.donut = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Market Rate Revenue', 'Legacy Rate Revenue'],
                datasets: [{
                    data: [data.market_revenue, data.legacy_revenue],
                    backgroundColor: ['#10B981', '#F59E0B'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percent = ((value / total) * 100).toFixed(1);
                                return `${label}: $${value.toLocaleString()} (${percent}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Render stacked area chart showing historical trend
     * @param {Object} data - Historical data (weekly snapshots)
     */
    renderHistoricalTrend(data) {
        const ctx = document.getElementById('revenueTrendChart').getContext('2d');

        // Extract dates and values
        const dates = data.map(d => d.snapshot_date);
        const marketRevenue = data.map(d => d.market_revenue);
        const legacyRevenue = data.map(d => d.legacy_revenue);

        this.charts.trend = new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Market Rate Revenue',
                        data: marketRevenue,
                        backgroundColor: 'rgba(16, 185, 129, 0.5)',
                        borderColor: '#10B981',
                        borderWidth: 2,
                        fill: true
                    },
                    {
                        label: 'Legacy Rate Revenue',
                        data: legacyRevenue,
                        backgroundColor: 'rgba(245, 158, 11, 0.5)',
                        borderColor: '#F59E0B',
                        borderWidth: 2,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    x: {
                        stacked: true,
                        title: {
                            display: true,
                            text: 'Week'
                        }
                    },
                    y: {
                        stacked: true,
                        title: {
                            display: true,
                            text: 'Monthly Recurring Revenue ($)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || '';
                                const value = context.parsed.y || 0;
                                return `${label}: $${value.toLocaleString()}`;
                            },
                            footer: function(tooltipItems) {
                                const market = tooltipItems[0].parsed.y;
                                const legacy = tooltipItems[1].parsed.y;
                                const gap = market - legacy;
                                return `Opportunity: +$${gap.toLocaleString()}`;
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Close panel
     */
    close() {
        document.getElementById('revenueDetailPanel')?.remove();
        document.getElementById('revenueDetailBackdrop')?.remove();

        // Destroy charts
        Object.values(this.charts).forEach(chart => chart.destroy());
        this.charts = {};
    }

    /**
     * Format currency
     */
    formatCurrency(amount) {
        return '$' + amount.toLocaleString('en-US', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }
}

// Global functions
function openPublicationDetail(paperCode) {
    const detail = new PublicationRevenueDetail();
    detail.show(paperCode);
}

function closeRevenueDetail() {
    const panel = document.querySelector('.publication-revenue-detail');
    if (panel) {
        panel.close();
    }
}
```

---

## 🔌 API Modifications

### Required API Changes

**File:** `web/api/revenue_intelligence.php`

#### 1. Add Per-Publication Revenue Breakdown

**New Function:**
```php
/**
 * Get revenue opportunity breakdown by publication
 * Calculates what subscribers COULD pay at market rates vs what they ARE paying
 *
 * @param PDO $pdo Database connection
 * @param string $snapshot_date Snapshot date
 * @return array Per-publication revenue metrics
 */
function getRevenueOpportunityByPublication($pdo, $snapshot_date) {
    // Get market rates per publication (from rate_flags table)
    $marketRates = getMarketRates($pdo);

    $sql = "
        SELECT
            s.business_unit,
            s.paper_code,
            s.paper_name,
            COUNT(*) as total_subscribers,

            -- Legacy rate subscribers (< $100/year, not SPECIAL/IGNORED)
            COUNT(CASE
                WHEN ABS(s.last_payment_amount) < 100
                  AND (rf.is_special IS NULL OR rf.is_special = 0)
                  AND (rf.is_ignored IS NULL OR rf.is_ignored = 0)
                THEN 1
            END) as legacy_rate_subscribers,

            -- Current revenue from legacy rate subscribers
            SUM(CASE
                WHEN ABS(s.last_payment_amount) < 100
                  AND (rf.is_special IS NULL OR rf.is_special = 0)
                  AND (rf.is_ignored IS NULL OR rf.is_ignored = 0)
                THEN ABS(s.last_payment_amount)
            END) as legacy_rate_revenue,

            -- Average legacy rate
            AVG(CASE
                WHEN ABS(s.last_payment_amount) < 100
                  AND (rf.is_special IS NULL OR rf.is_special = 0)
                  AND (rf.is_ignored IS NULL OR rf.is_ignored = 0)
                THEN ABS(s.last_payment_amount)
            END) as avg_legacy_rate

        FROM subscriber_snapshots s
        LEFT JOIN rate_flags rf
            ON s.rate_name = rf.zone
            AND s.paper_code = rf.paper_code
        WHERE s.snapshot_date = :snapshot_date
          AND s.last_payment_amount IS NOT NULL
          AND s.last_payment_amount != 0
        GROUP BY s.business_unit, s.paper_code, s.paper_name
        ORDER BY s.business_unit, s.paper_code
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['snapshot_date' => $snapshot_date]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate market rate revenue for each publication
    $publications = [];
    $totals = [
        'total_market_revenue' => 0,
        'total_legacy_revenue' => 0,
        'total_opportunity' => 0
    ];

    foreach ($results as $row) {
        $paperCode = $row['paper_code'];
        $legacySubscribers = (int)$row['legacy_rate_subscribers'];
        $legacyRevenue = (float)$row['legacy_rate_revenue'];

        // Get market rate for this publication
        $marketRate = $marketRates[$paperCode] ?? 14.99; // Default fallback

        // Calculate what they COULD pay at market rate
        $marketRateRevenue = $legacySubscribers * $marketRate;

        // Opportunity = Market potential - Current legacy revenue
        $opportunity = $marketRateRevenue - $legacyRevenue;

        $publications[] = [
            'business_unit' => $row['business_unit'],
            'paper_code' => $paperCode,
            'paper_name' => $row['paper_name'],
            'total_subscribers' => (int)$row['total_subscribers'],
            'legacy_rate_subscribers' => $legacySubscribers,
            'market_rate_revenue' => round($marketRateRevenue, 2),
            'legacy_rate_revenue' => round($legacyRevenue, 2),
            'opportunity' => round($opportunity, 2),
            'avg_legacy_rate' => round((float)$row['avg_legacy_rate'], 2),
            'market_rate' => $marketRate
        ];

        // Accumulate totals
        $totals['total_market_revenue'] += $marketRateRevenue;
        $totals['total_legacy_revenue'] += $legacyRevenue;
        $totals['total_opportunity'] += $opportunity;
    }

    return [
        'by_publication' => $publications,
        'totals' => [
            'total_market_revenue' => round($totals['total_market_revenue'], 2),
            'total_legacy_revenue' => round($totals['total_legacy_revenue'], 2),
            'total_opportunity' => round($totals['total_opportunity'], 2)
        ]
    ];
}

/**
 * Get market rates for each publication
 * Looks up the rate marked as "market" in rate_flags table
 *
 * @param PDO $pdo Database connection
 * @return array Associative array [paper_code => market_rate_amount]
 */
function getMarketRates($pdo) {
    $sql = "
        SELECT paper_code, zone, last_payment_amount
        FROM rate_flags
        WHERE is_market = 1
        ORDER BY paper_code
    ";

    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $marketRates = [];
    foreach ($results as $row) {
        $marketRates[$row['paper_code']] = (float)$row['last_payment_amount'];
    }

    return $marketRates;
}
```

#### 2. Add Publication Detail Endpoint

**New Action Handler:**
```php
// Handle publication_detail action
if (isset($_GET['action']) && $_GET['action'] === 'publication_detail') {
    handlePublicationDetail($pdo);
    exit();
}

/**
 * Get detailed revenue data for a specific publication
 * Includes current state and historical trend (last 12 weeks)
 */
function handlePublicationDetail($pdo) {
    $paperCode = $_GET['paper'] ?? '';

    if (empty($paperCode)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Paper code required']);
        exit();
    }

    // Get current state
    $current = getCurrentPublicationState($pdo, $paperCode);

    // Get historical trend (last 12 weeks)
    $historical = getHistoricalTrend($pdo, $paperCode, 12);

    // Calculate trend direction
    $trend = calculateTrendDirection($historical);

    echo json_encode([
        'success' => true,
        'paper_code' => $paperCode,
        'paper_name' => $current['paper_name'],
        'business_unit' => $current['business_unit'],
        'current' => $current,
        'historical' => $historical,
        'trend_direction' => $trend['direction'], // 'growing' or 'shrinking'
        'trend_percent' => $trend['percent']
    ], JSON_PRETTY_PRINT);
}

/**
 * Get current state for a publication
 */
function getCurrentPublicationState($pdo, $paperCode) {
    // Get latest snapshot date
    $stmt = $pdo->query("SELECT MAX(snapshot_date) as latest_date FROM subscriber_snapshots");
    $latest = $stmt->fetch();
    $snapshotDate = $latest['latest_date'];

    // Get market rate
    $marketRate = getMarketRates($pdo)[$paperCode] ?? 14.99;

    // Query current metrics
    $sql = "
        SELECT
            s.paper_name,
            s.business_unit,
            COUNT(CASE
                WHEN ABS(s.last_payment_amount) < 100
                  AND (rf.is_special IS NULL OR rf.is_special = 0)
                  AND (rf.is_ignored IS NULL OR rf.is_ignored = 0)
                THEN 1
            END) as legacy_subscribers,
            SUM(CASE
                WHEN ABS(s.last_payment_amount) < 100
                  AND (rf.is_special IS NULL OR rf.is_special = 0)
                  AND (rf.is_ignored IS NULL OR rf.is_ignored = 0)
                THEN ABS(s.last_payment_amount)
            END) as legacy_revenue,
            AVG(CASE
                WHEN ABS(s.last_payment_amount) < 100
                  AND (rf.is_special IS NULL OR rf.is_special = 0)
                  AND (rf.is_ignored IS NULL OR rf.is_ignored = 0)
                THEN ABS(s.last_payment_amount)
            END) as avg_legacy_rate
        FROM subscriber_snapshots s
        LEFT JOIN rate_flags rf
            ON s.rate_name = rf.zone
            AND s.paper_code = rf.paper_code
        WHERE s.snapshot_date = :snapshot_date
          AND s.paper_code = :paper_code
        GROUP BY s.paper_name, s.business_unit
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'snapshot_date' => $snapshotDate,
        'paper_code' => $paperCode
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $legacySubscribers = (int)$row['legacy_subscribers'];
    $legacyRevenue = (float)$row['legacy_revenue'];
    $marketRevenue = $legacySubscribers * $marketRate;

    return [
        'paper_name' => $row['paper_name'],
        'business_unit' => $row['business_unit'],
        'legacy_subscribers' => $legacySubscribers,
        'avg_legacy_rate' => round((float)$row['avg_legacy_rate'], 2),
        'market_rate' => $marketRate,
        'legacy_revenue' => round($legacyRevenue, 2),
        'market_revenue' => round($marketRevenue, 2),
        'monthly_opportunity' => round($marketRevenue - $legacyRevenue, 2)
    ];
}

/**
 * Get historical trend data (last N weeks)
 */
function getHistoricalTrend($pdo, $paperCode, $weeks = 12) {
    $marketRate = getMarketRates($pdo)[$paperCode] ?? 14.99;

    $sql = "
        SELECT
            s.snapshot_date,
            COUNT(CASE
                WHEN ABS(s.last_payment_amount) < 100
                  AND (rf.is_special IS NULL OR rf.is_special = 0)
                  AND (rf.is_ignored IS NULL OR rf.is_ignored = 0)
                THEN 1
            END) as legacy_subscribers,
            SUM(CASE
                WHEN ABS(s.last_payment_amount) < 100
                  AND (rf.is_special IS NULL OR rf.is_special = 0)
                  AND (rf.is_ignored IS NULL OR rf.is_ignored = 0)
                THEN ABS(s.last_payment_amount)
            END) as legacy_revenue
        FROM subscriber_snapshots s
        LEFT JOIN rate_flags rf
            ON s.rate_name = rf.zone
            AND s.paper_code = rf.paper_code
        WHERE s.paper_code = :paper_code
          AND s.snapshot_date >= DATE_SUB(CURDATE(), INTERVAL :weeks WEEK)
        GROUP BY s.snapshot_date
        ORDER BY s.snapshot_date ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'paper_code' => $paperCode,
        'weeks' => $weeks
    ]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate market revenue for each week
    $trend = [];
    foreach ($results as $row) {
        $legacySubscribers = (int)$row['legacy_subscribers'];
        $legacyRevenue = (float)$row['legacy_revenue'];
        $marketRevenue = $legacySubscribers * $marketRate;

        $trend[] = [
            'snapshot_date' => $row['snapshot_date'],
            'legacy_revenue' => round($legacyRevenue, 2),
            'market_revenue' => round($marketRevenue, 2),
            'opportunity' => round($marketRevenue - $legacyRevenue, 2)
        ];
    }

    return $trend;
}

/**
 * Calculate trend direction (is opportunity growing or shrinking?)
 */
function calculateTrendDirection($historical) {
    if (count($historical) < 2) {
        return ['direction' => 'unknown', 'percent' => 0];
    }

    $first = $historical[0]['opportunity'];
    $last = $historical[count($historical) - 1]['opportunity'];

    $change = (($last - $first) / $first) * 100;

    return [
        'direction' => $change > 0 ? 'growing' : 'shrinking',
        'percent' => round(abs($change), 1)
    ];
}
```

#### 3. Update Main Response

**Modify existing response to include new data:**
```php
// In main response (existing code)
$response = [
    'success' => true,
    'snapshot_date' => $snapshot_date,
    'generated_at' => date('Y-m-d H:i:s'),
    'expiration_risk' => getExpirationRisk($pdo, $snapshot_date),
    'legacy_rate_analysis' => getLegacyRateAnalysis($pdo, $snapshot_date),
    'revenue_metrics' => getRevenueMetrics($pdo, $snapshot_date),

    // NEW: Per-publication breakdown
    'revenue_opportunity' => getRevenueOpportunityByPublication($pdo, $snapshot_date)
];
```

---

## 📱 Responsive Design

### Mobile Layout (< 768px)

**Table Transforms to Cards:**
```html
<!-- Mobile view: Stack as cards -->
<div class="md:hidden space-y-4">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between mb-3">
            <div class="font-bold text-gray-900">The Journal</div>
            <div class="text-sm text-gray-500">South Carolina</div>
        </div>
        <div class="space-y-2">
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Market Rate MRR:</span>
                <span class="font-semibold text-green-700">$38,120</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Legacy Rate MRR:</span>
                <span class="font-semibold text-amber-700">$15,890</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Opportunity:</span>
                <span class="font-bold text-blue-700">+$22,230</span>
            </div>
        </div>
        <!-- Stacked bar -->
        <div class="mt-3">
            <div class="h-4 bg-gray-100 rounded-full overflow-hidden flex">
                <div class="bg-green-500 h-full" style="width: 70.6%"></div>
                <div class="bg-amber-500 h-full" style="width: 29.4%"></div>
            </div>
        </div>
    </div>
</div>
```

### Tablet Layout (768px - 1023px)

**Abbreviated Table:**
- Hide "Distribution" column
- Show only essential columns

### Desktop Layout (1024px+)

**Full Table:**
- All columns visible
- Horizontal scroll not needed

---

## 🎬 Implementation Plan

### Phase 1: Data Layer (30 minutes)

**Tasks:**
1. ✅ Add `getRevenueOpportunityByPublication()` function to `revenue_intelligence.php`
2. ✅ Add `getMarketRates()` helper function
3. ✅ Add `handlePublicationDetail()` action handler
4. ✅ Add `getCurrentPublicationState()` function
5. ✅ Add `getHistoricalTrend()` function
6. ✅ Add `calculateTrendDirection()` function
7. ✅ Update main API response to include new data structure

**Testing:**
```bash
# Test main endpoint includes new data
curl http://localhost:8081/api/revenue_intelligence.php | jq '.revenue_opportunity'

# Test publication detail endpoint
curl http://localhost:8081/api/revenue_intelligence.php?action=publication_detail&paper=TJ | jq
```

### Phase 2: Main Dashboard Table (45 minutes)

**Tasks:**
1. ✅ Create `web/assets/revenue-opportunity-table.js`
2. ✅ Implement `RevenueOpportunityTable` class
3. ✅ Replace existing "Legacy Rate Opportunity" card in `index.php`
4. ✅ Update `revenue-intelligence.js` to populate new table
5. ✅ Style mobile/tablet responsive views
6. ✅ Test sorting and click handlers

**HTML Change in `index.php`:**
```html
<!-- REPLACE existing Legacy Rate Opportunity card with: -->
<div id="revenueOpportunityTable">
    <!-- Table will be rendered here by JavaScript -->
</div>
```

**JavaScript Change in `revenue-intelligence.js`:**
```javascript
// Replace populateLegacyRateAnalysis() with:
function populateRevenueOpportunityTable(data) {
    const table = new RevenueOpportunityTable('revenueOpportunityTable');
    table.render(data.revenue_opportunity);
}
```

### Phase 3: Detail Slider (60 minutes)

**Tasks:**
1. ✅ Create `web/assets/publication-revenue-detail.js`
2. ✅ Implement `PublicationRevenueDetail` class
3. ✅ Implement donut chart rendering
4. ✅ Implement stacked area chart rendering
5. ✅ Add export functionality
6. ✅ Test chart rendering and animations
7. ✅ Test panel open/close transitions

**Include in `index.php` header:**
```html
<script src="assets/revenue-opportunity-table.js?v=20251212"></script>
<script src="assets/publication-revenue-detail.js?v=20251212"></script>
```

### Phase 4: Testing & Polish (30 minutes)

**Test Scenarios:**
1. ✅ Table sorts correctly by opportunity
2. ✅ Click row opens detail slider
3. ✅ Detail slider shows correct data
4. ✅ Charts render properly
5. ✅ Trend direction indicator shows correct state
6. ✅ Export functionality works
7. ✅ Mobile responsive view works
8. ✅ Close panel on Escape key
9. ✅ Close panel on backdrop click

**Performance:**
1. ✅ Cache API responses (already implemented)
2. ✅ Chart rendering is smooth (<100ms)
3. ✅ Panel slide animation is smooth

---

## 🎯 Success Criteria

### Main Dashboard Table
- ✅ Shows all publications with revenue breakdown
- ✅ Sorted by largest opportunity first
- ✅ Total row displays aggregate metrics
- ✅ Visual bar chart shows market/legacy split
- ✅ Click row opens detail view
- ✅ Responsive on all screen sizes

### Publication Detail Slider
- ✅ Donut chart shows current market vs legacy split
- ✅ Key metrics displayed in cards
- ✅ Stacked area chart shows 12-week trend
- ✅ Trend indicator shows if opportunity is growing/shrinking
- ✅ Export buttons work correctly
- ✅ Smooth animations and transitions

### Data Accuracy
- ✅ Revenue calculations use correct market rates per publication
- ✅ SPECIAL/IGNORED rates are excluded from calculations
- ✅ Publication isolation is maintained (no cross-bleeding)
- ✅ Historical data shows accurate week-over-week trends

---

## 🔍 Visual Mockups

### Main Dashboard Table

```
┌────────────────────────────────────────────────────────────────────────────┐
│ 💎 Revenue Opportunity by Publication                                      │
├────────────────┬──────────────┬──────────────┬──────────────┬─────────────┤
│ Publication    │ Market MRR   │ Legacy MRR   │ Opportunity  │ Distribution│
├────────────────┼──────────────┼──────────────┼──────────────┼─────────────┤
│ TR (Wyoming)   │ $45,234      │ $12,678      │ +$32,556     │ [███|█]     │
│ Wyoming        │              │              │              │ 72.0%       │
├────────────────┼──────────────┼──────────────┼──────────────┼─────────────┤
│ TJ (SC)        │ $38,120      │ $15,890      │ +$22,230     │ [██|██]     │
│ South Carolina │              │              │              │ 58.3%       │
├────────────────┼──────────────┼──────────────┼──────────────┼─────────────┤
│ TA (Michigan)  │ $28,450      │ $18,230      │ +$10,220     │ [█|██]      │
│ Michigan       │              │              │              │ 35.9%       │
├────────────────┴──────────────┴──────────────┴──────────────┴─────────────┤
│ TOTAL          │ $111,804     │ $46,798      │ +$65,006     │ [███|███]   │
│                │              │              │              │ 58.1%       │
└────────────────────────────────────────────────────────────────────────────┘
```

### Detail Slider Layout

```
┌─────────────────────────────────────────────────────────────┐
│ The Journal Revenue Opportunity                 [X]         │
│ South Carolina                                              │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ Current State                                               │
│ ┌──────────────────┐  ┌──────────────────────────────────┐ │
│ │                  │  │ Legacy Rate Subscribers          │ │
│ │   Donut Chart    │  │ 524                              │ │
│ │                  │  ├──────────────────────────────────┤ │
│ │  70% Market      │  │ Average Legacy Rate              │ │
│ │  30% Legacy      │  │ $8.42/mo                         │ │
│ │                  │  ├──────────────────────────────────┤ │
│ └──────────────────┘  │ Monthly Opportunity              │ │
│                       │ +$22,230                         │ │
│                       └──────────────────────────────────┘ │
│                                                             │
│ Trend Over Time (Last 12 Weeks)                            │
│ ┌───────────────────────────────────────────────────────┐  │
│ │                                                       │  │
│ │    Stacked Area Chart                                │  │
│ │    ┌─────────────────────────────────────────────┐   │  │
│ │ $  │                                             │   │  │
│ │ 40k│         Market Rate Revenue ████████████    │   │  │
│ │ 30k│         ████████████████████████████████    │   │  │
│ │ 20k│         ░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░    │   │  │
│ │ 10k│         Legacy Rate Revenue ░░░░░░░░░░░░    │   │  │
│ │    └─────────────────────────────────────────────┘   │  │
│ │     Week 1 → Week 12                                 │  │
│ └───────────────────────────────────────────────────────┘  │
│                                                             │
│ ⚠️ Opportunity Growing ↑ 8.5%                              │
│                                                             │
│ Actions                                                     │
│ [📊 Export Legacy Rate Subscribers] [⚙️ Manage Rates]      │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 📝 Testing Checklist

### Functionality
- [ ] Table renders with correct data
- [ ] Sorting works (largest opportunity first)
- [ ] Total row calculates correctly
- [ ] Stacked bars display correct percentages
- [ ] Click row opens detail slider
- [ ] Detail slider shows correct publication data
- [ ] Donut chart renders correctly
- [ ] Stacked area chart renders correctly
- [ ] Trend indicator shows correct direction
- [ ] Export buttons work
- [ ] Close panel with X button
- [ ] Close panel with Escape key
- [ ] Close panel by clicking backdrop

### Data Accuracy
- [ ] Revenue calculations match expected values
- [ ] SPECIAL rates excluded from calculations
- [ ] IGNORED rates excluded from calculations
- [ ] Publication isolation maintained (no cross-bleeding)
- [ ] Market rates pulled correctly per publication
- [ ] Historical data shows accurate trends

### Responsive Design
- [ ] Desktop view (1024px+): Full table
- [ ] Tablet view (768-1023px): Abbreviated table
- [ ] Mobile view (<768px): Card layout
- [ ] Detail slider responsive on all screen sizes
- [ ] Charts responsive and readable

### Performance
- [ ] Table renders in <100ms
- [ ] Detail slider opens smoothly
- [ ] Charts render without lag
- [ ] No console errors
- [ ] Cache working correctly (90% faster on cache hit)

### Accessibility
- [ ] Table has proper ARIA labels
- [ ] Keyboard navigation works
- [ ] Screen reader friendly
- [ ] Color contrast meets WCAG standards

---

## 🎉 Expected Outcome

**Main Dashboard:**
- Clear per-publication breakdown showing exactly where the largest revenue opportunities exist
- Sortable table prioritizing highest-value opportunities
- Visual representation (stacked bars) for quick comprehension
- Click-to-detail for deep dives

**Detail Views:**
- Current state snapshot with donut chart and key metrics
- Historical trend showing if opportunity is improving or worsening
- Actionable insights with export and management links
- Professional presentation suitable for executive reporting

**Business Value:**
- **Identify** which publications have the most revenue potential
- **Prioritize** sales efforts on highest-opportunity publications
- **Track** progress over time as legacy rates are converted
- **Measure** success with clear before/after metrics
- **Export** subscriber lists for targeted outreach campaigns

---

## 📌 Next Steps

**After approval of this design:**

1. **Phase 1**: Implement API changes (30 min)
2. **Phase 2**: Build main dashboard table (45 min)
3. **Phase 3**: Build detail slider with charts (60 min)
4. **Phase 4**: Test and polish (30 min)

**Total estimated time:** ~2.5 hours

**Ready to proceed?** Review this design and provide feedback/approval to begin implementation.
