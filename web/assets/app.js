/**
 * Circulation Dashboard v2 - Consolidated (Phase 2 merged)
 * Phase 1: Week navigation, YoY comparison, smart Y-axis scaling
 * Phase 2: Business unit comparisons, export, analytics, drill-down
 * Author: Claude Code
 * Date: 2025-12-01
 * Refactored: 2025-12-06 - Eliminated override pattern
 */

/**
 * LOAD ORDER: 1 of 11 - Must load first
 *
 * DEPENDENCIES:
 * - Chart.js (CDN): Chart rendering
 * - Flatpickr (CDN): Date picker
 *
 * PROVIDES:
 * - CircDashboard.state: Central state object
 * - BUSINESS_UNITS, PAPER_INFO: Configuration constants
 * - loadDashboardData(), renderDashboard(): Core functions
 * - formatNumber(), formatChange(): Utilities
 * - All dashboard rendering and navigation functions
 */

console.log('===== APP.JS FILE LOADED =====');

/**
 * CircDashboard Namespace
 * Centralized state management for the dashboard
 */
const CircDashboard = window.CircDashboard || {};

CircDashboard.state = {
    // Data from API
    dashboardData: null,
    dataRange: null,

    // Navigation state
    currentDate: null,      // null = latest, or 'YYYY-MM-DD'
    compareMode: 'previous', // 'yoy', 'previous', 'none'

    // Chart instances
    charts: {
        trend: null,
        delivery: null,
        businessUnits: {},   // keyed by unit name
    },

    // UI instances
    flatpickrInstance: null,
};

// Configuration
const API_BASE = './api.php';

console.log('===== CONSTANTS DEFINED =====');

// Global state
let dashboardData = null;
let trendChart = null;
let deliveryChart = null;
let businessUnitCharts = {}; // Store business unit mini charts
let currentDate = null; // null = latest, or YYYY-MM-DD
let compareMode = 'previous'; // yoy, previous, none - default to previous week
let dataRange = null;
let flatpickrInstance = null;

// Business unit configuration
const BUSINESS_UNITS = {
    'South Carolina': {
        papers: ['TJ'],
        color: '#3b82f6',
        icon: '🏛️'
    },
    'Michigan': {
        papers: ['TA'],
        color: '#10b981',
        icon: '🏭'
    },
    'Wyoming': {
        papers: ['TR', 'LJ', 'WRN'],
        color: '#f59e0b',
        icon: '🏔️'
    }
};

// Paper metadata
const PAPER_INFO = {
    'TJ': { name: 'The Journal', location: 'Seneca, SC' },
    'TA': { name: 'The Advertiser', location: 'Caro, MI' },
    'TR': { name: 'The Ranger', location: 'Riverton, WY' },
    'LJ': { name: 'The Lander Journal', location: 'Lander, WY' },
    'WRN': { name: 'Wind River News', location: 'Riverton, WY' },
    'FN': { name: 'Former News', location: 'Sold' }
};

console.log('===== SETTING UP DOM CONTENT LOADED LISTENER =====');

/**
 * Initialize dashboard on page load
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('===== DOM CONTENT LOADED EVENT FIRED =====');
    // Set dropdown to match default compareMode
    document.getElementById('compareMode').value = compareMode;
    console.log('===== DROPDOWN SET TO:', compareMode, '=====');

    updateDateTime();
    loadDashboardData();

    // Update time every minute
    setInterval(updateDateTime, 60000);
});

/**
 * Update current date and time display
 */
function updateDateTime() {
    const now = new Date();
    const timeOptions = { hour: '2-digit', minute: '2-digit' };
    document.getElementById('currentDateTime').textContent = now.toLocaleTimeString('en-US', timeOptions);
}

/**
 * Load dashboard data from API
 */
