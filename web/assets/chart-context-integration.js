/**
 * Chart Context Menu Integration
 * Adds interactive context menus to existing detail panel charts
 * Integrates with ContextMenu, SubscriberTablePanel, and ChartTransitionManager
 * Date: 2025-12-05
 */

/**
 * LOAD ORDER: 11 of 11 - Must load last
 *
 * DEPENDENCIES:
 * - All previous files must be loaded
 * - context-menu.js, subscriber-table-panel.js, chart-transition-manager.js
 *
 * PROVIDES:
 * - Integrates all chart interaction features
 */

// Global instances
let subscriberPanel = null;
let expirationTransitionManager = null;
let rateTransitionManager = null;
let subscriptionLengthTransitionManager = null;

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

    // Initialize transition managers for each chart
    if (!expirationTransitionManager) {
        const expirationContainer = document.querySelector('.detail-chart-container:has(#expirationChart)');
        if (expirationContainer) {
            expirationTransitionManager = new ChartTransitionManager(expirationContainer.id || 'expirationChartContainer');
        }
    }

    // Add context menus to charts
    addExpirationChartContextMenu();
    addRateDistributionContextMenu();
    addSubscriptionLengthContextMenu();

    console.log('✅ Chart context menus initialized');
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

        // Show context menu
        showChartContextMenu(e.clientX, e.clientY, {
            chartType: 'expiration',
            metric: label,
            count: count,
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

        // Show context menu
        showChartContextMenu(e.clientX, e.clientY, {
            chartType: 'rate',
            metric: label,
            count: count,
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

        // Show context menu
        showChartContextMenu(e.clientX, e.clientY, {
            chartType: 'subscription_length',
            metric: label,
            count: count,
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
 * Show historical trend for metric
 */
async function showHistoricalTrend(context) {
    const { chartType, metric, businessUnit, snapshotDate } = context;

    try {
        // Show loading indicator
        console.log('Loading trend data...');

        // Fetch trend data from API
        const timeRange = '12weeks'; // Default to 12 weeks
        const response = await fetch(
            `api.php?action=get_trend&business_unit=${encodeURIComponent(businessUnit)}&metric_type=${chartType}&metric_value=${encodeURIComponent(metric)}&time_range=${timeRange}&end_date=${snapshotDate}`
        );

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Failed to load trend data');
        }

        // Get appropriate transition manager
        let transitionManager = null;
        if (chartType === 'expiration') {
            transitionManager = expirationTransitionManager;
        } else if (chartType === 'rate') {
            transitionManager = rateTransitionManager;
        } else if (chartType === 'subscription_length') {
            transitionManager = subscriptionLengthTransitionManager;
        }

        if (!transitionManager) {
            console.error('Transition manager not found for chart type:', chartType);
            return;
        }

        // Show trend view
        await transitionManager.showTrend({
            chartType: chartType,
            metric: metric,
            timeRange: timeRange,
            data: result.data.data_points,
            businessUnit: businessUnit,
            snapshotDate: snapshotDate,
            onBack: () => {
                console.log('Returned to chart view');
            }
        });

    } catch (error) {
        console.error('Error showing trend:', error);
        alert('Failed to load trend data. Please try again.');
    }
}

/**
 * Show subscriber list for metric
 */
async function showSubscriberList(context) {
    const { chartType, metric, count, businessUnit, snapshotDate } = context;

    try {
        // Show loading state
        console.log('Loading subscriber data...');

        // Fetch subscriber data from API
        const response = await fetch(
            `api.php?action=get_subscribers&business_unit=${encodeURIComponent(businessUnit)}&metric_type=${chartType}&metric_value=${encodeURIComponent(metric)}&snapshot_date=${snapshotDate}`
        );

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Failed to load subscriber data');
        }

        // Show subscriber panel
        subscriberPanel.show({
            title: `${metric} Subscribers - ${businessUnit}`,
            subtitle: `${count.toLocaleString()} subscribers • Snapshot: ${snapshotDate}`,
            data: result.data
        });

    } catch (error) {
        console.error('Error showing subscribers:', error);
        alert('Failed to load subscriber data. Please try again.');
    }
}

/**
 * Cleanup context menus (call when detail panel closes)
 */
function cleanupChartContextMenus() {
    if (subscriberPanel) {
        subscriberPanel.close();
    }

    if (expirationTransitionManager) {
        expirationTransitionManager.reset();
    }

    if (rateTransitionManager) {
        rateTransitionManager.reset();
    }

    if (subscriptionLengthTransitionManager) {
        subscriptionLengthTransitionManager.reset();
    }
}

// Export functions
window.initializeChartContextMenus = initializeChartContextMenus;
window.cleanupChartContextMenus = cleanupChartContextMenus;

console.log('Chart context menu integration loaded');
