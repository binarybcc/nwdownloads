/**
 * Churn Dashboard - Main JavaScript Module
 * Handles data loading, rendering, and component integration for churn tracking dashboard
 */

// Extend CircDashboard namespace
window.CircDashboard = window.CircDashboard || {};

// Churn-specific state
CircDashboard.churnState = {
    // Current selection
    timeRange: '4weeks',
    endDate: null,           // null = today
    startDate: null,         // Calculated from timeRange

    // Loaded data
    overview: null,
    byType: {},
    byPublication: {},
    trendData: [],

    // Chart instances
    charts: {
        renewalRateTrend: null,
        renewalsVsExpirations: null
    }
};

// Heat map color thresholds
const RENEWAL_RATE_THRESHOLDS = {
    EXCELLENT: 85,   // Green
    GOOD: 70,        // Yellow
    WARNING: 60,     // Orange
    CRITICAL: 0      // Red
};

/**
 * Show loading overlay
 */
function showLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.remove('hidden');
    }
}

/**
 * Hide loading overlay
 */
function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.add('hidden');
    }
}

/**
 * Show error message
 */
function showErrorMessage(title, message) {
    alert(`${title}\n\n${message}\n\nPlease try refreshing the page or contact support if the issue persists.`);
}

/**
 * Refresh dashboard data (called from refresh button)
 */
async function refreshData() {
    console.log('🔄 Refreshing churn dashboard data...');

    showLoading();
    try {
        await Promise.all([
            loadOverview(),
            loadBySubscriptionType(),
            loadByPublication(),
            loadTrendData()
        ]);
        console.log('✅ Dashboard refreshed');
    } catch (error) {
        console.error('❌ Error refreshing dashboard:', error);
        showErrorMessage('Refresh Failed', error.message);
    } finally {
        hideLoading();
    }
}

/**
 * Initialize dashboard on page load
 */
async function initChurnDashboard() {
    console.log('🔄 Initializing churn dashboard...');

    showLoading();
    try {
        // Calculate date range
        const endDate = new Date();
        const startDate = calculateStartDate(endDate, '4weeks');

        // Update state
        CircDashboard.churnState.endDate = endDate;
        CircDashboard.churnState.startDate = startDate;
        CircDashboard.churnState.timeRange = '4weeks';

        // Update date range display
        updateDateRangeDisplay();
        updatePeriodDisplay();
        updateNavigationButtons();

        // Load all data in parallel
        await Promise.all([
            loadOverview(),
            loadBySubscriptionType(),
            loadByPublication(),
            loadTrendData()
        ]);

        console.log('✅ Churn dashboard loaded successfully');

    } catch (error) {
        console.error('❌ Error initializing churn dashboard:', error);
        showErrorMessage('Dashboard Load Failed', error.message);
    } finally {
        hideLoading();
    }
}

/**
 * Calculate start date based on end date and time range
 */
function calculateStartDate(endDate, timeRange) {
    const date = new Date(endDate);

    if (timeRange === '4weeks') {
        date.setDate(date.getDate() - 28);
    } else if (timeRange === '12weeks') {
        date.setDate(date.getDate() - 84);
    }

    return date;
}

/**
 * Format date as YYYY-MM-DD
 */
function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

/**
 * Format date for display (e.g., "Dec 15, 2025")
 */
function formatDisplayDate(date) {
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
}

/**
 * Update date range display
 */
function updateDateRangeDisplay() {
    const startStr = formatDisplayDate(CircDashboard.churnState.startDate);
    const endStr = formatDisplayDate(CircDashboard.churnState.endDate);
    document.getElementById('churnDateRange').textContent = `${startStr} - ${endStr}`;
}

/**
 * Load overview metrics
 */