async function loadDashboardData() {
    console.log('===== LOAD DASHBOARD DATA CALLED =====');
    try {
        const params = new URLSearchParams({
            action: 'overview',
            compare: compareMode
        });

        if (currentDate) {
            params.append('date', currentDate);
        }

        console.log('===== FETCHING DATA FROM API =====', `${API_BASE}?${params}`);
        const response = await fetch(`${API_BASE}?${params}`);
        console.log('===== API RESPONSE RECEIVED =====', response.status);
        const result = await response.json();
        console.log('===== API RESULT =====', result.success ? 'SUCCESS' : 'FAILED');

        if (result.success) {
            dashboardData = CircDashboard.state.dashboardData = result.data;
            dataRange = CircDashboard.state.dataRange = result.data.data_range;
            console.log('===== DASHBOARD DATA SET, CALLING RENDER =====');
            console.log('===== ABOUT TO ENTER TRY BLOCK =====');

            try {
                console.log('===== INSIDE TRY BLOCK, CALLING renderDashboard() =====');
                renderDashboard();
                console.log('===== renderDashboard() RETURNED =====');
            } catch (renderError) {
                console.error('===== CAUGHT ERROR IN RENDER =====', renderError);
                console.error('Error rendering dashboard:', renderError);
                console.error('Dashboard data:', dashboardData);
                showError('Failed to render dashboard: ' + renderError.message);
                return;
            }

            console.log('===== AFTER TRY/CATCH, UPDATING NAVIGATION =====');
            updateNavigationState();
            initDateNavigation(); // Initialize after first data load
        } else {
            showError('Failed to load dashboard data: ' + (result.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error loading dashboard:', error);
        showError('Failed to connect to API. Please check your connection.');
    }
}

/**
 * Initialize date navigation controls
 */
function initDateNavigation() {
    if (!dataRange) return;

    // Initialize Flatpickr (only once)
    if (!flatpickrInstance) {
        flatpickrInstance = CircDashboard.state.flatpickrInstance = flatpickr('#datePicker', {
            dateFormat: 'Y-m-d',
            maxDate: dataRange.max_date,
            minDate: dataRange.min_date,
            defaultDate: currentDate || dataRange.max_date,
            onChange: function(selectedDates, dateStr) {
                if (dateStr && dateStr !== currentDate) {
                    currentDate = CircDashboard.state.currentDate = dateStr;
                    loadDashboardData();
                }
            }
        });

        // Previous Week button
        document.getElementById('prevWeek').addEventListener('click', navigatePreviousWeek);

        // Next Week button
        document.getElementById('nextWeek').addEventListener('click', navigateNextWeek);

        // This Week button
        document.getElementById('thisWeek').addEventListener('click', navigateThisWeek);

        // Comparison Mode selector
        document.getElementById('compareMode').addEventListener('change', function(e) {
            compareMode = CircDashboard.state.compareMode = e.target.value;
            loadDashboardData();
        });
    } else {
        // Update existing Flatpickr instance
        flatpickrInstance.set('maxDate', dataRange.max_date);
        flatpickrInstance.set('minDate', dataRange.min_date);
    }

    // Update data range display
    const minDate = new Date(dataRange.min_date);
    const maxDate = new Date(dataRange.max_date);
    const minDateText = minDate.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
    const maxDateText = maxDate.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
    document.getElementById('dataRangeDisplay').textContent = `${minDateText} - ${maxDateText}`;
}

/**
 * Navigate to previous week
 */
function navigatePreviousWeek() {
    const date = new Date(currentDate || dataRange.max_date);
    date.setDate(date.getDate() - 7);

    // Don't go before 2025 data cutoff
    if (date < new Date('2025-01-01')) {
        return;
    }

    currentDate = CircDashboard.state.currentDate = date.toISOString().split('T')[0];
    if (flatpickrInstance) {
        flatpickrInstance.setDate(currentDate);
    }
    loadDashboardData();
}

/**
 * Navigate to next week
 */
function navigateNextWeek() {
    const date = new Date(currentDate || dataRange.max_date);
    date.setDate(date.getDate() + 7);

    // Don't go after max date
    if (date > new Date(dataRange.max_date)) {
        return;
    }

    currentDate = CircDashboard.state.currentDate = date.toISOString().split('T')[0];
    if (flatpickrInstance) {
        flatpickrInstance.setDate(currentDate);
    }
    loadDashboardData();
}

/**
 * Navigate to this week (latest data)
 */
function navigateThisWeek() {
    currentDate = CircDashboard.state.currentDate = null;
    if (flatpickrInstance) {
        flatpickrInstance.setDate(dataRange.max_date);
    }
    loadDashboardData();
}

/**
 * Update navigation button states
 */
function updateNavigationState() {
    if (!dashboardData || !dataRange) return;

    const prevBtn = document.getElementById('prevWeek');
    const nextBtn = document.getElementById('nextWeek');

    const viewingDate = currentDate || dataRange.max_date;
    const maxDate = new Date(dataRange.max_date);
    const currentViewDate = new Date(viewingDate);

    // With week-based system, allow navigation to any week
    // API will return empty state if no data exists
    // Only prevent going before 2025-01-01 (data cutoff)
    const dataCutoff = new Date('2025-01-01');
    const weekBeforeCurrent = new Date(currentViewDate);
    weekBeforeCurrent.setDate(weekBeforeCurrent.getDate() - 7);
    prevBtn.disabled = weekBeforeCurrent < dataCutoff;

    // Disable next if at or after max (today)
    const weekAfterCurrent = new Date(currentViewDate);
    weekAfterCurrent.setDate(weekAfterCurrent.getDate() + 7);
    nextBtn.disabled = weekAfterCurrent > maxDate;
}

/**
 * Render empty state when week has no data
 */
function renderEmptyState() {
    const week = dashboardData.week || {};

    // Update period display
    const periodLabel = document.getElementById('periodLabel');
    const dateRange = document.getElementById('dateRange');
    const comparisonDisplay = document.getElementById('comparisonDisplay');

    if (periodLabel) periodLabel.textContent = week.label || 'Unknown Week';
    if (dateRange) dateRange.textContent = week.date_range || '';
    if (comparisonDisplay) comparisonDisplay.textContent = '';

    // Hide or clear all metric cards
    document.getElementById('totalActive').textContent = '--';
    document.getElementById('onVacation').textContent = '--';
    document.getElementById('vacationPercent').textContent = '--';
    document.getElementById('deliverable').textContent = '--';
    document.getElementById('totalActiveComparison').textContent = '';
    document.getElementById('deliverableComparison').textContent = '';
    document.getElementById('comparisonChange').textContent = '--';
    document.getElementById('comparisonPercent').textContent = 'No data';

    // Show empty state in business units section
    const businessUnitsContainer = document.getElementById('businessUnits');
    if (!businessUnitsContainer) {
        console.error('businessUnits container not found');
        return;
    }
    businessUnitsContainer.textContent = '';

    const emptyCard = document.createElement('div');
    emptyCard.className = 'col-span-full bg-amber-50 border-2 border-amber-200 rounded-xl p-8 text-center';

    const icon = document.createElement('div');
    icon.className = 'text-6xl mb-4';
    icon.textContent = '📊';
    emptyCard.appendChild(icon);

    const title = document.createElement('h3');
    title.className = 'text-xl font-semibold text-amber-900 mb-2';
    title.textContent = `No Data for ${week.label}`;
    emptyCard.appendChild(title);

    const dateRangeDisplay = document.createElement('p');
    dateRangeDisplay.className = 'text-amber-700 mb-4';
    dateRangeDisplay.textContent = week.date_range;
    emptyCard.appendChild(dateRangeDisplay);

    const message = document.createElement('p');
    message.className = 'text-amber-800 mb-4';
    message.textContent = dashboardData.message || 'No snapshot uploaded for this week.';
    emptyCard.appendChild(message);

    const explanation = document.createElement('p');
    explanation.className = 'text-sm text-amber-700 mb-2';
    explanation.textContent = 'AllSubs reports are point-in-time snapshots.';
    emptyCard.appendChild(explanation);

    const note = document.createElement('p');
    note.className = 'text-sm text-amber-600 italic';
    note.textContent = 'Historical data cannot be recreated for missed weeks.';
    emptyCard.appendChild(note);

    businessUnitsContainer.appendChild(emptyCard);

    // Clear other sections
    const paperCardsContainer = document.getElementById('paperCards');
    if (paperCardsContainer) {
        paperCardsContainer.textContent = '';
    }
    if (trendChart) trendChart.destroy();
    if (deliveryChart) deliveryChart.destroy();

    // Dispatch event even for empty state rendering
    document.dispatchEvent(new Event('DashboardRendered'));
}

/**
 * Render complete dashboard
 * CONSOLIDATED VERSION - Phase 2 merged
 */
function renderDashboard() {
    console.log('===== RENDER DASHBOARD CALLED =====');
    if (!dashboardData) {
        console.log('===== NO DASHBOARD DATA, RETURNING =====');
        return;
    }

    // Debug: Log has_data value
    console.log('renderDashboard called, has_data:', dashboardData.has_data, 'type:', typeof dashboardData.has_data);

    // Check if this week has data (handle empty state)
    if (!dashboardData.has_data || dashboardData.has_data === false) {
        console.log('No data for this week, rendering empty state');
        renderEmptyState();
        return;
    }

    // Has data - render normally
    renderPeriodDisplay();
    renderKeyMetrics();
    renderBusinessUnits();
    renderPaperCards();
    renderTrendChart();
    renderDeliveryChart();
    renderAnalytics();  // PHASE 2: Add analytics

    // Dispatch custom event to signal dashboard is fully rendered
    // This replaces the setTimeout(500) hack with explicit event coordination
    // Include backfill data for visual indicators
    const event = new CustomEvent('DashboardRendered', {
        detail: {
            backfill: dashboardData.backfill || null
        }
    });
    document.dispatchEvent(event);
}


/**
 * Render period display
 */
function renderPeriodDisplay() {
    const week = dashboardData.week || {};

    const periodLabel = document.getElementById('periodLabel');
    const dateRangeEl = document.getElementById('dateRange');

    if (periodLabel) periodLabel.textContent = week.label || 'Unknown Week';
    if (dateRangeEl) dateRangeEl.textContent = week.date_range || '';

    // Comparison display
    const comparison = dashboardData.comparison;
    const comparisonDisplay = document.getElementById('comparisonDisplay');

    if (comparison) {
        // Clear and rebuild safely
        comparisonDisplay.textContent = '';

        if (comparison.is_fallback) {
            // Show fallback warning in amber
            const span = document.createElement('span');
            span.className = 'text-amber-600';
            span.textContent = comparison.label;
            comparisonDisplay.appendChild(span);
        } else {
            // Normal comparison display
            const label = document.createElement('span');
            label.className = 'text-gray-600';
            label.textContent = `vs ${comparison.label}: `;
            comparisonDisplay.appendChild(label);

            const period = document.createElement('span');
            period.className = 'font-medium';
            period.textContent = comparison.period.label;
            comparisonDisplay.appendChild(period);
        }
    } else {
        comparisonDisplay.textContent = '';
    }
}

/**
 * Render key metrics cards
 */
function renderKeyMetrics() {
    const current = dashboardData.current;
    const comparison = dashboardData.comparison;

    // Total Active
    document.getElementById('totalActive').textContent = formatNumber(current.total_active);

    // On Vacation
    document.getElementById('onVacation').textContent = formatNumber(current.on_vacation);
    const vacPercent = current.total_active > 0 ?
        (current.on_vacation / current.total_active * 100).toFixed(2) : '0.00';
    document.getElementById('vacationPercent').textContent = `${vacPercent}%`;

    // Load longest vacations
    loadLongestVacations();

    // Deliverable
    document.getElementById('deliverable').textContent = formatNumber(current.deliverable);

    // Comparison metrics
    if (comparison) {
        // Total Active Comparison
        const totalChange = comparison.changes.total_active;
        const totalPercent = comparison.changes.total_active_percent;
        const totalCompEl = document.getElementById('totalActiveComparison');
        totalCompEl.innerHTML = renderComparisonBadge(totalChange, totalPercent, comparison.label);

        // Deliverable Comparison
        const delivChange = comparison.changes.deliverable;
        const delivCompEl = document.getElementById('deliverableComparison');
        if (delivChange !== 0) {
            delivCompEl.innerHTML = renderComparisonBadge(delivChange, null, 'vs comparison');
        }

        // Main comparison card
        document.getElementById('comparisonChange').textContent = formatChange(totalChange);
        document.getElementById('comparisonChange').className =
            'text-3xl font-bold ' + (totalChange >= 0 ? 'trend-up' : 'trend-down');
        document.getElementById('comparisonPercent').textContent =
            `${totalPercent > 0 ? '+' : ''}${totalPercent.toFixed(2)}%`;
    } else {
        // Show helpful message if available (e.g., "Year-over-year unavailable (no 2024 data)")
        const message = dashboardData.comparison_message || 'No comparison data';
        console.log('Comparison message:', dashboardData.comparison_message);
        console.log('Displaying message:', message);
        document.getElementById('totalActiveComparison').innerHTML =
            `<span class="text-gray-500">${message}</span>`;
        document.getElementById('deliverableComparison').innerHTML =
            '<span class="text-gray-500">Ready for delivery</span>';
        document.getElementById('comparisonChange').textContent = '--';
        document.getElementById('comparisonPercent').textContent = 'No comparison';
    }
}

/**
 * Render comparison badge
 */
function renderComparisonBadge(change, percent, label) {
    const sign = change > 0 ? '+' : '';
    const percentText = percent !== null ? ` (${sign}${percent.toFixed(2)}%)` : '';
    const badgeClass = change > 0 ? 'positive' : change < 0 ? 'negative' : 'neutral';
    const arrow = change > 0 ? '↑' : change < 0 ? '↓' : '→';

    return `
        <span class="comparison-badge ${badgeClass}">
            ${arrow} ${sign}${formatNumber(Math.abs(change))}${percentText}
        </span>
        <span class="text-xs text-gray-500 ml-1">${label}</span>
    `;
}

/**
 * Calculate smart Y-axis scale
 */
function calculateSmartScale(data, key = 'total_active') {
    const values = data.map(d => d[key]);
    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = max - min;

    // Add 10% padding
    const padding = range * 0.1;
    let scaledMin = min - padding;
    let scaledMax = max + padding;

    // Round to clean numbers
    if (range < 100) {
        scaledMin = Math.floor(scaledMin / 10) * 10;
        scaledMax = Math.ceil(scaledMax / 10) * 10;
    } else if (range < 1000) {
        scaledMin = Math.floor(scaledMin / 100) * 100;
        scaledMax = Math.ceil(scaledMax / 100) * 100;
    } else {
        scaledMin = Math.floor(scaledMin / 500) * 500;
        scaledMax = Math.ceil(scaledMax / 500) * 500;
    }

    // Ensure non-zero range
    if (scaledMin === scaledMax) {
        scaledMin -= 50;
        scaledMax += 50;
    }

    return { min: scaledMin, max: scaledMax };
}

/**
 * Render 12-week trend chart with smart scaling
 */
function renderTrendChart() {
    const ctx = document.getElementById('trendChart');
    if (!ctx) return;

    const trend = dashboardData.trend || [];

    // Destroy existing chart
    if (trendChart) {
        trendChart.destroy();
    }

    // Prepare data - use week labels instead of dates (handles missing weeks)
    const labels = trend.map(d => `W${d.week_num}`);

    const activeData = trend.map(d => d.total_active); // null for missing weeks
    const deliverableData = trend.map(d => d.deliverable);
    const vacationData = trend.map(d => d.on_vacation);

    // Calculate smart scale (filter out null values)
    const validTrend = trend.filter(d => d.total_active !== null);
    const scale = validTrend.length > 0 ?
        calculateSmartScale(validTrend, 'total_active') :
        { min: 0, max: 100 };

    trendChart = CircDashboard.state.charts.trend = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Total Active',
                    data: activeData,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    spanGaps: true  // Connect across missing weeks
                },
                {
                    label: 'Deliverable',
                    data: deliverableData,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    spanGaps: true
                },
                {
                    label: 'On Vacation',
                    data: vacationData,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    spanGaps: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += formatNumber(context.parsed.y);
                            return label;
                        }
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                    grid: {
                        display: false
                    }
                },
                y: {
                    display: true,
                    min: scale.min,
                    max: scale.max,
                    ticks: {
                        callback: function(value) {
                            return formatNumber(value);
                        }
                    }
                }
            }
        }
    });
}

