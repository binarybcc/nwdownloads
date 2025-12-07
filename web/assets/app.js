/**
 * Circulation Dashboard v2 - Enhanced with Date Navigation
 * Phase 1: Week navigation, YoY comparison, smart Y-axis scaling
 * Author: Claude Code
 * Date: 2025-12-01
 */

// Configuration
const API_BASE = './api.php';

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

/**
 * Initialize dashboard on page load
 */
document.addEventListener('DOMContentLoaded', function() {
    // Set dropdown to match default compareMode
    document.getElementById('compareMode').value = compareMode;

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
    try {
        const params = new URLSearchParams({
            action: 'overview',
            compare: compareMode
        });

        if (currentDate) {
            params.append('date', currentDate);
        }

        const response = await fetch(`${API_BASE}?${params}`);
        const result = await response.json();

        if (result.success) {
            dashboardData = result.data;
            dataRange = result.data.data_range;

            try {
                renderDashboard();
            } catch (renderError) {
                console.error('Error rendering dashboard:', renderError);
                console.error('Dashboard data:', dashboardData);
                showError('Failed to render dashboard: ' + renderError.message);
                return;
            }

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
        flatpickrInstance = flatpickr('#datePicker', {
            dateFormat: 'Y-m-d',
            maxDate: dataRange.max_date,
            minDate: dataRange.min_date,
            defaultDate: currentDate || dataRange.max_date,
            onChange: function(selectedDates, dateStr) {
                if (dateStr && dateStr !== currentDate) {
                    currentDate = dateStr;
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
            compareMode = e.target.value;
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

    currentDate = date.toISOString().split('T')[0];
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

    currentDate = date.toISOString().split('T')[0];
    if (flatpickrInstance) {
        flatpickrInstance.setDate(currentDate);
    }
    loadDashboardData();
}

/**
 * Navigate to this week (latest data)
 */
function navigateThisWeek() {
    currentDate = null;
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
    const week = dashboardData.week;

    // Update period display
    document.getElementById('periodLabel').textContent = week.label;
    document.getElementById('dateRange').textContent = week.date_range;
    document.getElementById('comparisonDisplay').textContent = '';

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

    const dateRange = document.createElement('p');
    dateRange.className = 'text-amber-700 mb-4';
    dateRange.textContent = week.date_range;
    emptyCard.appendChild(dateRange);

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
}

/**
 * Render complete dashboard
 */
function renderDashboard() {
    if (!dashboardData) return;

    // Debug: Log has_data value
    console.log('renderDashboard called, has_data:', dashboardData.has_data, 'type:', typeof dashboardData.has_data);

    // Check if this week has data
    if (!dashboardData.has_data || dashboardData.has_data === false) {
        console.log('No data for this week, rendering empty state');
        renderEmptyState();
        return;
    }

    renderPeriodDisplay();
    renderKeyMetrics();
    renderBusinessUnits();
    renderPaperCards();
    renderTrendChart();
    renderDeliveryChart();
}

/**
 * Render period display
 */
function renderPeriodDisplay() {
    const week = dashboardData.week;

    document.getElementById('periodLabel').textContent = week.label;
    document.getElementById('dateRange').textContent = week.date_range;

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

    trendChart = new Chart(ctx, {
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

    deliveryChart = new Chart(ctx, {
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
 */
function renderBusinessUnits() {
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

        // Get comparison data for this business unit
        const comparison = comparisons[unitName];
        let comparisonBadge = '';
        if (comparison) {
            // Use the appropriate comparison based on current mode
            const compData = compareMode === 'yoy' ? comparison.yoy : comparison.previous_week;
            if (compData && compData.change !== undefined) {
                const change = compData.change;
                const changePercent = compData.change_percent;
                comparisonBadge = renderComparisonBadge(change, changePercent, 'vs comparison');
            }
        }

        html += `
            <div class="paper-card bg-white rounded-xl shadow-sm p-6 border-l-4" style="border-left-color: ${config.color}" onclick="openDetailPanel('${unitName}', '${dashboardData.current.snapshot_date}')">
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
                        ${comparisonBadge ? `<div class="mt-1">${comparisonBadge}</div>` : ''}
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
                            <div class="mt-3 text-center">
                                <div class="text-sm text-gray-600">🏖️ On Vacation</div>
                                <div class="text-lg font-semibold text-gray-900">${formatNumber(data.on_vacation)} <span class="text-sm text-gray-500">(${vacPercent}%)</span></div>
                            </div>
                        </div>

                        <!-- Legend -->
                        <div class="flex-1 ml-6 space-y-2">
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center space-x-2">
                                    <span class="inline-block w-3 h-3 rounded-full bg-blue-500"></span>
                                    <span class="text-gray-600">Mail</span>
                                </div>
                                <span class="font-medium text-gray-900">${formatNumber(data.mail)} <span class="text-xs text-gray-500">(${(data.mail/data.total*100).toFixed(1)}%)</span></span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center space-x-2">
                                    <span class="inline-block w-3 h-3 rounded-full bg-green-500"></span>
                                    <span class="text-gray-600">Digital</span>
                                </div>
                                <span class="font-medium text-gray-900">${formatNumber(data.digital)} <span class="text-xs text-gray-500">(${(data.digital/data.total*100).toFixed(1)}%)</span></span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center space-x-2">
                                    <span class="inline-block w-3 h-3 rounded-full bg-amber-500"></span>
                                    <span class="text-gray-600">Carrier</span>
                                </div>
                                <span class="font-medium text-gray-900">${formatNumber(data.carrier)} <span class="text-xs text-gray-500">(${(data.carrier/data.total*100).toFixed(1)}%)</span></span>
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

        businessUnitCharts[unitName] = new Chart(ctx, {
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
                    legend: {
                        display: false
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
        const info = PAPER_INFO[code];
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