async function loadOverview() {
    try {
        const params = new URLSearchParams({
            action: 'get_churn_overview',
            time_range: CircDashboard.churnState.timeRange,
            end_date: formatDate(CircDashboard.churnState.endDate)
        });

        const response = await fetch(`api.php?${params}`);
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Failed to load overview data');
        }

        CircDashboard.churnState.overview = result.data;
        renderOverviewCards(result.data);

    } catch (error) {
        console.error('Error loading overview:', error);
        showError('overview', error.message);
    }
}

/**
 * Render overview metric cards
 */
function renderOverviewCards(data) {
    // Card 1: Overall Renewal Rate
    document.getElementById('overallRenewalRate').textContent =
        data.renewal_rate ? `${data.renewal_rate.toFixed(1)}%` : '--';

    // Comparison badge
    const comparison = data.comparison || {};
    const changePercent = comparison.change_percent || 0;
    const comparisonHtml = changePercent !== 0
        ? `<span class="comparison-badge ${changePercent > 0 ? 'positive' : 'negative'}">
             ${changePercent > 0 ? '↑' : '↓'} ${Math.abs(changePercent).toFixed(1)}%
           </span> vs previous period`
        : '<span class="text-gray-500">No change</span>';

    document.getElementById('overallRenewalComparison').innerHTML = comparisonHtml;

    // Card 2: Total Renewals
    document.getElementById('totalRenewals').textContent =
        (data.total_renewed || 0).toLocaleString();

    const renewalPct = data.total_expiring ?
        (data.total_renewed / data.total_expiring * 100).toFixed(1) : 0;
    document.getElementById('renewalPercent').textContent = `${renewalPct}%`;

    // Card 3: Total Expirations
    document.getElementById('totalExpirations').textContent =
        (data.total_stopped || 0).toLocaleString();

    const expirationPct = data.total_expiring ?
        (data.total_stopped / data.total_expiring * 100).toFixed(1) : 0;
    document.getElementById('expirationPercent').textContent = `${expirationPct}%`;

    // Card 4: Net Change
    const netChange = (data.total_renewed || 0) - (data.total_stopped || 0);
    const netChangeEl = document.getElementById('netChange');
    netChangeEl.textContent = netChange >= 0 ?
        `+${netChange.toLocaleString()}` : netChange.toLocaleString();

    // Color code net change
    netChangeEl.className = netChange >= 0 ?
        'text-3xl font-bold text-green-600' : 'text-3xl font-bold text-red-600';
}

/**
 * Load subscription type breakdown
 */
async function loadBySubscriptionType() {
    try {
        const params = new URLSearchParams({
            action: 'get_churn_by_subscription_type',
            time_range: CircDashboard.churnState.timeRange,
            end_date: formatDate(CircDashboard.churnState.endDate)
        });

        const response = await fetch(`api.php?${params}`);
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Failed to load subscription type data');
        }

        CircDashboard.churnState.byType = result.data;
        renderSubscriptionTypeCards(result.data);

    } catch (error) {
        console.error('Error loading subscription type data:', error);
        showError('subscription-type', error.message);
    }
}

/**
 * Render subscription type cards
 */
function renderSubscriptionTypeCards(data) {
    const types = ['REGULAR', 'MONTHLY', 'COMPLIMENTARY'];

    types.forEach(type => {
        const typeData = data[type] || {};

        // Renewal rate
        const renewalRate = typeData.renewal_rate || 0;
        document.getElementById(`renewalRate-${type}`).textContent = `${renewalRate.toFixed(1)}%`;

        // Renewed count
        document.getElementById(`renewed-${type}`).textContent =
            (typeData.renewed || 0).toLocaleString();

        // Stopped count
        document.getElementById(`stopped-${type}`).textContent =
            (typeData.stopped || 0).toLocaleString();

        // Expiring count
        document.getElementById(`expiring-${type}`).textContent =
            (typeData.expiring || 0).toLocaleString();

        // Apply heat map color
        applyHeatMapColor(`typeCard-${type}`, renewalRate);
    });
}

/**
 * Load publication breakdown
 */
