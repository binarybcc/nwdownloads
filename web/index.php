<?php

/**
 * Circulation Dashboard - Main Interface (TEST VERSION - HEADER REDESIGN)
 * Requires Newzware authentication to access
 */

require_once 'auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Circulation Dashboard v2</title>

    <!-- Tailwind CSS - Optimized Production Build -->
    <link rel="stylesheet" href="assets/output.css?v=20251206">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <!-- Flatpickr Date Picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <!-- PHASE 2: Export Libraries -->
    <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <!-- Backfill Indicator Module -->
    <script src="assets/backfill-indicator.js?v=20251208"></script>

    <!-- Custom Styles -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        :root {
            /* Professional Color System (UI/UX Pro Max - B2B Dashboard) */
            --color-primary: #0F172A;      /* Navy - Headers, important text */
            --color-secondary: #334155;    /* Slate - Secondary text */
            --color-cta: #0369A1;          /* Professional Blue - CTAs, links */
            --color-background: #F8FAFC;   /* Off-white - Page background */
            --color-text: #020617;         /* Near-black - Body text */
            --color-border: #E2E8F0;       /* Light grey - Borders */

            /* Status Colors */
            --color-success: #10B981;      /* Green - Growth, positive */
            --color-warning: #F59E0B;      /* Amber - Caution */
            --color-danger: #EF4444;       /* Red - Decline */
            --color-info: #3B82F6;         /* Blue - Information */

            /* Shades for variations */
            --color-slate-50: #F8FAFC;
            --color-slate-100: #F1F5F9;
            --color-slate-200: #E2E8F0;
            --color-slate-600: #475569;
            --color-slate-700: #334155;
            --color-slate-900: #0F172A;
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

        /* Colored card variants with heat map hover effects - full border glow */
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

        .paper-card {
            cursor: pointer;
            transition: all 0.3s;
        }

        .paper-card:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .trend-up {
            color: #10b981;
        }

        .trend-down {
            color: #ef4444;
        }

        .progress-bar {
            transition: width 1s ease-out;
        }

        /* Date Navigation Styles */
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

        /* Context Menu Hover Effects */
        .context-menu-section {
            position: relative;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .context-menu-section:hover {
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        /* Key Metrics vacation card - blue theme */
        .context-menu-section.vacation-overall:hover {
            background-color: rgba(59, 130, 246, 0.1);
            border: 2px solid rgba(59, 130, 246, 0.5);
        }

        /* Show action buttons on hover */
        .context-menu-section:hover .vacation-actions {
            opacity: 1 !important;
        }

        .date-navigation button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .date-navigation input[type="text"] {
            padding: 0.375rem 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            width: 120px;
        }

        .comparison-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .comparison-badge.positive {
            background: #D1FAE5;
            color: #065F46;  /* WCAG AAA compliant: 9.2:1 ratio */
        }

        .comparison-badge.negative {
            background: #FEE2E2;
            color: #991B1B;  /* WCAG AAA compliant: 8.1:1 ratio */
        }

        .comparison-badge.neutral {
            background: #F3F4F6;
            color: #374151;  /* WCAG AAA compliant: 9.4:1 ratio */
        }

        /* Badge icons */
        .comparison-badge svg {
            width: 0.875rem;
            height: 0.875rem;
        }

        /* Screen reader only */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border-width: 0;
        }

        select {
            padding: 0.375rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            background: white;
            cursor: pointer;
        }

        /* Smooth scroll for section navigation */
        html {
            scroll-behavior: smooth;
        }

        /* Section navigation active state */
        .section-nav-link {
            padding-bottom: 0.25rem;
            transition: all 0.2s;
            border-bottom: 2px solid transparent;
        }

        .section-nav-link:hover {
            color: #3b82f6;
        }

        .section-nav-link.active {
            color: #1d4ed8;
            font-weight: 600;
            border-bottom-color: #1d4ed8;
        }

        /* Detail Panel Styles - Enhanced UX with State Navigation */
        #detailPanel {
            position: fixed;
            top: 0;
            right: -82%;
            width: 82%;
            height: 100vh;
            background: white;
            box-shadow: -8px 0 32px rgba(0,0,0,0.12);
            transition: right 400ms cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 60;
            overflow: hidden;
            will-change: right;
            display: flex;
        }

        #detailPanel.open {
            right: 0;
        }

        /* State Navigation Sidebar */
        #stateNavSidebar {
            width: 8%;
            min-width: 80px;
            background: linear-gradient(180deg, #F8FAFC 0%, #F1F5F9 100%);
            border-right: 1px solid var(--color-border);
            display: flex;
            flex-direction: column;
            padding: 1.5rem 0.5rem;
            gap: 1rem;
            overflow-y: auto;
        }

        .state-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 0.5rem;
            border-radius: 12px;
            cursor: pointer;
            transition: all 200ms cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .state-nav-item:hover {
            background: rgba(3, 105, 161, 0.1);
            transform: scale(1.05);
        }

        .state-nav-item.active {
            background: var(--color-cta);
            color: white;
            box-shadow: 0 4px 12px rgba(3, 105, 161, 0.3);
        }

        .state-nav-item.active .state-abbr {
            color: white;
        }

        .state-icon-wrapper {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .state-icon {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
            transition: filter 200ms;
        }

        .state-nav-item.active .state-icon {
            filter: brightness(0) invert(1) drop-shadow(0 4px 8px rgba(255,255,255,0.3));
        }

        .state-abbr {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--color-secondary);
            text-align: center;
        }

        .state-count {
            font-size: 0.625rem;
            color: var(--color-slate-600);
            font-weight: 500;
        }

        .state-nav-item.active .state-count {
            color: rgba(255,255,255,0.9);
        }

        /* Detail Content Area */
        #detailContent {
            flex: 1;
            overflow-y: auto;
            padding: 2rem;
        }

        /* Backdrop overlay for focus */
        #detailPanelBackdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            opacity: 0;
            visibility: hidden;
            transition: opacity 250ms cubic-bezier(0.4, 0, 0.2, 1),
                        visibility 250ms cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 59;
            backdrop-filter: blur(2px);
        }

        #detailPanelBackdrop.visible {
            opacity: 1;
            visibility: visible;
        }

        #mainContent {
            transition: all 400ms cubic-bezier(0.4, 0, 0.2, 1);
        }

        #mainContent.docked {
            width: 18%;
            overflow-y: auto;
            height: calc(100vh - 64px);
        }

        /* Drag and Drop Chart Styles */
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .chart-grid.single-column {
            grid-template-columns: 1fr;
        }

        .chart-draggable {
            cursor: move;
            transition: all 200ms;
            border: 2px dashed transparent;
            border-radius: 12px;
            padding: 1rem;
            background: white;
        }

        .chart-draggable:hover {
            border-color: var(--color-cta);
            background: var(--color-slate-50);
        }

        .chart-draggable.dragging {
            opacity: 0.5;
            transform: scale(0.95);
        }

        .chart-draggable.drag-over {
            border-color: var(--color-success);
            background: rgba(16, 185, 129, 0.05);
        }

        .drag-handle {
            cursor: move;
            color: var(--color-slate-600);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            transition: all 200ms;
        }

        .drag-handle:hover {
            background: var(--color-slate-100);
            color: var(--color-primary);
        }

        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            #detailPanel,
            #detailPanelBackdrop,
            #mainContent,
            .chart-draggable,
            .state-nav-item {
                transition: none;
            }
        }

        /* Mobile optimization */
        @media (max-width: 768px) {
            #detailPanel {
                width: 100%;
                right: -100%;
                flex-direction: column;
            }

            #stateNavSidebar {
                width: 100%;
                flex-direction: row;
                padding: 1rem;
                border-right: none;
                border-bottom: 1px solid var(--color-border);
                min-width: unset;
            }

            .state-nav-item {
                flex: 1;
                padding: 0.75rem 0.5rem;
            }

            .state-icon-wrapper {
                width: 40px;
                height: 40px;
            }

            #detailContent {
                padding: 1rem;
            }

            .chart-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            #mainContent.docked {
                display: none;
            }
        }

        .detail-chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 1rem;
        }

        .chart-clickable {
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .chart-clickable:hover {
            opacity: 0.8;
        }
    </style>
</head>
<body class="bg-gray-50">

    <!-- TIER 1: INFO BAR (Scrollable) -->
    <header class="bg-white border-b border-gray-200" role="banner">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2">
            <div class="flex justify-between items-center">
                <!-- Left: Title + Status -->
                <div class="flex items-center gap-4">
                    <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                        <img src="assets/egh-logo-gold.webp" alt="Edwards Group Holdings" class="w-6 h-6 object-contain">
                        Circulation
                    </h1>
                    <div class="text-xs text-gray-500 flex items-center gap-2">
                        <span id="currentDateTime">Loading...</span>
                        <span>•</span>
                        <span>Logged in as <strong><?php echo htmlspecialchars($_SESSION['user']); ?></strong></span>
                        <span>•</span>
                        <span>Data: <span id="dataRangeDisplay">--</span></span>
                    </div>
                </div>

                <!-- Right: Actions (Icon-only buttons) -->
                <div class="flex items-center gap-2">
                    <a href="upload_unified.php"
                       class="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"
                       title="Upload Weekly Circulation Data"
                       aria-label="Upload data">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                    </a>

                    <div class="relative">
                        <button id="exportBtn" onclick="toggleExportMenu()"
                                class="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"
                                aria-label="Export data"
                                aria-expanded="false"
                                aria-haspopup="true"
                                title="Export Dashboard to CSV, Excel, or PDF">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </button>

                        <div id="exportMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                            <button onclick="exportToCSV()" class="w-full px-4 py-2 text-left hover:bg-gray-50 flex items-center space-x-2 rounded-t-lg text-sm">
                                <span>📊</span><span>Export to CSV</span>
                            </button>
                            <button onclick="exportToPDF()" class="w-full px-4 py-2 text-left hover:bg-gray-50 flex items-center space-x-2 text-sm">
                                <span>📄</span><span>Export to PDF</span>
                            </button>
                            <button onclick="exportToExcel()" class="w-full px-4 py-2 text-left hover:bg-gray-50 flex items-center space-x-2 rounded-b-lg text-sm">
                                <span>📗</span><span>Export to Excel</span>
                            </button>
                        </div>
                    </div>

                    <button onclick="refreshData()"
                            class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition"
                            aria-label="Refresh dashboard data"
                            title="Refresh Dashboard Data">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </button>

                    <a href="rates.php"
                       class="p-2 text-gray-600 hover:text-orange-600 hover:bg-orange-50 rounded-lg transition"
                       title="Manage Rate Classifications"
                       aria-label="Rate Management">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </a>

                    <a href="settings.php"
                       class="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"
                       title="Dashboard Settings"
                       aria-label="Settings">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </a>

                    <a href="logout.php"
                       class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition ml-2"
                       title="Sign Out of Dashboard"
                       aria-label="Logout">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- TIER 2: NAVIGATION BAR (Sticky) -->
    <nav class="sticky top-0 z-50 bg-white border-b border-gray-200 shadow-sm" role="navigation" aria-label="Dashboard navigation">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <!-- Row 1: Week Controls + Comparison -->
            <div class="flex justify-between items-center mb-3">
                <div class="date-navigation">
                    <button id="prevWeek"
                            class="flex items-center space-x-1"
                            aria-label="Go to previous week">
                        <span aria-hidden="true">‹</span>
                        <span>Previous</span>
                    </button>

                    <input type="text"
                           id="datePicker"
                           placeholder="Select date..."
                           readonly
                           aria-label="Select date"
                           role="combobox"
                           aria-expanded="false">

                    <button id="nextWeek"
                            class="flex items-center space-x-1"
                            aria-label="Go to next week">
                        <span>Next</span>
                        <span aria-hidden="true">›</span>
                    </button>

                    <button id="thisWeek"
                            class="bg-blue-50 text-blue-700 border-blue-300"
                            aria-label="Go to current week">
                        This Week
                    </button>
                </div>

                <div class="flex items-center gap-2 text-sm">
                    <label for="compareMode" class="text-gray-600 font-medium">Compare to:</label>
                    <select id="compareMode"
                            class="text-sm"
                            aria-label="Select comparison mode">
                        <option value="previous">Previous Week</option>
                        <option value="yoy">Same Week Last Year</option>
                        <option value="none">No Comparison</option>
                    </select>
                </div>
            </div>

            <!-- Row 2: Section Navigation Links -->
            <div class="flex items-center gap-6 text-sm border-t border-gray-100 pt-3 pb-2">
                <a href="#overview" class="section-nav-link active">Overview</a>
                <a href="#metrics" class="section-nav-link">Metrics</a>
                <a href="#analytics" class="section-nav-link">Analytics</a>
                <a href="#reports" class="section-nav-link">Reports</a>
            </div>

            <!-- Row 3: Week Info + Comparison -->
            <div class="flex justify-between items-center text-sm pt-2 border-t border-gray-100">
                <div>
                    <span class="text-gray-600">Viewing:</span>
                    <span class="font-semibold text-gray-900 ml-1" id="periodLabel">Loading...</span>
                    <span class="text-gray-500 ml-2" id="dateRange">--</span>
                </div>
                <div id="comparisonDisplay" class="text-gray-600">
                    <!-- Comparison info will be inserted here -->
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main id="mainContent" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" role="main" aria-label="Dashboard content">

        <!-- Business Units -->
        <section class="mb-8" aria-labelledby="business-units-heading">
            <h2 id="business-units-heading" class="text-lg font-semibold text-gray-900 mb-4">
                <span aria-hidden="true">📍</span> By Business Unit
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="businessUnits" role="status" aria-live="polite">
                <div class="text-center py-12">
                    <div class="loading mx-auto mb-4"></div>
                    <div class="text-gray-500">Loading business units...</div>
                </div>
            </div>
        </section>

        <!-- Key Metrics -->
        <section id="overview" class="mb-8" aria-labelledby="key-metrics-heading">
            <h2 id="key-metrics-heading" class="text-lg font-semibold text-gray-900 mb-4">
                <span aria-hidden="true">📈</span> Key Metrics
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

                <!-- Total Active -->
                <div class="metric-card bg-white rounded-xl shadow p-6"
                     role="region"
                     aria-labelledby="metric-total-active-label">
                    <div class="flex items-center justify-between mb-2">
                        <div id="metric-total-active-label" class="text-sm font-medium text-gray-600">Total Active Subscribers</div>
                        <div class="text-2xl" aria-hidden="true">📊</div>
                    </div>
                    <div class="text-3xl font-bold text-gray-900" id="totalActive" aria-live="polite">--</div>
                    <div class="text-sm mt-2" id="totalActiveComparison" role="status">
                        <span class="text-gray-500">Loading...</span>
                    </div>
                </div>

                <!-- On Vacation -->
                <div class="metric-card bg-white rounded-xl shadow p-6 relative context-menu-section vacation-overall transition-all duration-200 cursor-pointer"
                     role="region"
                     aria-labelledby="metric-vacation-label"
                     onclick="event.stopPropagation(); showVacationSubscriberList('overall');">
                    <div class="flex items-center justify-between mb-2">
                        <div id="metric-vacation-label" class="text-sm font-medium text-gray-600">Subscribers On Vacation</div>
                        <div class="text-2xl" aria-hidden="true">🏖️</div>
                    </div>
                    <div class="text-3xl font-bold text-gray-900" id="onVacation" aria-live="polite">--</div>
                    <div class="text-sm text-gray-500 mt-2">
                        <span id="vacationPercent">--</span> of total
                    </div>

                    <!-- Longest 3 Vacations -->
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <div class="text-xs font-medium text-gray-500 mb-2">Longest Vacations</div>
                        <div id="longestVacationsOverall" class="space-y-1.5">
                            <div class="text-xs text-gray-400 italic">Loading...</div>
                        </div>
                    </div>

                    <!-- Hover Actions -->
                    <div class="vacation-actions text-center" style="opacity: 0; transition: opacity 0.2s ease; margin-top: 12px; color: #3b82f6; font-weight: 600; font-size: 0.875rem;">
                        👥 View All on Vacation
                    </div>
                </div>

                <!-- Deliverable -->
                <div class="metric-card bg-white rounded-xl shadow p-6"
                     role="region"
                     aria-labelledby="metric-deliverable-label">
                    <div class="flex items-center justify-between mb-2">
                        <div id="metric-deliverable-label" class="text-sm font-medium text-gray-600">Deliverable Subscribers</div>
                        <div class="text-2xl" aria-hidden="true">📦</div>
                    </div>
                    <div class="text-3xl font-bold text-gray-900" id="deliverable" aria-live="polite">--</div>
                    <div class="text-sm mt-2" id="deliverableComparison" role="status">
                        <span class="text-gray-500">Ready for delivery</span>
                    </div>
                </div>

                <!-- Week Change -->
                <div class="metric-card bg-white rounded-xl shadow p-6"
                     role="region"
                     aria-labelledby="metric-comparison-label">
                    <div class="flex items-center justify-between mb-2">
                        <div id="metric-comparison-label" class="text-sm font-medium text-gray-600">Comparison Change</div>
                        <div class="text-2xl" aria-hidden="true">📈</div>
                    </div>
                    <div class="text-3xl font-bold" id="comparisonChange" aria-live="polite">--</div>
                    <div class="text-sm text-gray-500 mt-2">
                        <span id="comparisonPercent">--</span>
                    </div>
                </div>

            </div>
        </section>

        <!-- PHASE 2: Analytics Insights -->
        <section id="analytics" class="mb-8" aria-labelledby="analytics-heading">
            <h2 id="analytics-heading" class="text-lg font-semibold text-gray-900 mb-4">
                <span aria-hidden="true">🔍</span> Analytics Insights
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6" id="analyticsInsights" role="status" aria-live="polite">
                <div class="text-center py-8 text-gray-500 col-span-full">Loading analytics...</div>
            </div>
        </section>

        <!-- Revenue Intelligence -->
        <section id="metrics" class="mb-8" aria-labelledby="revenue-intelligence-heading">
            <h2 id="revenue-intelligence-heading" class="text-lg font-semibold text-gray-900 mb-4">
                <span aria-hidden="true">💰</span> Revenue Intelligence
            </h2>

            <!-- Expiration Risk Cards -->
            <div class="mb-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                    <span class="mr-2" aria-hidden="true">⏰</span>
                    <span>Expiration Risk</span>
                    <span class="ml-2 text-xs font-normal text-gray-500">(subscribers by time to renewal)</span>
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Expired -->
                    <div class="metric-card card-red bg-white rounded-xl shadow p-6 cursor-pointer"
                         onclick="showExpirationSubscribers('Expired')"
                         role="region"
                         aria-labelledby="expiration-expired-label">
                        <div class="flex items-center justify-between mb-2">
                            <div id="expiration-expired-label" class="text-sm font-medium text-gray-600">Expired</div>
                            <div class="text-2xl" aria-hidden="true">🚨</div>
                        </div>
                        <div class="text-3xl font-bold text-gray-900" id="expiredCount">--</div>
                        <div class="text-sm mt-2">
                            <span class="text-red-600 font-semibold" id="expiredRevenue">$--</span>
                            <span class="text-gray-500"> at risk</span>
                        </div>
                    </div>

                    <!-- 0-4 Weeks -->
                    <div class="metric-card card-orange bg-white rounded-xl shadow p-6 cursor-pointer"
                         onclick="showExpirationSubscribers('0-4 weeks')"
                         role="region"
                         aria-labelledby="expiration-04-label">
                        <div class="flex items-center justify-between mb-2">
                            <div id="expiration-04-label" class="text-sm font-medium text-gray-600">Expiring 0-4 Weeks</div>
                            <div class="text-2xl" aria-hidden="true">⚠️</div>
                        </div>
                        <div class="text-3xl font-bold text-gray-900" id="risk04Count">--</div>
                        <div class="text-sm mt-2">
                            <span class="text-orange-600 font-semibold" id="risk04Revenue">$--</span>
                            <span class="text-gray-500"> at risk</span>
                        </div>
                    </div>

                    <!-- 5-8 Weeks -->
                    <div class="metric-card card-yellow bg-white rounded-xl shadow p-6 cursor-pointer"
                         onclick="showExpirationSubscribers('5-8 weeks')"
                         role="region"
                         aria-labelledby="expiration-58-label">
                        <div class="flex items-center justify-between mb-2">
                            <div id="expiration-58-label" class="text-sm font-medium text-gray-600">Expiring 5-8 Weeks</div>
                            <div class="text-2xl" aria-hidden="true">⏳</div>
                        </div>
                        <div class="text-3xl font-bold text-gray-900" id="risk58Count">--</div>
                        <div class="text-sm mt-2">
                            <span class="text-yellow-600 font-semibold" id="risk58Revenue">$--</span>
                            <span class="text-gray-500"> at risk</span>
                        </div>
                    </div>

                    <!-- 9-12 Weeks -->
                    <div class="metric-card card-green bg-white rounded-xl shadow p-6 cursor-pointer"
                         onclick="showExpirationSubscribers('9-12 weeks')"
                         role="region"
                         aria-labelledby="expiration-912-label">
                        <div class="flex items-center justify-between mb-2">
                            <div id="expiration-912-label" class="text-sm font-medium text-gray-600">Expiring 9-12 Weeks</div>
                            <div class="text-2xl" aria-hidden="true">✅</div>
                        </div>
                        <div class="text-3xl font-bold text-gray-900" id="risk912Count">--</div>
                        <div class="text-sm mt-2">
                            <span class="text-green-600 font-semibold" id="risk912Revenue">$--</span>
                            <span class="text-gray-500"> at risk</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Charts -->
        <section class="mb-8" aria-labelledby="charts-heading">
            <h2 id="charts-heading" class="sr-only">Data Visualizations</h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- 12-Week Trend -->
                <div class="bg-white rounded-xl shadow p-6" role="region" aria-labelledby="trend-chart-heading">
                    <h3 id="trend-chart-heading" class="text-lg font-semibold text-gray-900 mb-4">
                        <span aria-hidden="true">📊</span> 12-Week Trend
                    </h3>
                    <div style="position: relative; height: 250px;">
                        <canvas id="trendChart" aria-label="Line chart showing subscriber trend over 12 weeks"></canvas>
                    </div>
                </div>

                <!-- Delivery Type Breakdown -->
                <div class="bg-white rounded-xl shadow p-6" role="region" aria-labelledby="delivery-chart-heading">
                    <h3 id="delivery-chart-heading" class="text-lg font-semibold text-gray-900 mb-4">
                        <span aria-hidden="true">📦</span> Delivery Type Distribution
                    </h3>
                    <div style="position: relative; height: 250px;">
                        <canvas id="deliveryChart" aria-label="Donut chart showing distribution of delivery types"></canvas>
                    </div>
                </div>

            </div>
        </section>

        <!-- Paper Breakdown -->
        <section id="reports" class="mb-8" aria-labelledby="publications-heading">
            <h2 id="publications-heading" class="text-lg font-semibold text-gray-900 mb-4">
                <span aria-hidden="true">📰</span> By Publication
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="paperCards" role="status" aria-live="polite">
                <div class="text-center py-12 col-span-full">
                    <div class="loading mx-auto mb-4" aria-hidden="true"></div>
                    <div class="text-gray-500">Loading publications...</div>
                </div>
            </div>
        </section>

        <!-- Revenue Opportunities -->
        <section class="mb-8" aria-labelledby="revenue-opportunities-heading">
            <!-- Caution Notice -->
            <div class="bg-amber-50 border-l-4 border-amber-400 p-4 mb-4 rounded-r-lg" role="alert">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-amber-400" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-amber-800">
                            ⚠️ <strong>Notice:</strong> Data shown in this section is not yet adjusted per product. <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-800 ml-2">COMING SOON</span>
                        </p>
                    </div>
                </div>
            </div>

            <h2 id="revenue-opportunities-heading" class="text-lg font-semibold text-gray-900 mb-4">
                <span aria-hidden="true">💎</span> Revenue Opportunities
            </h2>

            <!-- Under Construction Notice -->
            <div class="bg-amber-50 border-l-4 border-amber-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-amber-800">
                            <strong class="font-semibold">Under Development:</strong> Revenue opportunity calculations are being refined.
                            Numbers are approximate while we validate rate classifications and market rate detection.
                            <a href="rates.php" class="underline hover:text-amber-900">Review and classify rates here</a>.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Revenue Opportunity Table (replaces old card) -->
            <div id="revenueOpportunityTable" class="mb-6">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="text-center py-12">
                        <div class="loading mx-auto mb-4"></div>
                        <div class="text-gray-500">Loading revenue opportunity data...</div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-1 gap-6">

                <!-- Revenue Per Subscriber -->
                <div class="bg-white rounded-xl shadow-lg p-6 border-2 border-purple-200 hover:shadow-xl transition-all">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-gray-700 flex items-center">
                            <span class="mr-2" aria-hidden="true">📊</span>
                            <span>Revenue Per Subscriber</span>
                        </h3>
                        <div class="text-2xl" aria-hidden="true">💵</div>
                    </div>

                    <div class="space-y-3">
                        <!-- Overall ARPU -->
                        <div class="bg-purple-50 rounded-lg p-4">
                            <div class="text-xs text-purple-700 mb-1">Average Revenue Per User (ARPU)</div>
                            <div class="text-3xl font-bold text-purple-900" id="overallARPU">$--</div>
                            <div class="text-xs text-purple-600 mt-1">
                                MRR: <span id="overallMRR" class="font-semibold">$--</span>/month
                            </div>
                        </div>

                        <!-- By Delivery Type -->
                        <div class="space-y-2" id="arpuByDelivery">
                            <div class="flex justify-between items-center text-sm py-2 border-b border-gray-100">
                                <span class="text-gray-600">📮 Mail</span>
                                <span class="font-semibold text-gray-900">$--</span>
                            </div>
                            <div class="flex justify-between items-center text-sm py-2 border-b border-gray-100">
                                <span class="text-gray-600">🚗 Carrier</span>
                                <span class="font-semibold text-gray-900">$--</span>
                            </div>
                            <div class="flex justify-between items-center text-sm py-2">
                                <span class="text-gray-600">💻 Digital</span>
                                <span class="font-semibold text-gray-900">$--</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12" role="contentinfo">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex justify-between items-center text-sm text-gray-500">
                <div>
                    <span class="font-medium text-gray-900">Circulation Dashboard</span> <?php
                        require_once 'version.php';
                        echo VERSION_STRING;
                    ?>
                </div>
                <div>
                    © <?php echo date('Y'); ?> Edwards Group Holdings, Inc. ESOP
                </div>
            </div>
        </div>
    </footer>

    <!-- Detail Panel Backdrop -->
    <div id="detailPanelBackdrop" onclick="closeDetailPanel()" aria-hidden="true"></div>

    <!-- Detail Panel -->
    <div id="detailPanel" class="hidden" role="dialog" aria-modal="true" aria-labelledby="detailPanelTitle">
        <!-- State Navigation Sidebar -->
        <div id="stateNavSidebar" role="navigation" aria-label="State navigation">
            <!-- State nav items will be populated by JavaScript -->
        </div>

        <!-- Detail Content Area -->
        <div id="detailContent">
            <!-- Header -->
            <div class="flex justify-between items-center mb-6 border-b border-gray-200 pb-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900" id="detailPanelTitle">Business Unit Details</h2>
                    <p class="text-sm text-gray-500 mt-1" id="detailPanelSubtitle">Loading...</p>
                </div>
                <button onclick="closeDetailPanel()"
                        class="p-2 hover:bg-gray-100 rounded-lg transition focus:ring-2 focus:ring-blue-500"
                        aria-label="Close detail panel"
                        title="Close (ESC)">
                    <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Content Loading State -->
            <div id="detailPanelLoading" class="text-center py-12">
                <div class="loading mx-auto mb-4"></div>
                <div class="text-gray-500">Loading details...</div>
            </div>

            <!-- Content Container -->
            <div id="detailPanelContent" class="hidden space-y-6">
                <!-- Current Stats Summary -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">📊 Current Stats</h3>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="bg-white rounded-lg p-4 shadow-sm">
                            <div class="text-sm text-gray-600">Total Active</div>
                            <div class="text-2xl font-bold text-gray-900" id="detailTotalActive">--</div>
                        </div>
                        <div class="bg-white rounded-lg p-4 shadow-sm">
                            <div class="text-sm text-gray-600">Deliverable</div>
                            <div class="text-2xl font-bold text-green-600" id="detailDeliverable">--</div>
                        </div>
                        <div class="bg-white rounded-lg p-4 shadow-sm">
                            <div class="text-sm text-gray-600">On Vacation</div>
                            <div class="text-2xl font-bold text-amber-600" id="detailVacation">--</div>
                        </div>
                    </div>

                    <!-- Comparison Data -->
                    <div id="comparisonSection" class="mt-4 pt-4 border-t border-gray-200 hidden">
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Year over Year -->
                            <div id="yoyComparison" class="bg-white rounded-lg p-3 shadow-sm hidden">
                                <div class="text-xs text-gray-500 mb-1">📈 Year-over-Year</div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold" id="yoyChange">--</span>
                                    <span class="text-xs" id="yoyPercent">--</span>
                                </div>
                            </div>

                            <!-- Previous Week -->
                            <div id="prevWeekComparison" class="bg-white rounded-lg p-3 shadow-sm hidden">
                                <div class="text-xs text-gray-500 mb-1">📅 Previous Week</div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold" id="prevWeekChange">--</span>
                                    <span class="text-xs" id="prevWeekPercent">--</span>
                                </div>
                            </div>

                            <!-- Trend Direction -->
                            <div id="trendDirection" class="bg-white rounded-lg p-3 shadow-sm col-span-2 hidden">
                                <div class="text-xs text-gray-500 mb-1">📊 Trend Direction</div>
                                <div class="flex items-center justify-center">
                                    <span class="text-lg font-semibold" id="trendText">--</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4-Week Expiration Chart -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">📅 Subscription Expirations (4-Week View)</h3>
                    <p class="text-sm text-gray-500 mb-4">Click on any bar to see trend over time</p>
                    <div class="detail-chart-container" id="expirationChartContainer">
                        <canvas id="expirationChart"></canvas>
                    </div>
                </div>

                <!-- Rate Distribution Chart -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">💰 Rate Distribution (All Rates)</h3>
                    <p class="text-sm text-gray-500 mb-4">Click on any bar to see trend over time</p>
                    <div class="detail-chart-container" id="rateDistributionChartContainer">
                        <canvas id="rateDistributionChart"></canvas>
                    </div>
                </div>

                <!-- Subscription Length Chart -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">📆 Subscription Length Distribution</h3>
                    <p class="text-sm text-gray-500 mb-4">Click on any bar to see trend over time</p>
                    <div class="detail-chart-container" id="subscriptionLengthChartContainer">
                        <canvas id="subscriptionLengthChart"></canvas>
                    </div>
                </div>

                <!-- Delivery Type Breakdown -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">📦 Delivery Type Breakdown</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <span class="inline-block w-3 h-3 rounded-full bg-blue-500"></span>
                                <span class="text-gray-600">Mail Delivery</span>
                            </div>
                            <span class="font-medium text-gray-900" id="detailMail">--</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <span class="inline-block w-3 h-3 rounded-full bg-green-500"></span>
                                <span class="text-gray-600">Digital Only</span>
                            </div>
                            <span class="font-medium text-gray-900" id="detailDigital">--</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <span class="inline-block w-3 h-3 rounded-full bg-amber-500"></span>
                                <span class="text-gray-600">Carrier Delivery</span>
                            </div>
                            <span class="font-medium text-gray-900" id="detailCarrier">--</span>
                        </div>
                    </div>
                </div>
            </div>
        </div> <!-- /detailPanelContent -->
        </div> <!-- /detailContent -->
    </div> <!-- /detailPanel -->

    <!-- JavaScript - Cache-busting version parameter forces browser to fetch latest files -->
    <script src="assets/app.js?v=20251207"></script>
    <!-- State Icons & Chart Layout -->
    <script src="assets/state-icons.js?v=20251207"></script>
    <script src="assets/chart-layout-manager.js?v=20251207"></script>
    <script src="assets/donut-to-state-animation.js?v=20251207"></script>
    <!-- Detail Panel -->
    <script src="assets/detail_panel.js?v=20251207"></script>
    <!-- UI/UX Quick Wins -->
    <script src="assets/ui-enhancements.js?v=20251207"></script>

    <!-- PHASE 3: Contextual Chart Menus -->
    <script src="assets/context-menu.js?v=20251207"></script>
    <script src="assets/export-utils.js?v=20251207"></script>
    <script src="assets/subscriber-table-panel.js?v=20251207"></script>
    <script src="assets/trend-slider.js?v=20251207"></script>
    <script src="assets/chart-context-integration.js?v=20251207"></script>
    <script src="assets/vacation-display.js?v=20251208"></script>
    <script src="assets/revenue-opportunity-table.js?v=20251213"></script>
    <script src="assets/publication-revenue-detail.js?v=20251213"></script>
    <script src="assets/revenue-intelligence.js?v=20251213"></script>
    <script>
        // Initialize UI enhancements after dashboard is fully rendered
        // This listens for the 'DashboardRendered' event dispatched by renderDashboard()
        // Replaces the old setTimeout(500) race condition with explicit event coordination
        document.addEventListener('DashboardRendered', function(event) {
            if (typeof initializeUIEnhancements === 'function') {
                initializeUIEnhancements();
            }

            // Update backfill indicators if data is backfilled
            if (window.backfillIndicator && event.detail && event.detail.backfill) {
                window.backfillIndicator.update(event.detail.backfill);
            }

            // Load revenue intelligence data
            if (typeof loadRevenueIntelligence === 'function') {
                loadRevenueIntelligence();
            }
        });

        // Override the existing toggleExportMenu function to update ARIA state
        const originalToggleExportMenu = window.toggleExportMenu;
        window.toggleExportMenu = function() {
            const exportMenu = document.getElementById('exportMenu');
            const isCurrentlyHidden = exportMenu.classList.contains('hidden');

            if (originalToggleExportMenu) {
                originalToggleExportMenu();
            }

            // Update ARIA state
            if (typeof updateExportMenuAria === 'function') {
                updateExportMenuAria(!isCurrentlyHidden);
            }
        };

        // Section navigation active state tracking
        document.addEventListener('DOMContentLoaded', function() {
            const sections = document.querySelectorAll('section[id]');
            const navLinks = document.querySelectorAll('.section-nav-link');

            // Update active nav link on scroll
            const observerOptions = {
                root: null,
                rootMargin: '-20% 0px -70% 0px',
                threshold: 0
            };

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const id = entry.target.getAttribute('id');
                        navLinks.forEach(link => {
                            link.classList.remove('active');
                            if (link.getAttribute('href') === `#${id}`) {
                                link.classList.add('active');
                            }
                        });
                    }
                });
            }, observerOptions);

            sections.forEach(section => observer.observe(section));
        });
    </script>

</body>
</html>
