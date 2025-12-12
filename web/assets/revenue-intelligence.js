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

/**
 * Load and display per-paper revenue intelligence
 * Shows legacy rate opportunity and ARPU metrics for each publication
 */
async function loadRevenueByPublication() {
    try {
        const response = await fetch('api/revenue_intelligence.php?action=by_paper');

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Failed to load per-paper metrics');
        }

        populateRevenueByPublication(data.papers, data.snapshot_date);

        console.log('Revenue by Publication loaded successfully', data);

    } catch (error) {
        console.error('Error loading revenue by publication:', error);
        displayRevenueByPublicationError(error.message);
    }
}

/**
 * Populate per-paper revenue intelligence cards
 * Creates a card for each publication showing legacy rate opportunity and ARPU
 */
function populateRevenueByPublication(papers, snapshotDate) {
    const container = document.getElementById('revenueByPublication');

    if (!papers || papers.length === 0) {
        container.innerHTML = '<div class="text-center py-12 text-gray-500">No publication data available</div>';
        return;
    }

    // Create card for each paper
    const cardsHTML = papers.map(paper => {
        const hasLegacyRates = paper.legacy_rate_count > 0;
        const revenueGap = hasLegacyRates ? paper.annual_revenue_gap : 0;

        return `
            <div class="bg-white rounded-xl shadow-lg p-6 border-2 border-blue-100 hover:shadow-xl transition-all cursor-pointer"
                 onclick="showPaperSubscribers('${paper.paper_code}', '${paper.paper_name}')"
                 role="region"
                 aria-label="Revenue metrics for ${paper.paper_name}">

                <!-- Paper Header -->
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">${paper.paper_code} - ${paper.paper_name}</h3>
                        <p class="text-xs text-gray-500">${formatNumber(paper.total_subscribers)} subscribers</p>
                    </div>
                    <div class="text-2xl" aria-hidden="true">📰</div>
                </div>

                <!-- Legacy Rate Opportunity -->
                <div class="mb-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold text-blue-700">Legacy Rate Opportunity</span>
                        <span class="text-xl" aria-hidden="true">🎯</span>
                    </div>
                    <div class="text-2xl font-bold text-blue-900">
                        ${formatNumber(paper.legacy_rate_count)}
                        <span class="text-sm font-normal text-blue-600">subscribers</span>
                    </div>
                    ${hasLegacyRates ? `
                        <div class="text-xs text-blue-700 mt-1">
                            Avg rate: ${formatCurrency(paper.avg_legacy_rate)}/year
                        </div>
                        <div class="text-sm font-bold text-green-600 mt-2">
                            Revenue Gap: ${formatCurrency(revenueGap)}
                        </div>
                    ` : `
                        <div class="text-xs text-gray-500 mt-1 italic">No legacy rate subscribers</div>
                    `}
                </div>

                <!-- Revenue Per Subscriber -->
                <div class="bg-gradient-to-r from-purple-50 to-purple-100 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold text-purple-700">Revenue Per Subscriber</span>
                        <span class="text-xl" aria-hidden="true">💰</span>
                    </div>

                    <!-- Overall ARPU (annualized) -->
                    <div class="text-2xl font-bold text-purple-900 mb-2">
                        ${formatCurrency(paper.overall_arpu_annualized)}
                        <span class="text-sm font-normal text-purple-600">/year (annualized)</span>
                    </div>
                    <div class="text-xs text-purple-700 mb-3">
                        Actual avg: ${formatCurrency(paper.overall_arpu_actual)}
                    </div>

                    <!-- By Delivery Type -->
                    <div class="space-y-1">
                        ${paper.arpu_mail ? `
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-gray-600">📮 Mail (${formatNumber(paper.count_mail)})</span>
                                <span class="font-semibold text-gray-900">${formatCurrency(paper.arpu_mail)}</span>
                            </div>
                        ` : ''}
                        ${paper.arpu_carrier ? `
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-gray-600">🚗 Carrier (${formatNumber(paper.count_carrier)})</span>
                                <span class="font-semibold text-gray-900">${formatCurrency(paper.arpu_carrier)}</span>
                            </div>
                        ` : ''}
                        ${paper.arpu_digital ? `
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-gray-600">💻 Digital (${formatNumber(paper.count_digital)})</span>
                                <span class="font-semibold text-gray-900">${formatCurrency(paper.arpu_digital)}</span>
                            </div>
                        ` : ''}
                    </div>
                </div>

                <!-- Subscription Length Distribution -->
                <div class="mt-4 bg-gray-50 rounded-lg p-3 border border-gray-200">
                    <div class="text-xs font-semibold text-gray-700 mb-2">📊 Subscription Mix</div>
                    <div class="grid grid-cols-3 gap-2 text-xs">
                        <div class="text-center">
                            <div class="font-bold text-blue-600">${paper.monthly_count}</div>
                            <div class="text-gray-600">Monthly</div>
                            <div class="text-gray-500">${Math.round(paper.monthly_count / paper.total_subscribers * 100)}%</div>
                        </div>
                        <div class="text-center">
                            <div class="font-bold text-green-600">${paper.yearly_count}</div>
                            <div class="text-gray-600">Yearly</div>
                            <div class="text-gray-500">${Math.round(paper.yearly_count / paper.total_subscribers * 100)}%</div>
                        </div>
                        <div class="text-center">
                            <div class="font-bold text-orange-600">${paper.weekly_count}</div>
                            <div class="text-gray-600">Weekly</div>
                            <div class="text-gray-500">${Math.round(paper.weekly_count / paper.total_subscribers * 100)}%</div>
                        </div>
                    </div>
                </div>

                <!-- Click to view -->
                <div class="mt-4 text-center text-xs text-blue-600 font-semibold">
                    Click to view subscriber list →
                </div>
            </div>
        `;
    }).join('');

    container.innerHTML = cardsHTML;
}