async function loadByPublication() {
    try {
        const params = new URLSearchParams({
            action: 'get_churn_by_publication',
            time_range: CircDashboard.churnState.timeRange,
            end_date: formatDate(CircDashboard.churnState.endDate)
        });

        const response = await fetch(`api.php?${params}`);
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Failed to load publication data');
        }

        CircDashboard.churnState.byPublication = result.data;
        renderPublicationCards(result.data);

    } catch (error) {
        console.error('Error loading publication data:', error);
        showError('publication', error.message);
    }
}

/**
 * Render publication cards
 */
function renderPublicationCards(data) {
    const publications = ['TJ', 'TA', 'TR', 'LJ', 'WRN'];

    publications.forEach(pub => {
        const pubData = data[pub] || {};

        // Renewal rate
        const renewalRate = pubData.renewal_rate || 0;
        document.getElementById(`renewalRate-${pub}`).textContent = `${renewalRate.toFixed(1)}%`;

        // Renewed count
        document.getElementById(`renewed-${pub}`).textContent =
            (pubData.renewed || 0).toLocaleString();

        // Apply heat map color
        applyHeatMapColor(`pubCard-${pub}`, renewalRate);
    });
}

/**
 * Apply heat map color to card based on renewal rate
 */
function applyHeatMapColor(cardId, renewalRate) {
    const card = document.getElementById(cardId);
    if (!card) return;

    // Remove all color classes first
    card.classList.remove('card-red', 'card-orange', 'card-yellow', 'card-green');

    // Apply color based on threshold
    if (renewalRate >= RENEWAL_RATE_THRESHOLDS.EXCELLENT) {
        card.classList.add('card-green');      // Excellent: ≥85%
    } else if (renewalRate >= RENEWAL_RATE_THRESHOLDS.GOOD) {
        card.classList.add('card-yellow');    // Good: 70-84%
    } else if (renewalRate >= RENEWAL_RATE_THRESHOLDS.WARNING) {
        card.classList.add('card-orange');    // Warning: 60-69%
    } else {
        card.classList.add('card-red');        // Critical: <60%
    }
}

/**
 * Load trend data for charts
 */
async function loadTrendData() {
    try {
        const params = new URLSearchParams({
            action: 'get_churn_trend',
            time_range: CircDashboard.churnState.timeRange,
            end_date: formatDate(CircDashboard.churnState.endDate),
            metric: 'renewal_rate'
        });

        const response = await fetch(`api.php?${params}`);
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Failed to load trend data');
        }

        CircDashboard.churnState.trendData = result.data.data_points || [];
        renderTrendCharts(result.data.data_points || []);

    } catch (error) {
        console.error('Error loading trend data:', error);
        showError('trends', error.message);
    }
}

/**
 * Render trend charts using Chart.js
 */
function renderTrendCharts(dataPoints) {
    // Extract labels and values
    const labels = dataPoints.map(d => {
        const date = new Date(d.snapshot_date);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    });

    const renewalRates = dataPoints.map(d => d.value);

    // Chart 1: Renewal Rate Trend (Line Chart)
    const ctx1 = document.getElementById('renewalRateTrendChart');
    if (ctx1) {
        // Destroy existing chart if it exists
        if (CircDashboard.churnState.charts.renewalRateTrend) {
            CircDashboard.churnState.charts.renewalRateTrend.destroy();
        }

        CircDashboard.churnState.charts.renewalRateTrend = new Chart(ctx1, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Renewal Rate (%)',
                    data: renewalRates,
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => `${context.parsed.y.toFixed(1)}%`
                        }
                    }
                },
                scales: {
                    y: {
                        min: 0,
                        max: 100,
                        ticks: {
                            callback: (value) => value + '%'
                        }
                    }
                }
            }
        });
    }

    // Chart 2: Renewals vs Expirations (Stacked Bar Chart)
    const ctx2 = document.getElementById('renewalsVsExpirationsChart');
    if (ctx2) {
        // Destroy existing chart if it exists
        if (CircDashboard.churnState.charts.renewalsVsExpirations) {
            CircDashboard.churnState.charts.renewalsVsExpirations.destroy();
        }

        // Extract renewed and stopped counts from data points
        const renewedCounts = dataPoints.map(d => d.renewed || 0);
        const stoppedCounts = dataPoints.map(d => d.stopped || 0);

        CircDashboard.churnState.charts.renewalsVsExpirations = new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Renewed',
                        data: renewedCounts,
                        backgroundColor: '#10B981'
                    },
                    {
                        label: 'Expired',
                        data: stoppedCounts,
                        backgroundColor: '#EF4444'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    x: { stacked: true },
                    y: { stacked: true }
                }
            }
        });
    }
}

