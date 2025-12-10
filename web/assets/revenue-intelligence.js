/**
 * Revenue Intelligence Module
 * Handles display of expiration risk, legacy rate analysis, and ARPU metrics
 *
 * STEP 1 Implementation from Strategic Plan:
 * - Revenue Cliff Detector
 * - Legacy Rate Gap Analyzer
 * - Revenue Per Subscriber Analytics
 */

/**
 * Load and display all revenue intelligence data
 * Called after main dashboard data is loaded
 */
async function loadRevenueIntelligence() {
    try {
        const response = await fetch('api/revenue_intelligence.php');

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Failed to load revenue intelligence data');
        }

        // Populate all sections
        populateExpirationRisk(data.expiration_risk);
        populateLegacyRateAnalysis(data.legacy_rate_analysis);
        populateRevenueMetrics(data.revenue_metrics);

        console.log('Revenue Intelligence loaded successfully', data);

    } catch (error) {
        console.error('Error loading revenue intelligence:', error);
        displayRevenueIntelligenceError(error.message);
    }
}

/**
 * Populate expiration risk cards
 * Shows subscribers expiring in 0-4, 5-8, 9-12 weeks and expired
 */
function populateExpirationRisk(data) {
    const totals = data.totals;

    // Expired
    document.getElementById('expiredCount').textContent = formatNumber(totals['Expired']?.subscribers || 0);
    document.getElementById('expiredRevenue').textContent = formatCurrency(totals['Expired']?.revenue || 0);

    // 0-4 Weeks
    document.getElementById('risk04Count').textContent = formatNumber(totals['0-4 weeks']?.subscribers || 0);
    document.getElementById('risk04Revenue').textContent = formatCurrency(totals['0-4 weeks']?.revenue || 0);

    // 5-8 Weeks
    document.getElementById('risk58Count').textContent = formatNumber(totals['5-8 weeks']?.subscribers || 0);
    document.getElementById('risk58Revenue').textContent = formatCurrency(totals['5-8 weeks']?.revenue || 0);

    // 9-12 Weeks
    document.getElementById('risk912Count').textContent = formatNumber(totals['9-12 weeks']?.subscribers || 0);
    document.getElementById('risk912Revenue').textContent = formatCurrency(totals['9-12 weeks']?.revenue || 0);
}

/**
 * Populate legacy rate opportunity card
 * Shows revenue gap from subscribers on <$100/year rates
 */
function populateLegacyRateAnalysis(data) {
    const totals = data.totals;

    // Legacy rate count
    document.getElementById('legacyRateCount').textContent = formatNumber(totals.legacy_rate_subs);

    // Average legacy rate
    document.getElementById('legacyRateAvg').textContent = formatCurrency(totals.avg_legacy_rate);

    // Monthly revenue gap
    document.getElementById('legacyRevenueGap').textContent = formatCurrency(totals.monthly_revenue_gap);

    // Annual opportunity
    document.getElementById('legacyAnnualOpportunity').textContent = formatCurrency(totals.annual_opportunity);
}

/**
 * Populate revenue per subscriber metrics
 * Shows ARPU overall and by delivery type
 */
function populateRevenueMetrics(data) {
    const totals = data.totals;
    const byDelivery = data.by_delivery_type;

    // Overall ARPU
    document.getElementById('overallARPU').textContent = formatCurrency(totals.overall_arpu);

    // Overall MRR
    document.getElementById('overallMRR').textContent = formatCurrency(totals.overall_mrr);

    // By delivery type
    const arpuContainer = document.getElementById('arpuByDelivery');

    if (byDelivery && byDelivery.length > 0) {
        const deliveryTypeLabels = {
            'MAIL': '📮 Mail',
            'CARR': '🚗 Carrier',
            'INTE': '💻 Digital',
            'EMAI': '📧 Email'
        };

        const deliveryHTML = byDelivery.map(item => {
            const label = deliveryTypeLabels[item.delivery_type] || item.delivery_type;
            const arpu = parseFloat(item.arpu) || 0;

            return `
                <div class="flex justify-between items-center text-sm py-2 border-b border-gray-100">
                    <span class="text-gray-600">${label}</span>
                    <span class="font-semibold text-gray-900">${formatCurrency(arpu)}</span>
                </div>
            `;
        }).join('');

        arpuContainer.innerHTML = deliveryHTML;
    }
}

/**
 * Display error message if revenue intelligence fails to load
 */
function displayRevenueIntelligenceError(message) {
    // Display error in expiration risk section
    document.getElementById('expiredCount').textContent = 'Error';
    document.getElementById('risk04Count').textContent = 'Error';
    document.getElementById('risk58Count').textContent = 'Error';
    document.getElementById('risk912Count').textContent = 'Error';

    // Display error in legacy rate section
    document.getElementById('legacyRateCount').textContent = 'Error';

    // Display error in revenue metrics section
    document.getElementById('overallARPU').textContent = 'Error';

    console.error('Revenue Intelligence Error:', message);
}

/**
 * Format number with commas
 * @param {number} num
 * @returns {string}
 */
function formatNumber(num) {
    if (typeof num !== 'number' || isNaN(num)) {
        return '--';
    }
    return num.toLocaleString('en-US');
}

/**
 * Format currency with $ and commas
 * @param {number} amount
 * @returns {string}
 */
function formatCurrency(amount) {
    if (typeof amount !== 'number' || isNaN(amount)) {
        return '$--';
    }
    return '$' + Math.round(amount).toLocaleString('en-US');
}

/**
 * Show subscribers in a specific expiration risk bucket
 * Uses SubscriberTablePanel for consistent UI
 * @param {string} bucket - 'Expired', '0-4 weeks', '5-8 weeks', or '9-12 weeks'
 */
async function showExpirationSubscribers(bucket) {
    // Bucket display titles
    const titleMap = {
        'Expired': 'Expired Subscribers',
        '0-4 weeks': 'Subscribers Expiring in 0-4 Weeks',
        '5-8 weeks': 'Subscribers Expiring in 5-8 Weeks',
        '9-12 weeks': 'Subscribers Expiring in 9-12 Weeks'
    };

    try {
        // Fetch subscribers for this bucket
        const response = await fetch(`api/revenue_intelligence.php?action=subscribers&bucket=${encodeURIComponent(bucket)}`);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Failed to load subscribers');
        }

        // Prepare data for SubscriberTablePanel
        const panelData = {
            title: titleMap[bucket] || bucket,
            subtitle: `${data.count} subscriber${data.count !== 1 ? 's' : ''} • Snapshot: ${data.snapshot_date}`,
            data: {
                subscribers: data.subscribers,
                count: data.count,
                bucket: bucket,
                snapshotDate: data.snapshot_date,
                metricType: 'expiration'
            }
        };

        console.log('Opening SubscriberTablePanel for expiration risk:', panelData);

        // Show using SubscriberTablePanel
        const panel = new SubscriberTablePanel({ colorScheme: 'teal' });
        panel.show(panelData);

    } catch (error) {
        console.error('Error loading subscribers:', error);
        alert('Failed to load subscriber data: ' + error.message);
    }
}

// Export for use in main app
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { loadRevenueIntelligence, showExpirationSubscribers };
}