/**
 * Show subscribers for a specific paper
 * Opens SubscriberTablePanel with all subscribers from this publication
 */
async function showPaperSubscribers(paperCode, paperName) {
    try {
        // Fetch all subscribers for this paper from latest snapshot
        const response = await fetch(`api.php?papers=${encodeURIComponent(paperCode)}`);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (!data.subscribers || data.subscribers.length === 0) {
            alert(`No subscribers found for ${paperName}`);
            return;
        }

        // Map to SubscriberTablePanel format
        const subscribers = data.subscribers.map(sub => ({
            account_id: sub.sub_num || sub.account_id,
            subscriber_name: sub.name || sub.subscriber_name,
            paper_code: sub.paper_code,
            paper_name: sub.paper_name,
            business_unit: sub.business_unit,
            expiration_date: sub.paid_thru || sub.expiration_date,
            delivery_type: sub.delivery_type,
            current_rate: sub.rate_name || sub.current_rate,
            rate_amount: Math.abs(sub.last_payment_amount || sub.rate_amount || 0),
            mailing_address: sub.address && sub.city_state_postal
                ? `${sub.address}, ${sub.city_state_postal}`
                : (sub.mailing_address || ''),
            phone: sub.phone,
            email: sub.email,
            route: sub.route
        }));

        // Prepare data for SubscriberTablePanel
        const panelData = {
            title: `${paperCode} - ${paperName}`,
            subtitle: `${subscribers.length} subscriber${subscribers.length !== 1 ? 's' : ''} • Snapshot: ${data.snapshotDate || 'Latest'}`,
            data: {
                subscribers: subscribers,
                count: subscribers.length,
                paperCode: paperCode,
                paperName: paperName,
                metricType: 'by_paper'
            }
        };

        console.log('Opening SubscriberTablePanel for paper:', panelData);

        // Show using SubscriberTablePanel
        const panel = new SubscriberTablePanel({ colorScheme: 'blue' });
        panel.show(panelData);

    } catch (error) {
        console.error('Error loading paper subscribers:', error);
        alert('Failed to load subscriber data: ' + error.message);
    }
}

/**
 * Display error message if per-paper data fails to load
 */
function displayRevenueByPublicationError(message) {
    const container = document.getElementById('revenueByPublication');
    container.innerHTML = `
        <div class="col-span-full text-center py-12">
            <div class="text-red-600 font-semibold mb-2">Error Loading Publication Metrics</div>
            <div class="text-gray-500 text-sm">${message}</div>
        </div>
    `;
    console.error('Revenue by Publication Error:', message);
}

/**
 * Load and display sweet spot analysis
 * Shows optimal subscription length distribution metrics
 */
async function loadSweetSpotAnalysis() {
    try {
        const response = await fetch('api/revenue_intelligence.php?action=sweet_spot');

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Failed to load sweet spot analysis');
        }

        populateSweetSpotMetrics(data.metrics);

        console.log('Sweet Spot Analysis loaded successfully', data);

    } catch (error) {
        console.error('Error loading sweet spot analysis:', error);
        displaySweetSpotError(error.message);
    }
}

/**
 * Populate sweet spot metrics UI
 * Shows scores, recommendations, and statistics
 */
