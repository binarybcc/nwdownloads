/**
 * Chart Context Menu Integration
 * Adds interactive context menus to existing detail panel charts
 * Integrates with ContextMenu, SubscriberTablePanel, and TrendSlider
 * Date: 2025-12-07 (Updated to use TrendSlider instead of ChartTransitionManager)
 */

/**
 * LOAD ORDER: 11 of 11 - Must load last
 *
 * DEPENDENCIES:
 * - All previous files must be loaded
 * - context-menu.js, subscriber-table-panel.js, trend-slider.js
 *
 * PROVIDES:
 * - Integrates all chart interaction features
 * - Captures bar colors for visual continuity in trend slider
 */

// Global instances
let subscriberPanel = null;

/**
 * Initialize context menus for all charts
 * Call this after detail panel data is loaded
 */
function initializeChartContextMenus() {
    console.log('Initializing chart context menus...');

    // Initialize subscriber panel (singleton)
    if (!subscriberPanel) {
        subscriberPanel = new SubscriberTablePanel({
            onClose: () => {
                console.log('Subscriber panel closed');
            }
        });
    }

    // Add context menus to charts
    addExpirationChartContextMenu();
    addRateDistributionContextMenu();
    addSubscriptionLengthContextMenu();

    console.log('✅ Chart context menus initialized (using TrendSlider)');
}

/**
 * Add context menu to Expiration Chart
 */
function addExpirationChartContextMenu() {
    const canvas = document.getElementById('expirationChart');
    if (!canvas) {
        console.warn('Expiration chart not found');
        return;
    }

    // Add right-click handler
    canvas.addEventListener('contextmenu', (e) => {
        e.preventDefault();

        // Get clicked element from Chart.js
        const chart = Chart.getChart(canvas);
        if (!chart) return;

        const elements = chart.getElementsAtEventForMode(e, 'nearest', { intersect: true }, false);
        if (elements.length === 0) return;

        const index = elements[0].index;
        const label = chart.data.labels[index];
        const count = chart.data.datasets[0].data[index];

        // Get bar color for visual continuity in trend slider
        const backgroundColor = chart.data.datasets[0].backgroundColor;
        const color = Array.isArray(backgroundColor) ? backgroundColor[index] : backgroundColor;

        // Show context menu
        showChartContextMenu(e.clientX, e.clientY, {
            chartType: 'expiration',
            metric: label,
            count: count,
            color: color,
            businessUnit: currentBusinessUnit,
            snapshotDate: currentSnapshotDate
        });
    });

    // Add hover indicator (visual cue that right-click is available)
    canvas.style.cursor = 'context-menu';
    canvas.title = 'Right-click on bars for options';
}

/**
 * Add context menu to Rate Distribution Chart
 */
function addRateDistributionContextMenu() {
    const canvas = document.getElementById('rateDistributionChart');
    if (!canvas) {
        console.warn('Rate distribution chart not found');
        return;
    }

    // Add right-click handler
    canvas.addEventListener('contextmenu', (e) => {
        e.preventDefault();

        // Get clicked element from Chart.js
        const chart = Chart.getChart(canvas);
        if (!chart) return;

        const elements = chart.getElementsAtEventForMode(e, 'nearest', { intersect: true }, false);
        if (elements.length === 0) return;

        const index = elements[0].index;
        const label = chart.data.labels[index];
        const count = chart.data.datasets[0].data[index];

        // Get bar color for visual continuity in trend slider
        const backgroundColor = chart.data.datasets[0].backgroundColor;
        const color = Array.isArray(backgroundColor) ? backgroundColor[index] : backgroundColor;

        // Show context menu
        showChartContextMenu(e.clientX, e.clientY, {
            chartType: 'rate',
            metric: label,
            count: count,
            color: color,
            businessUnit: currentBusinessUnit,
            snapshotDate: currentSnapshotDate
        });
    });

    canvas.style.cursor = 'context-menu';
    canvas.title = 'Right-click on bars for options';
}

/**
 * Add context menu to Subscription Length Chart
 */
function addSubscriptionLengthContextMenu() {
    const canvas = document.getElementById('subscriptionLengthChart');
    if (!canvas) {
        console.warn('Subscription length chart not found');
        return;
    }

    // Add right-click handler
    canvas.addEventListener('contextmenu', (e) => {
        e.preventDefault();

        // Get clicked element from Chart.js
        const chart = Chart.getChart(canvas);
        if (!chart) return;

        const elements = chart.getElementsAtEventForMode(e, 'nearest', { intersect: true }, false);
        if (elements.length === 0) return;

        const index = elements[0].index;
        const label = chart.data.labels[index];
        const count = chart.data.datasets[0].data[index];

        // Get bar color for visual continuity in trend slider
        const backgroundColor = chart.data.datasets[0].backgroundColor;
        const color = Array.isArray(backgroundColor) ? backgroundColor[index] : backgroundColor;

        // Show context menu
        showChartContextMenu(e.clientX, e.clientY, {
            chartType: 'subscription_length',
            metric: label,
            count: count,
            color: color,
            businessUnit: currentBusinessUnit,
            snapshotDate: currentSnapshotDate
        });
    });

    canvas.style.cursor = 'context-menu';
    canvas.title = 'Right-click on bars for options';
}