/**
 * Render delivery type breakdown chart
 */
function renderDeliveryChart() {
    const ctx = document.getElementById('deliveryChart');
    if (!ctx) return;

    const current = dashboardData.current;

    // Destroy existing chart
    if (deliveryChart) {
        deliveryChart.destroy();
    }

    const mail = current.mail;
    const digital = current.digital;
    const carrier = current.carrier;

    deliveryChart = CircDashboard.state.charts.delivery = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Mail (USPS)', 'Digital Only', 'Carrier'],
            datasets: [{
                data: [mail, digital, carrier],
                backgroundColor: [
                    '#3b82f6',
                    '#10b981',
                    '#f59e0b'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: ${formatNumber(value)} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

/**
 * Render business unit cards
 * CONSOLIDATED VERSION - Phase 2 merged
 */
function renderBusinessUnits() {
    console.log('=== renderBusinessUnits CALLED ===');
    const container = document.getElementById('businessUnits');
    const byUnit = dashboardData.by_business_unit;
    const comparisons = dashboardData.business_unit_comparisons || {};

    if (!byUnit) {
        container.innerHTML = '<div class="text-center text-gray-500 py-8">No business unit data available</div>';
        return;
    }

    // Destroy existing business unit charts
    Object.values(businessUnitCharts).forEach(chart => chart.destroy());
    businessUnitCharts = {};

    let html = '';

    for (const [unitName, config] of Object.entries(BUSINESS_UNITS)) {
        const data = byUnit[unitName];
        if (!data) continue;

        const percentage = (data.total / dashboardData.current.total_active * 100).toFixed(1);
        const vacPercent = (data.on_vacation / data.total * 100).toFixed(2);
        const chartId = `chart-${unitName.replace(/\s+/g, '-').toLowerCase()}`;
        const comparison = comparisons[unitName];

        const snapshotDate = dashboardData.current.snapshot_date;
        html += `
            <div class="paper-card bg-white rounded-xl shadow-sm p-6 border-l-4" style="border-left-color: ${config.color}" onclick="openDetailPanel('${unitName}', '${snapshotDate}')">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <div class="flex items-center space-x-2 mb-1">
                            <span class="text-2xl">${config.icon}</span>
                            <h3 class="text-xl font-bold text-gray-900">${unitName}</h3>
                        </div>
                        <div class="text-sm text-gray-500">${config.papers.join(', ')}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-bold text-gray-900">${formatNumber(data.total)}</div>
                        <div class="text-sm text-gray-500">${percentage}% of total</div>
                        ${comparison && (compareMode === 'yoy' ? comparison.yoy : comparison.previous_week) && (compareMode === 'yoy' ? comparison.yoy : comparison.previous_week).change !== undefined ? `<div class="mt-1">${renderComparisonBadge((compareMode === 'yoy' ? comparison.yoy : comparison.previous_week).change, (compareMode === 'yoy' ? comparison.yoy : comparison.previous_week).change_percent, 'vs comparison')}</div>` : ''}
                    </div>
                </div>

                <div class="mb-4">
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="progress-bar h-2 rounded-full" style="width: ${percentage}%; background-color: ${config.color}"></div>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-gray-100">
                    <div class="flex items-start justify-between">
                        <!-- Chart with center text -->
                        <div class="flex flex-col items-center">
                            <div class="relative" style="width: 160px; height: 160px;">
                                <canvas id="${chartId}"></canvas>
                                <div class="absolute inset-0 flex flex-col items-center justify-center">
                                    <div class="text-2xl font-bold text-gray-900">${formatNumber(data.deliverable)}</div>
                                    <div class="text-xs text-gray-500">Deliverable</div>
                                    <div class="text-xs text-gray-400">of ${formatNumber(data.total)}</div>
                                </div>
                            </div>
                            <!-- Vacation info below chart -->
                            <div class="mt-3 text-center w-full relative context-menu-section rounded-lg p-2 -m-2 vacation-section-${unitName.replace(/\s+/g, '-').toLowerCase()} cursor-pointer"
                                 data-unit-color="${config.color}"
                                 onclick="event.stopPropagation(); showVacationSubscriberList('${unitName}');"
                                 onmouseenter="this.style.backgroundColor='${hexToRgba(config.color, 0.1)}'; this.style.border='2px solid ${hexToRgba(config.color, 0.5)}';"
                                 onmouseleave="this.style.backgroundColor=''; this.style.border='';"
                                 style="transition: all 0.2s ease;">
                                <div class="text-sm text-gray-600">🏖️ On Vacation</div>
                                <div class="text-lg font-semibold text-gray-900">${formatNumber(data.on_vacation)} <span class="text-sm text-gray-500">(${vacPercent}%)</span></div>

                                <!-- Longest 3 Vacations for this unit -->
                                <div class="mt-3 pt-3 border-t border-gray-100">
                                    <div class="text-xs font-medium text-gray-400 mb-1.5 text-left">Longest Vacations</div>
                                    <div id="longestVacations${unitName.replace(/\s+/g, '')}" class="space-y-1 text-left">
                                        <div class="text-xs text-gray-300 italic">Loading...</div>
                                    </div>
                                </div>

                                <!-- Hover Actions -->
                                <div class="vacation-actions text-center" style="opacity: 0; transition: opacity 0.2s ease; margin-top: 12px; color: ${config.color}; font-weight: 600; font-size: 0.875rem;">
                                    👥 View ${unitName} Vacations
                                </div>
                            </div>
                        </div>

                        <!-- Legend and Comparisons -->
                        <div class="ml-4 flex flex-col gap-1.5" style="height: 160px; min-width: 144px; max-width: 176px;">
                            <div class="flex-1 bg-blue-500 text-white flex flex-col items-center justify-center px-4 py-1">
                                <div class="font-semibold text-xs">Mail</div>
                                <div class="font-bold text-sm whitespace-nowrap">${formatNumber(data.mail)} <span class="opacity-90 text-xs">(${(data.mail/data.total*100).toFixed(1)}%)</span></div>
                            </div>
                            <div class="flex-1 bg-green-500 text-white flex flex-col items-center justify-center px-4 py-1">
                                <div class="font-semibold text-xs">Digital</div>
                                <div class="font-bold text-sm whitespace-nowrap">${formatNumber(data.digital)} <span class="opacity-90 text-xs">(${(data.digital/data.total*100).toFixed(1)}%)</span></div>
                            </div>
                            <div class="flex-1 bg-amber-500 text-white flex flex-col items-center justify-center px-4 py-1">
                                <div class="font-semibold text-xs">Carrier</div>
                                <div class="font-bold text-sm whitespace-nowrap">${formatNumber(data.carrier)} <span class="opacity-90 text-xs">(${(data.carrier/data.total*100).toFixed(1)}%)</span></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        `;
    }

    container.innerHTML = html;

    // Create mini charts after DOM is updated
    for (const [unitName, config] of Object.entries(BUSINESS_UNITS)) {
        const data = byUnit[unitName];
        if (!data) continue;

        const chartId = `chart-${unitName.replace(/\s+/g, '-').toLowerCase()}`;
        const ctx = document.getElementById(chartId);
        if (!ctx) continue;

        businessUnitCharts[unitName] = CircDashboard.state.charts.businessUnits[unitName] = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Mail', 'Digital', 'Carrier'],
                datasets: [{
                    data: [data.mail, data.digital, data.carrier],
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '70%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: ${formatNumber(value)} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
}


/**
 * Render individual paper cards
 */
function renderPaperCards() {
    const container = document.getElementById('paperCards');
    const byPaper = dashboardData.by_edition;

    if (!byPaper) {
        container.innerHTML = '<div class="text-center text-gray-500 py-8 col-span-full">No paper data available</div>';
        return;
    }

    let html = '';

    const sortedPapers = Object.entries(byPaper)
        .filter(([code]) => code !== 'FN')
        .sort((a, b) => b[1].total - a[1].total);

    for (const [code, data] of sortedPapers) {
        const info = PAPER_INFO[code] || { name: code, location: 'Unknown' };
        const vacPercent = (data.on_vacation / data.total * 100).toFixed(2);

        html += `
            <div class="paper-card bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition">
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-lg font-bold text-gray-900">${info.name}</h3>
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded">${code}</span>
                    </div>
                    <div class="text-sm text-gray-500">${info.location}</div>
                </div>

                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600">Total Active</span>
                            <span class="font-semibold text-gray-900">${formatNumber(data.total)}</span>
                        </div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600">On Vacation</span>
                            <span class="font-semibold text-gray-900">${formatNumber(data.on_vacation)} (${vacPercent}%)</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Deliverable</span>
                            <span class="font-semibold text-gray-900">${formatNumber(data.deliverable)}</span>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-gray-100">
                        <div class="text-xs text-gray-600 mb-2">Delivery Types</div>
                        <div class="flex justify-between text-xs">
                            <span>📮 Mail: ${formatNumber(data.mail)}</span>
                            <span>💻 Digital: ${formatNumber(data.digital)}</span>
                            <span>🚗 Carrier: ${formatNumber(data.carrier)}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    container.innerHTML = html;
}

/**
 * Refresh dashboard data
 */
function refreshData() {
    const button = event.target.closest('button');
    button.disabled = true;
    button.innerHTML = '<div class="loading"></div><span>Refreshing...</span>';

    loadDashboardData().then(() => {
        button.disabled = false;
        button.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg><span>Refresh</span>';
    });
}

/**
 * Show error message
 */
function showError(message) {
    const main = document.querySelector('main');
    main.innerHTML = `
        <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
            <div class="text-4xl mb-4">⚠️</div>
            <h3 class="text-lg font-semibold text-red-900 mb-2">Error Loading Dashboard</h3>
            <p class="text-red-700">${message}</p>
            <button onclick="window.location.reload()" class="mt-4 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                Retry
            </button>
        </div>
    `;
}

/**
 * Format number with commas
 */
function formatNumber(num) {
    return num.toLocaleString('en-US');
}

/**
 * Format change with +/- sign
 */
function formatChange(num) {
    if (num > 0) return `+${formatNumber(num)}`;
    if (num < 0) return formatNumber(num);
    return '0';
}

/**
 * Convert hex color to rgba with opacity
 */
function hexToRgba(hex, opacity) {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r}, ${g}, ${b}, ${opacity})`;
}

function exportToCSV() {
    const data = dashboardData;

    let csv = 'Period,' + data.week.label + '\n';
    csv += 'Date Range,' + data.week.date_range + '\n';
    csv += '\n';

    csv += 'Business Unit,Paper,Total Active,On Vacation,Deliverable,Mail,Digital,Carrier\n';

    for (const [unit, unitData] of Object.entries(data.by_business_unit)) {
        csv += `${unit},ALL,${unitData.total},${unitData.on_vacation},${unitData.deliverable},${unitData.mail},${unitData.digital},${unitData.carrier}\n`;
    }

    for (const [paper, paperData] of Object.entries(data.by_edition)) {
        csv += `${paperData.business_unit},${paper},${paperData.total},${paperData.on_vacation},${paperData.deliverable},${paperData.mail},${paperData.digital},${paperData.carrier}\n`;
    }

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `circulation-${data.week.label.replace(/\s/g, '-')}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
}

/**
 * Export to Excel (multi-sheet)
 */
function exportToExcel() {
    if (typeof XLSX === 'undefined') {
        alert('Excel export library not loaded. Please refresh the page.');
        return;
    }

    const wb = XLSX.utils.book_new();
    const data = dashboardData;

    // Sheet 1: Summary
    const summaryData = [
        ['Circulation Dashboard Export'],
        ['Period', data.week.label],
        ['Date Range', data.week.date_range],
        [''],
        ['Overall Metrics', ''],
        ['Total Active', data.current.total_active],
        ['On Vacation', data.current.on_vacation],
        ['Deliverable', data.current.deliverable],
        [''],
        ['Delivery Methods', ''],
        ['Mail', data.current.mail],
        ['Digital', data.current.digital],
        ['Carrier', data.current.carrier]
    ];

    if (data.comparison) {
        summaryData.push(['']);
        summaryData.push(['Comparison', data.comparison.label]);
        summaryData.push(['Change', data.comparison.changes.total_active]);
        summaryData.push(['Change %', data.comparison.changes.total_active_percent + '%']);
    }

    const summarySheet = XLSX.utils.aoa_to_sheet(summaryData);
    XLSX.utils.book_append_sheet(wb, summarySheet, 'Summary');

    // Sheet 2: By Business Unit
    const unitData = [['Business Unit', 'Papers', 'Total', 'Vacation', 'Deliverable', 'Mail', 'Digital', 'Carrier']];
    for (const [unit, unitValues] of Object.entries(data.by_business_unit)) {
        const papers = BUSINESS_UNITS[unit]?.papers.join(', ') || '';
        unitData.push([
            unit, papers,
            unitValues.total, unitValues.on_vacation, unitValues.deliverable,
            unitValues.mail, unitValues.digital, unitValues.carrier
        ]);
    }
    const unitSheet = XLSX.utils.aoa_to_sheet(unitData);
    XLSX.utils.book_append_sheet(wb, unitSheet, 'By Business Unit');

    // Sheet 3: By Publication
    const paperData = [['Paper', 'Name', 'Business Unit', 'Total', 'Vacation', 'Deliverable', 'Mail', 'Digital', 'Carrier']];
    for (const [code, paperValues] of Object.entries(data.by_edition)) {
        const info = PAPER_INFO[code];
        paperData.push([
            code, info?.name || code, paperValues.business_unit,
            paperValues.total, paperValues.on_vacation, paperValues.deliverable,
            paperValues.mail, paperValues.digital, paperValues.carrier
        ]);
    }
    const paperSheet = XLSX.utils.aoa_to_sheet(paperData);
    XLSX.utils.book_append_sheet(wb, paperSheet, 'By Publication');

    // Sheet 4: 12-Week Trend
    const trendData = [['Date', 'Total Active', 'Deliverable', 'On Vacation']];
    for (const week of data.trend) {
        trendData.push([week.snapshot_date, week.total_active, week.deliverable, week.on_vacation]);
    }
    const trendSheet = XLSX.utils.aoa_to_sheet(trendData);
    XLSX.utils.book_append_sheet(wb, trendSheet, '12-Week Trend');

    // Download
    XLSX.writeFile(wb, `circulation-export-${data.week.label.replace(/\s/g, '-')}.xlsx`);
}

/**
 * Export to PDF
 */
async function exportToPDF() {
    if (typeof jspdf === 'undefined' || typeof html2canvas === 'undefined') {
        alert('PDF export libraries not loaded. Please refresh the page.');
        return;
    }

    const { jsPDF } = window.jspdf;

    // Show loading message
    const exportBtn = document.getElementById('exportBtn');
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<div class="loading" style="width:16px;height:16px"></div><span>Generating PDF...</span>';
    exportBtn.disabled = true;

    try {
        const dashboard = document.querySelector('main');
        const canvas = await html2canvas(dashboard, {
            scale: 2,
            backgroundColor: '#ffffff',
            logging: false
        });

        const imgData = canvas.toDataURL('image/png');
        const pdf = new jsPDF({
            orientation: 'portrait',
            unit: 'mm',
            format: 'a4'
        });

        const imgWidth = 210; // A4 width
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        const pageHeight = 297; // A4 height

        let heightLeft = imgHeight;
        let position = 0;

        pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;

        while (heightLeft >= 0) {
            position = heightLeft - imgHeight;
            pdf.addPage();
            pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
        }

        pdf.save(`circulation-dashboard-${dashboardData.week.label.replace(/\s/g, '-')}.pdf`);
    } catch (error) {
        console.error('PDF generation failed:', error);
        alert('Failed to generate PDF. Please try again.');
    } finally {
        exportBtn.innerHTML = originalText;
        exportBtn.disabled = false;
    }
}

/**
 * Toggle export menu
 */
function toggleExportMenu() {
    const menu = document.getElementById('exportMenu');
    menu.classList.toggle('hidden');
}

// Close export menu when clicking outside
document.addEventListener('click', function(e) {
    const exportBtn = document.getElementById('exportBtn');
    const exportMenu = document.getElementById('exportMenu');
    if (exportBtn && exportMenu && !exportBtn.contains(e.target) && !exportMenu.contains(e.target)) {
        exportMenu.classList.add('hidden');
    }
});

// ========================================
// PHASE 2: Analytics Functions
// ========================================

/**
 * Render analytics insights section
 */
function renderAnalytics() {
    const analytics = dashboardData.analytics;
    if (!analytics) return;

    const container = document.getElementById('analyticsInsights');
    if (!container) return;

    // Find the section element (parent of container)
    const section = container.closest('section');

    let html = '';

    // Forecast Card
    if (analytics.forecast) {
        const f = analytics.forecast;
        const confidenceColors = {
            'high': 'bg-green-50 text-green-900 border-green-200',
            'medium': 'bg-blue-50 text-blue-900 border-blue-200',
            'low': 'bg-amber-50 text-amber-900 border-amber-200'
        };
        const confidenceColor = confidenceColors[f.confidence] || confidenceColors.medium;

        html += `
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl shadow p-6 border border-blue-200">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-sm font-medium text-blue-900">Next Week Forecast</div>
                    <div class="text-2xl">🔮</div>
                </div>
                <div class="text-3xl font-bold text-blue-900">${formatNumber(f.value)}</div>
                <div class="text-sm text-blue-700 mt-2">
                    <span class="${f.change >= 0 ? 'text-green-700' : 'text-red-700'} font-medium">
                        ${formatChange(f.change)} (${f.change_percent > 0 ? '+' : ''}${f.change_percent}%)
                    </span>
                </div>
                <div class="mt-3">
                    <span class="inline-block px-2 py-1 rounded text-xs font-medium ${confidenceColor} border">
                        ${f.confidence.toUpperCase()} confidence
                    </span>
                </div>
            </div>
        `;
    }

    // Strongest Growth Card
    if (analytics.performers?.strongest) {
        const p = analytics.performers.strongest;
        html += `
            <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl shadow p-6 border border-green-200">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-sm font-medium text-green-900">Strongest Growth</div>
                    <div class="text-2xl">🚀</div>
                </div>
                <div class="text-2xl font-bold text-green-900">${p.unit}</div>
                <div class="text-sm text-green-700 mt-2 font-medium">
                    ${formatChange(p.change)} (${p.change_percent > 0 ? '+' : ''}${p.change_percent}%)
                </div>
                <div class="text-xs text-green-600 mt-1">vs last year</div>
            </div>
        `;
    }

    // Anomaly Alert Card - only show if there are anomalies detected
    if (analytics.anomalies && analytics.anomalies.length > 0) {
        const anomalyCount = analytics.anomalies.length;
        const latestAnomaly = analytics.anomalies[analytics.anomalies.length - 1];

        html += `
            <div class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl shadow p-6 border border-amber-200">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-sm font-medium text-amber-900">Unusual Activity</div>
                    <div class="text-2xl">⚠️</div>
                </div>
                <div class="text-3xl font-bold text-amber-900">${anomalyCount}</div>
                <div class="text-sm text-amber-700 mt-2">
                    ${anomalyCount === 1 ? 'anomaly in last 12 weeks' :
                      'anomalies in last 12 weeks'}
                </div>
                ${latestAnomaly ? `
                    <div class="text-xs text-amber-600 mt-1">
                        Last: ${new Date(latestAnomaly.date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'})}
                        (${latestAnomaly.severity} severity)
                    </div>
                ` : ''}
            </div>
        `;
    }

    // If no analytics cards to show, hide the entire section
    if (html === '') {
        container.innerHTML = '';
        if (section) {
            section.style.display = 'none';
        }
    } else {
        container.innerHTML = html;
        if (section) {
            section.style.display = 'block';
        }
    }
}

// ========================================
// PHASE 2: Business Unit Drill-Down
// ========================================

/**
 * Toggle business unit detail expansion
 */
async function toggleBusinessUnitDetails(unitName) {
    const detailsId = `details-${unitName.replace(/\s+/g, '-').toLowerCase()}`;
    const details = document.getElementById(detailsId);
    const expandBtn = document.getElementById(`expand-${unitName.replace(/\s+/g, '-').toLowerCase()}`);

    if (!details || !expandBtn) return;

    if (details.classList.contains('hidden')) {
        // Expand
        expandBtn.innerHTML = '<span class="loading" style="width:12px;height:12px"></span> Loading...';

        try {
            // Load detailed data from API
            const response = await fetch(`${API_BASE}?action=business_unit_detail&unit=${encodeURIComponent(unitName)}&date=${currentDate || ''}`);
            const result = await response.json();

            if (result.success) {
                renderBusinessUnitDetail(unitName, result.data);
                details.classList.remove('hidden');
                expandBtn.textContent = '▲ Click to collapse';
            } else {
                expandBtn.textContent = '▼ Click for detailed breakdown';
                alert('Failed to load details: ' + (result.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error loading business unit details:', error);
            expandBtn.textContent = '▼ Click for detailed breakdown';
            alert('Failed to load business unit details. Please try again.');
        }
    } else {
        // Collapse
        details.classList.add('hidden');
        expandBtn.textContent = '▼ Click for detailed breakdown';
    }
}

/**
 * Render business unit detail section
 */
function renderBusinessUnitDetail(unitName, data) {
    const detailsId = `details-${unitName.replace(/\s+/g, '-').toLowerCase()}`;
    const container = document.getElementById(detailsId);
    if (!container) return;

    let html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-6">';

    // Left: Publication breakdown
    html += '<div><h4 class="text-sm font-semibold text-gray-700 mb-3">📰 Publications</h4>';
    html += '<div class="space-y-2">';

    for (const [paperCode, paperData] of Object.entries(data.paper_details)) {
        const mailPercent = paperData.total > 0 ? (paperData.mail / paperData.total * 100).toFixed(1) : 0;
        html += `
            <div class="bg-white p-3 rounded-lg border border-gray-200 hover:border-blue-300 transition">
                <div class="flex justify-between items-start mb-2">
                    <div class="font-medium text-gray-900">${paperData.name}</div>
                    <div class="text-lg font-bold text-gray-700">${formatNumber(paperData.total)}</div>
                </div>
                <div class="grid grid-cols-3 gap-2 text-xs text-gray-600">
                    <div>📮 ${formatNumber(paperData.mail)} (${mailPercent}%)</div>
                    <div>💻 ${formatNumber(paperData.digital)}</div>
                    <div>🚗 ${formatNumber(paperData.carrier)}</div>
                </div>
            </div>
        `;
    }

    html += '</div></div>';

    // Right: Trend chart for this unit
    const trendId = `trend-${unitName.replace(/\s+/g, '-').toLowerCase()}`;
    html += `
        <div>
            <h4 class="text-sm font-semibold text-gray-700 mb-3">📊 12-Week Trend</h4>
            <div style="position: relative; height: 200px;">
                <canvas id="${trendId}"></canvas>
            </div>
        </div>
    `;

    html += '</div>';

    // Delivery method breakdown chart
    const deliveryTrendId = `delivery-trend-${unitName.replace(/\s+/g, '-').toLowerCase()}`;
    html += `
        <div class="mt-6 pt-6 border-t border-gray-200">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">📦 Delivery Method Trends</h4>
            <div style="position: relative; height: 150px;">
                <canvas id="${deliveryTrendId}"></canvas>
            </div>
        </div>
    `;

    container.innerHTML = html;

    // Render charts
    setTimeout(() => {
        renderUnitTrendChart(trendId, data.trend);
        renderUnitDeliveryTrend(deliveryTrendId, data.trend);
    }, 100);
}

/**
 * Render unit-specific trend chart
 */
function renderUnitTrendChart(canvasId, trend) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    const labels = trend.map(d => {
        const date = new Date(d.snapshot_date);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    });

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Total Active',
                data: trend.map(d => d.total_active),
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    ticks: {
                        callback: function(value) {
                            return formatNumber(value);
                        }
                    }
                }
            }
        }
    });
}

/**
 * Render unit delivery method trend
 */
function renderUnitDeliveryTrend(canvasId, trend) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    const labels = trend.map(d => {
        const date = new Date(d.snapshot_date);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    });

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Mail',
                    data: trend.map(d => d.mail),
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Digital',
                    data: trend.map(d => d.digital),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Carrier',
                    data: trend.map(d => d.carrier),
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, padding: 10 }
                }
            },
            scales: {
                y: {
                    stacked: false,
                    ticks: {
                        callback: function(value) {
                            return formatNumber(value);
                        }
                    }
                }
            }
        }
    });
}

/**
 * Render comparison badge with trend indicator
 */
function renderComparisonWithTrend(comparison, trendDirection) {
    if (!comparison) {
        return '<span class="text-xs text-gray-400">No comparison data</span>';
    }

    const trendIcons = {
        'growing': '↗️',
        'declining': '↘️',
        'stable': '→'
    };
    const trendColors = {
        'growing': 'text-green-600',
        'declining': 'text-red-600',
        'stable': 'text-gray-600'
    };

    const icon = trendIcons[trendDirection] || '→';
    const color = trendColors[trendDirection] || 'text-gray-600';
    const changeColor = comparison.change >= 0 ? 'text-green-600' : 'text-red-600';

    return `
        <div class="flex items-center justify-between text-xs">
            <span class="text-gray-600">vs Last Year:</span>
            <span class="${changeColor} font-medium">
                ${formatChange(comparison.change)} (${comparison.change_percent > 0 ? '+' : ''}${comparison.change_percent}%)
            </span>
        </div>
        <div class="flex items-center justify-between mt-1 pt-1 border-t border-gray-100 text-xs">
            <span class="text-gray-600">Trend:</span>
            <span class="${color} font-medium">${icon} ${capitalizeFirst(trendDirection)}</span>
        </div>
    `;
}

/**
 * Capitalize first letter
 */
function capitalizeFirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

/**
 * Load longest vacations from API
 */
function loadLongestVacations() {
    const snapshotDate = dashboardData.current.snapshot_date;

    fetch(`api.php?action=get_longest_vacations&snapshot_date=${snapshotDate}`)
        .then(response => response.json())
        .then(response => {
            if (response.success && response.data) {
                const data = response.data;

                // Display overall longest vacations
                displayLongestVacationsOverall(data.overall);

                // Display per-unit longest vacations
                if (data.by_unit) {
                    Object.entries(data.by_unit).forEach(([unit, vacations]) => {
                        displayLongestVacationsForUnit(unit, vacations);
                    });
                }
            } else {
                console.error('Failed to load vacation data:', response.error);
            }
        })
        .catch(error => {
            console.error('Error loading vacation data:', error);
            // Show friendly error message in UI
            const container = document.getElementById('longestVacationsOverall');
            if (container) {
                container.innerHTML = '<div class="text-xs text-gray-400 italic">Unable to load vacation data</div>';
            }
        });
}

console.log('App.js loaded - Phase 2 consolidated successfully!');