function populateSweetSpotMetrics(metrics) {
    // Overall Sweet Spot Score
    const overallScore = Math.round(metrics.sweet_spot_score);
    document.getElementById('sweetSpotScore').textContent = overallScore;
    document.getElementById('sweetSpotScoreBar').style.width = overallScore + '%';

    // Individual Scores
    const cashFlow = Math.round(metrics.cash_flow_score);
    const profitMargin = Math.round(metrics.profit_margin_score);
    const stability = Math.round(metrics.stability_score);
    const adminEfficiency = Math.round(metrics.admin_efficiency_score);

    document.getElementById('cashFlowScore').textContent = cashFlow;
    document.getElementById('cashFlowBar').style.width = cashFlow + '%';

    document.getElementById('profitMarginScore').textContent = profitMargin;
    document.getElementById('profitMarginBar').style.width = profitMargin + '%';

    document.getElementById('stabilityScore').textContent = stability;
    document.getElementById('stabilityBar').style.width = stability + '%';

    document.getElementById('adminEfficiencyScore').textContent = adminEfficiency;
    document.getElementById('adminEfficiencyBar').style.width = adminEfficiency + '%';

    // Statistics
    document.getElementById('avgRenewals').textContent =
        (metrics.avg_renewals_per_subscriber || 0).toFixed(2) + '/year';
    document.getElementById('annualizedRevenue').textContent =
        formatCurrency(metrics.annualized_revenue || 0);
    document.getElementById('minRate').textContent =
        formatCurrency(metrics.min_rate || 0);
    document.getElementById('maxRate').textContent =
        formatCurrency(metrics.max_rate || 0);

    // Recommendations
    populateSweetSpotRecommendations(metrics.recommendations || []);
}

/**
 * Populate sweet spot recommendations
 * Shows prioritized action items based on score analysis
 */
function populateSweetSpotRecommendations(recommendations) {
    const container = document.getElementById('sweetSpotRecommendations');

    if (!recommendations || recommendations.length === 0) {
        container.innerHTML = '<div class="text-center py-4 text-gray-500 italic">No recommendations at this time</div>';
        return;
    }

    // Priority icons and styling
    const priorityConfig = {
        'high': {
            icon: '🔴',
            label: 'High Priority',
            classes: 'bg-red-50 border border-red-200',
            labelClasses: 'text-red-700'
        },
        'medium': {
            icon: '🟡',
            label: 'Medium Priority',
            classes: 'bg-yellow-50 border border-yellow-200',
            labelClasses: 'text-yellow-700'
        },
        'low': {
            icon: '🟢',
            label: 'Low Priority',
            classes: 'bg-green-50 border border-green-200',
            labelClasses: 'text-green-700'
        }
    };

    const recsHTML = recommendations.map(rec => {
        const config = priorityConfig[rec.priority] || priorityConfig['medium'];

        return `
            <div class="${config.classes} rounded-lg p-3">
                <div class="flex items-start gap-2">
                    <span class="text-lg flex-shrink-0">${config.icon}</span>
                    <div class="flex-1">
                        <div class="text-xs font-semibold ${config.labelClasses} mb-1">${config.label}</div>
                        <div class="text-sm text-gray-700">${rec.message}</div>
                    </div>
                </div>
            </div>
        `;
    }).join('');

    container.innerHTML = recsHTML;
}

/**
 * Toggle sweet spot details visibility
 */
function toggleSweetSpotDetails() {
    const details = document.getElementById('sweetSpotDetails');
    const toggle = document.getElementById('sweetSpotToggle');

    if (details.classList.contains('hidden')) {
        details.classList.remove('hidden');
        toggle.textContent = 'Hide Details ↑';
    } else {
        details.classList.add('hidden');
        toggle.textContent = 'Show Details ↓';
    }
}

/**
 * Display error message if sweet spot analysis fails to load
 */
function displaySweetSpotError(message) {
    document.getElementById('sweetSpotScore').textContent = 'Error';
    document.getElementById('sweetSpotRecommendations').innerHTML = `
        <div class="text-center py-4">
            <div class="text-red-600 font-semibold mb-1">Error Loading Sweet Spot Analysis</div>
            <div class="text-gray-500 text-sm">${message}</div>
        </div>
    `;
    console.error('Sweet Spot Analysis Error:', message);
}

// Export for use in main app
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        loadRevenueIntelligence,
        showExpirationSubscribers,
        loadRevenueByPublication,
        showPaperSubscribers,
        loadSweetSpotAnalysis,
        toggleSweetSpotDetails
    };
}