/**
 * Show context menu for chart interaction
 */
function showChartContextMenu(x, y, context) {
    const menu = createChartContextMenu({
        onSelect: (actionId, ctx) => {
            handleChartMenuAction(actionId, ctx);
        }
    });

    menu.show(x, y, context);
}

/**
 * Handle context menu action selection
 */
async function handleChartMenuAction(actionId, context) {
    console.log('Chart action:', actionId, context);

    const { chartType, metric, count, businessUnit, snapshotDate } = context;

    if (actionId === 'trend') {
        // Show historical trend
        await showHistoricalTrend(context);

    } else if (actionId === 'subscribers') {
        // Show subscriber list
        await showSubscriberList(context);

    } else {
        console.warn('Unknown action:', actionId);
    }
}

/**
 * Show historical trend for metric in separate slider
 * Uses TrendSlider with color continuity from clicked bar
 */
async function showHistoricalTrend(context) {
    const { chartType, metric, businessUnit, snapshotDate, count, color } = context;

    console.log('Chart action: trend', { chartType, metric, count, businessUnit, color });

    // Open trend slider immediately with context
    // Slider will handle loading state and API calls internally
    window.trendSlider.open({
        chartType: chartType,
        metric: metric,
        color: color || '#3B82F6', // Fallback to blue if no color provided
        businessUnit: businessUnit,
        snapshotDate: snapshotDate,
        timeRange: '4weeks', // Default to 4 weeks
        count: count || 0
    });
}

/**
 * Show subscriber list for metric
 */
async function showSubscriberList(context) {
    const { chartType, metric, count, businessUnit, snapshotDate } = context;

    try {
        // Show loading state
        console.log('🔍 Loading subscriber data...', context);
        console.log('🔍 subscriberPanel exists?', !!subscriberPanel);

        // Handle aggregated subscription lengths (e.g., "1 Year" = ["12M (1 Year)", "1Y", etc.])
        const isAggregatedSubscriptionLength = chartType === 'subscription_length' &&
            window.subscriptionLengthOriginalLabels &&
            window.subscriptionLengthOriginalLabels[metric];

        console.log('🔍 isAggregatedSubscriptionLength:', isAggregatedSubscriptionLength);

        let allSubscribers = [];

        if (isAggregatedSubscriptionLength) {
            // Query for each original label and combine subscribers
            const originalLabels = window.subscriptionLengthOriginalLabels[metric];
            console.log('🔍 Querying aggregated labels:', originalLabels);

            for (const originalLabel of originalLabels) {
                const url = `api.php?action=get_subscribers&business_unit=${encodeURIComponent(businessUnit)}&metric_type=${chartType}&metric_value=${encodeURIComponent(originalLabel)}&snapshot_date=${snapshotDate}`;
                console.log('🔍 Fetching aggregated:', url);
                const response = await fetch(url);
                const labelResult = await response.json();
                console.log('🔍 API response for', originalLabel, ':', labelResult);

                if (labelResult.success && labelResult.data && labelResult.data.subscribers) {
                    allSubscribers = allSubscribers.concat(labelResult.data.subscribers);
                    console.log('🔍 Added subscribers, total now:', allSubscribers.length);
                }
            }
        } else {
            // Standard single-metric query
            const url = `api.php?action=get_subscribers&business_unit=${encodeURIComponent(businessUnit)}&metric_type=${chartType}&metric_value=${encodeURIComponent(metric)}&snapshot_date=${snapshotDate}`;
            console.log('🔍 Fetching (non-aggregated):', url);
            const response = await fetch(url);
            const result = await response.json();
            console.log('🔍 API response:', result);

            if (!result.success) {
                throw new Error(result.error || 'Failed to load subscriber data');
            }

            allSubscribers = result.data?.subscribers || [];
        }

        // Ensure allSubscribers is an array
        if (!Array.isArray(allSubscribers)) {
            console.log('🔍 allSubscribers was not an array, converting');
            allSubscribers = [];
        }

        console.log('🔍 Final allSubscribers count:', allSubscribers.length);
        console.log('🔍 About to call subscriberPanel.show()');

        // Show subscriber panel with full context for exports
        subscriberPanel.show({
            title: `${metric} Subscribers - ${businessUnit}`,
            subtitle: `${allSubscribers.length.toLocaleString()} subscribers • Snapshot: ${snapshotDate}`,
            data: allSubscribers,
            // Export metadata
            exportData: {
                business_unit: businessUnit,
                metric: metric,
                count: allSubscribers.length,
                snapshot_date: snapshotDate,
                subscribers: allSubscribers
            }
        });

        console.log('🔍 subscriberPanel.show() completed');

    } catch (error) {
        console.error('Error showing subscribers:', error);
        alert('Failed to load subscriber data. Please try again.');
    }
}

/**
 * Cleanup context menus (call when detail panel closes)
 */
function cleanupChartContextMenus() {
    // Close subscriber panel if open
    if (subscriberPanel) {
        subscriberPanel.close();
    }

    // Close trend slider if open
    if (window.trendSlider && window.trendSlider.isOpen) {
        window.trendSlider.close();
    }
}

// Export functions
window.initializeChartContextMenus = initializeChartContextMenus;
window.cleanupChartContextMenus = cleanupChartContextMenus;

console.log('Chart context menu integration loaded');