/**
 * Show error message in a section
 */
function showError(section, message) {
    console.error(`Error in ${section}:`, message);
    // For now, just log - can add UI error display later
}

/**
 * Handle time range change
 */
async function handleTimeRangeChange(newTimeRange) {
    console.log(`🔄 Changing time range to: ${newTimeRange}`);

    // Update state
    CircDashboard.churnState.timeRange = newTimeRange;
    CircDashboard.churnState.startDate = calculateStartDate(
        CircDashboard.churnState.endDate,
        newTimeRange
    );

    // Update display
    updateDateRangeDisplay();
    updatePeriodDisplay();
    updateNavigationButtons();

    // Reload all data
    await initChurnDashboard();
}

/**
 * Navigate to previous week
 */
async function navigatePreviousWeek() {
    const date = new Date(CircDashboard.churnState.endDate);
    date.setDate(date.getDate() - 7);

    // Don't go before 2024-09-01 (earliest churn data)
    if (date < new Date('2024-09-01')) {
        return;
    }

    CircDashboard.churnState.endDate = date;
    CircDashboard.churnState.startDate = calculateStartDate(
        date,
        CircDashboard.churnState.timeRange
    );

    updateDateRangeDisplay();
    updatePeriodDisplay();
    updateNavigationButtons();

    await Promise.all([
        loadOverview(),
        loadBySubscriptionType(),
        loadByPublication(),
        loadTrendData()
    ]);
}

/**
 * Navigate to next week
 */
async function navigateNextWeek() {
    const date = new Date(CircDashboard.churnState.endDate);
    date.setDate(date.getDate() + 7);

    const today = new Date();
    // Don't go beyond today
    if (date > today) {
        return;
    }

    CircDashboard.churnState.endDate = date;
    CircDashboard.churnState.startDate = calculateStartDate(
        date,
        CircDashboard.churnState.timeRange
    );

    updateDateRangeDisplay();
    updatePeriodDisplay();
    updateNavigationButtons();

    await Promise.all([
        loadOverview(),
        loadBySubscriptionType(),
        loadByPublication(),
        loadTrendData()
    ]);
}

/**
 * Navigate to this week (current week)
 */
async function navigateThisWeek() {
    CircDashboard.churnState.endDate = new Date();
    CircDashboard.churnState.startDate = calculateStartDate(
        new Date(),
        CircDashboard.churnState.timeRange
    );

    updateDateRangeDisplay();
    updatePeriodDisplay();
    updateNavigationButtons();

    await Promise.all([
        loadOverview(),
        loadBySubscriptionType(),
        loadByPublication(),
        loadTrendData()
    ]);
}

/**
 * Update period display showing the week being viewed
 */
function updatePeriodDisplay() {
    const endDate = CircDashboard.churnState.endDate;
    const startOfWeek = new Date(endDate);
    startOfWeek.setDate(endDate.getDate() - endDate.getDay()); // Sunday
    const endOfWeek = new Date(startOfWeek);
    endOfWeek.setDate(startOfWeek.getDate() + 6); // Saturday

    const weekStr = `Week of ${formatDisplayDate(startOfWeek)} - ${formatDisplayDate(endOfWeek)}`;
    document.getElementById('churnPeriodDisplay').textContent = weekStr;
}

