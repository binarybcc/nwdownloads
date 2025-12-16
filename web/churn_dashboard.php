<?php

/**
 * Churn Tracking Dashboard
 * Displays renewal/expiration metrics with drill-down capabilities
 */

require_once 'auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Churn Tracking Dashboard</title>

    <!-- Tailwind CSS - Optimized Production Build -->
    <link rel="stylesheet" href="assets/output.css?v=20251206">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <!-- Export Libraries -->
    <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>

    <!-- Custom Styles -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        :root {
            /* Professional Color System */
            --color-primary: #0F172A;
            --color-secondary: #334155;
            --color-cta: #0369A1;
            --color-background: #F8FAFC;
            --color-text: #020617;
            --color-border: #E2E8F0;

            /* Status Colors */
            --color-success: #10B981;
            --color-warning: #F59E0B;
            --color-danger: #EF4444;
            --color-info: #3B82F6;
        }

        * {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .metric-card {
            transition: transform 0.2s, box-shadow 0.2s, background-color 0.2s, border 0.2s;
        }

        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        /* Heat map colored card variants */
        .metric-card.card-red:hover {
            background-color: rgba(239, 68, 68, 0.1);
            border: 2px solid rgba(239, 68, 68, 0.5);
            box-shadow: 0 10px 25px rgba(239, 68, 68, 0.2);
        }

        .metric-card.card-orange:hover {
            background-color: rgba(249, 115, 22, 0.1);
            border: 2px solid rgba(249, 115, 22, 0.5);
            box-shadow: 0 10px 25px rgba(249, 115, 22, 0.2);
        }

        .metric-card.card-yellow:hover {
            background-color: rgba(234, 179, 8, 0.1);
            border: 2px solid rgba(234, 179, 8, 0.5);
            box-shadow: 0 10px 25px rgba(234, 179, 8, 0.2);
        }

        .metric-card.card-green:hover {
            background-color: rgba(34, 197, 94, 0.1);
            border: 2px solid rgba(34, 197, 94, 0.5);
            box-shadow: 0 10px 25px rgba(34, 197, 94, 0.2);
        }

        .comparison-badge {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .comparison-badge.positive {
            color: #10B981;
        }

        .comparison-badge.negative {
            color: #EF4444;
        }

        /* Week Navigation Controls */
        .date-navigation {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .date-navigation button {
            padding: 0.375rem 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .date-navigation button:hover:not(:disabled) {
            background: #f3f4f6;
            border-color: #3b82f6;
        }

        .date-navigation button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .date-navigation button.current-week {
            background: #eff6ff;
            color: #1e40af;
            border-color: #93c5fd;
        }
    </style>
</head>
<body class="bg-gray-50">

    <!-- HEADER -->
    <header class="bg-white border-b border-gray-200" role="banner">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2">
            <div class="flex justify-between items-center">
                <!-- Left: Title + Status -->
                <div class="flex items-center gap-4">
                    <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                        <img src="assets/egh-logo-gold.webp" alt="Edwards Group Holdings" class="w-6 h-6 object-contain">
                        Churn Tracking
                    </h1>
                    <div class="text-xs text-gray-500 flex items-center gap-2">
                        <span id="currentDateTime">Loading...</span>
                        <span>•</span>
                        <span>Logged in as <strong><?php echo htmlspecialchars($_SESSION['user']); ?></strong></span>
                    </div>
                </div>

                <!-- Right: Actions -->
                <div class="flex items-center gap-2">
                    <a href="index.php"
                       class="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"
                       title="Back to Main Dashboard"
                       aria-label="Main Dashboard">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                    </a>

                    <a href="upload_renewals.html"
                       class="p-2 text-gray-600 hover:text-purple-600 hover:bg-purple-50 rounded-lg transition"
                       title="Upload Renewal Churn Data"
                       aria-label="Upload churn data">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </a>

                    <button onclick="refreshData()"
                            class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition"
                            aria-label="Refresh dashboard data"
                            title="Refresh Dashboard Data">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </button>

                    <a href="logout.php"
                       class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition ml-2"
                       title="Sign Out of Dashboard"
                       aria-label="Logout">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Time Range Selector -->
        <div class="flex items-center justify-between gap-4 mb-4">
            <div class="flex items-center gap-4">
                <label for="churnTimeRange" class="text-sm font-medium text-gray-700">Time Range:</label>
                <select id="churnTimeRange" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="4weeks">Last 4 Weeks</option>
                    <option value="12weeks">Last 12 Weeks</option>
                </select>
                <span class="text-sm text-gray-600" id="churnDateRange">Loading...</span>
            </div>

            <div class="text-sm text-gray-500">
                <span class="font-medium">Renewal Thresholds:</span>
                <span class="inline-block ml-2 px-2 py-0.5 bg-green-100 text-green-800 rounded text-xs">≥85% Excellent</span>
                <span class="inline-block ml-1 px-2 py-0.5 bg-yellow-100 text-yellow-800 rounded text-xs">70-84% Good</span>
                <span class="inline-block ml-1 px-2 py-0.5 bg-orange-100 text-orange-800 rounded text-xs">60-69% Warning</span>
                <span class="inline-block ml-1 px-2 py-0.5 bg-red-100 text-red-800 rounded text-xs">&lt;60% Critical</span>
            </div>
        </div>

        <!-- Week Navigation Controls -->
        <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-200">
            <div class="date-navigation">
                <button id="churnPrevWeek"
                        class="flex items-center space-x-1"
                        aria-label="Go to previous week">
                    <span aria-hidden="true">‹</span>
                    <span>Previous Week</span>
                </button>

                <button id="churnThisWeek"
                        class="current-week"
                        aria-label="Go to current week">
                    This Week
                </button>

                <button id="churnNextWeek"
                        class="flex items-center space-x-1"
                        aria-label="Go to next week">
                    <span>Next Week</span>
                    <span aria-hidden="true">›</span>
                </button>
            </div>

            <div class="text-sm text-gray-600">
                <span class="font-medium">Viewing Period:</span>
                <span class="ml-2" id="churnPeriodDisplay">Loading...</span>
            </div>
        </div>

        <!-- Section 1: Overview Metrics (4-column grid) -->
        <section class="mb-8" aria-labelledby="overview-heading">
            <h2 id="overview-heading" class="text-lg font-semibold text-gray-900 mb-4">📊 Overview Metrics</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

                <!-- Card 1: Overall Renewal Rate -->
                <div class="metric-card bg-white rounded-xl shadow p-6 cursor-pointer context-menu-section"
                     data-metric-type="overall"
                     onclick="showChurnDetails('overall')">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-sm font-medium text-gray-600">Overall Renewal Rate</div>
                        <div class="text-2xl">📊</div>
                    </div>
                    <div class="text-3xl font-bold text-gray-900" id="overallRenewalRate">--</div>
                    <div class="text-sm text-gray-500 mt-2" id="overallRenewalComparison">
                        <span>Loading...</span>
                    </div>
                </div>

                <!-- Card 2: Total Renewals -->
                <div class="metric-card bg-white rounded-xl shadow p-6 cursor-pointer context-menu-section"
                     data-metric-type="renewals"
                     onclick="showRenewalEvents('RENEW')">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-sm font-medium text-gray-600">Total Renewals</div>
                        <div class="text-2xl">✅</div>
                    </div>
                    <div class="text-3xl font-bold text-green-600" id="totalRenewals">--</div>
                    <div class="text-sm text-gray-500 mt-2">
                        <span id="renewalPercent">--</span> of expiring subscriptions
                    </div>
                </div>

                <!-- Card 3: Total Expirations -->
                <div class="metric-card bg-white rounded-xl shadow p-6 cursor-pointer context-menu-section"
                     data-metric-type="expirations"
                     onclick="showRenewalEvents('EXPIRE')">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-sm font-medium text-gray-600">Total Expirations</div>
                        <div class="text-2xl">❌</div>
                    </div>
                    <div class="text-3xl font-bold text-red-600" id="totalExpirations">--</div>
                    <div class="text-sm text-gray-500 mt-2">
                        <span id="expirationPercent">--</span> of expiring subscriptions
                    </div>
                </div>

                <!-- Card 4: Net Change -->
                <div class="metric-card bg-white rounded-xl shadow p-6">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-sm font-medium text-gray-600">Net Subscriber Change</div>
                        <div class="text-2xl">📈</div>
                    </div>
                    <div class="text-3xl font-bold text-gray-900" id="netChange">--</div>
                    <div class="text-sm text-gray-500 mt-2">
                        Renewals minus expirations
                    </div>
                </div>

            </div>
        </section>

        <!-- Section 2: By Subscription Type (3-column grid) -->
        <section class="mb-8" aria-labelledby="by-type-heading">
            <h2 id="by-type-heading" class="text-lg font-semibold text-gray-900 mb-4">📋 By Subscription Type</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                <!-- Card: Regular Subscriptions -->
                <div class="metric-card bg-white rounded-xl shadow p-6 cursor-pointer context-menu-section"
                     id="typeCard-REGULAR"
                     data-metric-type="subscription_type"
                     data-metric-value="REGULAR">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-sm font-medium text-gray-600">Regular Subscriptions</div>
                        <div class="text-2xl">📰</div>
                    </div>
                    <div class="text-3xl font-bold text-gray-900" id="renewalRate-REGULAR">--</div>
                    <div class="text-sm mt-2">
                        <span class="text-green-600 font-semibold" id="renewed-REGULAR">--</span> renewed •
                        <span class="text-red-600 font-semibold" id="stopped-REGULAR">--</span> stopped
                    </div>
                    <div class="text-sm text-gray-500 mt-1">
                        of <span id="expiring-REGULAR">--</span> expiring
                    </div>
                </div>

                <!-- Card: Monthly Subscriptions -->
                <div class="metric-card bg-white rounded-xl shadow p-6 cursor-pointer context-menu-section"
                     id="typeCard-MONTHLY"
                     data-metric-type="subscription_type"
                     data-metric-value="MONTHLY">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-sm font-medium text-gray-600">Monthly Subscriptions</div>
                        <div class="text-2xl">📅</div>
                    </div>
                    <div class="text-3xl font-bold text-gray-900" id="renewalRate-MONTHLY">--</div>
                    <div class="text-sm mt-2">
                        <span class="text-green-600 font-semibold" id="renewed-MONTHLY">--</span> renewed •
                        <span class="text-red-600 font-semibold" id="stopped-MONTHLY">--</span> stopped
                    </div>
                    <div class="text-sm text-gray-500 mt-1">
                        of <span id="expiring-MONTHLY">--</span> expiring
                    </div>
                </div>

                <!-- Card: Complimentary Subscriptions -->
                <div class="metric-card bg-white rounded-xl shadow p-6 cursor-pointer context-menu-section"
                     id="typeCard-COMPLIMENTARY"
                     data-metric-type="subscription_type"
                     data-metric-value="COMPLIMENTARY">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-sm font-medium text-gray-600">Complimentary Subscriptions</div>
                        <div class="text-2xl">🎁</div>
                    </div>
                    <div class="text-3xl font-bold text-gray-900" id="renewalRate-COMPLIMENTARY">--</div>
                    <div class="text-sm mt-2">
                        <span class="text-green-600 font-semibold" id="renewed-COMPLIMENTARY">--</span> renewed •
                        <span class="text-red-600 font-semibold" id="stopped-COMPLIMENTARY">--</span> stopped
                    </div>
                    <div class="text-sm text-gray-500 mt-1">
                        of <span id="expiring-COMPLIMENTARY">--</span> expiring
                    </div>
                </div>

            </div>
        </section>

        <!-- Section 3: By Publication (5-column grid) -->
        <section class="mb-8" aria-labelledby="by-publication-heading">
            <h2 id="by-publication-heading" class="text-lg font-semibold text-gray-900 mb-4">📰 By Publication</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">

                <!-- Card: The Journal (TJ) -->
                <div class="metric-card bg-white rounded-xl shadow p-6 cursor-pointer context-menu-section"
                     id="pubCard-TJ"
                     data-metric-type="publication"
                     data-metric-value="TJ">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-xs font-medium text-gray-600">The Journal (TJ)</div>
                        <div class="text-xl">📰</div>
                    </div>
                    <div class="text-2xl font-bold text-gray-900" id="renewalRate-TJ">--</div>
                    <div class="text-xs mt-2">
                        <span class="text-green-600 font-semibold" id="renewed-TJ">--</span> renewed
                    </div>
                </div>

                <!-- Card: The Advertiser (TA) -->
                <div class="metric-card bg-white rounded-xl shadow p-6 cursor-pointer context-menu-section"
                     id="pubCard-TA"
                     data-metric-type="publication"
                     data-metric-value="TA">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-xs font-medium text-gray-600">The Advertiser (TA)</div>
                        <div class="text-xl">📰</div>
                    </div>
                    <div class="text-2xl font-bold text-gray-900" id="renewalRate-TA">--</div>
                    <div class="text-xs mt-2">
                        <span class="text-green-600 font-semibold" id="renewed-TA">--</span> renewed
                    </div>
                </div>

                <!-- Card: The Ranger (TR) -->
                <div class="metric-card bg-white rounded-xl shadow p-6 cursor-pointer context-menu-section"
                     id="pubCard-TR"
                     data-metric-type="publication"
                     data-metric-value="TR">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-xs font-medium text-gray-600">The Ranger (TR)</div>
                        <div class="text-xl">📰</div>
                    </div>
                    <div class="text-2xl font-bold text-gray-900" id="renewalRate-TR">--</div>
                    <div class="text-xs mt-2">
                        <span class="text-green-600 font-semibold" id="renewed-TR">--</span> renewed
                    </div>
                </div>

                <!-- Card: Lander Journal (LJ) -->
                <div class="metric-card bg-white rounded-xl shadow p-6 cursor-pointer context-menu-section"
                     id="pubCard-LJ"
                     data-metric-type="publication"
                     data-metric-value="LJ">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-xs font-medium text-gray-600">Lander Journal (LJ)</div>
                        <div class="text-xl">📰</div>
                    </div>
                    <div class="text-2xl font-bold text-gray-900" id="renewalRate-LJ">--</div>
                    <div class="text-xs mt-2">
                        <span class="text-green-600 font-semibold" id="renewed-LJ">--</span> renewed
                    </div>
                </div>

                <!-- Card: Wind River News (WRN) -->
                <div class="metric-card bg-white rounded-xl shadow p-6 cursor-pointer context-menu-section"
                     id="pubCard-WRN"
                     data-metric-type="publication"
                     data-metric-value="WRN">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-xs font-medium text-gray-600">Wind River News (WRN)</div>
                        <div class="text-xl">📰</div>
                    </div>
                    <div class="text-2xl font-bold text-gray-900" id="renewalRate-WRN">--</div>
                    <div class="text-xs mt-2">
                        <span class="text-green-600 font-semibold" id="renewed-WRN">--</span> renewed
                    </div>
                </div>

            </div>
        </section>

        <!-- Section 4: Trend Charts (2-column grid) -->
        <section class="mb-8" aria-labelledby="trends-heading">
            <h2 id="trends-heading" class="text-lg font-semibold text-gray-900 mb-4">📈 Trends Over Time</h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- Chart 1: Renewal Rate Trend -->
                <div class="bg-white rounded-xl shadow p-6">
                    <h3 class="text-md font-semibold text-gray-900 mb-4">Renewal Rate Trend</h3>
                    <div style="position: relative; height: 300px;">
                        <canvas id="renewalRateTrendChart"></canvas>
                    </div>
                </div>

                <!-- Chart 2: Renewals vs Expirations -->
                <div class="bg-white rounded-xl shadow p-6">
                    <h3 class="text-md font-semibold text-gray-900 mb-4">Renewals vs Expirations</h3>
                    <div style="position: relative; height: 300px;">
                        <canvas id="renewalsVsExpirationsChart"></canvas>
                    </div>
                </div>

            </div>
        </section>

    </main>

    <!-- Component Scripts (Reused from main dashboard) -->
    <script src="assets/js/components/context-menu.js"></script>
    <script src="assets/js/components/subscriber-table-panel.js"></script>
    <script src="assets/js/components/trend-slider.js"></script>

    <!-- Churn Dashboard Logic -->
    <script src="assets/js/core/churn_dashboard.js"></script>

    <script>
        // Update date/time display
        function updateDateTime() {
            const now = new Date();
            const formatted = now.toLocaleString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
            document.getElementById('currentDateTime').textContent = formatted;
        }

        updateDateTime();
        setInterval(updateDateTime, 60000); // Update every minute

        // Refresh data
        function refreshData() {
            location.reload();
        }
    </script>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 shadow-xl flex flex-col items-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div>
            <p class="text-gray-700 font-medium">Loading churn data...</p>
        </div>
    </div>

</body>
</html>