/**
 * Update navigation button states (enable/disable)
 */
function updateNavigationButtons() {
    const prevBtn = document.getElementById('churnPrevWeek');
    const nextBtn = document.getElementById('churnNextWeek');
    const thisWeekBtn = document.getElementById('churnThisWeek');

    if (!prevBtn || !nextBtn || !thisWeekBtn) return;

    const endDate = CircDashboard.churnState.endDate;
    const today = new Date();
    const minDate = new Date('2024-09-01');

    // Check if we can go back
    const prevWeek = new Date(endDate);
    prevWeek.setDate(prevWeek.getDate() - 7);
    prevBtn.disabled = prevWeek < minDate;

    // Check if we can go forward
    const nextWeek = new Date(endDate);
    nextWeek.setDate(nextWeek.getDate() + 7);
    nextBtn.disabled = nextWeek > today;

    // Check if we're already on this week
    const isThisWeek = Math.abs(today - endDate) < 7 * 24 * 60 * 60 * 1000; // Within 7 days
    if (isThisWeek) {
        thisWeekBtn.classList.add('current-week');
    } else {
        thisWeekBtn.classList.remove('current-week');
    }
}

/**
 * Show renewal events in SubscriberTablePanel
 */
async function showRenewalEvents(status, paperCode = null, subscriptionType = null) {
    try {
        const params = new URLSearchParams({
            action: 'get_renewal_events',
            status: status,
            start_date: formatDate(CircDashboard.churnState.startDate),
            end_date: formatDate(CircDashboard.churnState.endDate)
        });

        if (paperCode) {
            params.append('paper_code', paperCode);
        }

        if (subscriptionType) {
            params.append('subscription_type', subscriptionType);
        }

        const response = await fetch(`api.php?${params}`);
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Failed to load renewal events');
        }

        const events = result.data.events || [];
        const count = result.data.count || 0;

        // Format title
        let title = status === 'RENEW' ? 'Renewal Events' : 'Expiration Events';
        if (paperCode) title += ` - ${paperCode}`;
        if (subscriptionType) title += ` (${subscriptionType})`;

        // Format subtitle
        const timeRangeText = CircDashboard.churnState.timeRange === '4weeks' ? '4 weeks' : '12 weeks';
        const subtitle = `${count.toLocaleString()} events • Last ${timeRangeText}`;

        // Show in SubscriberTablePanel
        if (typeof CircDashboard.subscriberTablePanel !== 'undefined') {
            CircDashboard.subscriberTablePanel.show({
                title: title,
                subtitle: subtitle,
                data: {
                    subscribers: events.map(e => ({
                        sub_num: e.sub_num,
                        paper_code: e.paper_code,
                        event_date: e.event_date,
                        status: e.status,
                        subscription_type: e.subscription_type
                    })),
                    count: count
                }
            });
        }

    } catch (error) {
        console.error('Error loading renewal events:', error);
        alert('Failed to load renewal events: ' + error.message);
    }
}

/**
 * Show churn trend in TrendSlider
 */
function showChurnTrend(metricType, metricValue) {
    console.log('showChurnTrend:', metricType, metricValue);

    // Will integrate with TrendSlider if available
    if (typeof CircDashboard.trendSlider !== 'undefined') {
        // This would open the trend slider with the appropriate filters
        // For now, just log - full TrendSlider integration would require
        // adapting it to work with churn data
        console.log('TrendSlider integration placeholder');
    }
}

/**
 * Generic function to show details (called from onclick handlers)
 */
function showChurnDetails(type) {
    console.log('showChurnDetails:', type);
    // This is called from card onclick handlers
    // Context menu provides more specific options
}

// Event listeners
document.addEventListener('DOMContentLoaded', () => {
    // Initialize dashboard
    initChurnDashboard();

    // Time range selector
    const timeRangeSelect = document.getElementById('churnTimeRange');
    if (timeRangeSelect) {
        timeRangeSelect.addEventListener('change', (e) => {
            handleTimeRangeChange(e.target.value);
        });
    }

    // Week navigation buttons
    const prevWeekBtn = document.getElementById('churnPrevWeek');
    if (prevWeekBtn) {
        prevWeekBtn.addEventListener('click', navigatePreviousWeek);
    }

    const nextWeekBtn = document.getElementById('churnNextWeek');
    if (nextWeekBtn) {
        nextWeekBtn.addEventListener('click', navigateNextWeek);
    }

    const thisWeekBtn = document.getElementById('churnThisWeek');
    if (thisWeekBtn) {
        thisWeekBtn.addEventListener('click', navigateThisWeek);
    }

    // Initialize context menus for all metric cards
    initializeContextMenus();
});

/**
 * Initialize context menus for all metric cards
 */
function initializeContextMenus() {
    const cards = document.querySelectorAll('.context-menu-section');

    cards.forEach(card => {
        card.addEventListener('contextmenu', (e) => {
            e.preventDefault();

            const metricType = card.dataset.metricType;
            const metricValue = card.dataset.metricValue;

            // Build context menu items based on card type
            const menuItems = [];

            // View renewal events
            menuItems.push({
                label: '✅ View Renewals',
                action: () => {
                    if (metricType === 'subscription_type') {
                        showRenewalEvents('RENEW', null, metricValue);
                    } else if (metricType === 'publication') {
                        showRenewalEvents('RENEW', metricValue, null);
                    } else {
                        showRenewalEvents('RENEW');
                    }
                }
            });

            // View expiration events
            menuItems.push({
                label: '❌ View Expirations',
                action: () => {
                    if (metricType === 'subscription_type') {
                        showRenewalEvents('EXPIRE', null, metricValue);
                    } else if (metricType === 'publication') {
                        showRenewalEvents('EXPIRE', metricValue, null);
                    } else {
                        showRenewalEvents('EXPIRE');
                    }
                }
            });

            // Show menu (using existing context menu component if available)
            if (typeof CircDashboard.contextMenu !== 'undefined') {
                CircDashboard.contextMenu.show(e.clientX, e.clientY, menuItems);
            } else {
                // Fallback: create simple custom menu
                showSimpleContextMenu(e.clientX, e.clientY, menuItems);
            }
        });
    });
}

/**
 * Simple context menu fallback (if main context menu component not available)
 */
function showSimpleContextMenu(x, y, items) {
    // Remove any existing menu
    const existing = document.getElementById('simple-context-menu');
    if (existing) {
        existing.remove();
    }

    // Create menu
    const menu = document.createElement('div');
    menu.id = 'simple-context-menu';
    menu.style.cssText = `
        position: fixed;
        left: ${x}px;
        top: ${y}px;
        background: white;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        min-width: 200px;
        padding: 4px 0;
    `;

    items.forEach(item => {
        const menuItem = document.createElement('div');
        menuItem.textContent = item.label;
        menuItem.style.cssText = `
            padding: 8px 16px;
            cursor: pointer;
            font-size: 14px;
        `;
        menuItem.addEventListener('mouseenter', () => {
            menuItem.style.backgroundColor = '#f0f0f0';
        });
        menuItem.addEventListener('mouseleave', () => {
            menuItem.style.backgroundColor = 'white';
        });
        menuItem.addEventListener('click', () => {
            item.action();
            menu.remove();
        });
        menu.appendChild(menuItem);
    });

    document.body.appendChild(menu);

    // Remove menu on click outside
    const removeMenu = (e) => {
        if (!menu.contains(e.target)) {
            menu.remove();
            document.removeEventListener('click', removeMenu);
        }
    };
    setTimeout(() => document.addEventListener('click', removeMenu), 10);
}
